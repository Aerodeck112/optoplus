<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PricingContext;
use Illuminate\Support\Collection;

/** Cardurile de produs pentru liste și selecții: prețul afișat vine din motorul de preț. */
class ProductShowcase
{
    public function __construct(private PriceEngine $prices) {}

    public function frames(?string $brandSlug = null, int $limit = 8): Collection
    {
        $products = Product::published()->frames()
            ->when($brandSlug, fn ($q) => $q->whereHas('brand', fn ($b) => $b->where('slug', $brandSlug)))
            ->whereHas('images', fn ($i) => $i->where('status', 'ready'))
            ->with(['brand', 'frameSpec', 'variants' => fn ($v) => $v->where('is_active', true), 'images' => fn ($i) => $i->where('status', 'ready')->with('media')])
            ->orderByDesc('legacy_wc_id')->limit(max(1, min(24, $limit)))->get();

        return $this->cards($products);
    }

    /** @param  iterable<Product>  $products */
    public function cards(iterable $products, ?PricingContext $ctx = null): Collection
    {
        $ctx ??= new PricingContext;

        return collect($products)->map(function (Product $p) use ($ctx) {
            $variant = $p->defaultVariant();
            $price = $variant ? $this->prices->displayPrice($variant, $ctx) : null;
            $image = $p->images->first();

            return [
                'product' => $p,
                'url' => $p->url(),
                'name' => $p->name,
                'brand' => $p->brand?->name,
                'price' => $price,
                'base' => $variant?->price,
                'image' => $image?->media,
                'variants' => $p->variants->count(),
                'size' => $p->frameSpec?->sizeNotation(),
            ];
        });
    }
}
