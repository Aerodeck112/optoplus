<?php

namespace App\Modules\Pricing\Http\Controllers;

use App\Core\Audit\Audit;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pricing\Models\Coupon;
use App\Modules\Pricing\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Promoțiile și cupoanele. Reducerile se aplică numai de motorul de preț (PriceEngine); aici se definesc regulile.
 * Prețul barat apare doar pentru o promoție activă, cu dată — nu se inventează „prețuri vechi”.
 */
class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('stare', 'active');
        $q = Promotion::query()->withCount('coupons');
        if ($filter === 'active') {
            $q->running(now());
        } elseif ($filter === 'inactive') {
            $q->where(fn ($w) => $w->where('is_active', false)->orWhere('ends_at', '<', now()));
        }

        return view('pricing::panel.promotions', ['promotions' => $q->orderBy('priority')->orderByDesc('id')->paginate(40)->withQueryString(), 'filter' => $filter]);
    }

    public function create()
    {
        return $this->form(new Promotion(['scope' => 'order', 'type' => 'percent', 'priority' => 100, 'channel' => 'b2c', 'is_active' => false]));
    }

    public function edit(int $id)
    {
        return $this->form(Promotion::with('coupons')->findOrFail($id));
    }

    private function form(Promotion $p)
    {
        return view('pricing::panel.promotion', [
            'p' => $p,
            'categories' => Category::orderBy('path')->pluck('path', 'id')->all(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id')->all(),
            'productSlugs' => $p->scope === 'product' ? Product::whereIn('id', (array) $p->scope_ids)->pluck('slug')->implode("\n") : '',
        ]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:160'], 'eticheta' => ['nullable', 'string', 'max:160'],
            'aplicare' => ['required', 'in:'.implode(',', array_keys(Promotion::SCOPES))],
            'categorii' => ['array'], 'categorii.*' => ['integer'], 'marci' => ['array'], 'marci.*' => ['integer'], 'produse' => ['nullable', 'string', 'max:20000'],
            'tip' => ['required', 'in:'.implode(',', array_keys(Promotion::TYPES))], 'valoare' => ['required', 'string', 'max:20'],
            'de_la' => ['nullable', 'date'], 'pana_la' => ['nullable', 'date', 'after_or_equal:de_la'],
            'activa' => ['nullable', 'boolean'], 'cumulabila' => ['nullable', 'boolean'], 'prioritate' => ['required', 'integer', 'min:0', 'max:9999'],
            'cu_cupon' => ['nullable', 'boolean'], 'canal' => ['required', 'in:b2c,b2b,all'],
            'minim' => ['nullable', 'string', 'max:20'], 'max_utilizari' => ['nullable', 'integer', 'min:1'], 'max_per_client' => ['nullable', 'integer', 'min:1'],
            'beneficiari' => ['nullable', 'string', 'max:60'], 'dovada' => ['nullable', 'boolean'],
        ], ['pana_la.after_or_equal' => 'Data de sfârșit trebuie să fie după data de început.']);

        $value = $data['tip'] === 'percent'
            ? (is_numeric($v = str_replace(',', '.', $data['valoare'])) && $v > 0 && $v <= 100 ? (int) round($v * 100) : null)
            : Money::parse($data['valoare']);
        if (! $value) {
            throw ValidationException::withMessages(['valoare' => $data['tip'] === 'percent' ? 'Procent între 0 și 100.' : 'Sumă invalidă (lei).']);
        }
        if ($data['tip'] === 'fixed_price' && in_array($data['aplicare'], ['order'], true)) {
            throw ValidationException::withMessages(['tip' => '„Preț fix” se aplică produselor, nu întregii comenzi.']);
        }
        $scopeIds = match ($data['aplicare']) {
            'category' => array_map('intval', $data['categorii'] ?? []),
            'brand' => array_map('intval', $data['marci'] ?? []),
            'product' => $this->productIds((string) ($data['produse'] ?? '')),
            default => null,
        };
        if (in_array($data['aplicare'], ['category', 'brand', 'product'], true) && ! $scopeIds) {
            throw ValidationException::withMessages(['aplicare' => 'Alegeți cel puțin o categorie, marcă sau un produs.']);
        }
        $p = $id ? Promotion::findOrFail($id) : new Promotion(['source' => 'manual']);
        $before = $p->exists ? $p->only(['value', 'type', 'scope', 'is_active', 'starts_at', 'ends_at']) : null;
        $p->fill([
            'name' => $data['nume'], 'customer_label' => $data['eticheta'] ?? null, 'scope' => $data['aplicare'], 'scope_ids' => $scopeIds,
            'type' => $data['tip'], 'value' => $value, 'starts_at' => $data['de_la'] ?? null, 'ends_at' => isset($data['pana_la']) ? \Illuminate\Support\Carbon::parse($data['pana_la'])->endOfDay() : null,
            'is_active' => (bool) ($data['activa'] ?? false), 'stackable' => (bool) ($data['cumulabila'] ?? false), 'priority' => (int) $data['prioritate'],
            'requires_coupon' => (bool) ($data['cu_cupon'] ?? false), 'channel' => $data['canal'],
            'min_order_total' => ($data['minim'] ?? '') !== '' ? Money::parse($data['minim']) : null,
            'max_uses' => $data['max_utilizari'] ?? null, 'max_uses_per_customer' => $data['max_per_client'] ?? null,
            'beneficiary_category' => $data['beneficiari'] ?? null, 'requires_proof' => (bool) ($data['dovada'] ?? false),
            'updated_by' => $request->user()->id,
        ])->save();
        Audit::log($id ? 'pricing.promotion_updated' : 'pricing.promotion_created', $p, ['inainte' => $before, 'dupa' => $p->only(['value', 'type', 'scope', 'is_active', 'starts_at', 'ends_at'])]);

        return redirect()->route('panou.pricing.promotions.edit', $p->id)->with('status', 'Promoția a fost salvată.'.($p->is_active ? '' : ' Este inactivă: bifați „Activă” ca să se aplice.'));
    }

    /** @return list<int> */
    private function productIds(string $list): array
    {
        $tokens = array_filter(array_map('trim', preg_split('/[\s,;]+/', $list)));
        if (! $tokens) {
            return [];
        }
        $slugs = array_map(fn ($t) => trim(parse_url($t, PHP_URL_PATH) ? basename(rtrim(parse_url($t, PHP_URL_PATH), '/')) : $t), $tokens);
        $ids = Product::whereIn('slug', $slugs)->pluck('id')
            ->merge(Product::whereHas('variants', fn ($v) => $v->whereIn('sku', $tokens))->pluck('id'))->unique()->values()->all();
        if (count($ids) < count($tokens)) {
            $found = Product::whereIn('id', $ids)->pluck('slug')->all();
            $missing = array_diff($slugs, $found);
            if ($missing && count($ids) === 0) {
                throw ValidationException::withMessages(['produse' => 'Produsele nu au fost găsite: '.implode(', ', array_slice($missing, 0, 10))]);
            }
        }

        return array_map('intval', $ids);
    }

    public function addCoupon(Request $request, int $id)
    {
        $p = Promotion::findOrFail($id);
        $data = $request->validate(['cod' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'], 'max' => ['nullable', 'integer', 'min:1'], 'expira' => ['nullable', 'date']],
            ['cod.regex' => 'Codul poate conține doar litere, cifre, - și _.']);
        $code = Coupon::normalize($data['cod']);
        if (Coupon::where('code', $code)->exists()) {
            throw ValidationException::withMessages(['cod' => 'Codul există deja.']);
        }
        DB::transaction(function () use ($p, $code, $data) {
            $p->coupons()->create(['code' => $code, 'max_uses' => $data['max'] ?? null, 'expires_at' => isset($data['expira']) ? \Illuminate\Support\Carbon::parse($data['expira'])->endOfDay() : null, 'is_active' => true]);
            if (! $p->requires_coupon) {
                $p->update(['requires_coupon' => true]);
            }
        });
        Audit::log('pricing.coupon_created', $p, ['code' => $code]);

        return back()->with('status', "Cuponul {$code} a fost creat. Promoția se aplică doar cu un cupon.");
    }

    public function toggleCoupon(int $id, int $couponId)
    {
        $c = Coupon::where('promotion_id', $id)->findOrFail($couponId);
        $c->update(['is_active' => ! $c->is_active]);
        Audit::log('pricing.coupon_toggled', $c, ['active' => $c->is_active]);

        return back()->with('status', 'Cuponul a fost '.($c->is_active ? 'activat' : 'dezactivat').'.');
    }
}
