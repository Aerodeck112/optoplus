<?php

namespace App\Modules\Orders\Services;

use App\Core\Audit\Audit;
use App\Modules\B2B\Models\Organization;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Services\ConfigurationValidator;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Services\PaymentMethods;
use App\Modules\Pricing\Models\Coupon;
use App\Modules\Pricing\Support\PricedComponent;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Production\Services\ProductionFlow;
use App\Modules\Shop\Models\Cart;
use App\Modules\Shop\Services\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Transformă coșul în comandă, într-o singură tranzacție:
 *  - idempotent (aceeași cheie de finalizare = aceeași comandă; dublu-click sau reîncărcare nu dublează);
 *  - re-validează configurațiile și re-prețuiește totul pe server; dacă totalul diferă de cel afișat clientului,
 *    comanda NU se plasează și clientul vede noul total;
 *  - păstrează snapshotul complet (ramă, lentilă, material, tratamente, reguli și limite, defalcarea prețului);
 *  - configurațiile devin „în comandă” (nu se mai pot modifica), coșul se golește.
 */
class OrderPlacer
{
    public function __construct(
        private CartService $carts,
        private ConfigurationValidator $validator,
        private OrderNumber $numbers,
        private OrderStatus $status,
        private PaymentMethods $paymentMethods,
        private ProductionFlow $production,
    ) {}

    /**
     * @param  array{email:string, phone:?string, billing:array, shipping:?array, shipping_method:string, payment_method:string, note:?string}  $data
     */
    public function place(Cart $cart, array $data, PricingContext $ctx, string $idempotencyKey, int $expectedTotal, ?string $ip = null): Order
    {
        if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        $order = DB::transaction(function () use ($cart, $data, $ctx, $idempotencyKey, $expectedTotal, $ip) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }
            $items = $cart->items()->with('variant.product.brand', 'configuration.prescription', 'configuration.offering')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cos' => 'Coșul este gol.']);
            }
            if ($errors = $this->carts->validationErrors($cart)) {
                throw ValidationException::withMessages(['cos' => array_merge(...array_values($errors))]);
            }
            foreach ($items as $item) {
                if ($item->kind === 'configuration' && $item->configuration?->status === 'in_comanda') {
                    throw ValidationException::withMessages(['cos' => 'O configurație din coș a fost deja comandată.']);
                }
            }
            $quote = $this->carts->quote($cart, $ctx);
            if (! $quote->isValid()) {
                throw ValidationException::withMessages(['cos' => $quote->errors ?: ['Unele produse nu pot fi comandate. Verificați coșul.']]);
            }
            if ($quote->totalGross !== $expectedTotal) {
                throw ValidationException::withMessages(['total' => 'Totalul s-a actualizat (preț, promoție sau livrare). Verificați noul total și confirmați din nou.']);
            }
            $organization = $ctx->organizationId ? Organization::find($ctx->organizationId) : null;
            if ($ctx->organizationId && (! $organization || ! $organization->isApproved() || $cart->organization_id !== $organization->id)) {
                throw ValidationException::withMessages(['cos' => 'Contul de partener nu este activ.']);
            }
            if (! array_key_exists($data['payment_method'], $this->paymentMethods->available($quote->shipping, $ctx->channel, $organization))) {
                throw ValidationException::withMessages(['plata' => 'Metoda de plată nu este disponibilă pentru această comandă.']);
            }
            if ($data['payment_method'] === 'termen' && $organization->credit_limit !== null) {
                $open = (int) Order::where('organization_id', $organization->id)->where('payment_method', 'termen')
                    ->whereNotIn('commercial_status', ['anulata'])->whereNotIn('payment_status', ['platita', 'rambursata'])
                    ->sum(DB::raw('total - paid_total'));
                if ($open + $quote->totalGross > $organization->credit_limit) {
                    throw ValidationException::withMessages(['plata' => 'Comanda depășește limita de credit aprobată. Alegeți plata prin transfer sau contactați-ne.']);
                }
            }

            $hasProduction = $items->contains(fn ($i) => $i->kind === 'configuration');
            [$daysMin, $daysMax] = $this->productionDays($items);
            $order = Order::create([
                'number' => $this->numbers->next(), 'uuid' => (string) Str::uuid(), 'channel' => $ctx->channel,
                'customer_id' => $ctx->customerId, 'organization_id' => $ctx->organizationId,
                'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'payment_method' => $data['payment_method'], 'shipping_method_code' => $data['shipping_method'],
                'subtotal' => $quote->subtotal, 'discount_total' => $quote->discountTotal,
                'shipping_total' => (int) ($quote->shipping['gross'] ?? 0), 'vat_total' => $quote->vatTotal,
                'total' => $quote->totalGross, 'prices_include_vat' => $quote->basisIncludesVat,
                'billing_address' => $data['billing'], 'shipping_address' => $data['shipping'] ?? null,
                'coupon_code' => $quote->couponStatus === 'valid' ? $ctx->couponCode : null,
                'customer_note' => $data['note'] ?? null, 'idempotency_key' => $idempotencyKey,
                'purchase_order_ref' => $data['po_ref'] ?? null,
                'placed_ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                'pricing_version' => $quote->version,
                'production_status' => 'nu_se_aplica',
                'production_days_min' => $daysMin, 'production_days_max' => $daysMax,
            ]);

            foreach ($items as $item) {
                $line = $quote->lines['i'.$item->id];
                $components = $line['components'];
                $gross = array_sum(array_map(fn (PricedComponent $c) => $c->gross, $components));
                $amount = array_sum(array_map(fn (PricedComponent $c) => $c->amount(), $components));
                $rates = array_unique(array_map(fn (PricedComponent $c) => $c->vatBp, $components));
                $cfg = $item->configuration;
                $order->items()->create([
                    'kind' => $item->kind, 'variant_id' => $item->variant_id, 'configuration_id' => $cfg?->id,
                    'prescription_id' => $cfg?->prescription_id,
                    'name' => mb_substr($item->kind === 'configuration' ? 'Ochelari configurați: '.$line['label'] : $line['label'], 0, 255),
                    'sku' => $item->variant?->sku, 'quantity' => $item->quantity,
                    'unit_price' => intdiv($amount, max(1, $item->quantity)),
                    'discount' => $amount - array_sum(array_map(fn (PricedComponent $c) => $c->afterDiscount(), $components)),
                    'line_total' => $gross, 'vat_rate_bp' => count($rates) === 1 ? reset($rates) : max($rates),
                    'vat_amount' => array_sum(array_map(fn (PricedComponent $c) => $c->vat, $components)),
                    'unit_cost' => $this->cost($components),
                    'patient_reference' => $item->patient_reference ?? $cfg?->prescription?->patient_reference,
                    'snapshot' => $this->snapshot($item, $line, $quote->version),
                    'requires_production' => $item->kind === 'configuration',
                ]);
                if ($cfg) {
                    $cfg->update(['status' => 'in_comanda']);
                }
                if ($item->kind === 'product' && $item->variant?->stock_qty !== null) {
                    ProductVariant::whereKey($item->variant_id)->decrement('stock_qty', $item->quantity);
                }
            }

            if ($order->coupon_code && ($coupon = Coupon::where('code', Coupon::normalize($order->coupon_code))->lockForUpdate()->first())) {
                DB::table('coupon_redemptions')->insert(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'customer_id' => $order->customer_id, 'discount' => $quote->discountTotal, 'created_at' => now()]);
                $coupon->increment('uses_count');
            }

            $this->status->event($order, 'commercial', null, 'noua', 'Comanda a fost plasată.', true, $ctx->customerId ? 'client' : 'client:vizitator');
            if ($hasProduction) {
                $this->production->createJobs($order);
            }
            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);
            Audit::log('order.placed', $order, ['total' => $order->total, 'items' => $items->count()], $order->customer_id);

            return $order;
        });

        return $order;
    }

    private function snapshot($item, array $line, string $pricingVersion): array
    {
        $base = [
            'pricing_version' => $pricingVersion,
            'components' => array_map(fn (PricedComponent $c) => $c->toArray(), $line['components']),
        ];
        if ($item->kind === 'product') {
            $v = $item->variant;

            return $base + [
                'entry' => 'produs',
                'product' => ['id' => $v->product_id, 'name' => $v->product->name, 'slug' => $v->product->slug, 'brand' => $v->product->brand?->name],
                'variant' => ['id' => $v->id, 'sku' => $v->sku, 'name' => $v->name, 'options' => $v->options],
            ];
        }
        /** @var Configuration $cfg */
        $cfg = $item->configuration->load('frameVariant.product.brand', 'frameVariant.product.frameSpec', 'offering.product.brand', 'offering.material', 'offering.lensProduct.family', 'offering.treatments.treatment');
        $input = $cfg->toInput();
        $validation = $this->validator->validate($input, [], true);
        $frame = $cfg->frameVariant;
        $o = $cfg->offering;
        $spec = $frame?->product->frameSpec;

        return $base + [
            'entry' => $cfg->entry,
            'usage' => $cfg->usage,
            'eyes' => $input->eyes,
            'configuration' => ['id' => $cfg->id, 'uuid' => $cfg->uuid, 'version' => $cfg->version],
            'frame' => $frame ? [
                'variant_id' => $frame->id, 'sku' => $frame->sku, 'name' => $frame->displayName(), 'brand' => $frame->product->brand?->name,
                'spec' => $spec ? ['lens_width_mm' => $spec->lens_width_mm, 'bridge_mm' => $spec->bridge_mm, 'temple_mm' => $spec->temple_mm, 'lens_height_mm' => $spec->lens_height_mm, 'rim_type' => $spec->rim_type, 'color_code' => $spec->color_code] : null,
            ] : null,
            'lens' => $o ? [
                'offering_id' => $o->id, 'product' => $o->product->name, 'family' => $o->lensProduct?->family?->name,
                'family_code' => $o->lensProduct?->family?->code, 'design' => $o->lensProduct?->design,
                'material' => $o->material->name, 'index' => $o->material->refractive_index, 'made_in_house' => (bool) $o->product->made_in_house,
                'sale_unit' => $o->product->sale_unit, 'supply_form' => $o->lensProduct?->supply_form, 'offering_status' => $o->status,
                'offering_code' => $o->code, 'requires_review' => (bool) $o->requires_review, 'diameter_mm' => $o->diameter_mm,
                'limits' => ['sph' => [$o->sph_min, $o->sph_max], 'cyl' => [$o->cyl_min, $o->cyl_max], 'max_combined' => $o->max_combined, 'add' => [$o->add_min, $o->add_max]],
            ] : null,
            'treatments' => collect($input->treatments)->map(function ($t) use ($o) {
                $ot = $o?->treatments->firstWhere('treatment_id', (int) $t['treatment_id']);

                return ['treatment_id' => (int) $t['treatment_id'], 'name' => $ot?->treatment->name, 'option' => $t['option'] ?? null, 'included' => (bool) $ot?->is_included];
            })->values()->all(),
            'prescription' => ['id' => $cfg->prescription_id, 'pd_mode' => $input->pdMode, 'has_file' => (bool) $cfg->prescription?->files()->exists(), 'customer_unsure' => (bool) $cfg->prescription?->customer_unsure],
            'validation' => [
                'requires_review' => $validation->requiresReview(), 'review_reasons' => $validation->reviewReasons,
                'warnings' => array_column($validation->warnings, 'message'), 'rules' => $validation->appliedRules,
            ],
        ];
    }

    private function cost(array $components): ?int
    {
        $total = 0;
        foreach ($components as $c) {
            if ($c->unitCost === null && ! $c->included) {
                return null;
            }
            $total += (int) $c->unitCost * $c->quantity;
        }

        return $total;
    }

    /** Intervalul de producție vine numai din datele configurate (ofertă sau produs); necunoscut = null. */
    private function productionDays($items): array
    {
        $min = $max = null;
        foreach ($items as $item) {
            $o = $item->configuration?->offering;
            $p = $item->variant?->product;
            [$a, $b] = $o ? [$o->lead_time_days_min, $o->lead_time_days_max] : [$p?->lead_time_days_min, $p?->lead_time_days_max];
            if ($a !== null) {
                $min = max($min ?? 0, $a);
            }
            if ($b !== null) {
                $max = max($max ?? 0, $b);
            }
        }

        return [$min, $max];
    }
}
