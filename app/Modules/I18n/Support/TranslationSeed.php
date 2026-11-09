<?php

namespace App\Modules\I18n\Support;

use Illuminate\Support\Facades\DB;

/**
 * Încarcă traducerile livrate cu aplicația (database/content/i18n/<limbă>.json: { "text românesc": "traducere" }).
 * Nu suprascrie o traducere verificată sau corectată din panou (stare „verificat”); actualizează doar ce e
 * „lipsă” sau „automat”. Clasă de sine stătătoare (rulează din migrare, cu codul vechi în memorie): doar DB.
 */
class TranslationSeed
{
    /** @return array<string, int> traduceri scrise pe limbă */
    public static function run(?string $dir = null): array
    {
        $dir ??= dirname(__DIR__, 4).'/database/content/i18n';
        $out = [];
        foreach (glob($dir.'/*.json') ?: [] as $file) {
            $locale = basename($file, '.json');
            if (! preg_match('/^[a-z]{2}$/', $locale) || $locale === 'ro') {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true) ?: [];
            $status = $data['_status'] ?? 'automat';
            unset($data['_status']);
            $now = now();
            $rows = [];
            foreach ($data as $source => $target) {
                if (! is_string($source) || ! is_string($target) || trim($target) === '') {
                    continue;
                }
                $normalized = self::normalize($source);
                $rows[] = ['locale' => $locale, 'source_hash' => sha1($normalized), 'source' => $normalized,
                    'target' => self::normalize($target, false), 'status' => $status, 'created_at' => $now, 'updated_at' => $now];
            }
            $written = 0;
            foreach (array_chunk($rows, 300) as $chunk) {
                $hashes = array_column($chunk, 'source_hash');
                $locked = DB::table('translations')->where('locale', $locale)->whereIn('source_hash', $hashes)
                    ->whereNotIn('status', ['lipsa', 'automat'])->pluck('source_hash')->all();
                $chunk = array_values(array_filter($chunk, fn ($r) => ! in_array($r['source_hash'], $locked, true)));
                if ($chunk) {
                    DB::table('translations')->upsert($chunk, ['locale', 'source_hash'], ['target', 'status', 'updated_at']);
                    $written += count($chunk);
                }
            }
            $out[$locale] = $written;
        }

        return $out;
    }

    /** La fel ca Locales::normalize (copie locală: clasa trebuie să meargă și cu codul vechi în memorie). */
    private static function normalize(string $text, bool $numbers = true): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (! $numbers) {
            return $text;
        }
        $i = 0;

        return (string) preg_replace_callback('/(?<![\p{L}\d{])\d+(?:[.,]\d+)*(?![\p{L}\d}])/u', function () use (&$i) {
            return '{'.($i++).'}';
        }, $text);
    }
}
