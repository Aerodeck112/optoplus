<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM (cereri, oportunități, sarcini, notițe, comunicări), asistentul (conversații cu date reduse, utilizare)
 * și importatorul (rulări, mapare sursă → țintă).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30)->comment('ajutor_lentile | contact | parteneriat | asistent | telefon | manual');
            $table->string('status', 20)->default('noua')->comment('noua | contactat | calificat | convertit | pierdut');
            $table->string('name', 160)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('summary')->nullable()->comment('Fără valori de rețetă');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('form_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->string('stage', 20)->default('nou')->comment('nou | calificat | oferta | negociere | castigat | pierdut');
            $table->unsignedInteger('estimated_value')->nullable();
            $table->date('expected_close_on')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lost_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->text('details')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['assignee_id', 'completed_at', 'due_at']);
        });

        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_communications', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('channel', 20)->comment('email | telefon | whatsapp | sms | intalnire | portal');
            $table->string('direction', 10)->comment('intrare | iesire');
            $table->string('summary', 500);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('site');
            $table->string('engine', 20)->comment('reguli | ai:<furnizor>');
            $table->unsignedSmallInteger('messages_count')->default(0);
            $table->timestamp('handoff_requested_at')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->string('role', 12)->comment('client | asistent');
            $table->text('text')->comment('Date personale mascate (email, telefon, valori de rețetă)');
            $table->json('tools')->nullable()->comment('Instrumentele apelate și parametrii, fără date personale');
            $table->timestamp('created_at');
        });

        Schema::create('assistant_usage', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('engine', 30);
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_microusd')->default(0)->comment('Cost estimat, milionimi de USD');
            $table->unique(['day', 'engine']);
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->comment('woocommerce_csv | woocommerce_api');
            $table->string('source_name', 255);
            $table->char('source_sha256', 64);
            $table->string('mode', 20)->comment('simulare | import');
            $table->string('status', 20)->default('in_curs')->comment('in_curs | intrerupt | finalizat | esuat');
            $table->unsignedInteger('cursor')->default(0)->comment('Ultimul rând procesat (reluare)');
            $table->unsignedInteger('total')->default(0);
            $table->json('counters')->nullable();
            $table->json('report')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);
            $table->string('source_type', 30)->comment('product | variation | brand | category | image | coupon | customer | order');
            $table->string('source_id', 120);
            $table->string('target_type', 60)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->char('source_hash', 64)->nullable()->comment('Hash-ul datelor sursă la ultimul import');
            $table->foreignId('last_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->string('status', 20)->default('importat')->comment('importat | eroare | ignorat');
            $table->json('messages')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_type', 'source_id'], 'import_items_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('assistant_usage');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
        Schema::dropIfExists('crm_communications');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
    }
};
