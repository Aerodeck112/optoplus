<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B: cereri de parteneriat, invitații, oferte cu versiuni, importuri CSV de comandă, documente și reclamații.
 * Organizațiile și membrii lor sunt în 2026_01_05 (sunt folosite de coș și configurații).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 190);
            $table->string('kind', 30);
            $table->string('vat_code', 30)->nullable();
            $table->string('contact_name', 160);
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->string('city', 120)->nullable();
            $table->text('message')->nullable();
            $table->json('documents')->nullable()->comment('Fișiere private încărcate (uuid)');
            $table->string('status', 20)->default('noua')->comment('noua | in_analiza | aprobata | respinsa');
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email', 190);
            $table->string('role', 30);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('ceruta')->comment('ceruta | ciorna | trimisa | acceptata | respinsa | expirata | anulata');
            $table->text('request_text')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('accepted_version_id')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete()->comment('Responsabil vânzări');
            $table->timestamps();
        });

        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->json('items')->comment('Linii cu descriere, cantitate, preț unitar, configurație');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('vat_total');
            $table->unsignedInteger('total');
            $table->boolean('prices_include_vat')->default(false);
            $table->date('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->text('note_to_customer')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['quote_id', 'number']);
        });

        Schema::create('b2b_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 190);
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_valid')->default(0);
            $table->json('report')->comment('Rezultatul validării pe rânduri');
            $table->string('status', 20)->default('validat')->comment('validat | adaugat_in_cos | respins');
            $table->timestamps();
        });

        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('title', 190);
            $table->string('kind', 30)->comment('contract | oferta | factura | certificat | altul');
            $table->string('disk_path', 255);
            $table->string('mime', 80);
            $table->unsignedInteger('size');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('reclamatie')->comment('reclamatie | refacere | retur');
            $table->string('reason', 40);
            $table->text('description');
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('noua')->comment('noua | in_analiza | acceptata | respinsa | rezolvata');
            $table->text('resolution')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('organization_documents');
        Schema::dropIfExists('b2b_imports');
        Schema::dropIfExists('quote_versions');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('partnership_requests');
    }
};
