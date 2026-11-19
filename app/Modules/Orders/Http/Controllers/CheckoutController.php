<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Consent;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderPlacer;
use App\Modules\Orders\Services\OrderStatus;
use App\Modules\Orders\Services\ProofStore;
use App\Modules\Payments\Services\PaymentMethods;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Production\Services\ProductionFlow;
use App\Modules\Shipping\Services\ShippingCalculator;
use App\Modules\Shop\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Finalizarea comenzii, pe o singură pagină, funcțională fără JavaScript. Totalul arătat clientului se trimite
 * înapoi și se compară pe server cu recalculul; cheia de finalizare face plasarea idempotentă.
 */
class CheckoutController extends Controller
{
    public function __construct(private CartService $carts, private PaymentMethods $payments, private ShippingCalculator $shipping, private \App\Modules\Regions\Services\Regions $regions) {}

    /** Țara de livrare aleasă (sau implicită după limba siteului), doar dintre țările în care livrăm. */
    private function country(Request $request): string
    {
        $countries = $this->regions->shippingCountries();
        $value = strtoupper((string) $request->old('tara', $request->query('tara', $request->input('tara', ''))));

        if (isset($countries[$value])) {
            return $value;
        }
        // Prima afișare: țara vizitatorului (dacă livrăm acolo), altfel după limba siteului.
        $detected = app(\App\Modules\Regions\Services\GeoCountry::class)->detect($request)['country'];

        return $this->regions->defaultCountry(app()->getLocale(), $detected);
    }

    /** Adresa /comanda/ cu livrarea și țara alese (după o recalculare sau o eroare). */
    private function back(?string $shipping, string $country): string
    {
        return '/comanda/?'.http_build_query(array_filter(['livrare' => $shipping, 'tara' => $country]));
    }

    public function show(Request $request)
    {
        $cart = $this->carts->current($request);
        if (! $cart || ! $cart->items()->exists()) {
            return redirect('/cos/');
        }
        $customer = Auth::guard('client')->user();
        $country = $this->country($request);
        $methods = $this->shipping->activeMethods('b2c', $country);
        $chosen = (string) $request->old('livrare', $request->query('livrare', $methods->first()?->code));
        if (! $methods->contains('code', $chosen)) {
            $chosen = (string) $methods->first()?->code;
        }
        $ctx = $this->context($cart->coupon_code, $chosen ?: null, $country);
        $quote = $this->carts->quote($cart, $ctx);
        $key = $request->session()->get('checkout_key') ?: Str::random(40);
        $request->session()->put('checkout_key', $key);

        return response()->view('orders::checkout', [
            'cart' => $cart, 'quote' => $quote, 'customer' => $customer, 'address' => $customer?->addresses()->where('is_default', true)->first(),
            'shippingMethods' => $methods->map(fn ($m) => ['model' => $m, 'quote' => $this->shipping->quote($m->code, $quote->totalGross - (int) ($quote->shipping['gross'] ?? 0), null, 'b2c', $country, $quote->currency)]),
            'chosen' => $chosen, 'paymentMethods' => $this->payments->available($quote->shipping, 'b2c', null, $quote->currency),
            'countries' => $this->regions->shippingCountries(), 'country' => $country,
            'bank' => $this->payments->bankDetails(), 'key' => $key,
            'itemErrors' => $this->carts->validationErrors($cart),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function place(Request $request, OrderPlacer $placer, PaymentService $paymentService, ProofStore $proofStore, OrderStatus $status)
    {
        $cart = $this->carts->current($request);
        if (! $cart) {
            return redirect('/cos/');
        }
        $country = $this->country($request);
        if ($request->input('actiune') === 'recalculeaza') {
            return redirect($this->back((string) $request->input('livrare'), $country))->withInput($request->except('_token', 'livrare', 'tara'));
        }
        $romania = $country === 'RO';
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'telefon' => ['required', 'string', 'max:40', 'regex:/^[0-9 +().-]{8,40}$/'],
            'nume' => ['required', 'string', 'max:120'],
            'tip' => ['required', 'in:pf,pj'],
            'firma' => ['required_if:tip,pj', 'nullable', 'string', 'max:190'],
            'cui' => ['required_if:tip,pj', 'nullable', 'string', 'max:20', $romania ? 'regex:/^(RO)?\s?[0-9]{2,10}$/i' : 'regex:/^[A-Z]{0,2}[0-9A-Z .-]{2,16}$/i'],
            'reg_com' => ['nullable', 'string', 'max:40'],
            'adresa' => ['required', 'string', 'max:255'],
            'localitate' => ['required', 'string', 'max:120'],
            'judet' => [$romania ? 'required' : 'nullable', 'string', 'max:60'],
            'cod_postal' => [$romania ? 'nullable' : 'required', 'nullable', 'string', 'max:12'],
            'alta_livrare' => ['nullable', 'boolean'],
            'livrare_nume' => ['required_if:alta_livrare,1', 'nullable', 'string', 'max:120'],
            'livrare_adresa' => ['required_if:alta_livrare,1', 'nullable', 'string', 'max:255'],
            'livrare_localitate' => ['required_if:alta_livrare,1', 'nullable', 'string', 'max:120'],
            'livrare_judet' => [$romania ? 'required_if:alta_livrare,1' : 'nullable', 'nullable', 'string', 'max:60'],
            'livrare_cod_postal' => ['nullable', 'string', 'max:12'],
            'livrare' => ['required', 'string', 'max:40'],
            'tara' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_keys($this->regions->shippingCountries()))],
            'plata' => ['required', 'string', 'max:30'],
            'observatii' => ['nullable', 'string', 'max:1000'],
            'acord_termeni' => ['accepted'],
            'total_afisat' => ['required', 'integer'],
            'cheie' => ['required', 'string', 'size:40'],
        ], [
            'acord_termeni.accepted' => 'Pentru a plasa comanda trebuie să acceptați termenii și condițiile.',
            'firma.required_if' => 'Completați denumirea firmei.', 'cui.required_if' => 'Completați codul fiscal (CUI).',
            'livrare.required' => 'Alegeți metoda de livrare.', 'plata.required' => 'Alegeți metoda de plată.',
            'tara.in' => 'Nu livrăm încă în țara aleasă.', 'cod_postal.required' => 'Completați codul poștal.',
        ]);
        if (! hash_equals((string) $request->session()->get('checkout_key'), $data['cheie'])) {
            return redirect('/comanda/')->withInput()->withErrors(['cheie' => 'Sesiunea de finalizare a expirat. Verificați datele și trimiteți din nou.']);
        }
        $billing = [
            'type' => $data['tip'], 'name' => $data['nume'], 'company' => $data['firma'] ?? null, 'tax_id' => $data['cui'] ?? null,
            'reg_no' => $data['reg_com'] ?? null, 'street' => $data['adresa'], 'city' => $data['localitate'],
            'county' => $data['judet'] ?? null, 'postal_code' => $data['cod_postal'] ?? null, 'country' => $country,
        ];
        $shipping = ! empty($data['alta_livrare']) ? [
            'name' => $data['livrare_nume'], 'street' => $data['livrare_adresa'], 'city' => $data['livrare_localitate'],
            'county' => $data['livrare_judet'] ?? null, 'postal_code' => $data['livrare_cod_postal'] ?? null, 'country' => $country, 'phone' => $data['telefon'],
        ] : array_merge($billing, ['phone' => $data['telefon']]);

        // Codurile cu dovadă (ex. LENTILE25): fișierul e obligatoriu și se verifică înainte de plasarea comenzii.
        $ctx = $this->context($cart->coupon_code, $data['livrare'], $country);
        $proofs = [];
        foreach ($this->carts->quote($cart, $ctx)->proofs as $promotionId => $proof) {
            $field = 'dovada.'.$promotionId;
            $file = $request->file('dovada')[$promotionId] ?? null;
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                return redirect($this->back($data['livrare'], $country))->withInput($request->except('_token', 'dovada'))
                    ->withErrors([$field => 'Pentru folosirea codului '.($proof['code'] ?? '').' trebuie să încarci dovada cerută.']);
            }
            try {
                $proofs[$promotionId] = $proof + ['file' => $proofStore->prepare($file, $field)];
            } catch (\Illuminate\Validation\ValidationException $e) {
                return redirect($this->back($data['livrare'], $country))->withInput($request->except('_token', 'dovada'))->withErrors($e->errors());
            }
        }

        $order = $placer->place($cart, [
            'email' => mb_strtolower($data['email']), 'phone' => $data['telefon'], 'billing' => $billing, 'shipping' => $shipping,
            'shipping_method' => $data['livrare'], 'payment_method' => $data['plata'], 'note' => $data['observatii'] ?? null,
            'shipping_country' => $country,
        ], $ctx, $data['cheie'], (int) $data['total_afisat'], $request->ip());
        foreach ($proofs as $promotionId => $proof) {
            $proofStore->save($order, $promotionId, $proof['code'], $proof['label'], $proof['file']);
        }
        if ($proofs) {
            $status->note($order, 'Reducere cu dovadă de verificat: '.implode(', ', array_filter(array_column($proofs, 'code'))).'. Deschideți dovezile din secțiunea „Dovezi pentru reduceri”.');
        }

        $request->session()->forget('checkout_key');
        $request->session()->push('comenzi', $order->uuid);
        Consent::record($order->customer_id, 'termeni_comanda', true, 'checkout', 'comanda:'.$order->number);
        $payment = $paymentService->start($order);
        if ($order->payment_method === 'card' && $payment->redirect_url) {
            return redirect()->away($payment->redirect_url);
        }

        return redirect('/comanda/'.$order->uuid.'/');
    }

    /** Pagina comenzii (și de revenire de la procesator): afișează starea reală, nu o presupune. */
    public function status(Request $request, string $uuid, ProductionFlow $flow)
    {
        $order = $this->authorizeOrder($request, $uuid);
        $order->load('items', 'payments', 'shipments', 'productionJobs');
        $analytics = null;
        if ($order->isPaid() || in_array($order->payment_method, ['ramburs', 'numerar_ridicare', 'transfer_bancar'], true)) {
            // Evenimentul „purchase” se emite o singură dată pe comandă (actualizare atomică), fără date medicale.
            if (Order::whereKey($order->id)->whereNull('analytics_purchase_sent_at')->update(['analytics_purchase_sent_at' => now()]) === 1) {
                $analytics = ['transaction_id' => $order->number, 'value' => $order->total / 100, 'currency' => $order->currency ?: 'RON',
                    'items' => $order->items->map(fn ($i) => ['item_name' => $i->kind === 'configuration' ? 'Ochelari configurați' : $i->name, 'quantity' => $i->quantity, 'price' => $i->line_total / 100 / max(1, $i->quantity)])->values()->all()];
            }
        }

        return response()->view('orders::status', [
            'order' => $order, 'stages' => $order->requiresProduction() ? $flow->stages($order->productionJobs->contains('requires_mounting', true))->where('visible_to_customer', true)
                // „Clarificări” este o etapă de excepție: apare doar când comanda chiar se află în ea.
                ->reject(fn ($s) => $s->code === 'clarificari' && $order->production_status !== 'clarificari')->values() : collect(),
            'bank' => $order->payment_method === 'transfer_bancar' ? $this->payments->bankDetails() : null,
            'analytics' => $analytics,
            'pendingCard' => $order->payment_method === 'card' && ! $order->isPaid(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function retryPayment(Request $request, string $uuid, PaymentService $payments)
    {
        $order = $this->authorizeOrder($request, $uuid);
        abort_if($order->isPaid() || $order->payment_method !== 'card' || $order->commercial_status === 'anulata', 409);
        $payment = $payments->start($order);

        return $payment->redirect_url ? redirect()->away($payment->redirect_url) : redirect('/comanda/'.$order->uuid.'/')->withErrors(['plata' => 'Plata cu cardul nu este disponibilă acum. Sunați-ne și vă ajutăm.']);
    }

    private function authorizeOrder(Request $request, string $uuid): Order
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $uuid), 404);
        $order = Order::where('uuid', $uuid)->first();
        abort_unless($order, 404);
        $customerId = Auth::guard('client')->id();
        $inSession = in_array($uuid, (array) $request->session()->get('comenzi', []), true);
        $signed = $request->hasValidSignature();
        abort_unless(($customerId !== null && $order->customer_id === $customerId) || ($order->customer_id === null && ($inSession || $signed)) || ($inSession && $order->customer_id === $customerId), 404);

        return $order;
    }

    /** Link semnat (trimis pe email) pentru comenzile fără cont. */
    public static function signedLink(Order $order): string
    {
        // Request::url() ignoră „/” final, deci semnătura rămâne validă pe forma canonică /comanda/<uuid>/?…
        return preg_replace('/\?/', '/?', URL::temporarySignedRoute('order.status', now()->addDays(90), ['uuid' => $order->uuid]), 1);
    }

    private function context(?string $coupon, ?string $shipping, ?string $country = null): PricingContext
    {
        return PricingContext::b2c(['customerId' => Auth::guard('client')->id(), 'couponCode' => $coupon, 'shippingMethodCode' => $shipping, 'shippingCountry' => $country]);
    }
}
