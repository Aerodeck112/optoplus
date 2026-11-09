<?php

use App\Modules\Pricing\Support\ProtocolCoupons;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.14: codurile de reducere din modulul WordPress „OptoPlus – Cupon reducere lentile + protocol MAPN” (v1.2.0):
 * - promoțiile pot calcula procentul din prețul inițial (reducerile cumulate se adună: 25% + 10% = 35%);
 * - textul dovezii cerute (eticheta câmpului și explicația) se configurează pe promoție;
 * - dovezile încărcate la finalizarea comenzii se păstrează privat, legate de comandă (`order_proofs`);
 * - codurile LENTILE25 și STUDENT10 (ProtocolCoupons). Clasa nouă se încarcă explicit: în timpul actualizării,
 *   în memorie este codul versiunii vechi (vezi CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('promotions', 'percent_of_original')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->boolean('percent_of_original')->default(false)->after('stackable')
                    ->comment('Procentul se calculează din prețul inițial, nu din cel rămas după alte reduceri');
                $table->string('proof_label', 160)->nullable()->after('requires_proof');
                $table->string('proof_help', 400)->nullable()->after('proof_label');
            });
        }
        if (! Schema::hasTable('order_proofs')) {
            Schema::create('order_proofs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
                $table->string('coupon_code', 60)->nullable();
                $table->string('label', 160);
                $table->string('disk_path', 80)->comment('Nume aleator pe discul privat „dovezi”');
                $table->string('mime', 40);
                $table->unsignedInteger('size');
                $table->char('sha256', 64);
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! class_exists(ProtocolCoupons::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Pricing/Support/ProtocolCoupons.php';
        }
        ProtocolCoupons::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_proofs');
        if (Schema::hasColumn('promotions', 'percent_of_original')) {
            Schema::table('promotions', fn (Blueprint $t) => $t->dropColumn(['percent_of_original', 'proof_label', 'proof_help']));
        }
    }
};
