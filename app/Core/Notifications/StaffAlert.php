<?php

namespace App\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Anunț intern scurt (cerere nouă de pe site, plată primită, comandă anulată). Fără date medicale: doar ce trebuie
 * ca echipa să deschidă înregistrarea din panou.
 */
class StaffAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<string>  $lines */
    public function __construct(public string $subject, public array $lines, public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject);
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $this->url ? $mail->action('Deschide în panou', $this->url) : $mail;
    }

    /** Trimite la toate adresele interne; o eroare de email nu blochează acțiunea care a declanșat anunțul. */
    public static function send(string $subject, array $lines, ?string $url = null): void
    {
        $to = StaffRecipients::emails();
        if ($to === []) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Notification::route('mail', $to)->notify(new self($subject, $lines, $url));
        } catch (\Throwable $e) {
            Log::warning('Anunțul intern nu a putut fi trimis', ['subiect' => $subject, 'eroare' => $e::class]);
        }
    }
}
