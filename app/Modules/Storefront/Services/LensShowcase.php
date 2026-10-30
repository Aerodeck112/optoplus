<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Lenses\Models\LensFamily;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PricingContext;
use Illuminate\Support\Collection;

/**
 * Prezentarea lentilelor pe site: familiile cu prețul „de la” (calculat de motorul de preț pentru cea mai ieftină
 * ofertă vandabilă) și lentilele fiecărei familii.
 */
class LensShowcase
{
    public function __construct(private PriceEngine $prices) {}

    /** @return list<array{code:string, name:string, description:string, from:?int, url:string}> */
    public function families(): array
    {
        return LensFamily::where('is_active', true)->orderBy('sort')->get()->map(function (LensFamily $f) {
            $cheapest = $this->cheapestOffering(fn ($q) => $q->whereHas('lensProduct', fn ($l) => $l->where('family_id', $f->id)));

            return [
                'code' => $f->code, 'name' => $f->name, 'description' => (string) $f->customer_description,
                'from' => $cheapest ? $this->offeringPrice($cheapest) : null,
                'url' => '/lentile/#'.$f->code,
            ];
        })->filter(fn ($f) => $f['from'] !== null)->values()->all();
    }

    /** Cea mai ieftină ofertă vandabilă a unui produs publicat (opțional filtrată). */
    public function cheapestOffering(?callable $scope = null): ?LensOffering
    {
        $q = LensOffering::query()->sellable()
            ->whereHas('product', fn ($p) => $p->where('status', Product::STATUS_PUBLISHED))
            ->whereHas('lensProduct', fn ($l) => $l->where('quote_only', false));
        if ($scope) {
            $scope($q);
        }

        return $q->orderBy('price')->first();
    }

    /** Prețul unei oferte fără tratamente, prin motorul de preț (promoțiile pe lentile incluse). */
    public function offeringPrice(LensOffering $o): int
    {
        return $this->prices->quoteConfiguration(new ConfigurationInput(entry: 'fara_rama', offeringId: $o->id), new PricingContext)->totalGross;
    }

    /** @return Collection<int, Product> lentilele publicate, cu ofertele vandabile */
    public function lensProducts(): Collection
    {
        return Product::published()->where('type', Product::TYPE_LENS)
            ->with(['lens.family', 'lens.offerings' => fn ($q) => $q->sellable()->with('material', 'treatments.treatment')])
            ->get()
            ->filter(fn (Product $p) => $p->lens && $p->lens->offerings->isNotEmpty())
            ->sortBy(fn (Product $p) => [$p->lens->family->sort, $p->lens->offerings->min('price')])
            ->values();
    }
}
