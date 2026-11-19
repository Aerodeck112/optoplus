<?php

namespace App\Core\Security;

/**
 * Surse CSP suplimentare pentru cererea curentă (ex. o etichetă de măsurare activă doar pe anumite pagini).
 * Middleware-ul SecurityHeaders le adaugă la politica de bază și le golește la începutul fiecărei cereri.
 */
final class Csp
{
    /** @var array<string, list<string>> */
    private static array $extra = [];

    public static function allow(string $directive, array $sources): void
    {
        foreach ($sources as $source) {
            if (preg_match('#^https://[a-z0-9*.-]+(/[^\s;]*)?$#i', $source)) {
                self::$extra[$directive][] = $source;
            }
        }
    }

    /** @return list<string> */
    public static function extra(string $directive): array
    {
        return array_values(array_unique(self::$extra[$directive] ?? []));
    }

    /**
     * Paginile de plată la care trimite formularul de comandă (BT iPay test și producție, Stripe Checkout).
     * Browserul aplică „form-action” și redirecționării de după trimiterea formularului: fără aceste adrese,
     * „Plasează comanda” nu face nimic (1.0.19). Adresele serverelor BT din setări se adaugă și ele.
     *
     * @return list<string>
     */
    public static function paymentFormTargets(): array
    {
        $out = ['https://ecclients.btrl.ro', 'https://ecclients.btrl.ro:5443', 'https://ecclients-sandbox.btrl.ro', 'https://checkout.stripe.com'];
        try {
            $settings = app(\App\Core\Settings\Settings::class);
            foreach (['payments.btipay.base_url_test', 'payments.btipay.base_url_production'] as $key) {
                $url = (string) $settings->get($key, '');
                if (preg_match('#^(https://[a-z0-9.-]+(?::\d{2,5})?)#i', $url, $m)) {
                    $out[] = strtolower($m[1]);
                }
            }
        } catch (\Throwable) {
            // fără setări (ex. instalare): rămân adresele cunoscute
        }

        return array_values(array_unique($out));
    }

    public static function reset(): void
    {
        self::$extra = [];
    }
}
