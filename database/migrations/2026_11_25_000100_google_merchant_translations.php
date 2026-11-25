<?php

use App\Modules\I18n\Support\TranslationSeed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.26: Google Merchant — traducerile textelor folosite în fluxurile pentru Ungaria și celelalte țări.
 * Fluxul este oprit până îl activează echipa (Setări → Google Merchant).
 */
return new class extends Migration
{
    public function up(): void
    {
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
        //
    }
};
