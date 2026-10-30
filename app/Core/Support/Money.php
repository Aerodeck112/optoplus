<?php

namespace App\Core\Support;

use InvalidArgumentException;

/**
 * Sume în bani (întregi, 1 leu = 100 bani), moneda RON. Nicio sumă nu circulă ca float prin aplicație.
 */
final class Money
{
    /** „1.234,50 lei” (separatorul de mii „.”, zecimale „,”, ca pe siteul actual). */
    public static function format(?int $bani, bool $withCurrency = true): string
    {
        if ($bani === null) {
            return '—';
        }
        $sign = $bani < 0 ? '−' : '';
        $abs = abs($bani);
        $text = number_format(intdiv($abs, 100), 0, ',', '.').','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return $sign.$text.($withCurrency ? ' lei' : '');
    }

    /** Acceptă „1234,5”, „1.234,50”, „1234.50”, „99” și returnează bani. */
    public static function parse(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value * 100;
        }
        $s = trim(str_replace(["\u{a0}", ' ', 'lei', 'RON'], '', (string) $value));
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        $s = str_replace(',', '.', $s);
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $s)) {
            throw new InvalidArgumentException("Sumă invalidă: {$value}");
        }
        [$int, $dec] = array_pad(explode('.', ltrim($s, '-')), 2, '0');
        $bani = (int) $int * 100 + (int) str_pad($dec, 2, '0');

        return str_starts_with($s, '-') ? -$bani : $bani;
    }

    /** Aplică un procent exprimat în puncte de bază (1% = 100), cu rotunjire la ban (jumătatea în sus). */
    public static function percent(int $bani, int $basisPoints): int
    {
        return intdiv($bani * $basisPoints + ($bani >= 0 ? 5000 : -5000), 10000);
    }

    /** TVA conținut într-un preț cu TVA inclus: preț − preț / (1 + cotă). */
    public static function vatIncluded(int $grossBani, int $vatBasisPoints): int
    {
        if ($vatBasisPoints <= 0) {
            return 0;
        }
        $net = intdiv($grossBani * 10000 + intdiv(10000 + $vatBasisPoints, 2), 10000 + $vatBasisPoints);

        return $grossBani - $net;
    }

    /** Prețul fără TVA dintr-un preț cu TVA. */
    public static function net(int $grossBani, int $vatBasisPoints): int
    {
        return $grossBani - self::vatIncluded($grossBani, $vatBasisPoints);
    }

    /** Prețul cu TVA dintr-un preț fără TVA. */
    public static function gross(int $netBani, int $vatBasisPoints): int
    {
        return $netBani + self::percent($netBani, $vatBasisPoints);
    }
}
