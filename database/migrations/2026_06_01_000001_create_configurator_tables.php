<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rețete (date privind sănătatea: valori criptate, fișiere private) și configurațiile salvate ale clienților.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_key', 64)->nullable()->index()->comment('Hash pentru configurațiile fără cont');
            $table->string('patient_reference', 60)->nullable()->comment('B2B: codul intern al partenerului, nu numele pacientului');
            $table->string('source', 20)->default('manual')->comment('manual | upload | ocr_confirmat | specialist');
            $table->text('values')->nullable()->comment('Criptat: {od:{sph,cyl,axis,add,prism...}, os:{...}}');
            $table->string('pd_mode', 12)->nullable()->comment('binocular | monocular');
            $table->text('measurements')->nullable()->comment('Criptat: DP, DP pe ochi, înălțimi de montaj');
            $table->date('issued_on')->nullable();
            $table->boolean('customer_unsure')->default(false)->comment('Clientul nu înțelege rețeta: cere ajutor');
            $table->timestamp('confirmed_by_customer_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable()->comment('Criptat');
            $table->date('retention_until')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique()->comment('Identificatorul public al fișierului (nepredictibil)');
            $table->string('disk_path', 255)->comment('Cale aleatoare în stocarea privată');
            $table->string('mime', 80);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->timestamp('created_at');
        });

        Schema::create('ocr_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_file_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->text('values')->comment('Criptat: valori propuse + încredere; nu modifică rețeta');
            $table->string('status', 20)->default('propusa')->comment('propusa | confirmata | respinsa');
            $table->timestamps();
        });

        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_key', 64)->nullable()->index();
            $table->string('entry', 20)->comment('cu_rama | fara_rama | rama_mea | ajutor');
            $table->foreignId('frame_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('usage', 30)->nullable()->comment('distanta | aproape | calculator | toate | nu_stiu');
            $table->foreignId('lens_offering_id')->nullable()->constrained('lens_offerings')->nullOnDelete();
            $table->json('treatments')->nullable()->comment('[{"treatment_id":1,"option":"maro_85"}]');
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->json('validation')->nullable()->comment('Rezultatul ultimei validări pe server');
            $table->json('price_snapshot')->nullable()->comment('Defalcarea ultimului calcul pe server');
            $table->string('status', 24)->default('draft')->comment('draft | valid | necesita_verificare | in_comanda');
            $table->char('share_token_hash', 64)->nullable()->unique();
            $table->timestamp('share_expires_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations');
        Schema::dropIfExists('ocr_suggestions');
        Schema::dropIfExists('prescription_files');
        Schema::dropIfExists('prescriptions');
    }
};
