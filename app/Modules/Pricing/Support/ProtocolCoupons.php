<?php

namespace App\Modules\Pricing\Support;

use App\Core\Audit\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Codurile din modulul WordPress „OptoPlus – Cupon reducere lentile + protocol MAPN” v1.2.0 (primit de la client
 * pe 2026-11-09), refăcute ca promoții obișnuite, editabile din Panou → Prețuri → Promoții:
 *
 * - LENTILE25 — 25%, permanent, protocolul MAPN; cere dovada (legitimație / cupon de pensie);
 * - STUDENT10 — încă 10%, valabil până la 31 octombrie 2026, 23:59:59; cere dovadă separată
 *   (legitimație de student / adeverință).
 *
 * Ca în modul: reducerea se aplică doar la ce e peste prețul ramei (lentile și tratamente), iar procentele se
 * adună, calculate din prețul inițial (25% + 10% = 35%, nu 10% din ce rămâne după 25%).
 *
 * Clasă nouă, de sine stătătoare (rulează din migrare, cu codul vechi în memorie): doar DB și Audit.
 * Un cod care există deja (creat între timp din panou) nu se atinge.
 */
class ProtocolCoupons
{
    public const COUPONS = [
        'LENTILE25' => [
            'name' => 'Protocol MAPN — 25% la lentile (LENTILE25)',
            'customer_label' => 'Reducere MAPN lentile',
            'value' => 2500, 'priority' => 40, 'ends_at' => null,
            'proof_label' => 'Încarcă dovada (legitimație, cupon de pensie)',
            'proof_help' => 'Dovada trebuie să ateste că faci parte din subiecții protocolului. Se acceptă fotografie sau PDF.',
        ],
        'STUDENT10' => [
            'name' => 'Copii studenți — încă 10% la lentile (STUDENT10)',
            'customer_label' => 'Reducere suplimentară copii studenți',
            'value' => 1000, 'priority' => 41, 'ends_at' => '2026-10-31 23:59:59',
            'proof_label' => 'Încarcă dovada studentului (legitimație de student / adeverință)',
            'proof_help' => 'Cod temporar, valabil până la 31 octombrie 2026 inclusiv, pentru copii studenți la facultate. Încarcă dovada eligibilității.',
        ],
    ];

    /** @return list<string> codurile create */
    public static function run(): array
    {
        $created = [];
        foreach (self::COUPONS as $code => $c) {
            if (DB::table('coupons')->where('code', $code)->exists()) {
                continue;
            }
            DB::transaction(function () use ($code, $c, &$created) {
                $now = now();
                $id = DB::table('promotions')->insertGetId([
                    'name' => $c['name'], 'customer_label' => $c['customer_label'],
                    'scope' => 'lenses_treatments', 'scope_ids' => null, 'type' => 'percent', 'value' => $c['value'],
                    'starts_at' => null, 'ends_at' => $c['ends_at'], 'is_active' => true,
                    'stackable' => true, 'percent_of_original' => true, 'priority' => $c['priority'],
                    'requires_coupon' => true, 'channel' => 'b2c', 'beneficiary_category' => null,
                    'requires_proof' => true, 'proof_label' => $c['proof_label'], 'proof_help' => $c['proof_help'],
                    'min_order_total' => null, 'max_uses' => null, 'max_uses_per_customer' => null, 'uses_count' => 0,
                    'source' => 'modul WordPress optoplus-lens-coupon v1.2.0', 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('coupons')->insert([
                    'promotion_id' => $id, 'code' => $code, 'max_uses' => null, 'uses_count' => 0,
                    'expires_at' => $c['ends_at'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $created[] = $code;
            });
        }
        if ($created !== []) {
            Audit::log('pricing.coupon_created', null, ['coduri' => $created, 'sursa' => 'actualizare 1.0.14 (modulul WordPress de cupoane lentile)']);
        }

        return $created;
    }
}
