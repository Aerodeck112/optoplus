<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogul: mărci, categorii, atribute filtrabile, produse (rame, lentile, accesorii, servicii), variante,
 * dimensiunile ramelor, imagini și indexul de căutare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_own_brand')->default(false)->comment('Marcă proprie (nu înseamnă producție proprie)');
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedBigInteger('legacy_wc_id')->nullable();
            $table->string('legacy_url', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('kind', 20)->default('rame')->comment('rame | lentile | accesorii');
            $table->string('name', 160);
            $table->string('slug', 160);
            $table->string('path', 255)->unique()->comment('Calea publică, ex. /rame/');
            $table->text('intro')->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique()->comment('gen | material | forma | tip_rama | culoare | constructie');
            $table->string('label', 120);
            $table->string('type', 20)->default('select')->comment('select | multiselect');
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_indexable')->default(false)->comment('Filtrul are URL curat indexabil (ex. /rame/femei/)');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('label', 120);
            $table->string('slug', 80);
            $table->char('swatch', 7)->nullable()->comment('Culoare hex pentru atributul „culoare”');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'code']);
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('frame | lens | accessory | service');
            $table->string('name', 255);
            $table->string('slug', 190)->unique()->comment('URL /produs/<slug>/ pentru rame, /lentile/<slug>/ pentru lentile');
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status', 20)->default('draft')->comment('draft | published | archived');
            $table->text('short_description')->nullable()->comment('HTML sanitizat');
            $table->mediumText('description')->nullable()->comment('HTML sanitizat');
            $table->boolean('made_in_house')->default(false)->comment('Produs în laboratorul Optoplus (doar lentile)');
            $table->string('sale_unit', 10)->default('piece')->comment('piece | pair');
            $table->string('availability_mode', 20)->default('neconfirmat')
                ->comment('stoc_propriu | furnizor | la_comanda | indisponibil | neconfirmat');
            $table->unsignedSmallInteger('lead_time_days_min')->nullable()->comment('Producție/aprovizionare, fără transport');
            $table->unsignedSmallInteger('lead_time_days_max')->nullable();
            $table->boolean('configurable_with_lenses')->default(false)->comment('Rama poate fi comandată cu lentile');
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('legacy_wc_id')->nullable()->unique();
            $table->string('legacy_url', 255)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('manual')->comment('manual | import | descriere');
            $table->primary(['product_id', 'attribute_value_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 80)->unique();
            $table->boolean('sku_generated')->default(false)->comment('SKU generat la import, de înlocuit');
            $table->string('gtin', 20)->nullable();
            $table->string('name', 160)->nullable()->comment('Ex. „Argintiu”');
            $table->json('options')->nullable()->comment('{"culoare":"argintiu"}');
            $table->unsignedInteger('price')->comment('Bani, preț B2C cu TVA');
            $table->unsignedInteger('compare_at_price')->nullable()->comment('Preț anterior, afișat doar cu promoție datată');
            $table->unsignedInteger('cost')->nullable()->comment('Cost real de achiziție (pentru marjă)');
            $table->unsignedSmallInteger('vat_rate_bp')->nullable()->comment('Null = cota implicită din setări');
            $table->integer('stock_qty')->nullable()->comment('Null = stoc negestionat');
            $table->unsignedInteger('weight_g')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedBigInteger('legacy_wc_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('frame_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('lens_width_mm')->nullable();
            $table->unsignedTinyInteger('bridge_mm')->nullable();
            $table->unsignedTinyInteger('temple_mm')->nullable();
            $table->unsignedTinyInteger('lens_height_mm')->nullable();
            $table->unsignedTinyInteger('total_width_mm')->nullable();
            $table->string('size_label', 10)->nullable();
            $table->string('rim_type', 20)->nullable()->comment('full | semi_nylon | rimless_screw');
            $table->string('color_code', 40)->nullable()->comment('Codul culorii producătorului, ex. C2');
            $table->json('sources')->nullable()->comment('{"lens_width_mm":"descriere"} — proveniența fiecărei valori');
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('source_url', 500)->nullable()->comment('Imaginea originală, până la descărcare');
            $table->string('status', 20)->default('ready')->comment('pending | ready | failed');
            $table->string('alt', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sort']);
        });

        Schema::create('product_relations', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_id')->constrained('products')->cascadeOnDelete();
            $table->string('kind', 20)->default('similar')->comment('similar | upsell | accesoriu');
            $table->primary(['product_id', 'related_id', 'kind']);
        });

        Schema::create('search_index', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('terms')->comment('Nume, marcă, SKU, atribute, sinonime — fără diacritice');
            $table->text('compact')->comment('Aceiași termeni fără spații, pentru „rayban” ~ „ray ban”');
            $table->timestamp('updated_at')->nullable();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE search_index ADD FULLTEXT search_index_terms_ft (terms)');
        }

        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 80)->unique();
            $table->string('expands_to', 255)->comment('Termeni separați prin virgulă');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('search_index');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('frame_specs');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
