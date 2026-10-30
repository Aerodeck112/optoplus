<?php

namespace App\Modules\Catalog\Services;

use App\Core\Support\Text;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Interogarea catalogului de rame: filtre după atribute, marcă, preț și dimensiuni, sortare, căutare tolerantă.
 * Filtrele vin numai din date existente: o valoare fără produse nu apare.
 */
class CatalogQuery
{
    public const PER_PAGE = 24;

    public const SORTS = [
        'recomandate' => 'Recomandate',
        'pret-crescator' => 'Preț crescător',
        'pret-descrescator' => 'Preț descrescător',
        'noi' => 'Cele mai noi',
    ];

    /** Codurile atributelor folosite ca filtre (în ordinea afișării). */
    public const FILTER_ATTRIBUTES = ['gen', 'material', 'constructie', 'forma', 'tip_rama'];

    /**
     * @param  array{attributes?: array<string, list<string>>, brands?: list<string>, price_min?: ?int, price_max?: ?int,
     *               width_min?: ?int, width_max?: ?int, q?: ?string, sort?: string}  $f
     */
    public function frames(array $f, int $page = 1): LengthAwarePaginator
    {
        $q = $this->base($f);
        $sort = $f['sort'] ?? 'recomandate';
        $priceSub = DB::table('product_variants')->selectRaw('MIN(price)')->whereColumn('product_variants.product_id', 'products.id')->where('is_active', true);
        $q->select('products.*')->selectSub($priceSub, 'min_price');
        match ($sort) {
            'pret-crescator' => $q->orderBy('min_price')->orderBy('products.id'),
            'pret-descrescator' => $q->orderByDesc('min_price')->orderBy('products.id'),
            'noi' => $q->orderByDesc('products.legacy_wc_id')->orderByDesc('products.id'),
            default => isset($f['q']) && $f['q'] !== '' ? $q : $q->orderBy('products.sort')->orderByDesc('products.id'),
        };

        return $q->with(['brand', 'variants' => fn ($v) => $v->where('is_active', true), 'images' => fn ($i) => $i->where('status', 'ready')->with('media'), 'frameSpec', 'attributeValues.attribute'])
            ->paginate(self::PER_PAGE, ['*'], 'pagina', $page);
    }

    private function base(array $f, ?string $exceptAttribute = null): Builder
    {
        $q = Product::query()->published()->frames();
        foreach (($f['attributes'] ?? []) as $code => $values) {
            if ($code === $exceptAttribute || $values === []) {
                continue;
            }
            $q->whereHas('attributeValues', fn ($w) => $w->whereIn('attribute_values.slug', $values)
                ->whereHas('attribute', fn ($a) => $a->where('code', $code)));
        }
        if (($f['brands'] ?? []) !== [] && $exceptAttribute !== 'brand') {
            $q->whereHas('brand', fn ($b) => $b->whereIn('slug', $f['brands']));
        }
        if (! empty($f['price_min'])) {
            $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('price', '>=', $f['price_min']));
        }
        if (! empty($f['price_max'])) {
            $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('price', '<=', $f['price_max']));
        }
        if (! empty($f['width_min']) || ! empty($f['width_max'])) {
            $q->whereHas('frameSpec', function ($s) use ($f) {
                if (! empty($f['width_min'])) {
                    $s->where('lens_width_mm', '>=', $f['width_min']);
                }
                if (! empty($f['width_max'])) {
                    $s->where('lens_width_mm', '<=', $f['width_max']);
                }
            });
        }
        if (isset($f['q']) && trim((string) $f['q']) !== '') {
            $ids = $this->searchIds((string) $f['q'], 'frame');
            $q->whereIn('products.id', $ids ?: [0]);
            if ($ids) {
                $q->orderByRaw('FIELD(products.id, '.implode(',', array_map('intval', $ids)).')');
            }
        }

        return $q;
    }

    /**
     * Fațetele pentru filtre, cu numărul de produse pentru fiecare valoare (calculat cu celelalte filtre aplicate).
     *
     * @return array<string, array{label:string, values: list<array{slug:string, label:string, count:int, checked:bool}>}>
     */
    public function facets(array $f): array
    {
        $out = [];
        $attributes = Attribute::whereIn('code', self::FILTER_ATTRIBUTES)->where('is_filterable', true)->orderBy('sort')->get();
        foreach ($attributes as $attr) {
            $ids = $this->base($f, $attr->code)->pluck('products.id');
            $counts = DB::table('product_attribute_values')
                ->join('attribute_values', 'attribute_values.id', '=', 'product_attribute_values.attribute_value_id')
                ->where('attribute_values.attribute_id', $attr->id)
                ->whereIn('product_attribute_values.product_id', $ids)
                ->groupBy('attribute_values.slug', 'attribute_values.label', 'attribute_values.sort')
                ->orderBy('attribute_values.sort')
                ->selectRaw('attribute_values.slug, attribute_values.label, COUNT(*) as n')->get();
            $values = $counts->map(fn ($r) => ['slug' => $r->slug, 'label' => $r->label, 'count' => (int) $r->n,
                'checked' => in_array($r->slug, $f['attributes'][$attr->code] ?? [], true)])->all();
            if ($values) {
                $out[$attr->code] = ['label' => $attr->label, 'values' => $values];
            }
        }
        $ids = $this->base($f, 'brand')->pluck('products.id');
        $brands = Brand::query()->join('products', 'products.brand_id', '=', 'brands.id')
            ->whereIn('products.id', $ids)->groupBy('brands.id', 'brands.slug', 'brands.name')
            ->orderBy('brands.name')->selectRaw('brands.slug, brands.name, COUNT(*) as n')->get();
        if ($brands->isNotEmpty()) {
            $out['brand'] = ['label' => 'Marcă', 'values' => $brands->map(fn ($b) => ['slug' => $b->slug, 'label' => $b->name, 'count' => (int) $b->n,
                'checked' => in_array($b->slug, $f['brands'] ?? [], true)])->all()];
        }

        return $out;
    }

    /** Intervalul lățimilor de lentilă existente (pentru filtrul „dimensiune”). */
    public function widthRange(): array
    {
        $r = DB::table('frame_specs')->join('products', 'products.id', '=', 'frame_specs.product_id')
            ->where('products.status', 'published')->whereNotNull('lens_width_mm')
            ->selectRaw('MIN(lens_width_mm) as mn, MAX(lens_width_mm) as mx')->first();

        return [(int) ($r->mn ?? 0), (int) ($r->mx ?? 0)];
    }

    /** Filtrele din cerere, validate (doar valori cunoscute; restul se ignoră). */
    public function filtersFromRequest(Request $request, array $preset = []): array
    {
        $f = ['attributes' => [], 'brands' => []];
        foreach (self::FILTER_ATTRIBUTES as $code) {
            $vals = array_values(array_filter((array) $request->query($code, []), fn ($v) => is_string($v) && preg_match('/^[a-z0-9_-]{1,40}$/', $v)));
            if ($vals) {
                $f['attributes'][$code] = $vals;
            }
        }
        $f['brands'] = array_values(array_filter((array) $request->query('marca', []), fn ($v) => is_string($v) && preg_match('/^[a-z0-9-]{1,60}$/', $v)));
        foreach (['pret_min' => 'price_min', 'pret_max' => 'price_max'] as $param => $key) {
            $v = $request->query($param);
            $f[$key] = is_numeric($v) && $v >= 0 && $v < 100000 ? (int) $v * 100 : null;
        }
        foreach (['latime_min' => 'width_min', 'latime_max' => 'width_max'] as $param => $key) {
            $v = $request->query($param);
            $f[$key] = is_numeric($v) && $v > 20 && $v < 90 ? (int) $v : null;
        }
        $sort = (string) $request->query('ordine', 'recomandate');
        $f['sort'] = array_key_exists($sort, self::SORTS) ? $sort : 'recomandate';
        $f['q'] = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        foreach ($preset['attributes'] ?? [] as $code => $vals) {
            $f['attributes'][$code] = array_values(array_unique(array_merge($f['attributes'][$code] ?? [], $vals)));
        }
        if (! empty($preset['brands'])) {
            $f['brands'] = array_values(array_unique(array_merge($f['brands'], $preset['brands'])));
        }

        return $f;
    }

    /**
     * Căutare tolerantă: FULLTEXT pe termeni normalizați + potrivire „compactă” (fără spații/cratime, pentru SKU și
     * mărci) + corecție de greșeli mici (distanța Levenshtein față de vocabularul indexului).
     *
     * @return list<int> ID-urile produselor, în ordinea relevanței
     */
    public function searchIds(string $query, ?string $type = null, int $limit = 200): array
    {
        $norm = Text::searchable($query);
        if ($norm === '') {
            return [];
        }
        $words = array_values(array_filter(explode(' ', $norm), fn ($w) => mb_strlen($w) >= 2));
        $words = array_merge($words, $this->synonyms($words));
        $corrected = $this->correct($words);
        $all = array_values(array_unique(array_merge($words, $corrected)));
        $compact = str_replace(' ', '', $norm);

        $q = DB::table('search_index')->join('products', 'products.id', '=', 'search_index.product_id')
            ->where('products.status', 'published')
            ->when($type, fn ($w) => $w->where('products.type', $type));
        $q->where(function ($w) use ($all, $compact) {
            foreach ($all as $word) {
                $w->orWhere('search_index.terms', 'like', '%'.addcslashes($word, '%_').'%');
            }
            if (mb_strlen($compact) >= 3) {
                $w->orWhere('search_index.compact', 'like', '%'.addcslashes($compact, '%_').'%');
            }
        });
        $rows = $q->select('search_index.product_id', 'search_index.terms', 'search_index.compact')->limit(1000)->get();

        // Scor: fiecare cuvânt găsit contează; potrivirea compactă (cod, marcă scrisă legat) contează dublu.
        $scored = $rows->map(function ($r) use ($all, $words, $compact) {
            $score = 0;
            foreach ($all as $w) {
                if (preg_match('/\b'.preg_quote($w, '/').'/', $r->terms)) {
                    $score += in_array($w, $words, true) ? 3 : 2;
                } elseif (str_contains($r->terms, $w)) {
                    $score += 1;
                }
            }
            if (mb_strlen($compact) >= 3 && str_contains($r->compact, $compact)) {
                $score += 6;
            }

            return ['id' => (int) $r->product_id, 'score' => $score];
        })->filter(fn ($r) => $r['score'] > 0);

        // Toate cuvintele căutate trebuie să se regăsească, dacă există astfel de rezultate.
        $required = count(array_unique($words));
        $strict = $scored->filter(fn ($r) => $r['score'] >= min(3 * $required, 6));
        $result = ($strict->isNotEmpty() ? $strict : $scored)->sortByDesc('score')->pluck('id')->take($limit)->values()->all();

        return $result;
    }

    /** @param  list<string>  $words */
    private function synonyms(array $words): array
    {
        if (! $words) {
            return [];
        }
        $rows = DB::table('search_synonyms')->whereIn('term', $words)->pluck('expands_to');

        return $rows->flatMap(fn ($s) => array_map(fn ($t) => Text::searchable($t), explode(',', $s)))->filter()->values()->all();
    }

    /** Corectează cuvintele inexistente în vocabular (distanță Levenshtein ≤ 1 pentru cuvinte scurte, ≤ 2 pentru lungi). */
    private function correct(array $words): array
    {
        $vocab = $this->vocabulary();
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 4 || isset($vocab[$w])) {
                continue;
            }
            $max = mb_strlen($w) >= 7 ? 2 : 1;
            $best = null;
            $bestD = PHP_INT_MAX;
            foreach ($vocab as $term => $_) {
                if (abs(strlen($term) - strlen($w)) > $max) {
                    continue;
                }
                $d = levenshtein($w, $term);
                if ($d <= $max && $d < $bestD) {
                    $best = $term;
                    $bestD = $d;
                }
            }
            if ($best) {
                $out[] = $best;
            }
        }

        return $out;
    }

    /** @return array<string, true> */
    private function vocabulary(): array
    {
        return cache()->remember('catalog.search.vocab', 600, function () {
            $vocab = [];
            DB::table('search_index')->orderBy('product_id')->chunk(500, function ($rows) use (&$vocab) {
                foreach ($rows as $r) {
                    foreach (explode(' ', $r->terms) as $t) {
                        if (strlen($t) >= 4) {
                            $vocab[$t] = true;
                        }
                    }
                }
            });

            return $vocab;
        });
    }

    /** Produsele similare (aceeași marcă sau același material), pentru pagina de produs. */
    public function similar(Product $product, int $limit = 4): Collection
    {
        return Product::published()->frames()->where('id', '!=', $product->id)
            ->where(fn ($q) => $q->where('brand_id', $product->brand_id))
            ->with(['brand', 'frameSpec', 'variants' => fn ($v) => $v->where('is_active', true), 'images' => fn ($i) => $i->where('status', 'ready')->with('media')])
            ->inRandomOrder(crc32($product->slug))->limit($limit)->get();
    }
}
