<?php

use App\Modules\I18n\Support\TranslationSeed;
use App\Modules\Shipping\Support\InternationalShipping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.17: țări, valute și livrare internațională.
 *  - cursurile de schimb (BNR) pentru prețurile în euro și forinți;
 *  - tarifele de livrare au moneda lor (22 lei România, 20 € Ungaria, 25 € celelalte țări);
 *  - plățile și comenzile își păstrează valuta, cursul folosit și echivalentul în lei (pentru rapoarte);
 *  - valorile implicite cerute de Optoplus (2026-11-19): notificările interne pe office@optoplus.ro și
 *    jeremias@optica-optofarm.ro, livrarea în Ungaria (20 €) și în celelalte țări (25 €), fără ramburs;
 *  - traducerile textelor noi.
 * Clasele noi se încarcă explicit: în timpul actualizării, în memorie este codul versiunii vechi (vezi CLAUDE.md).
 */
return new class extends Migration
{
    private const STAFF_EMAILS = ['office@optoplus.ro', 'jeremias@optica-optofarm.ro'];

    public function up(): void
    {
        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->char('currency', 3);
                $table->date('rate_date');
                $table->unsignedBigInteger('rate_scaled')->comment('Lei pentru o unitate de valută × 10^8');
                $table->string('source', 20)->default('BNR');
                $table->timestamps();
                $table->unique(['currency', 'rate_date']);
            });
        }
        if (! Schema::hasColumn('shipping_rules', 'currency')) {
            Schema::table('shipping_rules', fn (Blueprint $t) => $t->char('currency', 3)->default('RON')->after('price'));
        }
        if (! Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', fn (Blueprint $t) => $t->char('currency', 3)->default('RON')->after('amount'));
        }
        if (! Schema::hasColumn('orders', 'total_ron')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->unsignedBigInteger('rate_scaled')->nullable()->after('currency')->comment('Lei pentru o unitate de valută × 10^8, la plasare');
                $t->string('rate_note', 120)->nullable()->after('rate_scaled');
                $t->integer('total_ron')->nullable()->after('total')->comment('Totalul în lei (bani), pentru rapoarte');
                $t->char('shipping_country', 2)->nullable()->after('shipping_method_code');
            });
            DB::table('orders')->where('currency', 'RON')->update(['total_ron' => DB::raw('total')]);
        }

        $this->settingsDefaults();

        if (! class_exists(InternationalShipping::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Shipping/Support/InternationalShipping.php';
        }
        InternationalShipping::apply();

        if (Schema::hasTable('translations')) {
            if (! class_exists(TranslationSeed::class)) {
                require_once dirname(__DIR__, 2).'/app/Modules/I18n/Support/TranslationSeed.php';
            }
            TranslationSeed::run(dirname(__DIR__, 2).'/database/content/i18n');
            Cache::forever('i18n.version', (int) Cache::get('i18n.version', 0) + 1);
        }
        Cache::forget('optoplus.settings');
    }

    private function settingsDefaults(): void
    {
        $get = fn (string $key) => ($v = DB::table('settings')->where('key', $key)->value('value')) === null ? null : json_decode((string) $v, true);
        $set = fn (string $key, $value) => DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        $default = function (string $key, $value) use ($get, $set) {
            if ($get($key) === null) {
                $set($key, $value);
            }
        };

        // Notificările interne: adresele cerute, plus cea configurată deja (dacă era alta).
        $existing = preg_split('/[\s,;]+/', mb_strtolower((string) $get('notifications.orders_email')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $set('notifications.orders_email', implode(', ', array_values(array_unique(array_merge(self::STAFF_EMAILS, $existing)))));

        $default('currency.enabled', true);
        $default('currency.source', 'bnr');
        $default('currency.markup_bp', 0);
        $default('regions.auto_detect', true);
        $default('shipping.hu_enabled', true);
        $default('shipping.hu_price', 2000);          // 20,00 €
        $default('shipping.intl_enabled', true);
        $default('shipping.intl_price', 2500);        // 25,00 €
        $default('payments.stripe.enabled', false);
        $default('payments.stripe.currencies', 'EUR, HUF');
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        foreach ([['shipping_rules', ['currency']], ['payments', ['currency']], ['orders', ['rate_scaled', 'rate_note', 'total_ron', 'shipping_country']]] as [$table, $cols]) {
            if (Schema::hasColumn($table, $cols[0])) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($cols));
            }
        }
    }
};
