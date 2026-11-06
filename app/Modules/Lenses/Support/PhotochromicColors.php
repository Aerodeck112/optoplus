<?php

namespace App\Modules\Lenses\Support;

use Illuminate\Support\Facades\DB;

/**
 * 1.0.11 — date confirmate de Optoplus (2026-11-06):
 *  - Xtractive: 250 lei, pe toți indicii (până la 1.74), culori Maro, Gri, Albastru, Verde, Mov;
 *  - Helio: aceleași culori (Maro și Gri își păstrează intensitățile 65% / 85% de până acum);
 *  - ramele fără contur (montaj prin găurire): fără niciun fotocromatic, nici Helio, nici Xtractive.
 *
 * Doar DB (rulează și din migrare, cu codul vechi în memorie). Idempotent. Nu suprascrie ce a schimbat echipa
 * în panou: prețul Xtractive se pune doar unde a rămas cel livrat (0, inactiv), textele doar dacă sunt cele livrate.
 */
final class PhotochromicColors
{
    public const XTRACTIVE_PRICE = 25000;

    /** cod => [etichetă, culoare pentru bulină] */
    public const COLORS = [
        'maro' => ['Maro', '#6b4a2f'], 'gri' => ['Gri', '#4a4f55'], 'albastru' => ['Albastru', '#4d6a8c'],
        'verde' => ['Verde', '#4f6b52'], 'mov' => ['Mov', '#6d5577'],
    ];

    public const RIMLESS_OLD_MESSAGE = 'La ramele fără ramă, cu lentilele prinse prin găurire, nu putem folosi lentile fotocromatice Helio: lentilele pentru găurire sunt dintr-un material special. Puteți alege altă ramă sau renunța la Helio.';

    public const XTRACTIVE_OLD_LIMITS = 'Cât de mult se închid în mașină și afară depinde de lumină și de temperatură. Nu înlocuiesc ochelarii de soare polarizați. Pe ramele fără ramă, cu lentilele prinse prin găurire, specialistul confirmă dacă se pot monta.';

    public static function run(): void
    {
        DB::transaction(fn () => self::apply());
    }

    private static function apply(): void
    {
        $now = now();
        $helio = DB::table('lens_treatments')->where('code', 'fotocromatic')->first();
        $x = DB::table('lens_treatments')->where('code', 'transitions-xtractive')->first();
        if (! $helio || ! $x) {
            return;
        }

        $helioOldLimits = 'Nu se închid în mașină: parbrizul oprește razele UV care le activează, deci nu țin loc de ochelari de soare la volan (pentru condus, alegeți Xtractive). Viteza de închidere și de deschidere depinde de temperatură. Nu se pot monta pe ramele fără ramă, cu lentilele prinse prin găurire.';
        if ($helio->limits === $helioOldLimits) {
            DB::table('lens_treatments')->where('id', $helio->id)->update(['limits' => str_replace('fără ramă', 'fără contur', $helioOldLimits), 'updated_at' => $now]);
        }

        // Helio: Albastru, Verde, Mov devin disponibile, pe toate lentilele cu Helio.
        DB::table('lens_treatment_options')->where('treatment_id', $helio->id)->whereIn('code', ['albastru', 'verde', 'mov'])->update(['is_active' => true, 'updated_at' => $now]);
        foreach (DB::table('lens_offering_treatments')->where('treatment_id', $helio->id)->get() as $row) {
            $codes = json_decode((string) $row->option_codes, true);
            if (is_array($codes)) {
                $merged = array_values(array_unique(array_merge($codes, ['albastru', 'verde', 'mov'])));
                DB::table('lens_offering_treatments')->where('id', $row->id)->update(['option_codes' => json_encode($merged), 'updated_at' => $now]);
            }
        }

        // Xtractive: culorile, prețul de 250 lei și activarea, pe toate lentilele cu Helio.
        $sort = 0;
        foreach (self::COLORS as $code => [$label, $swatch]) {
            $exists = DB::table('lens_treatment_options')->where('treatment_id', $x->id)->where('code', $code)->exists();
            if (! $exists) {
                DB::table('lens_treatment_options')->insert(['treatment_id' => $x->id, 'code' => $code, 'label' => $label, 'swatch' => $swatch,
                    'is_active' => true, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now]);
            }
            $sort++;
        }
        DB::table('lens_offering_treatments')->where('treatment_id', $x->id)->where('price', 0)->where('status', 'inactiv')
            ->update(['price' => self::XTRACTIVE_PRICE, 'status' => 'neconfirmat', 'option_codes' => json_encode(array_keys(self::COLORS)), 'updated_at' => $now]);
        $xUpdate = ['is_active' => true, 'updated_at' => $now];
        if ($x->limits === self::XTRACTIVE_OLD_LIMITS) {
            $xUpdate['limits'] = 'Cât de mult se închid în mașină și afară depinde de lumină și de temperatură. Nu înlocuiesc ochelarii de soare polarizați. Nu se pot monta pe ramele fără contur, cu lentilele prinse prin găurire.';
        }
        DB::table('lens_treatments')->where('id', $x->id)->update($xUpdate);

        // Ramele fără contur (găurire): nici Helio, nici Xtractive.
        $rule = DB::table('compatibility_rules')->where('name', 'Rame fără ramă (găurire): fără fotocromatic Helio')->first();
        if ($rule) {
            $update = [
                'name' => 'Rame fără contur (găurire): fără fotocromatic',
                'conditions' => json_encode([[['field' => 'frame.rim_type', 'op' => 'eq', 'value' => 'rimless_screw'], ['field' => 'treatment', 'op' => 'has', 'value' => ['fotocromatic', 'fotocromatic_condus']]]]),
                'source' => 'Indicație Optoplus, 2026-11-05 / 2026-11-06: lentilele pentru găurire sunt din alt material; nici Helio, nici Xtractive.',
                'updated_at' => $now,
            ];
            if ($rule->message_customer === self::RIMLESS_OLD_MESSAGE) {
                $update['message_customer'] = 'La ramele fără contur, cu lentilele prinse prin găurire, nu putem folosi lentile fotocromatice (nici Helio, nici Xtractive): lentilele pentru găurire sunt dintr-un material special. Puteți alege altă ramă sau renunța la fotocromatic.';
            }
            DB::table('compatibility_rules')->where('id', $rule->id)->update($update);
        }
        // Verificarea de specialist pentru Xtractive pe găurire nu mai e necesară: combinația este interzisă.
        DB::table('compatibility_rules')->where('name', 'Rame fără ramă (găurire) + Xtractive: verificare specialist')->update(['is_active' => false, 'updated_at' => $now]);
    }
}
