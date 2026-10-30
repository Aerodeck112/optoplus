<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\Notification;
use App\Modules\Payments\Support\StartResult;
use Illuminate\Http\Request;

/**
 * Contractul unui procesator de plăți cu card. Plata se confirmă numai prin `parseNotification` (semnătură
 * verificată) sau `fetchStatus` (interogare server-la-server), niciodată prin revenirea clientului.
 */
interface PaymentGateway
{
    public function code(): string;

    public function label(): string;

    /** Are toate datele de configurare (contract, chei) și poate fi activat? */
    public function isConfigured(): bool;

    /** Motivul pentru care nu poate fi folosit (afișat în panou), sau null. */
    public function unavailableReason(): ?string;

    public function start(Payment $payment, Order $order, string $returnUrl): StartResult;

    public function parseNotification(Request $request): Notification;

    /** Starea curentă la procesator: platita | autorizata | in_asteptare | esuata | anulata | rambursata | null (necunoscut). */
    public function fetchStatus(Payment $payment): ?string;

    /** @return array{ok:bool, reference:?string, error:?string} */
    public function refund(Payment $payment, int $amount, string $idempotencyKey): array;
}
