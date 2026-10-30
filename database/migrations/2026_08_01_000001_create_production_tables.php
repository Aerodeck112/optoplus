<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Producția: etape configurabile, fișe de lucru per articol de comandă, istoricul etapelor și refacerile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_stages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120)->comment('Eticheta internă');
            $table->string('customer_label', 160)->comment('Ce vede clientul, în limbaj simplu');
            $table->string('customer_description', 255)->nullable();
            $table->unsignedSmallInteger('sort');
            $table->boolean('visible_to_customer')->default(true);
            $table->boolean('only_if_mounting')->default(false)->comment('Montajul: doar pentru ochelari compleți');
            $table->string('required_permission', 60)->nullable()->comment('Ex. production.approve pentru „aprobată pentru producție”');
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('production_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30)->default('primita');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->unsignedTinyInteger('priority')->default(2)->comment('1 urgent · 2 normal · 3 scăzut');
            $table->string('lot_reference', 80)->nullable()->comment('Lot / referință de trasabilitate, unde există');
            $table->text('work_sheet')->nullable()->comment('Criptat: rețeta verificată, lentila, tratamentele, centrarea');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->comment('Specialistul care a aprobat');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('remake_of_id')->nullable()->constrained('production_jobs')->nullOnDelete();
            $table->boolean('requires_mounting')->default(false);
            $table->unsignedInteger('version')->default(1)->comment('Blocare optimistă');
            $table->timestamps();
            $table->index(['stage', 'due_on']);
        });

        Schema::create('production_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_job_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 30)->nullable();
            $table->string('to_stage', 30)->nullable();
            $table->string('change', 40)->default('etapa')->comment('etapa | responsabil | termen | lot | nota');
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at', 3);
        });

        Schema::create('remakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_job_id')->constrained()->cascadeOnDelete()->comment('Lucrarea refăcută');
            $table->foreignId('new_job_id')->nullable()->constrained('production_jobs')->nullOnDelete();
            $table->unsignedBigInteger('complaint_id')->nullable()->index();
            $table->string('reason', 30)->comment('eroare_productie | eroare_reteta | deteriorare_transport | adaptare | altul');
            $table->string('cost_borne_by', 20)->default('optoplus')->comment('optoplus | client | partener | de_stabilit');
            $table->text('details')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remakes');
        Schema::dropIfExists('production_events');
        Schema::dropIfExists('production_jobs');
        Schema::dropIfExists('production_stages');
    }
};
