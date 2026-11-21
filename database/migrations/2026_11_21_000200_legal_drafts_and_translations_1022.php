<?php

use App\Modules\I18n\Support\TranslationSeed;
use App\Modules\Legal\Support\LegalDrafts2026;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.22: proiectele actualizate ale textelor juridice (ca ciorne, de verificat și publicat de Optoplus) și
 * traducerile textelor noi. Clasele se încarcă explicit: în memorie este codul versiunii vechi (vezi CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(LegalDrafts2026::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Legal/Support/LegalDrafts2026.php';
        }
        if (Schema::hasTable('cms_pages')) {
            LegalDrafts2026::run(dirname(__DIR__, 2).'/database/content/legal');
        }
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
        // ciornele rămân în istoricul paginilor
    }
};
