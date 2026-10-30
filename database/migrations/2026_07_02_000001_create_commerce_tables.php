<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coș, comenzi (trei statusuri separate: comercial, plată, producție), plăți cu evenimente idempotente,
 * rambursări, livrare (metode, reguli, expedieri) și referințele facturilor din soluția externă.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 60)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->comment('product | configuration');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('configuration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('patient_reference', 60)->nullable()->comment('B2B: cod intern');
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('kind', 20)->comment('curier | ridicare | posta');
            $table->string('carrier', 40)->nullable()->comment('Adaptorul de curier (manual până la integrare)');
            $table->text('customer_note')->nullable();
            $table->unsignedSmallInteger('transit_days_min')->nullable()->comment('Doar transport, separat de producție');
            $table->unsignedSmallInteger('transit_days_max')->nullable();
            $table->json('countries')->nullable();
            $table->boolean('allows_cod')->default(false)->comment('Permite ramburs');
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_subtotal')->default(0)->comment('Bani, cu TVA');
            $table->unsignedInteger('max_subtotal')->nullable();
            $table->unsignedInteger('max_weight_g')->nullable();
            $table->unsignedInteger('price')->comment('Bani, cu TVA; 0 = gratuit');
            $table->string('channel', 10)->default('all');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->uuid('uuid')->unique();
            $table->string('channel', 10)->default('b2c')->comment('b2c | b2b');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->string('commercial_status', 20)->default('noua')->comment('noua | confirmata | anulata | finalizata');
            $table->string('payment_status', 24)->default('neplatita')
                ->comment('neplatita | in_asteptare | autorizata | platita | partial_rambursata | rambursata | esuata');
            $table->string('production_status', 24)->default('nu_se_aplica');
            $table->string('payment_method', 30);
            $table->string('shipping_method_code', 40)->nullable();
            $table->unsignedInteger('subtotal')->comment('Bani, înainte de reduceri');
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('shipping_total')->default(0);
            $table->unsignedInteger('vat_total')->default(0);
            $table->unsignedInteger('total');
            $table->unsignedInteger('paid_total')->default(0);
            $table->unsignedInteger('refunded_total')->default(0);
            $table->boolean('prices_include_vat')->default(true);
            $table->string('currency', 3)->default('RON');
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();
            $table->string('coupon_code', 60)->nullable();
            $table->text('customer_note')->nullable();
            $table->string('purchase_order_ref', 80)->nullable()->comment('B2B: numărul comenzii la partener');
            $table->unsignedBigInteger('quote_id')->nullable()->index();
            $table->string('idempotency_key', 64)->unique()->comment('Previne plasarea dublă (dublu-click, reîncărcare)');
            $table->char('placed_ip_hash', 64)->nullable();
            $table->string('pricing_version', 20)->comment('Versiunea motorului de preț folosită');
            $table->boolean('is_archived_import')->default(false)->comment('Comandă istorică migrată, read-only');
            $table->unsignedBigInteger('legacy_wc_id')->nullable()->unique();
            $table->unsignedSmallInteger('production_days_min')->nullable();
            $table->unsignedSmallInteger('production_days_max')->nullable();
            $table->timestamp('analytics_purchase_sent_at')->nullable()->comment('Evenimentul purchase trimis o singură dată');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['commercial_status', 'created_at']);
            $table->index(['payment_status']);
            $table->index(['production_status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->comment('product | configuration | lens_only');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->string('sku', 80)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price')->comment('Bani, înainte de reduceri');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('line_total');
            $table->unsignedSmallInteger('vat_rate_bp');
            $table->unsignedInteger('vat_amount');
            $table->unsignedInteger('unit_cost')->nullable()->comment('Cost real, dacă există, pentru marjă');
            $table->string('patient_reference', 60)->nullable();
            $table->json('snapshot')->comment('Ramă, lentilă, tratamente, matrice, reguli, defalcare preț');
            $table->boolean('requires_production')->default(false);
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('axis', 20)->comment('commercial | payment | production | note | customer');
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('message', 500)->nullable();
            $table->boolean('visible_to_customer')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor', 40)->nullable()->comment('sistem | webhook:<furnizor> | client');
            $table->timestamp('created_at', 3);
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30)->comment('card | transfer_bancar | ramburs | numerar_ridicare');
            $table->string('provider', 30)->nullable()->comment('btipay | simulare | manual');
            $table->string('provider_reference', 120)->nullable()->comment('ID-ul plății la procesator');
            $table->string('status', 24)->default('initiata')->comment('initiata | in_asteptare | autorizata | platita | esuata | anulata | rambursata');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->string('idempotency_key', 64)->unique();
            $table->string('redirect_url', 1000)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete()->comment('Confirmare manuală (transfer)');
            $table->string('confirmation_note', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('provider_event_id', 160)->comment('Identificatorul unic al notificării');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60)->nullable();
            $table->boolean('signature_valid');
            $table->string('outcome', 30)->default('primit')->comment('primit | procesat | ignorat | respins');
            $table->json('payload')->nullable()->comment('Corp redus, fără date de card');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('reason', 255);
            $table->string('status', 20)->default('ceruta')->comment('ceruta | efectuata | esuata');
            $table->string('provider_reference', 120)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 40)->default('manual');
            $table->string('awb', 80)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('status', 24)->default('pregatita')->comment('pregatita | predata | in_tranzit | livrata | returnata | esuata');
            $table->json('events')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->comment('Soluția de facturare externă');
            $table->string('kind', 20)->default('factura')->comment('factura | storno | proforma');
            $table->string('series', 20)->nullable();
            $table->string('number', 40)->nullable();
            $table->string('external_id', 120)->nullable();
            $table->string('document_url', 500)->nullable()->comment('Documentul emis de soluția externă');
            $table->string('status', 20)->default('de_emis')->comment('de_emis | emisa | eroare | anulata');
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
