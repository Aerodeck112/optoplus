<?php

namespace App\Core\Support;

use App\Core\Settings\Settings;

/** Textele despre TVA afișate clienților, după statutul fiscal al firmei din Setări → Firma. */
final class Tax
{
    public static function payer(): bool
    {
        return app(Settings::class)->get('company.vat_payer') !== false;
    }

    /** Ex. „Prețurile includ TVA.” sau „Prețurile sunt finale; firma nu este plătitoare de TVA.” */
    public static function note(): string
    {
        return self::payer() ? 'Prețurile includ TVA.' : 'Prețurile afișate sunt finale (firma nu este plătitoare de TVA).';
    }

    public static function total(): string
    {
        return self::payer() ? 'Total cu TVA' : 'Total';
    }
}
