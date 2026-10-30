<?php

namespace App\Modules\Configurator\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PricingContext;
use Illuminate\Support\Collection;

/**
 * Variantele de lentilă pentru pasul 3: toate ofertele vandabile, fiecare validată pe server cu rețeta clientului.
 * Cele incompatibile rămân vizibile, dezactivate, cu motivul. Ordinea este după preț (fără a împinge varianta
 * cea mai scumpă); familiile potrivite utilizării declarate apar primele.
 */
class LensOptions
{
    public function __construct(private ConfigurationValidator $validator, private PriceEngine $prices) {}

    /**
     * @return array{recommended: Collection, other: Collection}  grupuri de [product, family, options: list<array>]
     */
    public function forInput(ConfigurationInput $in, PricingContext $ctx): array
    {
        $offerings = LensOffering::query()->sellable()
            ->whereHas('product', fn ($p) => $p->where('status', Product::STATUS_PUBLISHED))
            ->with(['product', 'material', 'lensProduct.family', 'treatments.treatment.options'])
            ->get();
        // Familiile potrivite utilizării declarate vin din date (Lentile → Familii → „Utilizări”), nu din cod.
        $usage = $in->usage ?? 'nu_stiu';
        $fits = fn ($family) => $usage === 'nu_stiu' || in_array($usage, (array) $family->usages, true);

        $groups = $offerings->groupBy('lens_product_id')->map(function (Collection $items) use ($in, $ctx) {
            $first = $items->first();
            $options = $items->sortBy(fn ($o) => $o->material->refractive_index)->map(function (LensOffering $o) use ($in, $ctx) {
                $candidate = $in->with(['offeringId' => $o->id, 'treatments' => []]);
                $result = $this->validator->validate($candidate, ['offering' => $o], false);
                $blocking = array_values(array_filter($result->errors, fn ($e) => in_array($e['code'], ['matrix_sph', 'matrix_cyl', 'matrix_combined', 'matrix_add', 'add_missing', 'sale_unit_pair', 'quote_only'], true) || str_starts_with($e['code'], 'rule_')));
                $lensOnly = $this->prices->quoteConfiguration(new ConfigurationInput(entry: 'fara_rama', offeringId: $o->id, eyes: $in->eyes), $ctx);

                return [
                    'offering' => $o,
                    'price' => $lensOnly->totalGross,
                    'available' => $blocking === [],
                    'reason' => $blocking[0]['message'] ?? null,
                    'warnings' => array_column($result->warnings, 'message'),
                ];
            })->values();

            return [
                'product' => $first->product,
                'family' => $first->lensProduct->family,
                'lens' => $first->lensProduct,
                'options' => $options,
                'from' => $options->where('available', true)->min('price') ?? $options->min('price'),
                'available' => $options->contains('available', true),
            ];
        })->sortBy([fn ($a, $b) => $b['available'] <=> $a['available'], fn ($a, $b) => $a['from'] <=> $b['from']])->values();

        return [
            'recommended' => $groups->filter(fn ($g) => $fits($g['family']))->values(),
            'other' => $groups->reject(fn ($g) => $fits($g['family']))->values(),
        ];
    }
}
