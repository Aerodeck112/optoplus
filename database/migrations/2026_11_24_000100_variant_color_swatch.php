<?php

use App\Modules\I18n\Support\TranslationSeed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.25: mostra de culoare a ramei (variantei), aleasă în panou, afișată pe site lângă numele culorii — pentru
 * pozele în care culoarea nu se vede bine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_variants', 'color_hex')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->char('color_hex', 7)->nullable()->after('name')->comment('Mostra de culoare #RRGGBB (opțional)');
            });
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
        if (Schema::hasColumn('product_variants', 'color_hex')) {
            Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('color_hex'));
        }
    }
};
