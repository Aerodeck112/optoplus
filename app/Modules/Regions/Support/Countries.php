<?php

namespace App\Modules\Regions\Support;

/**
 * Țările în care se poate livra și limba / valuta fiecăreia. Denumirile sunt în română (traducerea în engleză și
 * maghiară vine din dicționar). Lista țărilor pentru livrarea internațională se editează din panou
 * (Setări → Livrare); aici sunt doar valorile implicite.
 */
final class Countries
{
    /** Țările Europei (UE, SEE, Elveția, Regatul Unit, Republica Moldova): cod ISO => denumire. */
    public const NAMES = [
        'RO' => 'România', 'HU' => 'Ungaria', 'AT' => 'Austria', 'BE' => 'Belgia', 'BG' => 'Bulgaria',
        'CY' => 'Cipru', 'CZ' => 'Cehia', 'HR' => 'Croația', 'DK' => 'Danemarca', 'EE' => 'Estonia',
        'FI' => 'Finlanda', 'FR' => 'Franța', 'DE' => 'Germania', 'GR' => 'Grecia', 'IE' => 'Irlanda',
        'IT' => 'Italia', 'LV' => 'Letonia', 'LT' => 'Lituania', 'LU' => 'Luxemburg', 'MT' => 'Malta',
        'NL' => 'Țările de Jos', 'PL' => 'Polonia', 'PT' => 'Portugalia', 'SK' => 'Slovacia', 'SI' => 'Slovenia',
        'ES' => 'Spania', 'SE' => 'Suedia', 'NO' => 'Norvegia', 'IS' => 'Islanda', 'LI' => 'Liechtenstein',
        'CH' => 'Elveția', 'GB' => 'Regatul Unit', 'MD' => 'Republica Moldova',
    ];

    /** Livrarea internațională implicită: toate țările de mai sus, în afară de România și Ungaria (au tarif propriu). */
    public static function defaultInternational(): array
    {
        return array_values(array_diff(array_keys(self::NAMES), ['RO', 'HU']));
    }

    public static function name(?string $code): string
    {
        $code = strtoupper((string) $code);

        return self::NAMES[$code] ?? $code;
    }

    /** „DE, at ,fr” → ['DE', 'AT', 'FR'] (doar coduri cunoscute, fără RO și HU). */
    public static function parseList(?string $text): array
    {
        $codes = preg_split('/[\s,;]+/', strtoupper((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($codes, fn ($c) => isset(self::NAMES[$c]) && ! in_array($c, ['RO', 'HU'], true))));
    }

    /** Limba siteului pentru o țară: România → română, Ungaria → maghiară, restul → engleză. */
    public static function localeFor(string $country): string
    {
        return match (strtoupper($country)) {
            'RO' => 'ro',
            'HU' => 'hu',
            default => 'en',
        };
    }

    /** Valuta pentru o limbă a siteului: română → lei, maghiară → forinți, engleză → euro. */
    public static function currencyFor(string $locale): string
    {
        return match ($locale) {
            'hu' => 'HUF',
            'en' => 'EUR',
            default => 'RON',
        };
    }
}
