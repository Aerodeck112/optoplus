<?php

namespace App\Modules\B2B\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class Invitation extends Notification
{
    public function __construct(public string $organization, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitație în portalul Optoplus pentru parteneri')
            ->greeting('Bună ziua,')
            ->line("Ați fost invitat(ă) în contul organizației „{$this->organization}” din portalul Optoplus pentru parteneri.")
            ->action('Acceptă invitația', $this->url)
            ->line('Linkul este valabil 7 zile. Dacă nu vă așteptați la acest mesaj, îl puteți ignora.')
            ->salutation('Echipa Optoplus');
    }
}
