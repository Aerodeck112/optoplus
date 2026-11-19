<?php

namespace App\Modules\Regions\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Țara vizitatorului, pentru alegerea automată a limbii și a valutei la prima vizită.
 *
 * Surse, în ordine:
 *  1. țara dată de server sau de CDN (Cloudflare „CF-IPCountry”, mod_geoip „GEOIP_COUNTRY_CODE”);
 *  2. baza locală „IP to Country Lite” de la DB-IP (licență CC BY 4.0, descărcată lunar din cron în
 *     storage/app/geoip; adresa IP nu pleacă nicăieri și nu se păstrează);
 *  3. limba browserului, doar pentru maghiară (un browser în engleză nu înseamnă că vizitatorul e din străinătate).
 *
 * Rezultatul servește doar la redirecționarea de la prima vizită; clientul poate schimba oricând limba.
 */
class GeoCountry
{
    public const DB_FILE = 'geoip/dbip-country-lite.mmdb';

    private ?Reader $reader = null;

    private bool $opened = false;

    /** @return array{country:?string, source:string} */
    public function detect(Request $request): array
    {
        foreach ([$request->headers->get('CF-IPCountry'), $request->server('GEOIP_COUNTRY_CODE')] as $value) {
            $code = strtoupper(trim((string) $value));
            if (preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['XX', 'T1', 'EU', 'AP'], true)) {
                return ['country' => $code, 'source' => 'server'];
            }
        }
        if ($code = $this->lookup((string) $request->ip())) {
            return ['country' => $code, 'source' => 'db-ip'];
        }
        $lang = strtolower(substr((string) $request->getPreferredLanguage(['hu', 'ro', 'en']), 0, 2));
        if ($lang === 'hu' && str_starts_with(strtolower((string) $request->header('Accept-Language')), 'hu')) {
            return ['country' => 'HU', 'source' => 'browser'];
        }

        return ['country' => null, 'source' => 'necunoscut'];
    }

    public function lookup(string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        $reader = $this->reader();
        if (! $reader) {
            return null;
        }
        try {
            $row = $reader->get($ip);
            $code = is_array($row) ? ($row['country']['iso_code'] ?? null) : null;

            return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function path(): string
    {
        return storage_path('app/'.self::DB_FILE);
    }

    public static function installedAt(): ?int
    {
        return is_file(self::path()) ? (int) filemtime(self::path()) : null;
    }

    private function reader(): ?Reader
    {
        if (! $this->opened) {
            $this->opened = true;
            try {
                $this->reader = is_file(self::path()) ? new Reader(self::path()) : null;
            } catch (Throwable $e) {
                Log::warning('Baza DB-IP nu poate fi deschisă', ['eroare' => $e::class]);
                $this->reader = null;
            }
        }

        return $this->reader;
    }

    /**
     * Descarcă baza lunară DB-IP „country lite” (format MMDB, ~4 MB comprimat) și o înlocuiește atomic.
     *
     * @return array{ok:bool, message:string}
     */
    public static function download(?string $month = null): array
    {
        $months = $month ? [$month] : [date('Y-m'), date('Y-m', strtotime('first day of last month'))];
        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        foreach ($months as $m) {
            $url = "https://download.db-ip.com/free/dbip-country-lite-{$m}.mmdb.gz";
            $tmp = $dir.'/download-'.bin2hex(random_bytes(4)).'.gz';
            try {
                $response = Http::timeout(120)->connectTimeout(15)->withOptions(['sink' => $tmp])->get($url);
                if (! $response->successful()) {
                    @unlink($tmp);

                    continue;
                }
                $gz = gzopen($tmp, 'rb');
                $out = $dir.'/new-'.bin2hex(random_bytes(4)).'.mmdb';
                $fh = fopen($out, 'wb');
                while ($gz && ! gzeof($gz)) {
                    fwrite($fh, (string) gzread($gz, 1 << 20));
                }
                $gz && gzclose($gz);
                fclose($fh);
                @unlink($tmp);
                (new Reader($out))->get('1.1.1.1'); // fișierul se poate citi?
                rename($out, self::path());

                return ['ok' => true, 'message' => "Baza DB-IP ({$m}) a fost instalată."];
            } catch (Throwable $e) {
                @unlink($tmp);
                isset($out) && @unlink($out);
                Log::warning('Baza DB-IP nu a putut fi descărcată', ['luna' => $m, 'eroare' => $e::class]);
            }
        }

        return ['ok' => false, 'message' => 'Baza DB-IP nu a putut fi descărcată (verificați accesul la download.db-ip.com).'];
    }
}
