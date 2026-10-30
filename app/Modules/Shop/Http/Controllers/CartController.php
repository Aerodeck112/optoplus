<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Shop\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Coșul public: nu se cache-uiește niciodată; totalurile vin exclusiv din PriceEngine. */
class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(Request $request)
    {
        $cart = $this->cart->current($request);
        $items = $cart ? $cart->items()->with('variant.product.images.media', 'configuration.frameVariant.product.images.media', 'configuration.prescription')->get() : collect();
        $ctx = $this->context($cart?->coupon_code);
        $quote = $cart && $items->isNotEmpty() ? $this->cart->quote($cart, $ctx) : null;

        return response()->view('shop::cart', [
            'cart' => $cart, 'items' => $items, 'quote' => $quote,
            'itemErrors' => $cart ? $this->cart->validationErrors($cart) : [],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function add(Request $request)
    {
        $data = $request->validate(['varianta' => ['required', 'integer'], 'cantitate' => ['nullable', 'integer', 'min:1', 'max:20']]);
        $variant = ProductVariant::with('product')->find($data['varianta']);
        if (! $variant) {
            throw ValidationException::withMessages(['varianta' => 'Produsul nu mai este disponibil.']);
        }
        $this->cart->addProduct($request, $variant, (int) ($data['cantitate'] ?? 1));

        return redirect('/cos/')->with('status', 'Produsul a fost adăugat în coș.')->withCookie($this->cart->cookie($request));
    }

    public function update(Request $request)
    {
        foreach ((array) $request->input('cantitate', []) as $id => $qty) {
            if (is_numeric($id) && is_numeric($qty)) {
                $this->cart->updateQuantity($request, (int) $id, (int) $qty);
            }
        }
        if ($request->filled('sterge')) {
            $this->cart->remove($request, (int) $request->input('sterge'));
        }
        if ($request->has('cupon')) {
            $cart = $this->cart->current($request, true);
            $code = trim((string) $request->input('cupon'));
            $cart->update(['coupon_code' => $code === '' ? null : mb_substr($code, 0, 60)]);
        }

        return redirect('/cos/')->with('status', 'Coșul a fost actualizat.')->withCookie($this->cart->cookie($request));
    }

    private function context(?string $coupon): PricingContext
    {
        return PricingContext::b2c(['customerId' => Auth::guard('client')->id(), 'couponCode' => $coupon]);
    }
}
