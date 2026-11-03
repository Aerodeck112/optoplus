<?php

namespace App\Modules\Settings\Services;

use App\Core\Settings\Settings;

/**
 * Serverul de email (SMTP) din Panou → Setări → Email. Se aplică la prima folosire a serviciului de email
 * (nu la fiecare cerere). Fără SMTP configurat rămâne ce scrie în .env (la instalare: „log”, adică emailurile
 * se scriu doar în jurnal). Adresa expeditorului are mereu o valoare, ca niciun email să nu fie respins pentru
 * lipsa ei: setarea, apoi .env, apoi emailul de contact, apoi no-reply@domeniu.
 */
final class MailSettings
{
    public static function apply(): void
    {
        try {
            $settings = app(Settings::class);
            $all = $settings->all();
        } catch (\Throwable) {
            return; // ex. în timpul instalării, înainte de migrări
        }
        if (self::smtpEnabled($all)) {
            $enc = $all['mail.encryption'] ?? 'tls';
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.host' => $all['mail.host'],
                'mail.mailers.smtp.port' => (int) ($all['mail.port'] ?? ($enc === 'ssl' ? 465 : 587)),
                'mail.mailers.smtp.username' => $all['mail.username'] ?? null,
                'mail.mailers.smtp.password' => $settings->secret('mail.password'),
                'mail.mailers.smtp.timeout' => 20,
            ]);
        }
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        config([
            'mail.from.address' => ($all['mail.from_address'] ?? null) ?: (config('mail.from.address') ?: (($all['contact.email_b2c'] ?? null) ?: 'no-reply@'.$host)),
            'mail.from.name' => ($all['mail.from_name'] ?? null) ?: (config('mail.from.name') ?: 'Optoplus'),
        ]);
        if (! empty($all['mail.reply_to'])) {
            config(['mail.reply_to' => ['address' => $all['mail.reply_to'], 'name' => config('mail.from.name')]]);
        }
    }

    /** Reaplică setările după salvare (serviciul de email păstrează conexiunile create). */
    public static function refresh(): void
    {
        self::apply();
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    /** Emailurile pleacă efectiv (nu doar se scriu în jurnal). */
    public static function delivering(): bool
    {
        if (app()->resolved('mail.manager') === false) {
            app('mail.manager');
        }

        return config('mail.default') !== 'log';
    }

    public static function smtpEnabled(array $all): bool
    {
        return ! empty($all['mail.enabled']) && ! empty($all['mail.host']);
    }

    /** Textul de stare afișat în Setări → Email. */
    public static function status(array $all): array
    {
        if (self::smtpEnabled($all)) {
            return ['info', 'Serverul configurat: '.$all['mail.host'].':'.((int) ($all['mail.port'] ?? 0) ?: '—').'. Confirmați că funcționează cu un email de test, după fiecare modificare.'];
        }
        $env = (string) config('mail.default');

        return in_array($env, ['log', 'array'], true)
            ? ['warn', 'Emailurile NU se trimit acum: se scriu doar în jurnalul aplicației. Completați datele serverului SMTP de mai jos și bifați „Trimite emailurile prin acest server”.']
            : ['info', 'Se folosește configurarea din fișierul .env („'.$env.'”). Puteți completa aici serverul SMTP; datele de aici au prioritate.'];
    }
}
