<?php

namespace App\Modules\Pricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Pricing\Services\PriceTools;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Prețuri → Gestionare prețuri: previzualizare (GET), aplicare și anulare (POST). */
class PriceToolController extends Controller
{
    public function index(Request $request, PriceTools $tools)
    {
        $params = null;
        $preview = null;
        if ($request->has('previzualizare')) {
            if ($request->input('mod') === 'reducere') {
                abort_unless($request->user()->can('pricing.manage'), 403);
            }
            $params = $tools->params($request->all());
            $preview = $tools->preview($params);
        }

        return view('pricing::panel.tools', [
            'params' => $params, 'preview' => $preview, 'tools' => $tools,
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'batches' => DB::table('price_change_batches')->leftJoin('users', 'users.id', '=', 'price_change_batches.created_by')
                ->orderByDesc('price_change_batches.id')->limit(15)->get(['price_change_batches.*', 'users.name as user_name']),
            'discounts' => \App\Modules\Pricing\Models\Promotion::where('source', 'Gestionare prețuri')->latest('id')->limit(10)->get(),
        ]);
    }

    public function apply(Request $request, PriceTools $tools)
    {
        $params = $tools->params($request->all());
        if ($params['mod'] === 'reducere') {
            abort_unless($request->user()->can('pricing.manage'), 403);
            $promotion = $tools->createDiscount($params, $request->user());

            return redirect()->route('panou.pricing.tools')->with('status', "Reducerea „{$promotion->customer_label}” este activă între {$promotion->starts_at->format('d.m.Y')} și {$promotion->ends_at->format('d.m.Y')} pentru ".count($promotion->scope_ids).' produse. O puteți modifica sau opri din Promoții și cupoane.');
        }
        $result = $tools->applyBase($params, $request->user());

        return redirect()->route('panou.pricing.tools')->with('status', "Prețurile au fost modificate pentru {$result['count']} variante. Dacă v-ați răzgândit, apăsați „Anulează” în istoricul de mai jos.");
    }

    public function revert(Request $request, PriceTools $tools, int $id)
    {
        $r = $tools->revert($id, $request->user());

        return back()->with('status', "Am readus prețul vechi la {$r['restored']} variante.".($r['kept'] ? " {$r['kept']} variante au fost modificate între timp și au rămas cu prețul lor actual." : ''));
    }
}
