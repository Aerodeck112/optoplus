<?php

namespace App\Modules\Orders\Notifications;

use App\Core\Support\Money;
use App\Modules\Orders\Http\Controllers\CheckoutController;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Services\PaymentMethods;
use App\Modules\Production\Models\ProductionStage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailurile către client despre comandă. Nu conțin niciodată valorile rețetei sau date de card; linkul duce
 * la pagina comenzii (semnat pentru comenzile fără cont). Trimise din coadă (procesată din cron).
 */
class OrderUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId, public string $kind, public ?string $detail = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = Order::with('items', 'shipments')->findOrFail($this->orderId);
        $link = $order->customer_id ? url('/comanda/'.$order->uuid.'/') : CheckoutController::signedLink($order);
        $mail = (new MailMessage)->greeting('Bună ziua,')->salutation('Echipa Optoplus');

        switch ($this->kind) {
            case 'placed':
                $mail->subject("Am primit comanda {$order->number}")
                    ->line("Vă mulțumim! Am primit comanda {$order->number}, în valoare de ".Money::format($order->total, true, $order->currency).'.');
                foreach ($order->items as $item) {
                    $mail->line("• {$item->quantity} × {$item->name} — ".Money::format($item->line_total, true, $order->currency));
                }
                if ($order->requiresProduction()) {
                    $mail->line('Lentilele se produc în laboratorul nostru după comandă. Un specialist optic verifică rețeta înainte de producție și vă contactează dacă e nevoie de o clarificare.');
                }
                // 1.0.22: confirmarea contractului pe suport durabil — termenii, dreptul de retragere (și funcția online),
                // formularul tip și garanția legală.
                $mail->line('Termenii și condițiile comenzii: '.url('/termeni-si-conditii/'))
                    ->line('Dreptul de retragere: ramele fără lentile și accesoriile se pot returna în 14 zile de la primire, fără motiv; lentilele și ochelarii făcuți după rețetă sunt personalizați și nu fac obiectul dreptului de retragere. Vă puteți retrage online, aici: '.url('/retragere/?comanda='.urlencode($order->number)).' (formularul tip: '.url('/formular-retragere/').').')
                    ->line('Garanția legală de conformitate, de cel puțin 2 ani: '.url('/garantie-legala/'));
                if ($order->payment_method === 'transfer_bancar' && ($bank = app(PaymentMethods::class)->bankDetails())) {
                    $mail->line("Plata prin transfer: {$bank['beneficiary']}, IBAN {$bank['iban']}".($bank['bank'] ? ", {$bank['bank']}" : '').", suma ".Money::format($order->balanceDue(), true, $order->currency).", cu mențiunea „Comanda {$order->number}”.");
                }
                break;
            case 'paid':
                $mail->subject("Plata pentru comanda {$order->number} a fost confirmată")
                    ->line('Am primit plata de '.Money::format($order->paid_total, true, $order->currency).'. Vă anunțăm pe email la fiecare etapă importantă.');
                break;
            case 'production':
                $stage = ProductionStage::where('code', $this->detail)->first();
                $mail->subject("Comanda {$order->number}: ".($stage?->customer_label ?? 'actualizare'))
                    ->line($stage?->customer_description ?? 'Comanda a trecut într-o nouă etapă.');
                break;
            case 'shipped':
                $shipment = $order->shipments->last();
                $mail->subject("Comanda {$order->number} a fost expediată")
                    ->line('Am predat coletul curierului.'.($shipment?->awb ? " Numărul AWB: {$shipment->awb}." : ''));
                if ($shipment?->tracking_url) {
                    $mail->line('Urmărire colet: '.$shipment->tracking_url);
                }
                break;
            case 'cancelled':
                $mail->subject("Comanda {$order->number} a fost anulată")
                    ->line('Comanda a fost anulată.'.($order->cancel_reason ? ' Motiv: '.$order->cancel_reason : ''))
                    ->line('Dacă ați plătit deja, rambursarea se face în contul din care s-a efectuat plata.');
                break;
            default:
                $mail->subject("Comanda {$order->number}: mesaj de la Optoplus")->line((string) $this->detail);
        }

        return $mail->action('Vezi comanda', $link);
    }
}
