<?php

use App\Modules\I18n\Support\TranslationSeed;
use App\Modules\Lenses\Support\PhotochromicLineup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.16: gama de lentile fotocromatice (Transitions, Xtractive, PhotoFast, Heliomatic), fiecare pe indicii ei,
 * și regula corectată pentru ramele fără contur (găurire): fără lentile 1,50 / 1,56, fotocromaticele sunt permise.
 * Clasa nouă se încarcă explicit: în timpul actualizării, în memorie este codul versiunii vechi (vezi CLAUDE.md).
 *
 * Traducerile EN/HU ale textelor noi: se reîncarcă din database/content/i18n al ACESTEI versiuni (dosarul se dă
 * explicit: TranslationSeed din memorie poate fi cel vechi, care ar citi fișierele versiunii vechi). Traducerile
 * verificate sau corectate din panou nu se ating.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(PhotochromicLineup::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Lenses/Support/PhotochromicLineup.php';
        }
        PhotochromicLineup::run();

        if (Schema::hasTable('translations')) {
            if (! class_exists(TranslationSeed::class)) {
                require_once dirname(__DIR__, 2).'/app/Modules/I18n/Support/TranslationSeed.php';
            }
            TranslationSeed::run(dirname(__DIR__, 2).'/database/content/i18n');
            Cache::forever('i18n.version', (int) Cache::get('i18n.version', 0) + 1);
        }
    }

    public function down(): void
    {
        // Datele rămân: tratamentele și rândurile noi pot fi deja în comenzi.
    }
};
