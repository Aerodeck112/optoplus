<?php

namespace App\Modules\Payments\Gateways;

use App\Core\Settings\Settings;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\Notification;
use App\Modules\Payments\Support\StartResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Banca Transilvania iPay (API REST: register.do, getOrderStatusExtended.do).
 *
 * Sursa implementării: documentația BT iPay a comerciantului (btepos.ro/documentatie, acces cu cont) și
 * biblioteca publică stev/btipay (aceleași adrese și parametri). Adresele serverelor sunt configurabile din
 * Setări → Plăți, ca să poată fi aliniate exact cu documentația primită de la BT.
 *
 * Principiul de siguranță: nimic din ce vine din browser sau din notificare nu marchează plata. Atât revenirea
 * clientului, cât și notificarea (callback) declanșează doar o interogare server-la-server getOrderStatusExtended
 * cu credențialele comerciantului; plata devine „plătită” numai dacă BT răspunde orderStatus = 2 (depusă) pentru
 * aceeași sumă și aceeași comandă. Rambursările se fac din portalul BT și se înregistrează apoi în panou.
 */
class BtIpayGateway implements PaymentGateway
{
    /** orderStatus din getOrderStatusExtended → starea internă. */
    private const STATUS = [0 => 'in_asteptare', 1 => 'autorizata', 2 => 'platita', 3 => 'anulata', 4 => 'rambursata', 5 => 'in_asteptare', 6 => 'esuata'];

    private const RON = '946';

    public function __construct(private Settings $settings) {}

    public function code(): string
    {
        return 'btipay';
    }

    public function label(): string
    {
        return 'Card — Banca Transilvania iPay';
    }

    public function isConfigured(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        if (! $this->settings->get('payments.btipay.username') || ! $this->settings->secret('payments.btipay.password')) {
            return 'Completați utilizatorul și parola API primite de la BT (Setări → Plăți).';
        }
        if (! $this->baseUrl()) {
            return 'Completați adresa serverului BT iPay pentru mediul ales.';
        }

        return null;
    }

    private function baseUrl(): ?string
    {
        $env = $this->settings->get('payments.btipay.environment', 'test');

        return $env === 'productie'
            ? ($this->settings->get('payments.btipay.base_url_production') ?: null)
            : ($this->settings->get('payments.btipay.base_url_test') ?: null);
    }

    private function credentials(): array
    {
        return ['userName' => (string) $this->settings->get('payments.btipay.username'), 'password' => (string) $this->settings->secret('payments.btipay.password')];
    }

    private function post(string $path, array $params): ?array
    {
        try {
            $response = Http::asForm()->timeout(25)->connectTimeout(10)->retry(2, 500, throw: false)
                ->post($this->baseUrl().$path, $params);
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            Log::warning('BT iPay: cererea nu a reușit', ['path' => $path, 'eroare' => $e::class]);

            return null;
        }
    }

    public function start(Payment $payment, Order $order, string $returnUrl): StartResult
    {
        if (! $this->isConfigured()) {
            return new StartResult(false, error: $this->unavailableReason());
        }
        $billing = (array) $order->billing_address;
        $phone = preg_replace('/\D+/', '', (string) $order->phone);
        $bundle = [
            'orderCreationDate' => $order->created_at->format('Y-m-d'),
            'customerDetails' => array_filter([
                'email' => $order->email,
                'phone' => $phone !== '' ? (str_starts_with($phone, '0') ? '4'.$phone : $phone) : null,
                'contact' => mb_substr((string) ($billing['name'] ?? $billing['company'] ?? ''), 0, 40) ?: null,
                'billingInfo' => array_filter([
                    'country' => '642', 'city' => mb_substr((string) ($billing['city'] ?? ''), 0, 40) ?: null,
                    'postAddress' => mb_substr((string) ($billing['street'] ?? ''), 0, 50) ?: null,
                    'postalCode' => $billing['postal_code'] ?? null,
                ]),
            ]),
        ];
        $params = $this->credentials() + [
            // Numărul trimis la BT trebuie să fie unic pentru fiecare încercare de plată a aceleiași comenzi.
            'orderNumber' => $order->number.'-'.$payment->id,
            'amount' => $payment->amount,
            'currency' => self::RON,
            'returnUrl' => url('/plati/btipay/revenire'),
            'description' => mb_substr('Comanda '.$order->number.' optoplus.ro', 0, 99),
            'language' => 'ro',
            'email' => $order->email,
            'orderBundle' => json_encode($bundle, JSON_UNESCAPED_UNICODE),
        ];
        if ($this->settings->get('payments.btipay.force_3ds')) {
            $params['jsonParams'] = json_encode(['FORCE_3DS2' => 'true']);
        }
        $res = $this->post('/payment/rest/register.do', $params);
        if (! $res || (int) ($res['errorCode'] ?? 0) !== 0 || empty($res['orderId']) || empty($res['formUrl'])) {
            Log::warning('BT iPay: înregistrarea comenzii a fost refuzată', ['order' => $order->number, 'errorCode' => $res['errorCode'] ?? null]);

            return new StartResult(false, error: 'Plata cu cardul nu a putut fi inițiată'.(isset($res['errorMessage']) ? ': '.mb_substr((string) $res['errorMessage'], 0, 120) : '.'));
        }
        if (! str_starts_with((string) $res['formUrl'], 'https://')) {
            return new StartResult(false, error: 'Adresa paginii de plată primită nu este sigură.');
        }

        return new StartResult(true, (string) $res['formUrl'], (string) $res['orderId']);
    }

    /**
     * Notificarea (callback) nu este crezută: din ea se folosește doar identificatorul tranzacției, apoi starea
     * se cere direct de la BT. De aceea „semnătura” este validă doar dacă interogarea a reușit.
     */
    public function parseNotification(Request $request): Notification
    {
        $orderId = (string) ($request->input('mdOrder') ?? $request->input('orderId') ?? '');
        if (! preg_match('/^[0-9a-f-]{20,64}$/i', $orderId)) {
            return new Notification('btipay:invalid:'.hash('sha256', (string) $request->getQueryString().$request->getContent()), false, null, null, null, 'invalid');
        }
        $status = $this->queryStatus($orderId);
        $eventId = 'btipay:'.$orderId.':'.($status['orderStatus'] ?? 'x').':'.($request->input('operation') ?? 'status');

        return new Notification(
            $eventId, $status !== null, $orderId,
            $status ? (self::STATUS[(int) ($status['orderStatus'] ?? -1)] ?? null) : null,
            $status ? (int) ($status['amount'] ?? 0) : null,
            (string) ($request->input('operation') ?? 'status'),
            ['orderStatus' => $status['orderStatus'] ?? null, 'actionCode' => $status['actionCode'] ?? null, 'operation' => $request->input('operation')],
        );
    }

    public function fetchStatus(Payment $payment): ?string
    {
        if (! $payment->provider_reference) {
            return null;
        }
        $status = $this->queryStatus($payment->provider_reference);
        if (! $status) {
            return null;
        }
        $mapped = self::STATUS[(int) ($status['orderStatus'] ?? -1)] ?? null;
        // O plată „depusă” cu altă sumă decât cea așteptată nu se acceptă automat.
        if ($mapped === 'platita' && (int) ($status['amount'] ?? -1) !== (int) $payment->amount) {
            Log::warning('BT iPay: sumă diferită la verificare', ['payment' => $payment->id]);

            return null;
        }

        return $mapped;
    }

    /** @return array{orderStatus:int, amount:int, actionCode:mixed}|null */
    private function queryStatus(string $orderId): ?array
    {
        $res = $this->post('/payment/rest/getOrderStatusExtended.do', $this->credentials() + ['orderId' => $orderId, 'language' => 'ro']);
        if (! $res || (int) ($res['errorCode'] ?? 0) !== 0 || ! array_key_exists('orderStatus', $res)) {
            return null;
        }

        return ['orderStatus' => (int) $res['orderStatus'], 'amount' => (int) ($res['amount'] ?? 0), 'actionCode' => $res['actionCode'] ?? null];
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): array
    {
        return ['ok' => false, 'reference' => null, 'error' => 'Rambursarea cu cardul se face din portalul BT iPay; apoi se înregistrează aici ca rambursare manuală.'];
    }

    /** Verifică doar autentificarea: interogarea unei tranzacții inexistente trebuie să dea „comandă negăsită”, nu „acces refuzat”. */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => (string) $this->unavailableReason()];
        }
        $res = $this->post('/payment/rest/getOrderStatusExtended.do', $this->credentials() + ['orderId' => '00000000-0000-0000-0000-000000000000', 'language' => 'ro']);
        if ($res === null) {
            return ['ok' => false, 'message' => 'Serverul BT iPay nu a răspuns. Verificați adresa serverului și conexiunea hostingului.'];
        }
        $code = (int) ($res['errorCode'] ?? -1);
        $msg = mb_substr((string) ($res['errorMessage'] ?? ''), 0, 160);

        return in_array($code, [5, 6], true) && ! preg_match('/acces|access|password|parol|login|autentific/i', $msg)
            ? ['ok' => true, 'message' => 'Conexiunea la BT iPay funcționează (răspuns: '.$msg.').']
            : ['ok' => false, 'message' => 'BT iPay a răspuns cu eroarea '.$code.': '.$msg];
    }
}
