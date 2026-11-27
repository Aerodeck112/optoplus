<?php

use App\Modules\I18n\Support\TranslationSeed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.27:
 *  - recenziile clienților (pe produse și despre magazin, de pe prima pagină), lăsate fără cont, publicate după moderare;
 *  - istoricul prețurilor de bază pe variante (pentru „cel mai mic preț din ultimele 30 de zile” la reduceri —
 *    Directiva (UE) 2019/2161, Legea 363/2007 / OG 21/1992); se pornește cu prețul actual, valabil de la ultima
 *    modificare a variantei (estimare prudentă);
 *  - modificările de preț în masă din panou (Prețuri → Modificare prețuri), păstrate ca să poată fi anulate.
 * Doar Schema/DB: rulează cu codul versiunii vechi în memorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete()->comment('Gol = părere despre magazin');
                $table->unsignedTinyInteger('rating');
                $table->string('title', 120)->nullable();
                $table->text('body');
                $table->string('name', 80);
                $table->string('email', 190)->nullable()->comment('Nu se afișează; doar pentru verificarea cumpărării');
                $table->string('locale', 5)->default('ro');
                $table->string('status', 12)->default('noua')->comment('noua | publicata | respinsa');
                $table->boolean('verified')->default(false)->comment('Emailul are o comandă cu acest produs');
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->text('reply')->nullable()->comment('Răspunsul Optoplus, public');
                $table->timestamp('replied_at')->nullable();
                $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('moderated_at')->nullable();
                $table->string('reject_reason', 160)->nullable();
                $table->char('ip_hash', 64)->nullable();
                $table->timestamps();
                $table->index(['product_id', 'status'], 'reviews_product_status_idx');
                $table->index(['status', 'created_at'], 'reviews_status_created_idx');
            });
        }

        if (! Schema::hasTable('variant_price_history')) {
            Schema::create('variant_price_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->unsignedInteger('price')->comment('Prețul de bază, în bani');
                $table->timestamp('valid_from');
                $table->string('source', 40)->nullable()->comment('inițial | panou | modificare în masă | import');
                $table->index(['variant_id', 'valid_from'], 'vph_variant_from_idx');
            });
            // prețul actual, valabil de la ultima modificare a variantei (orice câmp) — nu pretindem o vechime mai mare
            DB::statement("INSERT INTO variant_price_history (variant_id, price, valid_from, source)
                SELECT id, price, COALESCE(updated_at, created_at, NOW()), 'inițial' FROM product_variants");
        }

        if (! Schema::hasTable('price_change_batches')) {
            Schema::create('price_change_batches', function (Blueprint $table) {
                $table->id();
                $table->string('description', 255);
                $table->json('params');
                $table->unsignedInteger('items_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reverted_at')->nullable();
                $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
            Schema::create('price_change_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained('price_change_batches')->cascadeOnDelete();
                $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->unsignedInteger('old_price');
                $table->unsignedInteger('new_price');
                $table->index(['batch_id'], 'pci_batch_idx');
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
        Schema::dropIfExists('price_change_items');
        Schema::dropIfExists('price_change_batches');
        Schema::dropIfExists('variant_price_history');
        Schema::dropIfExists('reviews');
    }
};
