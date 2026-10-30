<?php

namespace App\Modules\Orders\Notifications;

use App\Core\Support\Money;
use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Anunț intern pentru o comandă nouă: doar numărul, valoarea și linkul din panou (fără date personale sau medicale). */
class NewOrderForStaff extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = Order::findOrFail($this->orderId);

        return (new MailMessage)
            ->subject("Comandă nouă {$order->number}")
            ->line("Comanda {$order->number}: ".Money::format($order->total).", plată: {$order->paymentMethodLabel()}.")
            ->line($order->requiresProduction() ? 'Conține lentile: necesită verificarea rețetei de către un specialist.' : 'Fără producție.')
            ->action('Deschide în panou', url('/panou/comenzi/'.$order->uuid.'/'));
    }
}
