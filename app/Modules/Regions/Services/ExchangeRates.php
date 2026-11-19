<?php

namespace App\Modules\Regions\Services;

use App\Core\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cursul de schimb pentru prețurile în euro și forinți. Catalogul rămâne în lei; prețul în altă valută se
 * calculează din lei la cursul de referință BNR (implicit) sau la cursul introdus manual în panou, plus un
 * adaos opțional (ex. comisionul de conversie). Nu inventăm cursuri: fără curs, valuta nu se folosește și siteul
 * rămâne în lei.
 *
 * Sumele sunt întregi: cenți (EUR) și „fillér” (HUF, rotunjit la forint întreg, ×100). Cursul se păstrează
 * ca întreg: lei pentru o unitate de valută × 10^8.
 */
class ExchangeRates
{
    public const SCALE = 100_000_000;

    public const CURRENCIES = ['EUR', 'HUF'];

    /** Fișierul public al BNR cu cursurile de referință (curs.bnr.ro; www.bnr.ro ca rezervă). */
    public const BNR_URLS = ['https://curs.bnr.ro/nbrfxrates.xml', 'https://www.bnr.ro/nbrfxrates.xml'];

    /** @var array<string, ?array{rate:int, date:?string, source:string}> */
    private array $memo = [];

    public function __construct(private Settings $settings) {}

    /** Lei pentru o unitate de valută (× 10^8), cu adaosul aplicat; null = valută indisponibilă. */
    public function rate(string $currency): ?int
    {
        $info = $this->info($currency);
        if ($info === null) {
            return null;
        }
        $markup = max(0, (int) $this->settings->get('currency.markup_bp', 0));

        // Adaosul face valuta „mai ieftină” în lei, deci prețul în valută puțin mai mare.
        return intdiv($info['rate'] * 10000, 10000 + $markup);
    }

    public function has(string $currency): bool
    {
        return strtoupper($currency) === 'RON' || $this->rate($currency) !== null;
    }

    /** @return array{rate:int, date:?string, source:string}|null cursul de bază (fără adaos), data și sursa */
    public function info(string $currency): ?array
    {
        $currency = strtoupper($currency);
        if ($currency === 'RON') {
            return ['rate' => self::SCALE, 'date' => null, 'source' => 'RON'];
        }
        if (array_key_exists($currency, $this->memo)) {
            return $this->memo[$currency];
        }
        $info = null;
        if ($this->settings->get('currency.source', 'bnr') === 'manual') {
            $manual = self::parseRate((string) $this->settings->get('currency.manual_'.strtolower($currency), ''));
            if ($manual !== null) {
                // HUF se introduce ca la BNR: lei pentru 100 de forinți.
                $info = ['rate' => $currency === 'HUF' ? intdiv($manual, 100) : $manual, 'date' => null, 'source' => 'manual'];
            }
        } elseif (Schema::hasTable('exchange_rates')) {
            $row = DB::table('exchange_rates')->where('currency', $currency)->orderByDesc('rate_date')->first();
            if ($row) {
                $info = ['rate' => (int) $row->rate_scaled, 'date' => (string) $row->rate_date, 'source' => (string) $row->source];
            }
        }

        return $this->memo[$currency] = $info;
    }

    /** Lei (bani) → valută (subunități). EUR: la cent; HUF: la forint întreg. */
    public function fromRon(int $bani, string $currency): int
    {
        $currency = strtoupper($currency);
        if ($currency === 'RON') {
            return $bani;
        }
        $rate = $this->rate($currency);
        if ($rate === null || $rate <= 0) {
            return $bani;
        }
        $unit = $currency === 'HUF' ? 100 : 1; // HUF: forinți întregi
        $num = $bani * self::SCALE;
        $den = $rate * $unit;
        $q = intdiv(abs($num) * 2 + $den, 2 * $den); // rotunjire la jumătate în sus

        return ($bani < 0 ? -$q : $q) * $unit;
    }

    /** Valută (subunități) → lei (bani), pentru rapoarte. */
    public function toRon(int $amount, string $currency): int
    {
        $currency = strtoupper($currency);
        if ($currency === 'RON') {
            return $amount;
        }
        $rate = $this->rate($currency);
        if ($rate === null) {
            return $amount;
        }

        return (int) round($amount * $rate / self::SCALE);
    }

    /** O valută (subunități) în alta, prin lei (ex. 20 € pentru Ungaria → forinți). */
    public function convert(int $amount, string $from, string $to): int
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return $amount;
        }
        if ($from === 'RON') {
            return $this->fromRon($amount, $to);
        }
        $rate = $this->rate($from);
        if ($rate === null) {
            return $amount;
        }
        // întâi în lei, cu precizie (fără rotunjire la ban), apoi în valuta cerută
        $bani = intdiv($amount * $rate * 2 + self::SCALE, 2 * self::SCALE);
        if ($to === 'RON') {
            return $bani;
        }

        return $this->fromRon($bani, $to);
    }

    /** Cursul de schimb în text, pentru panou și comandă: „1 EUR = 4,9772 lei (BNR, 18.11.2026)”. */
    public function describe(string $currency): ?string
    {
        $info = $this->info($currency);
        if ($info === null || $currency === 'RON') {
            return null;
        }
        $per = $currency === 'HUF' ? 100 : 1;
        $value = number_format($info['rate'] * $per / self::SCALE, 4, ',', '.');
        $when = $info['date'] ? ', '.date('d.m.Y', strtotime($info['date'])) : '';

        return "{$per} {$currency} = {$value} lei (".($info['source'] === 'manual' ? 'curs introdus manual' : 'BNR').$when.')';
    }

    /** „4,9772” sau „4.9772” → 497720000 (lei × 10^8); null dacă nu e un curs valid. */
    public static function parseRate(string $text): ?int
    {
        $text = str_replace(',', '.', trim($text));
        if (! preg_match('/^\d{1,4}(\.\d{1,8})?$/', $text) || (float) $text <= 0) {
            return null;
        }
        [$int, $dec] = array_pad(explode('.', $text), 2, '');

        return (int) $int * self::SCALE + (int) str_pad(substr($dec, 0, 8), 8, '0');
    }

    /**
     * Cursurile de referință BNR (fișierul public nbrfxrates.xml). Păstrează doar EUR și HUF.
     *
     * @return array{ok:bool, date:?string, message:string}
     */
    public function fetchBnr(?string $xml = null): array
    {
        try {
            $parsed = $xml !== null ? self::parseBnr($xml) : null;
            foreach ($xml === null ? self::BNR_URLS : [] as $url) {
                $response = Http::timeout(20)->connectTimeout(10)->retry(2, 1000, throw: false)->get($url);
                if ($response->successful() && ($parsed = self::parseBnr($response->body())) !== null) {
                    break;
                }
            }
        } catch (Throwable $e) {
            Log::warning('Cursul BNR nu a putut fi citit', ['eroare' => $e::class]);

            return ['ok' => false, 'date' => null, 'message' => 'Cursul BNR nu a putut fi citit.'];
        }
        if ($parsed === null) {
            return ['ok' => false, 'date' => null, 'message' => 'Răspunsul BNR nu are formatul așteptat.'];
        }
        $now = now();
        foreach ($parsed['rates'] as $currency => $rate) {
            DB::table('exchange_rates')->updateOrInsert(
                ['currency' => $currency, 'rate_date' => $parsed['date']],
                ['rate_scaled' => $rate, 'source' => 'BNR', 'updated_at' => $now, 'created_at' => $now],
            );
        }
        $this->memo = [];

        return ['ok' => true, 'date' => $parsed['date'], 'message' => 'Cursul BNR din '.date('d.m.Y', strtotime($parsed['date'])).' a fost salvat.'];
    }

    /** @return array{date:string, rates:array<string,int>}|null */
    public static function parseBnr(string $xml): ?array
    {
        if (! preg_match('/<Cube\s+date="(\d{4}-\d{2}-\d{2})"\s*>(.*?)<\/Cube>/s', $xml, $cube)) {
            return null;
        }
        $rates = [];
        preg_match_all('/<Rate\s+currency="([A-Z]{3})"(?:\s+multiplier="(\d+)")?\s*>([\d.]+)<\/Rate>/', $cube[2], $m, PREG_SET_ORDER);
        foreach ($m as [, $currency, $multiplier, $value]) {
            if (! in_array($currency, self::CURRENCIES, true) || ($rate = self::parseRate($value)) === null) {
                continue;
            }
            $rates[$currency] = intdiv($rate, max(1, (int) ($multiplier ?: 1)));
        }

        return $rates === [] ? null : ['date' => $cube[1], 'rates' => $rates];
    }

    /** Data ultimului curs BNR salvat, sau null. */
    public function lastBnrDate(): ?string
    {
        return Schema::hasTable('exchange_rates') ? DB::table('exchange_rates')->where('source', 'BNR')->max('rate_date') : null;
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
