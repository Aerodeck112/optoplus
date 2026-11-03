<?php

namespace App\Core\Mail;

use Illuminate\Support\Facades\Log;

/**
 * Trimiterea unui email nu are voie să oprească acțiunea principală (crearea unui cont, o resetare de parolă):
 * dacă serverul de email nu e configurat sau refuză mesajul, notăm în jurnal (fără conținutul mesajului,
 * fără parole) și apelantul decide ce îi spune utilizatorului.
 */
final class SafeMail
{
    public static function attempt(callable $send, string $context): bool
    {
        try {
            $send();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Emailul nu a putut fi trimis', ['context' => $context, 'eroare' => $e::class, 'detalii' => mb_substr($e->getMessage(), 0, 300)]);

            return false;
        }
    }
}
