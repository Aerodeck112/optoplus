<?php

namespace App\Modules\B2B\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteSent extends Notification
{
    public function __construct(public string $number, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Oferta {$this->number} este gata")->greeting('Bună ziua,')
            ->line("Oferta {$this->number} poate fi consultată și acceptată în portalul pentru parteneri.")
            ->action('Vezi oferta', $this->url)->salutation('Echipa Optoplus');
    }
}
