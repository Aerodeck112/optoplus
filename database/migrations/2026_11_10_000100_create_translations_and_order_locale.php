<?php

use App\Modules\I18n\Support\TranslationSeed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.15: siteul în engleză (/en/) și maghiară (/hu/). Traducerile sunt un dicționar „text românesc → traducere”
 * (ca la TranslatePress pe siteul vechi), aplicat paginilor la afișare; se corectează din Panou → Site → Traduceri.
 * Comanda reține limba clientului, pentru emailuri. Datele inițiale vin din database/content/i18n/*.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('translations')) {
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->string('locale', 5);
                $table->char('source_hash', 40)->comment('sha1 al textului sursă normalizat');
                $table->text('source');
                $table->text('target')->nullable();
                $table->string('status', 12)->default('lipsa')->comment('lipsa | automat | import | verificat');
                $table->string('context', 190)->nullable()->comment('Pagina unde a fost văzut prima dată');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['locale', 'source_hash'], 'translations_locale_hash_unique');
                $table->index(['locale', 'status'], 'translations_locale_status_index');
            });
        }
        if (! Schema::hasColumn('orders', 'locale')) {
            Schema::table('orders', fn (Blueprint $t) => $t->string('locale', 5)->default('ro')->after('channel'));
        }

        if (! class_exists(TranslationSeed::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/I18n/Support/TranslationSeed.php';
        }
        TranslationSeed::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        if (Schema::hasColumn('orders', 'locale')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('locale'));
        }
    }
};
