<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Cms\Models\FaqItem;
use App\Modules\Lenses\Models\LensFamily;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Storefront\Services\LensShowcase;

/**
 * Lentilele ca produse independente: o pagină pe produs-lentilă (nu pe fiecare combinație optică), cu indicii,
 * intervalele de fabricație, tratamentele, unitatea de vânzare și prețurile din motorul de preț.
 */
class LensController extends Controller
{
    public function index(LensShowcase $showcase)
    {
        $products = $showcase->lensProducts();
        $families = LensFamily::where('is_active', true)->orderBy('sort')->get()
            ->map(fn ($f) => ['family' => $f, 'products' => $products->filter(fn ($p) => $p->lens->family_id === $f->id)->values()])
            ->filter(fn ($f) => $f['products']->isNotEmpty());

        return view('storefront::lenses.index', [
            'families' => $families,
            'prices' => $products->mapWithKeys(fn ($p) => [$p->id => $showcase->offeringPrice($p->lens->offerings->sortBy('price')->first())]),
        ]);
    }

    public function show(string $slug, LensShowcase $showcase)
    {
        $product = Product::where('slug', $slug)->where('type', Product::TYPE_LENS)->where('status', Product::STATUS_PUBLISHED)
            ->with(['lens.family', 'lens.offerings' => fn ($q) => $q->sellable()->with('material', 'treatments.treatment.options', 'treatments.treatment.datasheet')])->firstOrFail();
        $offerings = $product->lens->offerings->sortBy(fn ($o) => $o->material->refractive_index)->values();
        $treatments = $offerings->flatMap(fn ($o) => $o->treatments)->filter(fn ($t) => $t->treatment?->is_active)
            ->groupBy('treatment_id')->map(fn ($group) => ['treatment' => $group->first()->treatment, 'price_min' => $group->min('price'), 'price_max' => $group->max('price'), 'options' => $group->flatMap(fn ($t) => $t->option_codes ?? [])->unique()->values()]);

        return view('storefront::lenses.show', [
            'product' => $product,
            'offerings' => $offerings->map(fn (LensOffering $o) => ['offering' => $o, 'price' => $showcase->offeringPrice($o)]),
            'treatments' => $treatments->values(),
            'unconfirmed' => $offerings->contains(fn ($o) => $o->status !== LensOffering::STATUS_ACTIVE),
            'faqs' => FaqItem::where('is_published', true)->whereIn('topic', ['lentile', 'reteta'])->orderBy('sort')->limit(6)->get(),
        ]);
    }
}
