<?php

namespace App\Core\Support;

use InvalidArgumentException;

/**
 * Sume în subunități întregi (1 leu = 100 bani; la fel cenți pentru EUR și „fillér” pentru HUF). Moneda de bază a
 * catalogului este RON; EUR și HUF apar doar ca valută de afișare / de comandă (vezi modulul Regions).
 * Nicio sumă nu circulă ca float prin aplicație.
 */
final class Money
{
    /**
     * „1.234,50 lei” (RON, ca pe siteul actual), „€1,234.50” (EUR), „12.990 Ft” (HUF, fără zecimale).
     * Fără monedă: doar numărul, în formatul monedei.
     */
    public static function format(?int $amount, bool $withCurrency = true, ?string $currency = null): string
    {
        if ($amount === null) {
            return '—';
        }
        $sign = $amount < 0 ? '−' : '';
        $abs = abs($amount);

        return match (strtoupper($currency ?? 'RON')) {
            'EUR' => $sign.($withCurrency ? '€' : '').number_format(intdiv($abs, 100), 0, '.', ',').'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT),
            'HUF' => $sign.number_format((int) round($abs / 100), 0, ',', '.').($withCurrency ? ' Ft' : ''),
            default => $sign.number_format(intdiv($abs, 100), 0, ',', '.').','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT).($withCurrency ? ' lei' : ''),
        };
    }

    /** Un preț din catalog (în lei) afișat în valuta vizitatorului, convertit ca în motorul de preț. */
    public static function display(?int $bani): string
    {
        if ($bani === null) {
            return '—';
        }
        $regions = app(\App\Modules\Regions\Services\Regions::class);
        $currency = $regions->currency();

        return self::format($regions->rates()->fromRon($bani, $currency), true, $currency);
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
