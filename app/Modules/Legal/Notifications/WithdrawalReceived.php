<?php

namespace App\Modules\Legal\Notifications;

use App\Modules\Legal\Models\ContractWithdrawal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmarea de primire a declarației de retragere, pe suport durabil (email), cu conținutul declarației, data și
 * ora transmiterii (OUG 34/2014 art. 11¹ alin. (7)). Se trimite imediat, nu prin coadă.
 */
class WithdrawalReceived extends Notification
{
    public function __construct(public int $withdrawalId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $w = ContractWithdrawal::findOrFail($this->withdrawalId);

        $mail = (new MailMessage)
            ->subject('Am primit declarația de retragere din contract — comanda '.$w->order_reference)
            ->greeting('Bună ziua,')
            ->line('Confirmăm primirea declarației dumneavoastră de retragere din contract, transmisă online pe site.')
            ->line('Data și ora transmiterii: '.$w->submitted_at->format('d.m.Y H:i').'.')
            ->line('Conținutul declarației:')
            ->line('• Nume: '.$w->name)
            ->line('• Comanda: '.$w->order_reference)
            ->line('• Email pentru confirmare: '.$w->email);
        if ($w->details) {
            $mail->line('• Mesaj / produse: '.$w->details);
        }

        return $mail
            ->line('Vă contactăm cu pașii următori pentru returnarea produselor. Produsele realizate după rețeta sau specificațiile dumneavoastră (de exemplu lentilele) nu fac obiectul dreptului de retragere; pentru ele vă răspundem separat.')
            ->salutation('Echipa Optoplus');
    }
}
