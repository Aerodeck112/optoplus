<?php

namespace App\Modules\Catalog\Http\Controllers\Panel;

use App\Core\Audit\Audit;
use App\Core\Support\Html;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\SearchIndexer;
use App\Modules\Cms\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 1.0.20: rame (și accesorii) noi din panou, fără import: formularul „Ramă nouă”, copierea unei rame, variante
 * (culori) noi, pozele produsului și filtrele de pe site. Produsul nou intră ca ciornă; apare pe site doar după
 * „Publică”. Prețul se completează doar cu dreptul „Prețuri” (auditat), ca la editare.
 */
class ProductEditorController extends Controller
{
    public function create(Request $request)
    {
        return view('catalog::panel.product-new', [
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'categories' => Category::orderBy('path')->pluck('name', 'id'),
            'attributes' => self::attributes(),
            'defaultCategory' => Category::where('slug', 'rame')->value('id'),
            'canPrice' => $request->user()->can('catalog.prices'),
        ]);
    }

    public function store(Request $request, SearchIndexer $indexer, MediaLibrary $media)
    {
        $canPrice = $request->user()->can('catalog.prices');
        $data = $request->validate([
            'tip' => ['required', 'in:frame,accessory'],
            'nume' => ['required', 'string', 'max:255'], 'marca' => ['nullable', 'integer', 'exists:brands,id'],
            'categorii' => ['array'], 'categorii.*' => ['integer', 'exists:categories,id'],
            'atribute' => ['array'], 'atribute.*' => ['integer', 'exists:attribute_values,id'],
            'scurta' => ['nullable', 'string', 'max:5000'], 'descriere' => ['nullable', 'string', 'max:100000'],
            'disponibilitate' => ['required', 'in:'.implode(',', array_keys(Product::AVAILABILITY))],
            'cu_lentile' => ['nullable', 'boolean'],
            'latime' => ['nullable', 'integer', 'min:30', 'max:80'], 'punte' => ['nullable', 'integer', 'min:10', 'max:30'], 'brat' => ['nullable', 'integer', 'min:100', 'max:160'],
            'inaltime' => ['nullable', 'integer', 'min:15', 'max:70'], 'tip_rama' => ['nullable', 'in:full,semi_nylon,rimless_screw'],
            'varianta' => ['required', 'array'], 'varianta.nume' => ['nullable', 'string', 'max:160'], 'varianta.sku' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'varianta.pret' => [$canPrice ? 'required' : 'nullable', 'nullable', 'string', 'max:20'], 'varianta.stoc' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'poze' => ['nullable', 'array', 'max:20'], 'poze.*' => ['file', 'max:10240'],
        ], [
            'nume.required' => 'Completați numele ramei.', 'varianta.pret.required' => 'Completați prețul (cu TVA, în lei).',
            'varianta.sku.regex' => 'Codul (SKU) poate conține doar litere, cifre și . _ / -',
        ]);
        $price = $canPrice ? Money::parse((string) $data['varianta']['pret']) : null;
        if ($canPrice && ($price === null || $price <= 0)) {
            throw ValidationException::withMessages(['varianta.pret' => 'Prețul nu este valid.']);
        }
        if (! empty($data['varianta']['sku']) && ProductVariant::where('sku', $data['varianta']['sku'])->exists()) {
            throw ValidationException::withMessages(['varianta.sku' => 'Există deja un produs cu acest cod (SKU).']);
        }

        $product = DB::transaction(function () use ($data, $request, $price) {
            $product = Product::create([
                'type' => $data['tip'], 'name' => $data['nume'], 'slug' => self::uniqueSlug(Str::slug($data['nume']) ?: 'produs'),
                'brand_id' => $data['marca'] ?? null, 'status' => Product::STATUS_DRAFT,
                'short_description' => Html::rich((string) ($data['scurta'] ?? '')), 'description' => Html::rich((string) ($data['descriere'] ?? '')),
                'made_in_house' => false, 'sale_unit' => 'piece', 'availability_mode' => $data['disponibilitate'],
                'configurable_with_lenses' => $data['tip'] === 'frame' && (bool) ($data['cu_lentile'] ?? false), 'updated_by' => $request->user()->id,
            ]);
            $categories = $data['categorii'] ?? [];
            $product->categories()->sync($categories);
            $product->update(['primary_category_id' => $categories[0] ?? null]);
            $product->attributeValues()->sync(collect($data['atribute'] ?? [])->mapWithKeys(fn ($id) => [(int) $id => ['source' => 'panou']])->all());
            if ($data['tip'] === 'frame') {
                $product->frameSpec()->create([
                    'lens_width_mm' => $data['latime'] ?? null, 'bridge_mm' => $data['punte'] ?? null, 'temple_mm' => $data['brat'] ?? null,
                    'lens_height_mm' => $data['inaltime'] ?? null, 'rim_type' => $data['tip_rama'] ?? null,
                    'sources' => ['panou' => 'adăugată de '.$request->user()->name.', '.now()->format('d.m.Y')],
                ]);
            }
            $v = $data['varianta'];
            $product->variants()->create([
                'sku' => ! empty($v['sku']) ? $v['sku'] : self::uniqueSku('OP-'.strtoupper(Str::random(6))), 'sku_generated' => empty($v['sku']),
                'name' => $v['nume'] ?? null, 'price' => $price ?? 0, 'stock_qty' => isset($v['stoc']) && $v['stoc'] !== '' ? (int) $v['stoc'] : null,
                // fără preț (cine nu are dreptul „Prețuri”) varianta rămâne inactivă; produsul nu se poate publica fără preț
                'is_active' => $price !== null, 'sort' => 0,
            ]);

            return $product;
        });
        $count = $this->attachUploads($request, $product, $media);
        $indexer->index($product->fresh());
        Audit::log('catalog.product_created', $product, ['tip' => $product->type, 'poze' => $count, 'pret' => $price]);

        return redirect()->route('panou.catalog.product', $product->id)
            ->with('status', 'Produsul a fost creat ca ciornă'.($count ? " cu {$count} poze" : '').'. Verificați datele, apoi apăsați „Publică”.'.($price === null ? ' Prețul îl completează cine are dreptul „Prețuri”.' : ''));
    }

    /** O copie (ciornă) a unui produs: aceleași date, filtre, dimensiuni și poze; codurile SKU primesc un sufix. */
    public function duplicate(Request $request, int $id, SearchIndexer $indexer)
    {
        $source = Product::with('variants', 'frameSpec', 'categories', 'attributeValues', 'images')->findOrFail($id);
        abort_if($source->type === Product::TYPE_LENS, 404);
        $copy = DB::transaction(function () use ($source, $request) {
            $copy = $source->replicate(['slug', 'legacy_wc_id', 'legacy_url', 'published_at', 'status']);
            $copy->name = $source->name.' (copie)';
            $copy->slug = self::uniqueSlug($source->slug.'-copie');
            $copy->status = Product::STATUS_DRAFT;
            $copy->updated_by = $request->user()->id;
            $copy->save();
            $copy->categories()->sync($source->categories->pluck('id')->all());
            $copy->attributeValues()->sync($source->attributeValues->mapWithKeys(fn ($v) => [$v->id => ['source' => 'panou']])->all());
            if ($source->frameSpec) {
                $spec = $source->frameSpec->replicate(['color_code']);
                $spec->product_id = $copy->id;
                $spec->sources = ['panou' => 'copiată din „'.$source->name.'” de '.$request->user()->name.', '.now()->format('d.m.Y')];
                $spec->save();
            }
            $variantMap = [];
            foreach ($source->variants as $v) {
                $nv = $v->replicate(['legacy_wc_id', 'gtin']);
                $nv->product_id = $copy->id;
                $nv->sku = self::uniqueSku($v->sku.'-C');
                $nv->sku_generated = true;
                $nv->save();
                $variantMap[$v->id] = $nv->id;
            }
            foreach ($source->images as $im) {
                ProductImage::create(['product_id' => $copy->id, 'variant_id' => $variantMap[$im->variant_id] ?? null, 'media_id' => $im->media_id, 'source_url' => null,
                    'status' => $im->status, 'alt' => $im->alt, 'sort' => $im->sort]);
            }

            return $copy;
        });
        $indexer->index($copy->fresh());
        Audit::log('catalog.product_copied', $copy, ['din' => $source->id]);

        return redirect()->route('panou.catalog.product', $copy->id)->with('status', 'Copia a fost creată ca ciornă. Schimbați numele, culorile, codurile și pozele, apoi apăsați „Publică”.');
    }

    /** O variantă (culoare) nouă. Prețul doar cu dreptul „Prețuri”. */
    public function addVariant(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        abort_if($product->type === Product::TYPE_LENS, 404);
        $canPrice = $request->user()->can('catalog.prices');
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:160'], 'sku' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._\/-]+$/', 'unique:product_variants,sku'],
            'pret' => [$canPrice ? 'required' : 'nullable', 'nullable', 'string', 'max:20'], 'stoc' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['nume.required' => 'Completați numele variantei (ex. culoarea).', 'sku.unique' => 'Există deja un produs cu acest cod (SKU).']);
        $price = $canPrice ? Money::parse((string) $data['pret']) : null;
        if ($canPrice && ($price === null || $price <= 0)) {
            throw ValidationException::withMessages(['pret' => 'Prețul nu este valid.']);
        }
        $variant = $product->variants()->create([
            'sku' => $data['sku'] ?? self::uniqueSku('OP-'.strtoupper(Str::random(6))), 'sku_generated' => empty($data['sku']),
            'name' => $data['nume'], 'price' => $price ?? 0, 'stock_qty' => $data['stoc'] ?? null,
            'is_active' => $price !== null, 'sort' => (int) $product->variants()->max('sort') + 1,
        ]);
        Audit::log('catalog.variant_created', $product, ['sku' => $variant->sku, 'pret' => $price]);

        return back()->with('status', "Varianta „{$variant->name}” a fost adăugată.");
    }

    /** Pozele produsului: încărcare (mai multe deodată), prima devine principală dacă nu există alta. */
    public function uploadImages(Request $request, int $id, MediaLibrary $media)
    {
        $product = Product::findOrFail($id);
        $request->validate(['poze' => ['required', 'array', 'max:20'], 'poze.*' => ['file', 'max:10240'], 'varianta' => ['nullable', 'integer']], ['poze.required' => 'Alegeți cel puțin o poză.']);
        $count = $this->attachUploads($request, $product, $media, $this->variantOf($product, $request->input('varianta')));
        Audit::log('catalog.images_uploaded', $product, ['poze' => $count]);

        return back()->with('status', $count === 1 ? 'Poza a fost adăugată.' : "{$count} poze au fost adăugate.");
    }

    /** Ordinea pozelor, poza principală, textul alternativ, ștergerea (doar legătura; fișierul rămâne în biblioteca media). */
    public function imageAction(Request $request, int $id, int $imageId)
    {
        $product = Product::findOrFail($id);
        $image = ProductImage::where('product_id', $product->id)->findOrFail($imageId);
        $data = $request->validate(['actiune' => ['required', 'in:principala,sus,jos,sterge,alt,culoare'], 'alt' => ['nullable', 'string', 'max:255'], 'varianta' => ['nullable', 'integer']]);
        $variantId = $data['actiune'] === 'culoare' ? $this->variantOf($product, $data['varianta'] ?? null) : null;
        DB::transaction(function () use ($product, $image, $data, $variantId) {
            $images = ProductImage::where('product_id', $product->id)->orderBy('sort')->orderBy('id')->get()->values();
            $index = $images->search(fn ($i) => $i->id === $image->id);
            $order = $images->all();
            match ($data['actiune']) {
                'principala' => array_unshift($order, ...array_splice($order, $index, 1)),
                'sus' => $index > 0 ? [$order[$index - 1], $order[$index]] = [$order[$index], $order[$index - 1]] : null,
                'jos' => $index < count($order) - 1 ? [$order[$index + 1], $order[$index]] = [$order[$index], $order[$index + 1]] : null,
                default => null,
            };
            if ($data['actiune'] === 'sterge') {
                $image->delete();
                $order = array_values(array_filter($order, fn ($i) => $i->id !== $image->id));
            }
            if ($data['actiune'] === 'culoare') {
                $image->update(['variant_id' => $variantId]);
            }
            if ($data['actiune'] === 'alt') {
                $image->update(['alt' => $data['alt'] ?? null]);
                $image->media?->update(['alt' => $data['alt'] ?? null]);
            }
            foreach ($order as $i => $im) {
                if ($im->exists && (int) $im->sort !== $i) {
                    ProductImage::whereKey($im->id)->update(['sort' => $i]);
                }
            }
            $product->touch(); // golește cache-ul paginilor
        });
        Audit::log('catalog.image_'.$data['actiune'], $product, ['imagine' => $image->id]);

        return back()->with('status', match ($data['actiune']) {
            'sterge' => 'Poza a fost scoasă de la produs.',
            'culoare' => $variantId ? 'Poza apare acum când clientul alege culoarea „'.($product->variants()->find($variantId)?->name ?: 'aleasă').'”.' : 'Poza apare acum la toate culorile.',
            default => 'Pozele au fost actualizate.',
        });
    }

    /** Varianta (culoarea) aleasă pentru o poză; doar dintre variantele produsului. Gol = toate culorile. */
    private function variantOf(Product $product, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        $variant = $product->variants()->whereKey((int) $id)->first();
        if (! $variant) {
            throw ValidationException::withMessages(['varianta' => 'Culoarea aleasă nu aparține acestui produs.']);
        }

        return $variant->id;
    }

    private function attachUploads(Request $request, Product $product, MediaLibrary $media, ?int $variantId = null): int
    {
        $files = (array) $request->file('poze', []);
        if ($files === []) {
            return 0;
        }
        $sort = (int) ProductImage::where('product_id', $product->id)->max('sort');
        $hasImages = ProductImage::where('product_id', $product->id)->exists();
        $count = 0;
        foreach ($files as $file) {
            $m = $media->upload($file, $product->name, 'catalog');
            $m->update(['uploaded_by' => $request->user()->id]);
            ProductImage::create(['product_id' => $product->id, 'variant_id' => $variantId, 'media_id' => $m->id, 'source_url' => null,
                'status' => 'ready', 'alt' => $product->name, 'sort' => $hasImages || $count > 0 ? ++$sort : 0]);
            $count++;
        }
        $product->touch();

        return $count;
    }

    /** Atributele pentru filtrele de pe site: cod => [etichetă, [id valoare => etichetă]]. */
    public static function attributes(): array
    {
        return Attribute::with(['values' => fn ($q) => $q->orderBy('sort')])->where('is_filterable', true)->orderBy('sort')->get()
            ->filter(fn ($a) => $a->values->isNotEmpty())
            ->mapWithKeys(fn ($a) => [$a->code => [$a->label, $a->values->pluck('label', 'id')->all()]])->all();
    }

    public static function uniqueSlug(string $slug): string
    {
        $slug = mb_substr($slug, 0, 120);
        $candidate = $slug;
        $i = 2;
        while (Product::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }

    public static function uniqueSku(string $sku): string
    {
        $candidate = mb_substr($sku, 0, 74);
        $i = 2;
        while (ProductVariant::where('sku', $candidate)->exists()) {
            $candidate = mb_substr($sku, 0, 74).'-'.$i++;
        }

        return $candidate;
    }
}
