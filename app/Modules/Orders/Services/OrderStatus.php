<?php

namespace App\Modules\Orders\Services;

use App\Core\Audit\Audit;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderEvent;
use App\Modules\Orders\Notifications\OrderUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Tranzițiile celor trei axe de stare. Fiecare schimbare lasă un eveniment (cine, când, din ce în ce) și o
 * intrare de audit. Starea de plată se schimbă numai din serviciul de plăți (webhook verificat, interogare la
 * procesator sau confirmare manuală) — niciodată din revenirea clientului pe pagina de succes.
 */
class OrderStatus
{
    private const COMMERCIAL_FLOW = [
        'noua' => ['confirmata', 'anulata'],
        'confirmata' => ['finalizata', 'anulata'],
        'anulata' => [],
        'finalizata' => [],
    ];

    public function commercial(Order $order, string $to, ?string $message = null, bool $visible = true, ?string $actor = null): void
    {
        if (! in_array($to, self::COMMERCIAL_FLOW[$order->commercial_status] ?? [], true)) {
            throw new InvalidArgumentException("Tranziție comercială nepermisă: {$order->commercial_status} → {$to}");
        }
        $from = $order->commercial_status;
        $changes = ['commercial_status' => $to];
        if ($to === 'confirmata') {
            $changes['confirmed_at'] = now();
        }
        if ($to === 'anulata') {
            $changes['cancelled_at'] = now();
            $changes['cancel_reason'] = $message ? mb_substr($message, 0, 255) : null;
        }
        $order->update($changes);
        $this->event($order, 'commercial', $from, $to, $message, $visible, $actor);
    }

    /** Folosit doar de serviciul de plăți. */
    public function payment(Order $order, string $to, ?string $message, string $actor): void
    {
        if (! array_key_exists($to, Order::PAYMENT)) {
            throw new InvalidArgumentException("Stare de plată necunoscută: {$to}");
        }
        if ($order->payment_status === $to) {
            return;
        }
        $from = $order->payment_status;
        $order->update(['payment_status' => $to]);
        $this->event($order, 'payment', $from, $to, $message, true, $actor);
    }

    public function production(Order $order, string $to, ?string $message = null, ?string $actor = null): void
    {
        if ($order->production_status === $to) {
            return;
        }
        $from = $order->production_status;
        $order->update(['production_status' => $to]);
        $this->event($order, 'production', $from, $to, $message, true, $actor);
    }

    public function note(Order $order, string $message, bool $visible = false): OrderEvent
    {
        return $this->event($order, $visible ? 'customer' : 'note', null, null, $message, $visible, null);
    }

    public function event(Order $order, string $axis, ?string $from, ?string $to, ?string $message, bool $visible, ?string $actor): OrderEvent
    {
        $user = Auth::guard('web')->user();
        $event = $order->events()->create([
            'axis' => $axis, 'from_status' => $from, 'to_status' => $to,
            'message' => $message ? mb_substr($message, 0, 500) : null, 'visible_to_customer' => $visible,
            'user_id' => $user?->id, 'actor' => $actor ?? ($user ? null : 'sistem'), 'created_at' => now(),
        ]);
        if ($axis !== 'note' && $axis !== 'customer') {
            Audit::log("order.{$axis}", $order, ['from' => $from, 'to' => $to, 'actor' => $actor], $order->customer_id);
            app(OrderNotifier::class)->onEvent($order, $axis, $to);
        } elseif ($visible && $message) {
            DB::afterCommit(fn () => OrderNotifier::safely(fn () => Notification::route('mail', $order->email)->notify(new OrderUpdate($order->id, 'message', $message)), $order->id, 'message'));
        }

        return $event;
    }
}
