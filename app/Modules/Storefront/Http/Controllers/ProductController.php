<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogQuery;
use App\Modules\Cms\Models\FaqItem;
use App\Modules\Configurator\Support\ConfigurationInput;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Storefront\Services\LensShowcase;
use App\Modules\Storefront\Services\ProductShowcase;

/** Pagina unei rame: URL-ul vechi /produs/<slug>/ este păstrat. Lentilele au pagina lor (LensController). */
class ProductController extends Controller
{
    public function show(string $slug, PriceEngine $prices, LensShowcase $lenses, ProductShowcase $showcase, CatalogQuery $catalog)
    {
        // 1.0.21: colegii autentificați în panou (cu dreptul de a vedea catalogul) pot previzualiza și ciornele.
        $staff = \Illuminate\Support\Facades\Auth::guard('web')->user();
        $preview = $staff?->can('catalog.view') ?? false;
        $product = Product::where('slug', $slug)->when(! $preview, fn ($q) => $q->where('status', Product::STATUS_PUBLISHED))
            ->with(['brand', 'variants' => fn ($v) => $v->where('is_active', true), 'images' => fn ($i) => $i->where('status', 'ready')->with('media'), 'frameSpec', 'attributeValues.attribute'])
            ->first();
        if (! $product) {
            abort(404);
        }
        if ($product->isLens()) {
            return redirect($product->url(), 301);
        }

        $ctx = PricingContext::b2c();
        $variants = $product->variants->map(fn ($v) => [
            'model' => $v,
            'price' => $prices->displayPrice($v, $ctx),
        ]);
        $default = $variants->first();
        // 1.0.27: prețul anterior (cel mai mic din ultimele 30 de zile) doar la reducerile care îl permit
        $reduced = $default ? app(\App\Modules\Pricing\Services\ReducedPrice::class)->forVariant($default['model'], $ctx) : null;

        // „Cu lentile de la”: rama + cea mai ieftină lentilă vandabilă, calculat de motorul de preț.
        $withLenses = null;
        if ($product->configurable_with_lenses && $default && ($cheapest = $lenses->cheapestOffering())) {
            $withLenses = $prices->quoteConfiguration(new ConfigurationInput(entry: 'cu_rama', frameVariantId: $default['model']->id, offeringId: $cheapest->id), $ctx)->totalGross;
        }

        $response = response()->view('storefront::product', [
            'staffEdit' => $preview ? route('panou.catalog.product', $product->id) : null,
            'product' => $product,
            'variants' => $variants,
            'default' => $default,
            'reduced' => $reduced,
            'withLenses' => $withLenses,
            'spec' => $product->frameSpec,
            'attrs' => collect(['gen' => 'Pentru', 'material' => 'Material', 'forma' => 'Formă', 'tip_rama' => 'Tip ramă', 'constructie' => 'Construcție'])
                ->map(fn ($label, $code) => ['label' => $label, 'values' => $product->attributeLabels($code)])
                ->filter(fn ($a) => $a['values'] !== []),
            'similar' => $showcase->cards($catalog->similar($product)),
            'faqs' => FaqItem::where('is_published', true)->whereIn('topic', ['rame', 'livrare', 'reteta'])->orderBy('sort')->limit(6)->get(),
        ]);
        if ($preview) {
            // pagina văzută de un coleg (cu butonul de editare / ciornă) nu se păstrează în cache și nu se indexează
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-No-Page-Cache', '1');
            if ($product->status !== Product::STATUS_PUBLISHED) {
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }
        }

        return $response;
    }
}
