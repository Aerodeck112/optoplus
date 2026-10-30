<?php

namespace App\Modules\B2B\Services;

use App\Core\Audit\Audit;
use App\Modules\B2B\Models\Quote;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderNumber;
use App\Modules\Orders\Services\OrderStatus;
use App\Modules\Production\Services\ProductionFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ofertele B2B: cererea partenerului, versiunile pregătite de vânzări (în panou) și acceptarea, care
 * transformă versiunea acceptată într-o comandă cu exact sumele din ofertă (nu se re-prețuiește).
 */
class Quotes
{
    public function __construct(private OrderNumber $numbers, private OrderStatus $status, private ProductionFlow $production) {}

    public function request(Partner $partner, string $text): Quote
    {
        $quote = Quote::create([
            'number' => 'OF-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'organization_id' => $partner->organizationId(), 'customer_id' => $partner->customer->id,
            'status' => 'ceruta', 'request_text' => mb_substr($text, 0, 5000),
        ]);
        Audit::log('b2b.quote_requested', $quote, [], $partner->customer->id);

        return $quote;
    }

    public function accept(Quote $quote, Partner $partner, string $paymentMethod, ?string $poRef, string $idempotencyKey): Order
    {
        if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($quote, $partner, $paymentMethod, $poRef, $idempotencyKey) {
            $quote = Quote::whereKey($quote->id)->where('organization_id', $partner->organizationId())->lockForUpdate()->firstOrFail();
            $version = $quote->currentVersion;
            if ($quote->status !== 'trimisa' || ! $version || ! $version->sent_at) {
                throw ValidationException::withMessages(['oferta' => 'Oferta nu poate fi acceptată în starea actuală.']);
            }
            if ($version->isExpired()) {
                $quote->update(['status' => 'expirata']);
                throw ValidationException::withMessages(['oferta' => 'Oferta a expirat. Cereți o actualizare.']);
            }
            $org = $partner->organization();
            if (! in_array($paymentMethod, ['transfer_bancar', 'termen'], true) || ($paymentMethod === 'termen' && ! $org->hasApprovedCredit())) {
                throw ValidationException::withMessages(['plata' => 'Metoda de plată nu este disponibilă.']);
            }
            $items = $version->items;
            $hasProduction = collect($items)->contains(fn ($i) => ($i['kind'] ?? '') === 'configuration');
            $order = Order::create([
                'number' => $this->numbers->next(), 'uuid' => (string) Str::uuid(), 'channel' => 'b2b',
                'customer_id' => $partner->customer->id, 'organization_id' => $org->id,
                'email' => $partner->customer->email, 'phone' => $org->contact_phone,
                'payment_method' => $paymentMethod, 'subtotal' => $version->subtotal, 'discount_total' => $version->discount_total,
                'vat_total' => $version->vat_total, 'total' => $version->total, 'prices_include_vat' => $version->prices_include_vat,
                'billing_address' => ['type' => 'pj', 'company' => $org->name, 'tax_id' => $org->vat_code, 'reg_no' => $org->registry_number, 'street' => $org->billing_address, 'country' => 'RO'],
                'purchase_order_ref' => $poRef ? mb_substr($poRef, 0, 80) : null, 'quote_id' => $quote->id,
                'idempotency_key' => $idempotencyKey, 'pricing_version' => 'oferta/v'.$version->number, // oferta e legată prin quote_id
            ]);
            foreach ($items as $i) {
                $cfg = isset($i['configuration_id']) ? Configuration::where('organization_id', $org->id)->find($i['configuration_id']) : null;
                $order->items()->create([
                    'kind' => $i['kind'] ?? 'product', 'variant_id' => $i['variant_id'] ?? null, 'configuration_id' => $cfg?->id,
                    'prescription_id' => $cfg?->prescription_id, 'name' => mb_substr((string) $i['description'], 0, 255),
                    'quantity' => (int) $i['quantity'], 'unit_price' => (int) $i['unit_price'], 'discount' => (int) ($i['discount'] ?? 0),
                    'line_total' => (int) $i['line_total'], 'vat_rate_bp' => (int) $i['vat_bp'], 'vat_amount' => (int) ($i['vat_amount'] ?? 0),
                    'patient_reference' => $i['patient_reference'] ?? null,
                    'snapshot' => ['entry' => $cfg?->entry ?? 'oferta', 'quote' => $quote->number, 'quote_version' => $version->number, 'line' => $i],
                    'requires_production' => ($i['kind'] ?? '') === 'configuration',
                ]);
                $cfg?->update(['status' => 'in_comanda']);
            }
            $quote->update(['status' => 'acceptata', 'accepted_version_id' => $version->id, 'order_id' => $order->id]);
            $this->status->event($order, 'commercial', null, 'noua', 'Comanda a fost creată din oferta '.$quote->number.'.', true, 'client');
            if ($hasProduction) {
                $this->production->createJobs($order);
            }
            Audit::log('b2b.quote_accepted', $quote, ['order' => $order->number, 'version' => $version->number], $partner->customer->id);

            return $order;
        });
    }
}
