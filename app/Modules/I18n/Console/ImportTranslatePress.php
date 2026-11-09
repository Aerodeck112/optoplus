<?php

namespace App\Modules\I18n\Console;

use App\Core\Audit\Audit;
use App\Modules\I18n\Services\Dictionary;
use App\Modules\I18n\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importă traducerile din exportul TranslatePress al siteului vechi (tabela wp_trp_dictionary_ro_ro_<limbă>,
 * exportată din phpMyAdmin ca CSV sau SQL). Se iau doar rândurile traduse; cele traduse de om (status 2) au
 * prioritate. Nu se suprascrie o traducere verificată din panou. Starea rezultată: „import” (din siteul vechi).
 */
class ImportTranslatePress extends Command
{
    protected $signature = 'optoplus:i18n:import-translatepress {file : Exportul CSV sau SQL} {--locale=hu} {--dry-run}';

    protected $description = 'Importă traducerile TranslatePress (siteul vechi) în dicționarul siteului';

    public function handle(): int
    {
        $locale = (string) $this->option('locale');
        if (! Locales::isTranslated($locale)) {
            $this->error('Limbă necunoscută: '.$locale);

            return self::FAILURE;
        }
        $file = (string) $this->argument('file');
        $raw = is_file($file) ? (string) file_get_contents($file) : '';
        if ($raw === '') {
            $this->error('Fișierul nu există sau este gol.');

            return self::FAILURE;
        }
        $rows = str_contains($raw, 'INSERT INTO') ? $this->fromSql($raw) : $this->fromCsv($file);
        // [original, translated, status]; status 2 = tradus de om, 1 = automat
        $best = [];
        foreach ($rows as [$original, $translated, $status]) {
            $original = trim(html_entity_decode(strip_tags((string) $original), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $translated = trim(html_entity_decode(strip_tags((string) $translated), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($original === '' || $translated === '' || (int) $status < 1) {
                continue;
            }
            $normalized = Locales::normalize($original, $numbers);
            $target = $translated;
            foreach ($numbers as $i => $n) { // aceleași cifre în traducere devin marcaje
                $target = preg_replace('/(?<![\d{])'.preg_quote($n, '/').'(?![\d}])/u', '{'.$i.'}', $target, 1) ?? $target;
            }
            $hash = Locales::hash($normalized);
            if (! isset($best[$hash]) || (int) $status > $best[$hash]['status']) {
                $best[$hash] = ['source' => $normalized, 'target' => trim(preg_replace('/\s+/u', ' ', $target) ?? $target), 'status' => (int) $status];
            }
        }
        $this->info('Traduceri găsite: '.count($best).' (traduse de om: '.count(array_filter($best, fn ($r) => $r['status'] === 2)).').');
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        $written = 0;
        foreach (array_chunk($best, 300, true) as $chunk) {
            $locked = DB::table('translations')->where('locale', $locale)->whereIn('source_hash', array_keys($chunk))
                ->where('status', 'verificat')->pluck('source_hash')->all();
            $now = now();
            $data = [];
            foreach ($chunk as $hash => $r) {
                if (! in_array($hash, $locked, true)) {
                    $data[] = ['locale' => $locale, 'source_hash' => $hash, 'source' => $r['source'], 'target' => $r['target'],
                        'status' => 'import', 'context' => 'TranslatePress', 'created_at' => $now, 'updated_at' => $now];
                }
            }
            if ($data) {
                DB::table('translations')->upsert($data, ['locale', 'source_hash'], ['target', 'status', 'context', 'updated_at']);
                $written += count($data);
            }
        }
        Dictionary::forget();
        Audit::log('i18n.translatepress_imported', null, ['limba' => $locale, 'traduceri' => $written]);
        $this->info("Scrise: {$written}.");

        return self::SUCCESS;
    }

    /** @return list<array{0:string,1:string,2:int}> */
    private function fromCsv(string $file): array
    {
        $h = fopen($file, 'r');
        $first = (string) fgets($h);
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($h);
        $head = array_map(fn ($c) => strtolower(trim((string) $c, "\"' \xEF\xBB\xBF")), fgetcsv($h, 0, $sep) ?: []);
        $io = array_search('original', $head, true);
        $it = array_search('translated', $head, true);
        $is = array_search('status', $head, true);
        $out = [];
        if ($io === false || $it === false) {
            return $out;
        }
        while (($r = fgetcsv($h, 0, $sep)) !== false) {
            $out[] = [$r[$io] ?? '', $r[$it] ?? '', $is === false ? 2 : (int) ($r[$is] ?? 0)];
        }

        return $out;
    }

    /** INSERT INTO `wp_trp_dictionary_…` (`id`,`original`,`translated`,`status`,…) VALUES (…),(…); @return list<array{0:string,1:string,2:int}> */
    private function fromSql(string $sql): array
    {
        $out = [];
        preg_match_all('/INSERT INTO\s+`?\w*trp_dictionary\w*`?\s*\(([^)]*)\)\s*VALUES\s*(.+?);\s*$/msi', $sql, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $b) {
            $cols = array_map(fn ($c) => trim($c, " `\n\r\t"), explode(',', $b[1]));
            $io = array_search('original', $cols, true);
            $it = array_search('translated', $cols, true);
            $is = array_search('status', $cols, true);
            if ($io === false || $it === false) {
                continue;
            }
            foreach ($this->tuples($b[2]) as $t) {
                $out[] = [$t[$io] ?? '', $t[$it] ?? '', $is === false ? 2 : (int) ($t[$is] ?? 0)];
            }
        }

        return $out;
    }

    /** Desparte „(1,'a\'b',NULL),(2,…)” în valori. @return list<list<string|null>> */
    private function tuples(string $values): array
    {
        $rows = [];
        $row = null;
        $buf = '';
        $inStr = false;
        $len = strlen($values);
        for ($i = 0; $i < $len; $i++) {
            $c = $values[$i];
            if ($inStr) {
                if ($c === '\\' && $i + 1 < $len) {
                    $n = $values[++$i];
                    $buf .= match ($n) { 'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", default => $n };
                } elseif ($c === "'" && ($values[$i + 1] ?? '') === "'") {
                    $buf .= "'";
                    $i++;
                } elseif ($c === "'") {
                    $inStr = false;
                } else {
                    $buf .= $c;
                }

                continue;
            }
            if ($c === "'") {
                $inStr = true;
            } elseif ($c === '(' && $row === null) {
                $row = [];
                $buf = '';
            } elseif (($c === ',' || $c === ')') && $row !== null) {
                $v = trim($buf);
                $row[] = strtoupper($v) === 'NULL' ? null : $v;
                $buf = '';
                if ($c === ')') {
                    $rows[] = $row;
                    $row = null;
                }
            } elseif ($row !== null) {
                $buf .= $c;
            }
        }

        return $rows;
    }
}
