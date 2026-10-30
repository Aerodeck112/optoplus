<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogQuery;
use App\Modules\Storefront\Services\ProductShowcase;
use Illuminate\Http\Request;

/** Căutarea: denumiri, SKU, mărci și termeni uzuali, cu toleranță la greșeli mici. Rezultatele nu se indexează. */
class SearchController extends Controller
{
    public function __invoke(Request $request, CatalogQuery $catalog, ProductShowcase $showcase)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $lenses = collect();
        $frames = collect();
        $total = 0;
        if ($q !== '') {
            $lensIds = $catalog->searchIds($q, Product::TYPE_LENS, 12);
            $lenses = Product::whereIn('id', $lensIds ?: [0])->with('lens.family')->get()->sortBy(fn ($p) => array_search($p->id, $lensIds))->values();
            $page = $catalog->frames($catalog->filtersFromRequest($request), max(1, (int) $request->query('pagina', 1)));
            $page->withPath('/cautare/')->appends(['q' => $q]);
            $frames = $page;
            $total = $page->total() + $lenses->count();
        }

        return view('storefront::search', [
            'q' => $q,
            'lenses' => $lenses,
            'frames' => $frames,
            'cards' => $frames instanceof \Illuminate\Pagination\LengthAwarePaginator ? $showcase->cards($frames->items()) : collect(),
            'total' => $total,
        ]);
    }
}
