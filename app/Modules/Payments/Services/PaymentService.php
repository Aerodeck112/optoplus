<?php

namespace App\Modules\Payments\Services;

use App\Core\Audit\Audit;
use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderStatus;
use App\Modules\Payments\Gateways\PaymentGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentEvent;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Support\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Singurul loc care schimbă starea de plată. Surse acceptate: notificare cu semnătură validă, interogare
 * server-la-server la procesator, confirmare manuală auditată. Toate operațiile sunt idempotente.
 */
class PaymentService
{
    public function __construct(private PaymentMethods $methods, private OrderStatus $status) {}

    /** Creează plata pentru comandă și, pentru card, inițiază sesiunea la procesator. */
    public function start(Order $order): Payment
    {
        $existing = $order->payments()->whereIn('status', ['initiata', 'in_asteptare'])->latest('id')->first();
        if ($existing && $existing->redirect_url) {
            return $existing;
        }
        $provider = match ($order->payment_method) {
            'card' => $this->methods->gateway()?->code(),
            default => 'manual',
        };
        $payment = $order->payments()->create([
            'method' => $order->payment_method, 'provider' => $provider, 'amount' => $order->balanceDue(),
            'status' => 'initiata', 'idempotency_key' => hash('sha256', $order->uuid.'|'.Str::uuid()),
        ]);
        if ($order->payment_method !== 'card') {
            $payment->update(['status' => 'in_asteptare']);
            if ($order->payment_method === 'transfer_bancar') {
                $this->status->payment($order, 'in_asteptare', 'Așteptăm plata prin transfer bancar.', 'sistem');
            }

            return $payment;
        }
        $gateway = $this->methods->gateway($provider);
        if (! $gateway || ! $gateway->isConfigured()) {
            $payment->update(['status' => 'esuata', 'failure_reason' => 'Procesatorul nu este configurat.']);

            return $payment;
        }
        $result = $gateway->start($payment, $order, url('/comanda/'.$order->uuid.'/'));
        if (! $result->ok) {
            $payment->update(['status' => 'esuata', 'failure_reason' => mb_substr((string) $result->error, 0, 255)]);

            return $payment;
        }
        $payment->update(['status' => 'in_asteptare', 'provider_reference' => $result->providerReference, 'redirect_url' => $result->redirectUrl]);
        $this->status->payment($order, 'in_asteptare', 'Plata cu cardul a fost inițiată.', 'sistem');

        return $payment;
    }

    /**
     * Procesează o notificare. Returnează rezultatul înregistrat: procesat | ignorat | respins | duplicat.
     */
    public function handleNotification(PaymentGateway $gateway, Notification $n): string
    {
        try {
            $event = PaymentEvent::create([
                'provider' => $gateway->code(), 'provider_event_id' => mb_substr($n->eventId, 0, 160),
                'type' => $n->type ? mb_substr($n->type, 0, 60) : null, 'signature_valid' => $n->signatureValid,
                'payload' => $n->safePayload,
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return 'duplicat';
            }
            throw $e;
        }
        if (! $n->signatureValid) {
            $event->update(['outcome' => 'respins', 'processed_at' => now()]);
            Audit::log('payment.notification_rejected', $event, ['provider' => $gateway->code(), 'reason' => 'semnătură invalidă']);

            return 'respins';
        }
        $payment = $n->providerReference
            ? Payment::where('provider', $gateway->code())->where('provider_reference', $n->providerReference)->first()
            : null;
        if (! $payment || $n->status === null) {
            $event->update(['outcome' => 'ignorat', 'processed_at' => now(), 'payment_id' => $payment?->id]);

            return 'ignorat';
        }
        $event->update(['payment_id' => $payment->id]);
        if ($n->status === 'platita' && $n->amount !== null && $n->amount !== (int) $payment->amount) {
            $event->update(['outcome' => 'respins', 'processed_at' => now()]);
            $this->status->note($payment->order, "Notificare de plată cu sumă diferită ({$n->amount} bani față de {$payment->amount}). Verificați manual la procesator.");
            Audit::log('payment.amount_mismatch', $payment, ['expected' => $payment->amount, 'received' => $n->amount]);

            return 'respins';
        }
        $outcome = $this->apply($payment, $n->status, 'webhook:'.$gateway->code());
        $event->update(['outcome' => $outcome, 'processed_at' => now()]);

        return $outcome;
    }

    /** Interogare la procesator pentru plățile rămase în așteptare (cron). */
    public function reconcile(Payment $payment): string
    {
        $gateway = $this->methods->gateway($payment->provider);
        $payment->update(['last_checked_at' => now()]);
        if (! $gateway || ! $gateway->isConfigured()) {
            return 'ignorat';
        }
        $status = $gateway->fetchStatus($payment);

        return $status ? $this->apply($payment, $status, 'interogare:'.$gateway->code()) : 'ignorat';
    }

    /** Confirmarea manuală a unei încasări (transfer bancar, ramburs, plată la ridicare). */
    public function confirmManual(Payment $payment, User $user, string $note): void
    {
        if ($payment->method === 'card') {
            throw new InvalidArgumentException('Plățile cu cardul se confirmă numai de procesator.');
        }
        $payment->update(['confirmed_by' => $user->id, 'confirmation_note' => mb_substr($note, 0, 255)]);
        $this->apply($payment, 'platita', 'manual:'.$user->id, $note);
        Audit::log('payment.confirmed_manual', $payment, ['order' => $payment->order->number], $payment->order->customer_id, $user->id);
    }

    public function markFailed(Payment $payment, string $reason, string $actor): void
    {
        $payment->update(['failure_reason' => mb_substr($reason, 0, 255)]);
        $this->apply($payment, 'esuata', $actor, $reason);
    }

    private function apply(Payment $payment, string $to, string $actor, ?string $note = null): string
    {
        return DB::transaction(function () use ($payment, $to, $actor, $note) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();
            if ($payment->status === $to || ($payment->isFinal() && $to !== 'rambursata')) {
                return 'ignorat';
            }
            $changes = ['status' => $to];
            if ($to === 'platita') {
                $changes['paid_at'] = now();
            }
            $payment->update($changes);
            if ($to === 'platita') {
                $order->update(['paid_total' => $order->paid_total + $payment->amount]);
                $this->status->payment($order, $order->paid_total >= $order->total ? 'platita' : 'in_asteptare', $note ?? 'Plata a fost confirmată.', $actor);
                if ($order->commercial_status === 'noua') {
                    $this->status->commercial($order, 'confirmata', 'Comanda a fost confirmată după încasare.', true, $actor);
                }
            } elseif ($to === 'autorizata') {
                $this->status->payment($order, 'autorizata', $note ?? 'Plata a fost autorizată.', $actor);
            } elseif (in_array($to, ['esuata', 'anulata'], true) && ! $order->isPaid()) {
                $this->status->payment($order, 'esuata', $note ?? 'Plata nu a reușit. Puteți încerca din nou.', $actor);
            }

            return 'procesat';
        });
    }

    /** Rambursare totală sau parțială; pentru card trece prin procesator, altfel se consemnează manual. */
    public function refund(Payment $payment, int $amount, string $reason, User $user): Refund
    {
        $available = $payment->amount - $payment->refunded_amount;
        if ($payment->status !== 'platita' && $payment->status !== 'rambursata' || $amount <= 0 || $amount > $available) {
            throw new InvalidArgumentException('Suma de rambursat nu este validă pentru această plată.');
        }
        $refund = $payment->refunds()->create([
            'amount' => $amount, 'reason' => mb_substr($reason, 0, 255), 'status' => 'ceruta',
            'idempotency_key' => hash('sha256', $payment->id.'|'.Str::uuid()), 'requested_by' => $user->id,
        ]);
        if ($payment->method === 'card') {
            $gateway = $this->methods->gateway($payment->provider);
            $res = $gateway?->isConfigured() ? $gateway->refund($payment, $amount, $refund->idempotency_key) : ['ok' => false, 'reference' => null, 'error' => 'Procesator indisponibil'];
            if (! $res['ok']) {
                $refund->update(['status' => 'esuata']);
                Audit::log('payment.refund_failed', $refund, ['error' => $res['error']], $payment->order->customer_id, $user->id);

                return $refund;
            }
            $refund->update(['provider_reference' => $res['reference']]);
        }
        $this->completeRefund($refund, $user);

        return $refund;
    }

    public function completeRefund(Refund $refund, User $user): void
    {
        DB::transaction(function () use ($refund, $user) {
            $refund->update(['status' => 'efectuata', 'completed_at' => now()]);
            $payment = Payment::whereKey($refund->payment_id)->lockForUpdate()->first();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();
            $payment->update(['refunded_amount' => $payment->refunded_amount + $refund->amount]);
            if ($payment->refunded_amount >= $payment->amount) {
                $payment->update(['status' => 'rambursata']);
            }
            $order->update(['refunded_total' => $order->refunded_total + $refund->amount]);
            $this->status->payment($order, $order->refunded_total >= $order->paid_total ? 'rambursata' : 'partial_rambursata', 'Rambursare: '.\App\Core\Support\Money::format($refund->amount), 'manual:'.$user->id);
            Audit::log('payment.refunded', $refund, ['amount' => $refund->amount], $order->customer_id, $user->id);
        });
    }
}
