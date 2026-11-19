<?php

namespace App\Modules\Shipping\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.17: livrarea în afara României, din setări (Setări → Livrare):
 *  - Ungaria: metoda „curier-hu”, tarif în euro (implicit 20 €, plătit în forinți la cursul zilei);
 *  - celelalte țări din listă: metoda „curier-international”, tarif în euro (implicit 25 €).
 * Ramburs doar în România: metodele internaționale nu acceptă plata la livrare.
 *
 * Doar DB (rulează și din migrare, cu codul vechi în memorie). Idempotent.
 */
final class InternationalShipping
{
    public const HU = 'curier-hu';

    public const INTL = 'curier-international';

    public static function apply(): void
    {
        if (! Schema::hasTable('shipping_methods') || ! Schema::hasColumn('shipping_rules', 'currency')) {
            return;
        }
        $get = function (string $key, $default = null) {
            $row = DB::table('settings')->where('key', $key)->value('value');

            return $row === null ? $default : json_decode((string) $row, true);
        };
        $countries = self::countries((string) $get('shipping.intl_countries', ''));
        $transitMin = $get('shipping.intl_transit_min');
        $transitMax = $get('shipping.intl_transit_max');
        DB::transaction(function () use ($get, $countries, $transitMin, $transitMax) {
            DB::table('shipping_methods')->where('code', 'curier')->update(['countries' => json_encode(['RO'])]);
            self::method(self::HU, 'Livrare prin curier în Ungaria', ['HU'], (bool) $get('shipping.hu_enabled', false), $get('shipping.hu_price'), 10, $transitMin, $transitMax,
                'Livrare prin curier în Ungaria. Timpul de transport se adaugă după ce comanda este gata.');
            self::method(self::INTL, 'Livrare internațională prin curier', $countries, (bool) $get('shipping.intl_enabled', false), $get('shipping.intl_price'), 11, $transitMin, $transitMax,
                'Livrare prin curier în țara aleasă. Timpul de transport se adaugă după ce comanda este gata.');
        });
    }

    private static function method(string $code, string $name, array $countries, bool $enabled, $price, int $sort, $transitMin, $transitMax, string $note): void
    {
        $now = now();
        $data = ['name' => $name, 'kind' => 'curier', 'carrier' => 'manual', 'countries' => json_encode(array_values($countries)),
            'allows_cod' => false, 'is_active' => $enabled && $price !== null && $countries !== [], 'sort' => $sort,
            'transit_days_min' => $transitMin, 'transit_days_max' => $transitMax, 'customer_note' => $note, 'updated_at' => $now];
        $id = DB::table('shipping_methods')->where('code', $code)->value('id');
        if ($id) {
            DB::table('shipping_methods')->where('id', $id)->update($data);
        } else {
            $id = DB::table('shipping_methods')->insertGetId($data + ['code' => $code, 'created_at' => $now]);
        }
        DB::table('shipping_rules')->where('shipping_method_id', $id)->delete();
        if ($price !== null) {
            DB::table('shipping_rules')->insert(['shipping_method_id' => $id, 'min_subtotal' => 0, 'max_subtotal' => null,
                'price' => (int) $price, 'currency' => 'EUR', 'channel' => 'b2c', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** Lista țărilor pentru livrarea internațională (fără România și Ungaria); goală = toate țările europene. */
    public static function countries(string $text): array
    {
        $all = ['AT', 'BE', 'BG', 'CY', 'CZ', 'HR', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'SK', 'SI', 'ES', 'SE', 'NO', 'IS', 'LI', 'CH', 'GB', 'MD'];
        $codes = preg_split('/[\s,;]+/', strtoupper($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $codes = array_values(array_unique(array_intersect($codes, $all)));

        return $codes ?: $all;
    }
}
