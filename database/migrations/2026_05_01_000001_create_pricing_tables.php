<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prețuri: liste de preț (B2C implicită, liste pentru parteneri), praguri cantitative, promoții și cupoane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('channel', 10)->default('b2b')->comment('b2c | b2b');
            $table->boolean('prices_include_vat')->default(false);
            $table->string('fallback', 20)->default('b2c_discount')->comment('none | b2c | b2c_discount — ce preț se folosește pentru articolele fără preț în listă');
            $table->unsignedSmallInteger('fallback_discount_bp')->default(0)->comment('Reducere aplicată prețului B2C când fallback = b2c_discount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 30)->comment('variant | offering | offering_treatment');
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('min_qty')->default(1)->comment('Prag cantitativ');
            $table->unsignedInteger('price')->comment('Bani; cu/fără TVA după lista de preț');
            $table->timestamps();
            $table->unique(['price_list_id', 'item_type', 'item_id', 'min_qty'], 'price_list_items_unique');
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('customer_label', 160)->nullable()->comment('Textul afișat clientului');
            $table->string('scope', 20)->comment('order | frames | lenses | treatments | category | brand | product');
            $table->json('scope_ids')->nullable();
            $table->string('type', 20)->comment('percent | fixed_amount | fixed_price');
            $table->unsignedInteger('value')->comment('Puncte de bază (procent) sau bani');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('stackable')->default(false)->comment('Se cumulează cu alte promoții cumulabile');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('requires_coupon')->default(false);
            $table->string('channel', 10)->default('b2c')->comment('b2c | b2b | all');
            $table->string('beneficiary_category', 60)->nullable()->comment('Ex. pensionari, studenți');
            $table->boolean('requires_proof')->default(false)->comment('Dovada eligibilității, verificată de vânzări');
            $table->unsignedInteger('min_order_total')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->string('source', 255)->nullable()->comment('manual | import WooCommerce ...');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60)->unique()->comment('Normalizat: majuscule, fără spații');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_wc_id')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('discount')->comment('Bani');
            $table->timestamp('created_at');
            $table->unique(['coupon_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
