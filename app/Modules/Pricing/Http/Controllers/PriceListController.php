<?php

namespace App\Modules\Pricing\Http\Controllers;

use App\Core\Audit\Audit;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\B2B\Models\Organization;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceListItem;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Listele de preț B2B. Prețurile se introduc pe cod (SKU ramă sau cod ofertă de lentilă), cu prag cantitativ.
 * Articolele fără preț în listă folosesc regula de rezervă a listei (preț B2C sau B2C cu reducere).
 */
class PriceListController extends Controller
{
    public function index()
    {
        return view('pricing::panel.lists', ['lists' => PriceList::withCount('items')->orderBy('name')->get(),
            'usage' => Organization::selectRaw('price_list_id, count(*) n')->whereNotNull('price_list_id')->groupBy('price_list_id')->pluck('n', 'price_list_id')]);
    }

    public function show(int $id)
    {
        $list = PriceList::findOrFail($id);
        $items = PriceListItem::where('price_list_id', $id)->orderBy('item_type')->orderBy('item_id')->orderBy('min_qty')->get();
        $variants = ProductVariant::with('product:id,name')->whereIn('id', $items->where('item_type', 'variant')->pluck('item_id'))->get()->keyBy('id');
        $offerings = LensOffering::with('product:id,name', 'material')->whereIn('id', $items->where('item_type', 'offering')->pluck('item_id'))->get()->keyBy('id');

        return view('pricing::panel.list', compact('list', 'items', 'variants', 'offerings'));
    }

    public function save(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:160'], 'cod' => ['required', 'string', 'max:40', 'alpha_dash'],
            'cu_tva' => ['nullable', 'boolean'], 'rezerva' => ['required', 'in:none,b2c,b2c_discount'], 'reducere' => ['nullable', 'string', 'max:10'], 'activa' => ['nullable', 'boolean'],
        ]);
        $list = $id ? PriceList::findOrFail($id) : new PriceList(['channel' => 'b2b']);
        if (PriceList::where('code', $data['cod'])->where('id', '!=', $list->id ?? 0)->exists()) {
            throw ValidationException::withMessages(['cod' => 'Codul există deja.']);
        }
        $disc = ($data['reducere'] ?? '') !== '' && is_numeric($v = str_replace(',', '.', $data['reducere'])) ? (int) round($v * 100) : 0;
        $list->fill(['name' => $data['nume'], 'code' => $data['cod'], 'prices_include_vat' => (bool) ($data['cu_tva'] ?? false), 'fallback' => $data['rezerva'], 'fallback_discount_bp' => max(0, min(9000, $disc)), 'is_active' => (bool) ($data['activa'] ?? false)])->save();
        Audit::log($id ? 'pricing.list_updated' : 'pricing.list_created', $list);

        return redirect()->route('panou.pricing.lists.show', $list->id)->with('status', 'Lista a fost salvată.');
    }

    /** Adăugare în bloc: un rând = „COD;PREȚ” sau „COD;PREȚ;CANTITATE_MINIMĂ”. */
    public function addItems(Request $request, int $id)
    {
        $list = PriceList::findOrFail($id);
        $rows = preg_split('/\r\n|\r|\n/', (string) $request->validate(['randuri' => ['required', 'string', 'max:100000']])['randuri']);
        $errors = [];
        $added = 0;
        foreach ($rows as $n => $row) {
            if (trim($row) === '') {
                continue;
            }
            [$code, $price, $min] = array_pad(array_map('trim', preg_split('/[;\t]/', $row)), 3, null);
            $bani = Money::parse((string) $price);
            $offering = LensOffering::where('code', $code)->first();
            $variant = $offering ? null : ProductVariant::where('sku', $code)->first();
            if ((! $offering && ! $variant) || ! $bani) {
                $errors[] = 'Rândul '.($n + 1).': '.(! $bani ? 'preț invalid' : "codul „{$code}” nu există");

                continue;
            }
            PriceListItem::updateOrCreate(
                ['price_list_id' => $list->id, 'item_type' => $offering ? 'offering' : 'variant', 'item_id' => ($offering ?? $variant)->id, 'min_qty' => max(1, (int) ($min ?: 1))],
                ['price' => $bani],
            );
            $added++;
        }
        Audit::log('pricing.list_items', $list, ['adaugate' => $added, 'erori' => count($errors)]);

        return back()->with('status', "Au fost salvate {$added} prețuri.")->withErrors($errors ? ['randuri' => implode(' · ', array_slice($errors, 0, 20))] : []);
    }

    public function removeItem(int $id, int $itemId)
    {
        $item = PriceListItem::where('price_list_id', $id)->findOrFail($itemId);
        $item->delete();
        Audit::log('pricing.list_item_removed', PriceList::find($id), ['item' => $item->item_type.':'.$item->item_id, 'price' => $item->price]);

        return back()->with('status', 'Prețul a fost eliminat din listă.');
    }
}
