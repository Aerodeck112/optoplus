<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conturile clienților (persoane fizice și utilizatorii partenerilor B2B), separate de conturile personalului.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('password')->nullable()->comment('Null = cont creat la comandă sau migrat, fără parolă setată');
            $table->string('legacy_password_hash', 255)->nullable()->comment('Hash WordPress compatibil, șters după prima autentificare');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('type', 20)->default('persoana_fizica')->comment('persoana_fizica | persoana_juridica');
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('marketing_consent_at')->nullable()->comment('Separat de operațiunile necesare comenzii');
            $table->string('marketing_consent_source', 60)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('legacy_wc_id')->nullable()->unique();
            $table->timestamp('anonymized_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('livrare')->comment('livrare | facturare');
            $table->string('name', 160);
            $table->string('company', 160)->nullable();
            $table->string('vat_code', 30)->nullable()->comment('CUI pentru factură pe firmă');
            $table->string('phone', 40)->nullable();
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 120);
            $table->string('county', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country', 2)->default('RO');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_key', 64)->nullable()->index()->comment('Pentru vizitatori fără cont: hash de sesiune/comandă');
            $table->string('type', 40)->comment('termeni | confidentialitate | prelucrare_reteta | marketing | ai_extern');
            $table->string('document_version', 40)->nullable();
            $table->boolean('granted');
            $table->string('source', 60)->comment('checkout | cont | configurator | asistent');
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at');
            $table->index(['customer_id', 'type']);
        });

        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 190)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->comment('export | stergere');
            $table->string('status', 20)->default('noua')->comment('noua | in_lucru | finalizata | respinsa');
            $table->text('resolution')->nullable();
            $table->string('export_path', 255)->nullable()->comment('Fișier privat, expiră');
            $table->timestamp('due_at');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Organizațiile B2B (clinici, cabinete, magazine de optică) și membrii lor. Restul tabelelor B2B: 2026_09.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('kind', 30)->default('magazin_optica')->comment('clinica | cabinet | magazin_optica | altul');
            $table->string('vat_code', 30)->nullable()->comment('CUI');
            $table->string('registry_number', 40)->nullable();
            $table->string('status', 20)->default('cerere')->comment('cerere | aprobata | suspendata | respinsa');
            $table->unsignedBigInteger('price_list_id')->nullable()->index()->comment('Lista de preț a partenerului (2026_05)');
            $table->boolean('show_prices_with_vat')->default(false);
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->comment('Doar după aprobare explicită');
            $table->unsignedInteger('credit_limit')->nullable()->comment('Bani; doar după aprobare explicită');
            $table->foreignId('terms_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('terms_approved_at')->nullable();
            $table->string('billing_address', 500)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->comment('admin_organizatie | comenzi | vizualizare | financiar');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::dropIfExists('customer_consents');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
