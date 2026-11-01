<?php

namespace App\Modules\Catalog\Http\Controllers\Panel;

use App\Core\Audit\Audit;
use App\Core\Support\Html;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\SearchIndexer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Produsele (rame, accesorii) în panou. Prețurile și costurile au permisiune separată și audit cu valoarea
 * veche și cea nouă; descrierile trec prin HTMLPurifier. Lentilele produse în laborator se editează în „Lentile”.
 */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::query()->where('type', '!=', Product::TYPE_LENS)->with('brand:id,name', 'variants:id,product_id,price,stock_qty,is_active', 'images.media');
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($term, '%_').'%')->orWhereHas('variants', fn ($v) => $v->where('sku', $term)));
        }
        foreach (['status', 'brand_id', 'availability_mode'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($request->boolean('fara_dimensiuni')) {
            $q->whereDoesntHave('frameSpec', fn ($s) => $s->whereNotNull('lens_width_mm'));
        }

        return view('catalog::panel.products', [
            'products' => $q->orderByDesc('id')->paginate(40)->withQueryString(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'), 'filters' => $request->query(),
        ]);
    }

    public function edit(int $id)
    {
        $product = Product::with('variants', 'frameSpec', 'images.media', 'categories', 'brand')->findOrFail($id);

        return view('catalog::panel.product', [
            'product' => $product, 'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'categories' => Category::orderBy('path')->pluck('path', 'id'),
        ]);
    }

    public function update(Request $request, int $id, SearchIndexer $indexer)
    {
        $product = Product::with('frameSpec')->findOrFail($id);
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:255'], 'marca' => ['nullable', 'integer', 'exists:brands,id'],
            'categorii' => ['array'], 'categorii.*' => ['integer', 'exists:categories,id'],
            'scurta' => ['nullable', 'string', 'max:5000'], 'descriere' => ['nullable', 'string', 'max:100000'],
            'disponibilitate' => ['required', 'in:'.implode(',', array_keys(Product::AVAILABILITY))],
            'zile_min' => ['nullable', 'integer', 'min:0', 'max:180'], 'zile_max' => ['nullable', 'integer', 'min:0', 'max:180', 'gte:zile_min'],
            'cu_lentile' => ['nullable', 'boolean'], 'seo_titlu' => ['nullable', 'string', 'max:255'], 'seo_descriere' => ['nullable', 'string', 'max:320'], 'noindex' => ['nullable', 'boolean'],
            'latime' => ['nullable', 'integer', 'min:30', 'max:80'], 'punte' => ['nullable', 'integer', 'min:10', 'max:30'], 'brat' => ['nullable', 'integer', 'min:100', 'max:160'],
            'inaltime' => ['nullable', 'integer', 'min:15', 'max:70'], 'tip_rama' => ['nullable', 'in:full,semi_nylon,rimless_screw'],
        ]);
        DB::transaction(function () use ($product, $data, $request) {
            $product->update([
                'name' => $data['nume'], 'brand_id' => $data['marca'] ?? null,
                'short_description' => Html::rich((string) ($data['scurta'] ?? '')), 'description' => Html::rich((string) ($data['descriere'] ?? '')),
                'availability_mode' => $data['disponibilitate'], 'lead_time_days_min' => $data['zile_min'] ?? null, 'lead_time_days_max' => $data['zile_max'] ?? null,
                'configurable_with_lenses' => (bool) ($data['cu_lentile'] ?? false), 'seo_title' => $data['seo_titlu'] ?? null, 'seo_description' => $data['seo_descriere'] ?? null,
                'noindex' => (bool) ($data['noindex'] ?? false), 'updated_by' => $request->user()->id,
            ]);
            $product->categories()->sync($data['categorii'] ?? []);
            if ($product->isFrame()) {
                $spec = $product->frameSpec ?? $product->frameSpec()->make();
                $sources = (array) ($spec->sources ?? []);
                foreach (['lens_width_mm' => 'latime', 'bridge_mm' => 'punte', 'temple_mm' => 'brat', 'lens_height_mm' => 'inaltime'] as $col => $field) {
                    if (($data[$field] ?? null) != $spec->{$col}) {
                        $sources[$col] = 'panou: '.$request->user()->name.', '.now()->format('d.m.Y');
                    }
                    $spec->{$col} = $data[$field] ?? null;
                }
                $spec->rim_type = $data['tip_rama'] ?? null;
                $spec->sources = $sources;
                $spec->product_id = $product->id;
                $spec->save();
            }
        });
        $indexer->index($product->fresh());
        Audit::log('catalog.product_updated', $product, ['campuri' => array_keys($product->getChanges())]);

        return back()->with('status', 'Produsul a fost salvat.');
    }

    public function publish(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $to = $request->validate(['stare' => ['required', 'in:draft,published,archived']])['stare'];
        if ($to === 'published' && ! $product->variants()->where('is_active', true)->exists()) {
            return back()->with('warning', 'Produsul nu are nicio variantă activă cu preț.');
        }
        $product->update(['status' => $to, 'published_at' => $to === 'published' ? ($product->published_at ?? now()) : $product->published_at]);
        Audit::log('catalog.product_status', $product, ['status' => $to]);

        return back()->with('status', $to === 'archived' ? 'Produsul a fost retras. URL-ul vechi răspunde cu o pagină utilă (produs indisponibil), nu cu redirect spre prima pagină.' : 'Starea a fost actualizată.');
    }

    /** Prețuri, costuri și stoc pe variante: permisiune separată, audit cu valorile vechi și noi. */
    public function variants(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'v' => ['required', 'array'], 'v.*.pret' => ['required', 'string'], 'v.*.cost' => ['nullable', 'string'],
            'v.*.stoc' => ['nullable', 'integer', 'min:0', 'max:100000'], 'v.*.activa' => ['nullable', 'boolean'], 'v.*.nume' => ['nullable', 'string', 'max:160'],
        ]);
        $changes = [];
        DB::transaction(function () use ($product, $data, &$changes) {
            foreach ($data['v'] as $variantId => $row) {
                $variant = ProductVariant::where('product_id', $product->id)->findOrFail((int) $variantId);
                $price = Money::parse($row['pret']);
                if ($price === null || $price <= 0) {
                    abort(422, 'Preț invalid pentru varianta '.$variant->sku);
                }
                $new = ['price' => $price, 'cost' => ($row['cost'] ?? '') !== '' ? Money::parse($row['cost']) : null,
                    'stock_qty' => ($row['stoc'] ?? '') !== '' ? (int) $row['stoc'] : null, 'is_active' => (bool) ($row['activa'] ?? false), 'name' => $row['nume'] ?? $variant->name];
                foreach (['price', 'cost', 'stock_qty', 'is_active'] as $f) {
                    if ($variant->{$f} != $new[$f]) {
                        $changes[] = ['sku' => $variant->sku, 'camp' => $f, 'vechi' => $variant->{$f}, 'nou' => $new[$f]];
                    }
                }
                $variant->update($new);
            }
        });
        if ($changes) {
            Audit::log('catalog.prices_updated', $product, ['modificari' => $changes]);
        }

        return back()->with('status', $changes ? 'Prețurile și stocul au fost actualizate.' : 'Nicio modificare.');
    }
}
