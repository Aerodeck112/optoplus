<?php

namespace App\Modules\Import\Services\WooCommerce;

use App\Core\Support\Money;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;

/**
 * Reconcilierea numerică a catalogului importat cu sursa: produse, variante, imagini, mărci; prețurile normale și
 * cele promoționale verificate **separat** (prețul promoțional prin motorul de preț, ca în coș).
 */
class Reconciler
{
    public function __construct(private CsvReader $reader, private PriceEngine $prices) {}

    public function reconcile(string $csvPath, ?string $urlsPath = null): array
    {
        $records = $this->reader->read($csvPath)['records'];
        $parents = array_filter($records, fn ($r) => $r['type'] !== 'variation');
        $variations = array_filter($records, fn ($r) => $r['type'] === 'variation');
        $sellable = array_filter($records, fn ($r) => $r['type'] !== 'variable');
        $imageUrls = [];
        foreach ($records as $r) {
            foreach ($r['images'] as $u) {
                $imageUrls[$u] = true;
            }
        }
        $brands = [];
        foreach ($parents as $r) {
            foreach ($r['brands'] as $b) {
                $brands[mb_strtolower($b)] = true;
            }
        }

        $products = Product::whereNotNull('legacy_wc_id')->get(['id', 'legacy_wc_id', 'slug', 'status', 'brand_id']);
        $byLegacy = $products->keyBy('legacy_wc_id');
        $variants = ProductVariant::whereNotNull('legacy_wc_id')->get()->keyBy('legacy_wc_id');
        $images = ProductImage::whereIn('product_id', $products->pluck('id'))->get(['source_url', 'status']);

        $missingProducts = array_values(array_filter(array_map(fn ($r) => $byLegacy->has($r['id']) ? null : $r['id'], $parents)));
        $regularMismatch = [];
        $saleMismatch = [];
        $saleChecked = 0;
        foreach ($sellable as $r) {
            $v = $variants->get($r['id']);
            if (! $v) {
                continue;
            }
            $regular = $r['regular_price'] === '' ? null : Money::parse($r['regular_price']);
            if ($regular !== $v->price) {
                $regularMismatch[] = ['id' => $r['id'], 'sursa' => $regular, 'tinta' => $v->price];
            }
            if ($r['sale_price'] !== '') {
                $saleChecked++;
                $expected = Money::parse($r['sale_price']);
                $actual = $this->prices->quote([PriceLine::product('x', $v->id)], new PricingContext)->totalGross;
                if ($expected !== $actual) {
                    $saleMismatch[] = ['id' => $r['id'], 'sursa' => $expected, 'tinta' => $actual];
                }
            }
        }

        $slugCheck = null;
        if ($urlsPath && is_file($urlsPath)) {
            $map = json_decode((string) file_get_contents($urlsPath), true)['products'] ?? [];
            $diff = [];
            foreach ($map as $id => $u) {
                $p = $byLegacy->get((int) $id);
                if (! $p || $p->slug !== $u['slug']) {
                    $diff[] = ['id' => (int) $id, 'vechi' => $u['slug'], 'nou' => $p?->slug];
                }
            }
            $slugCheck = ['total' => count($map), 'pastrate' => count($map) - count($diff), 'diferente' => $diff];
        }

        $sourceImages = count($imageUrls);
        $targetImages = $images->pluck('source_url')->unique()->count();
        $checks = [
            'produse' => ['sursa' => count($parents), 'tinta' => $products->count(), 'ok' => count($parents) === $products->count() && $missingProducts === [], 'lipsa' => $missingProducts],
            'variatii' => ['sursa' => count($variations), 'tinta' => $variants->whereIn('legacy_wc_id', array_column($variations, 'id'))->count()],
            'variante_vandabile' => ['sursa' => count($sellable), 'tinta' => $variants->count(), 'ok' => count($sellable) === $variants->count()],
            'imagini_unice' => ['sursa' => $sourceImages, 'tinta' => $targetImages, 'ok' => $sourceImages === $targetImages,
                'descarcate' => $images->where('status', 'ready')->count(), 'in_asteptare' => $images->where('status', 'pending')->count(), 'esuate' => $images->where('status', 'failed')->count()],
            'marci' => ['sursa' => count($brands), 'tinta' => $products->pluck('brand_id')->filter()->unique()->count()],
            'preturi_normale' => ['verificate' => count($sellable), 'diferente' => $regularMismatch, 'ok' => $regularMismatch === []],
            'preturi_promotionale' => ['verificate' => $saleChecked, 'diferente' => $saleMismatch, 'ok' => $saleMismatch === []],
            'url_produse' => $slugCheck,
        ];
        $checks['variatii']['ok'] = $checks['variatii']['sursa'] === $checks['variatii']['tinta'];
        $checks['marci']['ok'] = $checks['marci']['sursa'] === $checks['marci']['tinta'];

        return $checks;
    }
}
