<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.22 (conformitate):
 *  - declarațiile de retragere trimise online prin funcția de retragere (OUG 34/2014 art. 11¹, introdus prin
 *    OUG 18/2026, aplicabil din 19.06.2026), cu data și ora transmiterii;
 *  - datele producătorului pe marcă (Regulamentul (UE) 2023/988 — GPSR, art. 19: numele și adresa producătorului
 *    și, pentru producătorii din afara UE, persoana responsabilă din UE, afișate în oferta online).
 * Doar Schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_withdrawals')) {
            Schema::create('contract_withdrawals', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $t->string('order_reference', 60)->comment('Numărul comenzii, cum l-a scris consumatorul');
                $t->string('name', 160);
                $t->string('email', 190);
                $t->text('details')->nullable()->comment('Ce produse / mesajul consumatorului');
                $t->string('locale', 5)->default('ro');
                $t->char('ip_hash', 64)->nullable();
                $t->timestamp('submitted_at')->comment('Data și ora transmiterii declarației');
                $t->timestamp('confirmation_sent_at')->nullable();
                $t->string('status', 20)->default('primita')->comment('primita | acceptata | respinsa (produs personalizat) | finalizata');
                $t->text('staff_note')->nullable();
                $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->index(['status', 'submitted_at']);
            });
        }
        if (! Schema::hasColumn('brands', 'manufacturer_name')) {
            Schema::table('brands', function (Blueprint $t) {
                $t->string('manufacturer_name', 190)->nullable();
                $t->string('manufacturer_address', 255)->nullable();
                $t->string('manufacturer_contact', 190)->nullable()->comment('Email sau site');
                $t->string('eu_responsible', 255)->nullable()->comment('Persoana responsabilă din UE (producători din afara UE)');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_withdrawals');
        if (Schema::hasColumn('brands', 'manufacturer_name')) {
            Schema::table('brands', fn (Blueprint $t) => $t->dropColumn(['manufacturer_name', 'manufacturer_address', 'manufacturer_contact', 'eu_responsible']));
        }
    }
};
