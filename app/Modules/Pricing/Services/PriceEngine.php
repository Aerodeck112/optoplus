<?php

namespace App\Modules\Pricing\Services;

use App\Core\Support\Money;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Pricing\Models\Coupon;
use App\Modules\Pricing\Models\PriceListItem;
use App\Modules\Pricing\Models\Promotion;
use App\Modules\Pricing\Support\PricedComponent;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Pricing\Support\Quote;
use App\Modules\Shipping\Services\ShippingCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motorul de preț: singura sursă a sumelor din configurator, coș, comandă, oferte B2B și asistent.
 *
 * Ordinea calculului:
 *  1. prețul de bază al fiecărei componente (B2C sau lista partenerului, cu praguri cantitative);
 *  2. promoțiile pe componente, în ordinea priorității (una necumulabilă nu se combină cu alta pe aceeași
 *     componentă; cele cumulabile se aplică succesiv, pe valoarea rămasă);
 *  3. promoțiile la nivel de comandă, repartizate proporțional pe componente (pentru TVA corect);
 *  4. TVA pe fiecare componentă, după reduceri;
 *  5. livrarea, din regulile metodei alese, pe valoarea mărfii după reduceri.
 */
class PriceEngine
{
    public const VERSION = '1.0';

    public function __construct(private VatRates $vat, private ShippingCalculator $shipping) {}

    public function quoteConfiguration(ConfigurationInput $input, PricingContext $ctx, int $quantity = 1): Quote
    {
        return $this->quote([PriceLine::configuration('cfg', $input, $quantity)], $ctx);
    }

    /** @param  list<PriceLine>  $lines */
    public function quote(array $lines, PricingContext $ctx): Quote
    {
        $quote = new Quote($ctx->basisIncludesVat(), self::VERSION);

        foreach ($lines as $line) {
            $quote->lines[$line->key] = $this->priceLine($line, $ctx);
        }

        $quote->subtotal = array_sum(array_map(fn (PricedComponent $c) => $c->amount(), $quote->components()));

        $promotions = $this->promotions($ctx, $quote);
        $this->applyComponentPromotions($quote, $promotions, $ctx);
        $this->applyOrderPromotions($quote, $promotions, $ctx);
        $this->computeVat($quote);

        $goodsGross = array_sum(array_map(fn (PricedComponent $c) => $c->gross, $quote->components()));
        $this->addShipping($quote, $ctx, $goodsGross);

        $quote->discountTotal = array_sum(array_map(fn (PricedComponent $c) => $c->discountTotal(), $quote->components()));
        $quote->vatTotal = array_sum(array_map(fn (PricedComponent $c) => $c->vat, $quote->components())) + ($quote->shipping['vat'] ?? 0);
        $quote->totalGross = $goodsGross + ($quote->shipping['gross'] ?? 0);
        $quote->totalNet = $quote->totalGross - $quote->vatTotal;

        if (! $this->vat->confirmed()) {
            $quote->messages[] = 'tva_neconfirmat';
        }

        return $quote;
    }

    /** @return array{key:string, kind:string, label:string, quantity:int, components:list<PricedComponent>, errors:list<string>} */
    private function priceLine(PriceLine $line, PricingContext $ctx): array
    {
        $out = ['key' => $line->key, 'kind' => $line->kind, 'label' => '', 'quantity' => $line->quantity, 'components' => [], 'errors' => []];

        if ($line->kind === 'product') {
            $variant = ProductVariant::with('product.categories')->find($line->variantId);
            if (! $variant || ! $variant->is_active) {
                $out['errors'][] = 'Produsul nu mai este disponibil.';

                return $out;
            }
            $out['label'] = $variant->displayName();
            $c = $this->variantComponent($variant, $line->quantity, $ctx, $variant->product->isFrame() ? 'frame' : 'product');
            if ($c) {
                $out['components'][] = $c;
            } else {
                $out['errors'][] = 'Produsul nu are preț pentru contul dumneavoastră. Cereți o ofertă.';
            }

            return $out;
        }

        $in = $line->configuration;
        $labels = [];
        if ($in->entry === 'cu_rama' && $in->frameVariantId) {
            $frame = ProductVariant::with('product.categories')->find($in->frameVariantId);
            if ($frame) {
                $labels[] = $frame->displayName();
                $c = $this->variantComponent($frame, $line->quantity, $ctx, 'frame');
                if ($c) {
                    $out['components'][] = $c;
                } else {
                    $out['errors'][] = 'Rama nu are preț pentru contul dumneavoastră.';
                }
            } else {
                $out['errors'][] = 'Rama nu mai este disponibilă.';
            }
        }

        if ($in->offeringId) {
            $offering = LensOffering::with('product.categories', 'material', 'lensProduct', 'treatments.treatment')->find($in->offeringId);
            if (! $offering) {
                $out['errors'][] = 'Lentila nu mai este disponibilă.';
            } else {
                $pair = $offering->product->sale_unit === 'pair';
                $eyes = $in->eyes === 'ambii' ? 2 : 1;
                $units = ($pair ? 1 : $eyes) * $line->quantity;
                $unitLabel = $pair ? 'pereche' : 'buc.';
                $name = $offering->product->name.' '.$offering->material->indexLabel();
                $labels[] = $name;
                $lensPrice = $this->resolve('offering', $offering->id, $offering->price, $units, $ctx);
                if ($lensPrice === null) {
                    $out['errors'][] = 'Lentila nu are preț pentru contul dumneavoastră. Cereți o ofertă.';
                } else {
                    $out['components'][] = new PricedComponent(
                        'lens', 'offering', $offering->id, $name, $lensPrice[0], $units, $unitLabel,
                        $this->vat->forType('lens'), $lensPrice[1], $offering->lens_product_id,
                        $offering->product->brand_id, $offering->product->categories->pluck('id')->all(), $offering->cost,
                    );
                }
                $byTreatment = $offering->treatments->keyBy('treatment_id');
                foreach ($in->treatments as $t) {
                    $ot = $byTreatment->get((int) $t['treatment_id']);
                    if (! $ot) {
                        $out['errors'][] = 'Un tratament ales nu este disponibil pentru această lentilă.';

                        continue;
                    }
                    $price = $ot->is_included ? [0, 'inclus'] : $this->resolve('offering_treatment', $ot->id, $ot->price, $units, $ctx);
                    if ($price === null) {
                        $out['errors'][] = "Tratamentul „{$ot->treatment->name}” nu are preț pentru contul dumneavoastră.";

                        continue;
                    }
                    $tName = $ot->treatment->name.(($t['option'] ?? null) ? ' ('.$this->optionLabel($ot->treatment, $t['option']).')' : '');
                    $out['components'][] = new PricedComponent(
                        'treatment', 'offering_treatment', $ot->id, $tName, $price[0], $units, $unitLabel,
                        $this->vat->forType('treatment'), $price[1], $offering->lens_product_id, null, [], null, $ot->is_included,
                    );
                }
            }
        }
        $out['label'] = implode(' + ', $labels) ?: 'Configurație';

        return $out;
    }

    private function optionLabel($treatment, string $code): string
    {
        return $treatment->options()->where('code', $code)->value('label') ?? $code;
    }

    private function variantComponent(ProductVariant $v, int $qty, PricingContext $ctx, string $type): ?PricedComponent
    {
        $price = $this->resolve('variant', $v->id, $v->price, $qty, $ctx);
        if ($price === null) {
            return null;
        }
        $vatBp = $v->vat_rate_bp ?? $this->vat->forType($v->product->type);

        return new PricedComponent(
            $type, 'variant', $v->id, $v->displayName(), $price[0], $qty, 'buc.', $vatBp, $price[1],
            $v->product_id, $v->product->brand_id, $v->product->categories->pluck('id')->all(), $v->cost,
        );
    }

    /**
     * Prețul unitar în baza calculului. B2C: prețul de bază (cu TVA). B2B: lista partenerului (pragul cel mai mare
     * atins de cantitate), altfel regula de rezervă a listei.
     *
     * @return array{0:int, 1:string}|null [preț, sursă] sau null dacă nu există preț pentru acest client
     */
    private function resolve(string $type, int $id, int $b2cGross, int $qty, PricingContext $ctx): ?array
    {
        $list = $ctx->priceList;
        if ($list === null) {
            return [$b2cGross, 'b2c'];
        }
        $item = PriceListItem::query()
            ->where('price_list_id', $list->id)->where('item_type', $type)->where('item_id', $id)
            ->where('min_qty', '<=', $qty)->orderByDesc('min_qty')->first();
        if ($item) {
            return [$item->price, 'lista:'.$list->code.($item->min_qty > 1 ? ':prag'.$item->min_qty : '')];
        }
        $vatBp = $this->vat->forType($type === 'variant' ? 'frame' : 'lens');
        $base = $list->prices_include_vat ? $b2cGross : Money::net($b2cGross, $vatBp);

        return match ($list->fallback) {
            'b2c' => [$base, 'b2c'],
            'b2c_discount' => [$base - Money::percent($base, (int) $list->fallback_discount_bp), 'b2c-'.($list->fallback_discount_bp / 100).'%'],
            default => null,
        };
    }

    /** @return Collection<int, Promotion> */
    private function promotions(PricingContext $ctx, Quote $quote): Collection
    {
        $all = Promotion::query()->running($ctx->now)
            ->whereIn('channel', [$ctx->channel, 'all'])
            ->orderBy('priority')->orderBy('id')->get();

        $couponPromotionId = null;
        if ($ctx->couponCode) {
            $coupon = Coupon::where('code', Coupon::normalize($ctx->couponCode))->first();
            if (! $coupon || ! $coupon->isUsable($ctx->now) || ! $all->contains('id', $coupon->promotion_id)) {
                $quote->couponStatus = 'invalid';
            } else {
                $couponPromotionId = $coupon->promotion_id;
                $quote->couponStatus = 'valid';
            }
        }

        return $all->filter(function (Promotion $p) use ($ctx, $quote, $couponPromotionId) {
            if ($p->requires_coupon && $p->id !== $couponPromotionId) {
                return false;
            }
            if ($p->min_order_total !== null && $quote->subtotal < $p->min_order_total) {
                return false;
            }
            if ($p->beneficiary_category && $p->beneficiary_category !== $ctx->declaredBeneficiary) {
                return false;
            }
            if ($p->max_uses_per_customer !== null && $ctx->customerId !== null && $this->customerUses($p, $ctx->customerId) >= $p->max_uses_per_customer) {
                return false;
            }

            return true;
        })->values();
    }

    private function customerUses(Promotion $p, int $customerId): int
    {
        return (int) DB::table('coupon_redemptions')
            ->join('coupons', 'coupons.id', '=', 'coupon_redemptions.coupon_id')
            ->where('coupons.promotion_id', $p->id)->where('coupon_redemptions.customer_id', $customerId)->count();
    }

    private function matchesComponent(Promotion $p, PricedComponent $c): bool
    {
        if ($c->included) {
            return false;
        }
        $ids = array_map('intval', (array) $p->scope_ids);

        return match ($p->scope) {
            'frames' => $c->type === 'frame',
            'lenses' => $c->type === 'lens',
            'treatments' => $c->type === 'treatment',
            'category' => array_intersect($c->categoryIds, $ids) !== [],
            'brand' => $c->brandId !== null && in_array($c->brandId, $ids, true),
            'product' => $c->productId !== null && in_array($c->productId, $ids, true),
            default => false,
        };
    }

    private function discountFor(Promotion $p, int $remaining, int $quantity): int
    {
        $d = match ($p->type) {
            'percent' => Money::percent($remaining, (int) $p->value),
            'fixed_amount' => (int) $p->value * $quantity,
            'fixed_price' => $remaining - (int) $p->value * $quantity,
            default => 0,
        };

        return max(0, min($d, $remaining));
    }

    private function applyComponentPromotions(Quote $quote, Collection $promotions, PricingContext $ctx): void
    {
        foreach ($quote->components() as $c) {
            $hasExclusive = false;
            foreach ($promotions as $p) {
                if ($p->scope === 'order' || ! $this->matchesComponent($p, $c)) {
                    continue;
                }
                if ($hasExclusive || (! $p->stackable && $c->discounts !== [])) {
                    continue;
                }
                $amount = $this->discountFor($p, $c->afterDiscount(), $c->quantity);
                if ($amount <= 0) {
                    continue;
                }
                $c->discounts[] = ['promotion_id' => $p->id, 'label' => $p->label(), 'amount' => $amount];
                $this->markApplied($quote, $p);
                $hasExclusive = ! $p->stackable;
            }
        }
    }

    private function applyOrderPromotions(Quote $quote, Collection $promotions, PricingContext $ctx): void
    {
        $exclusiveApplied = false;
        foreach ($promotions->where('scope', 'order') as $p) {
            $base = array_sum(array_map(fn (PricedComponent $c) => $c->afterDiscount(), $quote->components()));
            if ($base <= 0 || $exclusiveApplied || (! $p->stackable && $quote->orderDiscounts !== [])) {
                continue;
            }
            $amount = $p->type === 'percent' ? Money::percent($base, (int) $p->value) : min((int) $p->value, $base);
            if ($amount <= 0) {
                continue;
            }
            $quote->orderDiscounts[] = ['promotion_id' => $p->id, 'label' => $p->label(), 'amount' => $amount];
            $this->allocate($quote, $p, $amount, $base);
            $this->markApplied($quote, $p);
            $exclusiveApplied = ! $p->stackable;
        }
    }

    /** Repartizează o reducere de comandă proporțional pe componente; restul de rotunjire merge pe ultima. */
    private function allocate(Quote $quote, Promotion $p, int $amount, int $base): void
    {
        $components = array_values(array_filter($quote->components(), fn (PricedComponent $c) => $c->afterDiscount() > 0));
        $left = $amount;
        foreach ($components as $i => $c) {
            $share = $i === count($components) - 1 ? $left : intdiv($amount * $c->afterDiscount(), $base);
            $share = min($share, $c->afterDiscount());
            $left -= $share;
            if ($share > 0) {
                $c->discounts[] = ['promotion_id' => $p->id, 'label' => $p->label(), 'amount' => $share];
            }
        }
    }

    private function markApplied(Quote $quote, Promotion $p): void
    {
        if (! in_array($p->id, $quote->appliedPromotionIds, true)) {
            $quote->appliedPromotionIds[] = $p->id;
        }
        if ($p->requires_proof) {
            $quote->requiresProofCheck = true;
        }
    }

    private function computeVat(Quote $quote): void
    {
        foreach ($quote->components() as $c) {
            $value = $c->afterDiscount();
            if ($quote->basisIncludesVat) {
                $c->gross = $value;
                $c->vat = Money::vatIncluded($value, $c->vatBp);
                $c->net = $value - $c->vat;
            } else {
                $c->net = $value;
                $c->vat = Money::percent($value, $c->vatBp);
                $c->gross = $value + $c->vat;
            }
        }
    }

    private function addShipping(Quote $quote, PricingContext $ctx, int $goodsGross): void
    {
        if (! $ctx->shippingMethodCode) {
            return;
        }
        $s = $this->shipping->quote($ctx->shippingMethodCode, $goodsGross, null, $ctx->channel);
        if ($s === null) {
            $quote->errors[] = 'Metoda de livrare aleasă nu este disponibilă pentru această comandă.';

            return;
        }
        $vatBp = $this->vat->shipping();
        $s['gross'] = $s['price'];
        $s['vat'] = Money::vatIncluded($s['price'], $vatBp);
        $s['net'] = $s['price'] - $s['vat'];
        $quote->shipping = $s;
    }
}
