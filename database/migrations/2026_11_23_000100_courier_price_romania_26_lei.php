<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.23: livrarea prin curier în România costă 26 lei (cerut de Optoplus, 2026-11-23; înainte 22 lei).
 * Se schimbă doar dacă tariful este încă 22 lei (dacă echipa l-a modificat deja din Setări → Livrare, rămâne al ei):
 * setarea, regula de tarif a metodei „curier”, banda de sus și textele generate automat (versiunile paginilor create
 * de sistem, nu de echipă). Doar DB: rulează cu codul versiunii vechi în memorie.
 */
return new class extends Migration
{
    private const OLD = 2200;

    private const NEW = 2600;

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }
        $current = DB::table('settings')->where('key', 'shipping.courier_price')->value('value');
        $changed = false;
        if ($current === null || (int) json_decode((string) $current, true) === self::OLD) {
            DB::table('settings')->updateOrInsert(['key' => 'shipping.courier_price'], ['value' => json_encode(self::NEW), 'updated_at' => now()]);
            $changed = true;
        }
        if (! $changed) {
            return;
        }

        $courier = DB::table('shipping_methods')->where('code', 'curier')->value('id');
        if ($courier) {
            DB::table('shipping_rules')->where('shipping_method_id', $courier)->where('price', self::OLD)
                ->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'RON'))
                ->update(['price' => self::NEW, 'updated_at' => now()]);
        }

        $topbar = DB::table('settings')->where('key', 'site.topbar_text')->value('value');
        if (is_string($topbar) && str_contains($topbar, '22,00 lei')) {
            DB::table('settings')->where('key', 'site.topbar_text')->update(['value' => str_replace('22,00 lei', '26,00 lei', $topbar), 'updated_at' => now()]);
        }

        // Textele juridice generate din setări (ciornele și versiunile create automat); ce a scris echipa nu se atinge.
        foreach (DB::table('cms_page_versions')->whereNull('created_by')->where('blocks', 'like', '%22,00 lei%')->get(['id', 'blocks']) as $v) {
            DB::table('cms_page_versions')->where('id', $v->id)->update(['blocks' => str_replace('22,00 lei', '26,00 lei', (string) $v->blocks)]);
        }

        Cache::forget('optoplus.settings');
        Cache::forever('pagecache.generation', (int) Cache::get('pagecache.generation', 1) + 1);
    }

    public function down(): void
    {
        // tariful rămâne cel ales; se poate schimba din Setări → Livrare
    }
};
