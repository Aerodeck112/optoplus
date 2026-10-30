<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CatalogQuery;
use App\Modules\Storefront\Services\ProductShowcase;
use Illuminate\Http\Request;

/**
 * Catalogul de rame. URL-uri curate și indexabile doar pentru filtrele cu valoare de căutare (gen, material,
 * construcție, marcă): /rame/femei/, /rame/metal/, /rame/clip-on/, /marci/ray-ban/. Orice combinație cu parametri
 * (preț, sortare, mai multe filtre) primește noindex și canonical spre pagina de bază.
 */
class CatalogController extends Controller
{
    public function __construct(private CatalogQuery $catalog, private ProductShowcase $showcase) {}

    public function index(Request $request, ?string $filtru = null)
    {
        $preset = [];
        $heading = 'Ochelari și rame';
        $crumbs = ['Ochelari și rame' => '/rame/'];
        $basePath = '/rame/';
        if ($filtru) {
            $value = AttributeValue::where('slug', $filtru)
                ->whereHas('attribute', fn ($a) => $a->where('is_indexable', true))->with('attribute')->first();
            abort_unless($value, 404);
            $preset['attributes'][$value->attribute->code] = [$value->slug];
            $heading = $this->headingFor($value);
            $crumbs[$heading] = null;
            $basePath = '/rame/'.$value->slug.'/';
        }

        return $this->render($request, $preset, $heading, $crumbs, $basePath, Category::where('path', '/rame/')->first()?->intro);
    }

    public function brand(Request $request, string $brand)
    {
        $b = Brand::where('slug', $brand)->firstOrFail();
        $intro = $b->description ?: ($b->is_own_brand ? 'Marcă proprie Optoplus.' : null);

        return $this->render($request, ['brands' => [$b->slug]], 'Rame '.$b->name, ['Ochelari și rame' => '/rame/', $b->name => null], $b->url(), $intro, $b);
    }

    private function render(Request $request, array $preset, string $heading, array $crumbs, string $basePath, ?string $intro, ?Brand $brand = null)
    {
        $filters = $this->catalog->filtersFromRequest($request, $preset);
        $page = max(1, (int) $request->query('pagina', 1));
        $products = $this->catalog->frames($filters, $page);
        $products->withPath($basePath);
        $extraParams = array_diff(array_keys($request->query()), ['pagina']);

        return view('storefront::catalog', [
            'heading' => $heading,
            'intro' => $intro,
            'crumbs' => $crumbs,
            'products' => $products,
            'cards' => $this->showcase->cards($products->items()),
            'facets' => $this->catalog->facets($filters),
            'filters' => $filters,
            'preset' => $preset,
            'basePath' => $basePath,
            'widthRange' => $this->catalog->widthRange(),
            'sorts' => CatalogQuery::SORTS,
            'noindex' => $extraParams !== [] || $products->isEmpty(),
            'canonical' => rtrim((string) config('optoplus.site.canonical_host'), '/').$basePath.($page > 1 && $extraParams === [] ? '?pagina='.$page : ''),
            'brand' => $brand,
        ]);
    }

    private function headingFor(AttributeValue $v): string
    {
        return match ($v->attribute->code) {
            'gen' => match ($v->slug) {
                'femei' => 'Rame de ochelari pentru femei',
                'barbati' => 'Rame de ochelari pentru bărbați',
                'copii' => 'Rame de ochelari pentru copii',
                default => 'Rame de ochelari unisex',
            },
            'material' => 'Rame din '.mb_strtolower($v->label),
            'constructie' => 'Rame clip-on',
            default => 'Rame: '.$v->label,
        };
    }
}
