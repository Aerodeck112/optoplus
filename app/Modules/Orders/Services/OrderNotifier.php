<?php

namespace App\Modules\Orders\Services;

use App\Core\Settings\Settings;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Notifications\NewOrderForStaff;
use App\Modules\Orders\Notifications\OrderUpdate;
use App\Modules\Production\Models\ProductionStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Decide ce evenimente ale comenzii ajung pe email la client. Trimiterea se face după confirmarea tranzacției,
 * ca să nu anunțăm ceva ce s-a anulat (rollback).
 */
class OrderNotifier
{
    public function __construct(private Settings $settings) {}

    public function onEvent(Order $order, string $axis, ?string $to): void
    {
        $kind = match (true) {
            $axis === 'commercial' && $to === 'noua' => 'placed',
            $axis === 'commercial' && $to === 'anulata' => 'cancelled',
            $axis === 'payment' && $to === 'platita' => 'paid',
            $axis === 'production' && $to === 'expediata' => 'shipped',
            $axis === 'production' && $to !== null && $to !== 'primita' && $this->notifiesStage($to) => 'production',
            default => null,
        };
        if (! $kind || $order->is_archived_import) {
            return;
        }
        $email = $order->email;
        $orderId = $order->id;
        DB::afterCommit(function () use ($kind, $email, $orderId, $to) {
            self::safely(fn () => Notification::route('mail', $email)->notify(new OrderUpdate($orderId, $kind, $to)), $orderId, $kind);
            if ($kind === 'placed' && ($staff = $this->settings->get('notifications.orders_email'))) {
                self::safely(fn () => Notification::route('mail', $staff)->notify(new NewOrderForStaff($orderId)), $orderId, 'staff');
            }
        });
    }

    /**
     * Un email care nu pleacă (server de mail indisponibil, configurare lipsă) nu are voie să blocheze comanda
     * sau plata. Eroarea se jurnalizează fără date personale; coada reîncearcă joburile eșuate.
     */
    public static function safely(callable $send, int $orderId, string $kind): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::warning('Notificarea comenzii nu a putut fi trimisă', ['order_id' => $orderId, 'tip' => $kind, 'eroare' => $e::class]);
        }
    }

    /** Etapele interne (ex. control calitate) nu trimit email; cele vizibile și marcate importante, da. */
    private function notifiesStage(string $code): bool
    {
        return in_array($code, (array) $this->settings->get('notifications.production_stages', ['clarificari', 'in_lucru', 'pregatita']), true)
            && ProductionStage::where('code', $code)->where('visible_to_customer', true)->exists();
    }
}
