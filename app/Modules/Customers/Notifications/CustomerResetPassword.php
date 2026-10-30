<?php

namespace App\Modules\Customers\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerResetPassword extends Notification
{
    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('cont.parola.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false));

        return (new MailMessage)
            ->subject('Setarea parolei pentru contul Optoplus')
            ->greeting('Bună ziua,')
            ->line('Am primit o cerere de setare a parolei pentru contul dumneavoastră Optoplus.')
            ->action('Setează parola', $url)
            ->line('Linkul este valabil 60 de minute. Dacă nu ați cerut dumneavoastră, ignorați acest mesaj.')
            ->salutation('Echipa Optoplus');
    }
}
