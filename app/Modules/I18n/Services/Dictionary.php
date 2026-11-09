<?php

namespace App\Modules\I18n\Services;

use App\Modules\I18n\Support\Locales;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dicționarul de traduceri: „text românesc normalizat” → traducere, pe limbă. Cifrele sunt înlocuite cu {0}, {1}…
 * (o singură traducere pentru „766,00 lei” și „381,00 lei”), iar {t} într-o sursă înseamnă „orice text, păstrat”
 * (ex. „RAMA {t}” → „{t} frame”). Se păstrează în cache până la următoarea modificare din panou.
 */
class Dictionary
{
    /** @var array<string, array{map: array<string, string>, patterns: list<array{0:string,1:string}>}> */
    private array $loaded = [];

    /** @var array<string, array{source:string, context:?string}> texte fără traducere, pe hash */
    private array $missing = [];

    /** Colectează textele netraduse (scanare din panou / dezvoltare); dezactivat la afișarea obișnuită. */
    public bool $collect = false;

    public ?string $context = null;

    public function lookup(string $locale, string $text): ?string
    {
        $normalized = Locales::normalize(str_replace("\u{00A0}", ' ', html_entity_decode(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $numbers);
        if ($normalized === '' || ! preg_match('/\p{L}/u', $normalized) || mb_strlen($normalized) > 4000) {
            return null;
        }
        $target = $this->find($locale, $normalized, 0);
        if ($target === null) {
            if ($this->collect && count($this->missing) < 2000) {
                $this->missing[Locales::hash($normalized)] ??= ['source' => $normalized, 'context' => $this->context];
            }

            return null;
        }

        return preg_replace_callback('/\{(\d+)\}/', fn ($m) => $numbers[(int) $m[1]] ?? $m[0], $target);
    }

    /** Caută textul normalizat: întâi exact, apoi după reguli cu {t} (partea {t} se traduce și ea, dacă se poate). */
    private function find(string $locale, string $normalized, int $depth): ?string
    {
        $dict = $this->load($locale);
        $target = $dict['map'][Locales::hash($normalized)] ?? null;
        if ($target !== null || $depth > 1) {
            return $target;
        }
        foreach ($dict['patterns'] as [$regex, $tpl]) {
            if (preg_match($regex, $normalized, $m)) {
                array_shift($m);
                $parts = array_map(fn ($part) => $this->find($locale, $part, $depth + 1) ?? $part, $m);

                return (string) preg_replace_callback('/\{t\}/', function () use (&$parts) {
                    return (string) array_shift($parts);
                }, $tpl);
            }
        }

        return null;
    }

    /** Scrie textele netraduse găsite (stare „lipsă”) pentru toate limbile traduse. */
    public function flushMissing(): int
    {
        if ($this->missing === [] || ! Schema::hasTable('translations')) {
            return 0;
        }
        $now = now();
        $rows = [];
        foreach (Locales::translated() as $locale) {
            foreach ($this->missing as $hash => $m) {
                $rows[] = ['locale' => $locale, 'source_hash' => $hash, 'source' => $m['source'], 'target' => null,
                    'status' => 'lipsa', 'context' => $m['context'] ? mb_substr($m['context'], 0, 190) : null, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $count = 0;
        foreach (array_chunk($rows, 300) as $chunk) {
            $count += DB::table('translations')->insertOrIgnore($chunk);
        }
        $this->missing = [];

        return $count;
    }

    public static function forget(): void
    {
        Cache::forever('i18n.version', (int) Cache::get('i18n.version', 0) + 1);
    }

    /** @return array{map: array<string, string>, patterns: list<array{0:string,1:string}>} */
    private function load(string $locale): array
    {
        if (isset($this->loaded[$locale])) {
            return $this->loaded[$locale];
        }
        $version = (int) Cache::get('i18n.version', 0);

        return $this->loaded[$locale] = Cache::rememberForever("i18n.dict.{$locale}.{$version}", function () use ($locale) {
            $map = [];
            $patterns = [];
            if (! Schema::hasTable('translations')) {
                return ['map' => [], 'patterns' => []];
            }
            foreach (DB::table('translations')->where('locale', $locale)->whereNotNull('target')->where('target', '!=', '')
                ->get(['source_hash', 'source', 'target']) as $row) {
                if (str_contains($row->source, '{t}')) {
                    $patterns[] = ['/^'.str_replace('\{t\}', '(.+?)', preg_quote($row->source, '/')).'$/u', $row->target];
                } else {
                    $map[$row->source_hash] = $row->target;
                }
            }

            // regulile mai specifice (mai mult text fix) se încearcă primele
            usort($patterns, fn ($a, $b) => strlen(str_replace('(.+?)', '', $b[0])) <=> strlen(str_replace('(.+?)', '', $a[0])));

            return ['map' => $map, 'patterns' => $patterns];
        });
    }
}
