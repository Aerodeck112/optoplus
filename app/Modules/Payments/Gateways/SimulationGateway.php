<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\Notification;
use App\Modules\Payments\Support\StartResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Procesator simulat pentru dezvoltare și testare (NU în producție). Reproduce fluxul real: pagină de plată
 * „externă”, notificare server-la-server semnată HMAC-SHA256 pe corpul brut, notificări duplicate, sume
 * greșite, interogare de stare. Permite testarea completă a idempotenței și a verificării semnăturii.
 */
class SimulationGateway implements PaymentGateway
{
    public const HEADER = 'X-Simulare-Semnatura';

    public function code(): string
    {
        return 'simulare';
    }

    public function label(): string
    {
        return 'Simulator de plăți (doar dezvoltare)';
    }

    public function isConfigured(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        if (app()->isProduction() || config('optoplus.stage') === 'production') {
            return 'Simulatorul este refuzat în producție.';
        }

        return config('optoplus.payments.simulation') ? null : 'Simulatorul nu este pornit (OPTOPLUS_PAYMENT_SIMULATION).';
    }

    public function start(Payment $payment, Order $order, string $returnUrl): StartResult
    {
        $ref = 'SIM-'.Str::upper(Str::random(16));

        return new StartResult(true, url('/plati/simulare/'.$ref.'/'), $ref);
    }

    public static function secret(): string
    {
        return hash_hmac('sha256', 'optoplus-simulare-plati', (string) config('app.key'));
    }

    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::secret());
    }

    public function parseNotification(Request $request): Notification
    {
        $body = $request->getContent();
        $valid = hash_equals(self::sign($body), (string) $request->header(self::HEADER));
        $data = json_decode($body, true) ?: [];
        $status = match ($data['status'] ?? null) {
            'paid' => 'platita', 'authorized' => 'autorizata', 'failed' => 'esuata', 'cancelled' => 'anulata', 'refunded' => 'rambursata',
            default => null,
        };

        return new Notification(
            (string) ($data['event_id'] ?? hash('sha256', $body)), $valid,
            isset($data['reference']) ? (string) $data['reference'] : null, $status,
            isset($data['amount']) && is_int($data['amount']) ? $data['amount'] : null,
            (string) ($data['status'] ?? ''),
            ['reference' => $data['reference'] ?? null, 'status' => $data['status'] ?? null, 'amount' => $data['amount'] ?? null],
        );
    }

    public function fetchStatus(Payment $payment): ?string
    {
        // Simulatorul nu are stare proprie: starea reală vine numai din notificări.
        return null;
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): array
    {
        return ['ok' => true, 'reference' => 'SIMR-'.Str::upper(Str::random(10)), 'error' => null];
    }
}
