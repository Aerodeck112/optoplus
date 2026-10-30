<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lentile: familii, materiale (indici), tratamente, produsele-lentilă și matricea de fabricație (ofertele),
 * plus regulile de compatibilitate editabile din panou.
 *
 * Valorile optice se păstrează ca întregi: dioptriile în sutimi (−6,00 = −600), axul în grade, DP și înălțimile
 * în zecimi de milimetru (31,5 mm = 315). Astfel comparațiile din matrice nu depind de rotunjiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lens_families', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('monofocal | progresiv | degresiv | bifocal');
            $table->string('name', 120);
            $table->text('customer_description')->nullable()->comment('Explicație pentru nespecialiști (aprobată)');
            $table->json('usages')->nullable()->comment('Utilizările pentru care se propune: distanta, aproape, calculator, toate');
            $table->boolean('requires_add')->default(false)->comment('Adiția este obligatorie');
            $table->boolean('requires_fitting_height')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('lens_materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('refractive_index')->comment('Indicele × 1000, ex. 1600 = 1,60');
            $table->text('customer_description')->nullable();
            $table->string('datasheet_note', 255)->nullable()->comment('Sursa datelor tehnice (fișă tehnică)');
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('lens_treatments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('kind', 20)->comment('antireflex | lumina_albastra | fotocromatic | durificare | colorare | altul');
            $table->text('benefits')->nullable()->comment('Beneficii, text aprobat');
            $table->text('limits')->nullable()->comment('Limite, text aprobat');
            $table->foreignId('datasheet_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('brand_note', 160)->nullable()->comment('Ex. marcă înregistrată a unui terț');
            $table->boolean('requires_option')->default(false)->comment('Ex. fotocromaticul cere culoarea');
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('lens_treatment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained('lens_treatments')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('label', 120);
            $table->char('swatch', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['treatment_id', 'code']);
        });

        Schema::create('lens_products', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained('lens_families');
            $table->string('design', 120)->nullable()->comment('Ex. Round Top, Flat Top, standard, premium');
            $table->string('supply_form', 30)->default('personalizata')
                ->comment('bruta | semifinisata | finita_nedebitata | personalizata | montata');
            $table->string('supplier_name', 160)->nullable()->comment('Pentru lentilele care nu sunt produse de Optoplus');
            $table->boolean('quote_only')->default(false)->comment('Cerere de ofertă în loc de preț');
            $table->json('required_measurements')->nullable()->comment('["fitting_height","pd_monocular"]');
            $table->json('usages')->nullable();
            $table->string('tier', 20)->nullable()->comment('Poziționare: esential | echilibrat | avansat (doar orientare)');
            $table->text('customer_summary')->nullable()->comment('Pentru cine e potrivită, în limbaj simplu');
            $table->string('source', 255)->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->timestamps();
        });

        Schema::create('lens_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lens_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('lens_materials');
            $table->string('code', 60)->unique();
            $table->smallInteger('sph_min')->comment('Sutimi de dioptrie');
            $table->smallInteger('sph_max');
            $table->smallInteger('cyl_min')->default(0)->comment('Notație minus: cel mai negativ cilindru acceptat');
            $table->smallInteger('cyl_max')->default(0)->comment('Notație minus: de regulă 0');
            $table->unsignedSmallInteger('max_combined')->nullable()->comment('Limita |SPH| + |CYL| (în notația minus), sutimi');
            $table->unsignedSmallInteger('add_min')->nullable();
            $table->unsignedSmallInteger('add_max')->nullable();
            $table->unsignedSmallInteger('diameter_mm')->nullable();
            $table->unsignedInteger('price')->comment('Bani, B2C cu TVA, per unitatea de vânzare a produsului');
            $table->unsignedInteger('cost')->nullable()->comment('Cost real de producție, pentru marjă');
            $table->unsignedSmallInteger('lead_time_days_min')->nullable();
            $table->unsignedSmallInteger('lead_time_days_max')->nullable();
            $table->boolean('requires_review')->default(true)->comment('Specialistul validează înainte de producție');
            $table->string('status', 20)->default('neconfirmat')->comment('activ | neconfirmat | inactiv');
            $table->string('source', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['lens_product_id', 'material_id']);
        });

        Schema::create('lens_offering_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained('lens_offerings')->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained('lens_treatments')->cascadeOnDelete();
            $table->unsignedInteger('price')->default(0)->comment('Bani, B2C cu TVA, per unitatea de vânzare');
            $table->boolean('is_included')->default(false)->comment('Inclus în prețul ofertei');
            $table->json('option_codes')->nullable()->comment('Opțiunile permise (ex. culori fotocromatice); null = toate');
            $table->string('status', 20)->default('neconfirmat');
            $table->timestamps();
            $table->unique(['offering_id', 'treatment_id']);
        });

        Schema::create('compatibility_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('effect', 20)->comment('block | require_review | warn | require_field');
            $table->string('required_field', 40)->nullable()->comment('Pentru require_field: fitting_height, pd_monocular, add...');
            $table->json('conditions')->comment('[[{"field":"…","op":"…","value":…}, …], …] — grupuri SAU de condiții ȘI');
            $table->string('message_customer', 500);
            $table->string('message_staff', 500)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->string('source', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compatibility_rules');
        Schema::dropIfExists('lens_offering_treatments');
        Schema::dropIfExists('lens_offerings');
        Schema::dropIfExists('lens_products');
        Schema::dropIfExists('lens_treatment_options');
        Schema::dropIfExists('lens_treatments');
        Schema::dropIfExists('lens_materials');
        Schema::dropIfExists('lens_families');
    }
};
