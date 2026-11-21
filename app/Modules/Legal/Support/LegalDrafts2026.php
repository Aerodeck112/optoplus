<?php

namespace App\Modules\Legal\Support;

use Illuminate\Support\Facades\DB;

/**
 * 1.0.22: proiectele actualizate ale textelor juridice (retragerea online, livrarea și plata în străinătate,
 * garanția legală, fără platforma europeană SOL închisă în 2025) se adaugă ca CIORNE noi. Nu publică nimic: textele
 * juridice se publică doar de Optoplus, după verificare (dreptul „cms.legal”).
 *
 * Nu atinge o pagină a cărei ultimă versiune a fost scrisă de un om în panou (ciorna lui rămâne a lui).
 * Doar DB (rulează din migrare, cu codul vechi în memorie). Idempotent după notă.
 *
 * @return array<string, string> pagina => adăugat | sărit (motiv)
 */
final class LegalDrafts2026
{
    public const NOTE = 'Actualizare 1.0.22: conformitate (retragere online, livrare și plată în străinătate, garanție legală) — de verificat înainte de publicare';

    public static function run(string $dir): array
    {
        $get = function (string $key, $default = null) {
            $v = DB::table('settings')->where('key', $key)->value('value');

            return $v === null ? $default : json_decode((string) $v, true);
        };
        $lei = fn ($bani) => $bani === null ? '[cost]' : number_format(intdiv((int) $bani, 100), 0, ',', '.').','.str_pad((string) ((int) $bani % 100), 2, '0', STR_PAD_LEFT).' lei';
        $eur = fn ($cents) => $cents === null ? '[cost]' : number_format(((int) $cents) / 100, 2, ',', '.').' €';
        $e = fn ($v, $fallback) => htmlspecialchars((string) ($v ?? $fallback), ENT_QUOTES, 'UTF-8');
        $vars = [
            '{firma}' => $e($get('company.name'), '[denumirea firmei]'), '{cui}' => $e($get('company.vat_code'), '[CUI]'),
            '{regcom}' => $e($get('company.registry'), '[nr. Registrul Comerțului]'), '{sediu}' => $e($get('company.address'), '[sediul social]'),
            '{adresa_magazin}' => $e($get('contact.address'), '[adresa]'), '{telefon}' => $e($get('contact.phone_b2c'), '[telefon]'),
            '{email}' => $e($get('contact.email_b2c'), '[email]'),
            '{cost_livrare}' => $lei($get('shipping.courier_price')), '{cost_hu}' => $eur($get('shipping.hu_price')), '{cost_intl}' => $eur($get('shipping.intl_price')),
            '{termen_executie}' => ($get('orders.production_days_min') ?? 2).'–'.($get('orders.production_days_max') ?? 5),
            '{nota_tva}' => $get('company.vat_payer') ? 'Prețurile includ TVA.' : 'Prețurile afișate sunt finale (firma nu este plătitoare de TVA).',
            '{retentie_reteta}' => '3', '{retentie_configuratii}' => '60', '{data}' => now()->format('d.m.Y'),
        ];
        $out = [];
        foreach (glob(rtrim($dir, '/').'/*.html') ?: [] as $file) {
            $path = '/'.basename($file, '.html').'/';
            $page = DB::table('cms_pages')->where('path', $path)->first();
            if (! $page) {
                $out[$path] = 'sărit (pagina nu există)';

                continue;
            }
            if (DB::table('cms_page_versions')->where('page_id', $page->id)->where('note', self::NOTE)->exists()) {
                $out[$path] = 'sărit (există deja)';

                continue;
            }
            $latest = DB::table('cms_page_versions')->where('page_id', $page->id)->orderByDesc('number')->first();
            if ($latest && $latest->created_by !== null) {
                $out[$path] = 'sărit (ultima versiune e scrisă de echipă)';

                continue;
            }
            $html = strtr((string) file_get_contents($file), $vars);
            $now = now();
            $id = DB::table('cms_page_versions')->insertGetId([
                'page_id' => $page->id, 'number' => (int) ($latest->number ?? 0) + 1, 'title' => $latest->title ?? $page->title,
                'seo' => $latest->seo ?? json_encode(['noindex' => false]),
                'blocks' => json_encode([['type' => 'rich_text', 'data' => ['title' => '', 'html' => $html]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'note' => self::NOTE, 'created_by' => null, 'created_at' => $now, 'published_at' => null,
            ]);
            DB::table('cms_pages')->where('id', $page->id)->update(['current_version_id' => $id, 'needs_legal_review' => true,
                'lock_version' => (int) $page->lock_version + 1, 'updated_at' => $now]);
            $out[$path] = 'adăugat (ciornă)';
        }

        return $out;
    }
}
