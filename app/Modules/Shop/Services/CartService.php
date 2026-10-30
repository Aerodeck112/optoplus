<?php

namespace App\Modules\Shop\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Services\ConfigurationService;
use App\Modules\Configurator\Services\ConfigurationValidator;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Pricing\Support\Quote;
use App\Modules\Shop\Models\Cart;
use App\Modules\Shop\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Coșul: identificat printr-un cookie (vizitatori) sau prin cont. Conține variante simple și configurații.
 * La fiecare afișare și la finalizare totul se re-validează și se re-prețuiește pe server.
 */
class CartService
{
    public const COOKIE = 'optoplus_cos';

    private ?Cart $cart = null;

    /** Cererea pentru care e valabil coșul memorat (serviciul poate trăi mai mult decât o cerere). */
    private ?Request $cartRequest = null;

    public function __construct(
        private PriceEngine $prices,
        private ConfigurationValidator $validator,
        private ConfigurationService $configurations,
    ) {}

    public function current(Request $request, bool $create = false): ?Cart
    {
        if ($this->cart && $this->cartRequest === $request) {
            return $this->cart;
        }
        $this->cart = null;
        $this->cartRequest = $request;
        $customer = Auth::guard('client')->user();
        // Cookie-ul păstrează coșul 30 de zile; sesiunea îl acoperă până la primul răspuns care setează cookie-ul.
        $uuid = $request->cookie(self::COOKIE) ?: ($request->hasSession() ? $request->session()->get('cos_uuid') : null);
        $cart = null;
        if (is_string($uuid) && preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
            $cart = Cart::where('uuid', $uuid)->first();
            if ($cart && $cart->customer_id !== null && $cart->customer_id !== $customer?->id) {
                $cart = null; // coșul altui cont nu se deschide prin cookie
            }
        }
        if (! $cart && $customer) {
            $cart = Cart::where('customer_id', $customer->id)->whereNull('organization_id')->latest('id')->first();
        }
        if (! $cart && $create) {
            $cart = Cart::create(['uuid' => (string) Str::uuid(), 'customer_id' => $customer?->id, 'expires_at' => now()->addDays(30)]);
        }
        if ($cart && $request->hasSession()) {
            $request->session()->put('cos_uuid', $cart->uuid);
        }
        if ($cart && $customer && $cart->customer_id === null) {
            $cart->update(['customer_id' => $customer->id]);
        }

        return $this->cart = $cart;
    }

    /** Coșul unui utilizator al unui partener B2B (separat de coșul personal; izolat pe organizație). */
    public function forOrganization(int $organizationId, int $customerId): Cart
    {
        return Cart::firstOrCreate(['organization_id' => $organizationId, 'customer_id' => $customerId], ['uuid' => (string) Str::uuid(), 'expires_at' => now()->addDays(60)]);
    }

    public function cookie(Request $request): ?Cookie
    {
        $cart = $this->current($request);

        return $cart ? cookie(self::COOKIE, $cart->uuid, 60 * 24 * 30, null, null, null, true, false, 'lax') : null;
    }

    public function addProduct(Request $request, ProductVariant $variant, int $qty = 1): CartItem
    {
        if (! $variant->is_active || $variant->product->status !== Product::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['varianta' => 'Produsul nu mai este disponibil.']);
        }
        if ($variant->isOutOfStock()) {
            throw ValidationException::withMessages(['varianta' => 'Produsul nu mai este în stoc.']);
        }
        $cart = $this->current($request, true);
        $item = $cart->items()->where('kind', 'product')->where('variant_id', $variant->id)->first();
        if ($item) {
            $item->update(['quantity' => min(20, $item->quantity + $qty)]);

            return $item;
        }

        return $cart->items()->create(['kind' => 'product', 'variant_id' => $variant->id, 'quantity' => max(1, min(20, $qty))]);
    }

    public function addConfiguration(Request $request, Configuration $cfg): CartItem
    {
        $cart = $this->current($request, true);

        return $cart->items()->firstOrCreate(['kind' => 'configuration', 'configuration_id' => $cfg->id], ['quantity' => max(1, (int) $cfg->quantity)]);
    }

    public function updateQuantity(Request $request, int $itemId, int $qty): void
    {
        $item = $this->item($request, $itemId);
        $qty <= 0 ? $item->delete() : $item->update(['quantity' => min(20, $qty)]);
    }

    public function remove(Request $request, int $itemId): void
    {
        $this->item($request, $itemId)->delete();
    }

    private function item(Request $request, int $itemId): CartItem
    {
        $cart = $this->current($request);
        $item = $cart?->items()->whereKey($itemId)->first();
        abort_unless($item, 404);

        return $item;
    }

    /** @return list<PriceLine> */
    public function lines(Cart $cart): array
    {
        $lines = [];
        $channel = $cart->organization_id ? 'b2b' : 'b2c';
        foreach ($cart->items()->with('configuration.prescription')->get() as $item) {
            if ($item->kind === 'product') {
                $lines[] = PriceLine::product('i'.$item->id, (int) $item->variant_id, $item->quantity);
            } elseif ($item->configuration) {
                $lines[] = PriceLine::configuration('i'.$item->id, $item->configuration->toInput($channel), $item->quantity);
            }
        }

        return $lines;
    }

    public function quote(Cart $cart, PricingContext $ctx): Quote
    {
        return $this->prices->quote($this->lines($cart), $ctx);
    }

    /** Validarea finală a configurațiilor din coș (prețurile sau matricea pot fi schimbat între timp). */
    public function validationErrors(Cart $cart): array
    {
        $errors = [];
        $channel = $cart->organization_id ? 'b2b' : 'b2c';
        foreach ($cart->items()->with('configuration.prescription', 'variant.product')->get() as $item) {
            if ($item->kind === 'configuration' && $item->configuration) {
                if ($item->configuration->status === 'in_comanda') {
                    $errors['i'.$item->id][] = 'Configurația a fost deja comandată.';

                    continue;
                }
                $result = $this->validator->validate($item->configuration->toInput($channel), [], true);
                foreach ($result->errors as $e) {
                    $errors['i'.$item->id][] = $e['message'];
                }
            } elseif ($item->kind === 'product' && (! $item->variant || ! $item->variant->is_active || $item->variant->product->status !== Product::STATUS_PUBLISHED)) {
                $errors['i'.$item->id][] = 'Produsul nu mai este disponibil.';
            }
        }

        return $errors;
    }
}
