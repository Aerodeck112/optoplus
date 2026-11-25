<?php

namespace App\Modules\Payments\Gateways;

use App\Core\Settings\Settings;
use App\Modules\I18n\Support\Locales;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\Notification;
use App\Modules\Payments\Support\StartResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stripe Checkout — plata cu cardul pentru comenzile în euro și forinți (1.0.17).
 *
 * Fluxul: pentru fiecare încercare de plată se creează o sesiune Checkout (API public Stripe, POST
 * /v1/checkout/sessions) cu suma și valuta comenzii; clientul plătește pe pagina Stripe (datele cardului nu
 * trec prin site). Plata devine „plătită” doar dacă:
 *  - notificarea (webhook) are semnătura Stripe validă (antetul Stripe-Signature, HMAC-SHA256 cu secretul
 *    endpointului, toleranță 5 minute), și
 *  - interogarea directă a sesiunii la Stripe confirmă payment_status = paid, aceeași sumă și aceeași valută.
 * Revenirea clientului pe site declanșează doar aceeași interogare.
 */
class StripeGateway implements PaymentGateway
{
    public const API = 'https://api.stripe.com/v1';

    private const TOLERANCE = 300;

    public function __construct(private Settings $settings) {}

    public function code(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Card — Stripe';
    }

    public function isConfigured(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        $key = $this->secretKey();
        if ($key === '' || ! preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key)) {
            return 'Completați cheia secretă Stripe (sk_live_… sau cheia restricționată rk_live_…; pentru teste sk_test_…) în Setări → Plăți.';
        }
        if (! str_starts_with($this->webhookSecret(), 'whsec_')) {
            return 'Completați secretul de semnare al webhookului Stripe (începe cu whsec_) în Setări → Plăți.';
        }

        return null;
    }

    /** 1.0.27: cheile se citesc fără spații / rânduri noi copiate odată cu ele. */
    private function secretKey(): string
    {
        return (string) preg_replace('/\s+/u', '', (string) $this->settings->secret('payments.stripe.secret_key'));
    }

    private function webhookSecret(): string
    {
        return (string) preg_replace('/\s+/u', '', (string) $this->settings->secret('payments.stripe.webhook_secret'));
    }

    /** Mod test sau live, după cheia salvată. */
    public function mode(): ?string
    {
        $key = $this->secretKey();

        return preg_match('/^(?:sk|rk)_(test|live)_/', $key, $m) ? $m[1] : null;
    }

    /** Valutele plătite prin Stripe (implicit EUR, HUF). @return list<string> */
    public function currencies(): array
    {
        $list = preg_split('/[\s,;]+/', strtoupper((string) $this->settings->get('payments.stripe.currencies', 'EUR, HUF')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_intersect($list, ['RON', 'EUR', 'HUF']));
    }

    private function http(?string $idempotencyKey = null): PendingRequest
    {
        $http = Http::withToken($this->secretKey())
            ->asForm()->acceptJson()->timeout(25)->connectTimeout(10);

        return $idempotencyKey ? $http->withHeaders(['Idempotency-Key' => $idempotencyKey]) : $http;
    }

    /** @return array<string, mixed>|null */
    private function call(string $method, string $path, array $params = [], ?string $idempotencyKey = null): ?array
    {
        try {
            $response = $method === 'GET'
                ? $this->http()->get(self::API.$path, $params)
                : $this->http($idempotencyKey)->post(self::API.$path, $params);
            $json = $response->json();
            if (! $response->successful()) {
                Log::warning('Stripe: cerere refuzată', ['path' => preg_replace('#/cs_[A-Za-z0-9_]+#', '/cs_…', $path), 'status' => $response->status(),
                    'tip' => is_array($json) ? ($json['error']['type'] ?? null) : null, 'cod' => is_array($json) ? ($json['error']['code'] ?? null) : null]);

                return is_array($json) ? ['_error' => $json['error']['message'] ?? 'eroare', '_status' => $response->status()] : null;
            }

            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            Log::warning('Stripe: cererea nu a reușit', ['path' => $path, 'eroare' => $e::class]);

            return null;
        }
    }

    public function start(Payment $payment, Order $order, string $returnUrl): StartResult
    {
        if (! $this->isConfigured()) {
            return new StartResult(false, error: $this->unavailableReason());
        }
        $currency = strtoupper((string) ($payment->currency ?: $order->currency));
        if (! in_array($currency, $this->currencies(), true)) {
            return new StartResult(false, error: "Plata prin Stripe nu este activă pentru {$currency}.");
        }
        $locale = in_array($order->locale, ['ro', 'en', 'hu'], true) ? $order->locale : 'auto';
        $res = $this->call('POST', '/checkout/sessions', [
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => (int) $payment->amount,
                    'product_data' => ['name' => 'Optoplus — comanda '.$order->number],
                ],
            ]],
            'customer_email' => $order->email,
            'client_reference_id' => $order->number,
            'locale' => $locale,
            'metadata' => ['order' => $order->number, 'payment_id' => (string) $payment->id],
            'payment_intent_data' => ['metadata' => ['order' => $order->number, 'payment_id' => (string) $payment->id]],
            'success_url' => url('/plati/stripe/revenire/').'/?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url(Locales::path((string) ($order->locale ?: 'ro'), '/comanda/'.$order->uuid.'/')),
        ], 'optoplus-'.$payment->idempotency_key);
        if (! $res || isset($res['_error']) || empty($res['id']) || empty($res['url'])) {
            return new StartResult(false, error: 'Plata cu cardul nu a putut fi inițiată'.(isset($res['_error']) ? ': '.mb_substr((string) $res['_error'], 0, 120) : '.'));
        }
        if (! str_starts_with((string) $res['url'], 'https://')) {
            return new StartResult(false, error: 'Adresa paginii de plată primită nu este sigură.');
        }

        return new StartResult(true, (string) $res['url'], (string) $res['id']);
    }

    /**
     * Webhook: semnătura se verifică pe corpul brut. Din eveniment se folosește doar identificatorul sesiunii;
     * starea și suma vin din interogarea sesiunii la Stripe.
     */
    public function parseNotification(Request $request): Notification
    {
        $payload = (string) $request->getContent();
        $valid = self::verifySignature($payload, (string) $request->header('Stripe-Signature'), $this->webhookSecret());
        $event = json_decode($payload, true);
        $eventId = is_array($event) && is_string($event['id'] ?? null) ? 'stripe:'.$event['id'] : 'stripe:invalid:'.hash('sha256', $payload);
        $type = is_array($event) ? (string) ($event['type'] ?? '') : '';
        $object = is_array($event) ? ($event['data']['object'] ?? []) : [];
        $sessionId = is_array($object) && ($object['object'] ?? null) === 'checkout.session' && is_string($object['id'] ?? null) ? $object['id'] : null;
        if (! $valid || $sessionId === null) {
            return new Notification($eventId, $valid, $sessionId, null, null, $type ?: 'invalid', ['type' => $type]);
        }
        $session = $this->session($sessionId);
        [$status, $amount] = $session ? $this->statusOf($session) : [null, null];

        return new Notification($eventId, true, $sessionId, $status, $amount, $type, [
            'type' => $type, 'payment_status' => $session['payment_status'] ?? null, 'status' => $session['status'] ?? null,
            'currency' => $session['currency'] ?? null,
        ]);
    }

    /** Verifică antetul Stripe-Signature („t=…,v1=…”) pentru corpul brut și secretul endpointului. */
    public static function verifySignature(string $payload, string $header, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't' && ctype_digit($v)) {
                $timestamp = (int) $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }
        if ($timestamp === null || $signatures === [] || abs(($now ?? time()) - $timestamp) > self::TOLERANCE) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    private function session(string $id): ?array
    {
        if (! preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $id)) {
            return null;
        }
        $res = $this->call('GET', '/checkout/sessions/'.$id);

        return $res && ! isset($res['_error']) ? $res : null;
    }

    /** @return array{0:?string, 1:?int} starea internă și suma încasată */
    private function statusOf(array $session): array
    {
        $amount = isset($session['amount_total']) ? (int) $session['amount_total'] : null;
        $status = match (true) {
            ($session['payment_status'] ?? null) === 'paid' => 'platita',
            ($session['status'] ?? null) === 'expired' => 'anulata',
            ($session['status'] ?? null) === 'open', ($session['payment_status'] ?? null) === 'unpaid' => 'in_asteptare',
            default => null,
        };

        return [$status, $amount];
    }

    public function fetchStatus(Payment $payment): ?string
    {
        if (! $payment->provider_reference || ! ($session = $this->session($payment->provider_reference))) {
            return null;
        }
        [$status, $amount] = $this->statusOf($session);
        // O plată cu altă sumă sau valută decât cea așteptată nu se acceptă automat.
        if ($status === 'platita' && ($amount !== (int) $payment->amount || strtoupper((string) ($session['currency'] ?? '')) !== strtoupper((string) $payment->currency))) {
            Log::warning('Stripe: sumă sau valută diferită la verificare', ['payment' => $payment->id]);

            return null;
        }

        return $status;
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): array
    {
        $session = $payment->provider_reference ? $this->session($payment->provider_reference) : null;
        $intent = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null;
        if (! $intent) {
            return ['ok' => false, 'reference' => null, 'error' => 'Plata nu a fost găsită la Stripe.'];
        }
        $res = $this->call('POST', '/refunds', ['payment_intent' => $intent, 'amount' => $amount, 'metadata' => ['payment_id' => (string) $payment->id]], 'optoplus-refund-'.$idempotencyKey);
        if (! $res || isset($res['_error']) || empty($res['id'])) {
            return ['ok' => false, 'reference' => null, 'error' => 'Stripe a refuzat rambursarea'.(isset($res['_error']) ? ': '.mb_substr((string) $res['_error'], 0, 120) : '.')];
        }

        return ['ok' => true, 'reference' => (string) $res['id'], 'error' => null];
    }

    /** Verifică cheia secretă (citirea soldului contului; nu creează nimic). */
    public function testConnection(): array
    {
        $key = $this->secretKey();
        if (! preg_match('/^(sk|rk)_(test|live)_/', $key)) {
            return ['ok' => false, 'message' => 'Completați întâi cheia secretă Stripe.'];
        }
        $res = $this->call('GET', '/balance');
        if ($res === null) {
            return ['ok' => false, 'message' => 'Stripe nu a răspuns. Verificați conexiunea hostingului (api.stripe.com).'];
        }
        if (isset($res['_error'])) {
            return ['ok' => false, 'message' => 'Stripe a refuzat cheia: '.mb_substr((string) $res['_error'], 0, 160)];
        }
        $webhook = str_starts_with($this->webhookSecret(), 'whsec_') ? '' : ' Mai completați secretul webhookului (whsec_…).';

        return ['ok' => true, 'message' => 'Conexiunea la Stripe funcționează (mod '.($this->mode() === 'live' ? 'live — plăți reale' : 'test').').'.$webhook];
    }
}
