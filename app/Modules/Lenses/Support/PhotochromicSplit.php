<?php

namespace App\Modules\Lenses\Support;

use Illuminate\Support\Facades\DB;

/**
 * 1.0.10: fotocromaticele se împart în „Helio” (se închide doar afară) și „Xtractive” (se închide și în mașină,
 * potrivit pentru condus) — după indicațiile Optoplus din 2026-11-05 — plus regulile pentru ramele fără ramă,
 * cu lentilele prinse prin găurire.
 *
 * Doar DB (fără modele): rulează și din migrare, cu codul versiunii vechi în memorie (vezi CLAUDE.md).
 * Idempotent. Nu suprascrie textele modificate deja de echipă în panou (se schimbă doar dacă au rămas cele livrate).
 * Prețurile și culorile Xtractive NU se inventează: tratamentul se adaugă la lentile ca „inactiv”, cu preț 0,
 * iar laboratorul îl activează din panou după ce completează prețul și culorile.
 */
final class PhotochromicSplit
{
    public const HELIO_OLD_BENEFITS = 'Lentilele se închid la culoare afară, în lumina soarelui, și redevin aproape transparente în interior. O singură pereche pentru interior și exterior.';

    public static function run(): void
    {
        DB::transaction(fn () => self::apply());
    }

    private static function apply(): void
    {
        $now = now();
        $helio = DB::table('lens_treatments')->where('code', 'fotocromatic')->first();
        if (! $helio) {
            return; // instalare nouă, înainte de seedere
        }
        if (in_array($helio->name, ['Fotocromatic', 'Fotocromatic Helio'], true)) {
            DB::table('lens_treatments')->where('id', $helio->id)->update([
                'name' => 'Fotocromatic Helio',
                'benefits' => in_array($helio->benefits, [self::HELIO_OLD_BENEFITS, null, ''], true)
                    ? 'Lentilele se închid la culoare afară, la lumina soarelui, și redevin aproape transparente în interior. O singură pereche pentru interior și exterior.'
                    : $helio->benefits,
                'limits' => 'Nu se închid în mașină: parbrizul oprește razele UV care le activează, deci nu țin loc de ochelari de soare la volan (pentru condus, alegeți Xtractive). Viteza de închidere și de deschidere depinde de temperatură. Nu se pot monta pe ramele fără ramă, cu lentilele prinse prin găurire.',
                'updated_at' => $now,
            ]);
        }

        $x = DB::table('lens_treatments')->where('code', 'transitions-xtractive')->first();
        $xData = [
            'kind' => 'fotocromatic_condus', 'requires_option' => true, 'sort' => 4,
            'brand_note' => 'Transitions și XTRActive sunt mărci ale Transitions Optical. Prețurile și culorile se confirmă de laborator.',
            'updated_at' => $now,
        ];
        if (! $x) {
            DB::table('lens_treatments')->insert($xData + ['code' => 'transitions-xtractive', 'is_confirmed' => false, 'is_active' => true, 'created_at' => $now,
                'name' => 'Fotocromatic Xtractive (și pentru condus)']);
            $x = DB::table('lens_treatments')->where('code', 'transitions-xtractive')->first();
        }
        if (empty($x->benefits)) {
            $xData += [
                'name' => 'Fotocromatic Xtractive (și pentru condus)',
                'benefits' => 'Se închid la culoare afară, la soare, și se închid și în mașină, în spatele parbrizului, deci sunt potrivite și pentru condus. În interior se deschid la culoare.',
                'limits' => 'Cât de mult se închid în mașină și afară depinde de lumină și de temperatură. Nu înlocuiesc ochelarii de soare polarizați. Pe ramele fără ramă, cu lentilele prinse prin găurire, specialistul confirmă dacă se pot monta.',
                'is_active' => true,
            ];
        }
        DB::table('lens_treatments')->where('id', $x->id)->update($xData);

        // Xtractive la aceleași lentile ca Helio, dar inactiv și fără preț până îl completează laboratorul.
        foreach (DB::table('lens_offering_treatments')->where('treatment_id', $helio->id)->pluck('offering_id') as $offeringId) {
            $exists = DB::table('lens_offering_treatments')->where('offering_id', $offeringId)->where('treatment_id', $x->id)->exists();
            if (! $exists) {
                DB::table('lens_offering_treatments')->insert([
                    'offering_id' => $offeringId, 'treatment_id' => $x->id, 'price' => 0, 'is_included' => false,
                    'option_codes' => json_encode([]), 'status' => 'inactiv', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Ochelarii de citit: fără niciun fotocromatic (cum era și în configuratorul vechi).
        $reading = DB::table('compatibility_rules')->where('name', 'Fără fotocromatic la ochelarii de citit')->first();
        if ($reading) {
            $cond = json_decode((string) $reading->conditions, true) ?: [];
            foreach ($cond as &$group) {
                foreach ($group as &$c) {
                    if (($c['field'] ?? null) === 'treatment' && ($c['op'] ?? null) === 'has') {
                        $c['value'] = array_values(array_unique(array_merge((array) $c['value'], ['fotocromatic', 'fotocromatic_condus'])));
                    }
                }
            }
            unset($group, $c);
            DB::table('compatibility_rules')->where('id', $reading->id)->update(['conditions' => json_encode($cond), 'updated_at' => $now]);
        }

        foreach ([
            ['Rame fără ramă (găurire): fără fotocromatic Helio', 'block', 15, ['fotocromatic'],
                'La ramele fără ramă, cu lentilele prinse prin găurire, nu putem folosi lentile fotocromatice Helio: lentilele pentru găurire sunt dintr-un material special. Puteți alege altă ramă sau renunța la Helio.',
                'Indicație Optoplus, 2026-11-05: lentilele pentru găurire sunt din alt material, incompatibil cu Helio.'],
            ['Rame fără ramă (găurire) + Xtractive: verificare specialist', 'require_review', 16, ['fotocromatic_condus'],
                'Pentru ramele fără ramă, cu lentilele prinse prin găurire, specialistul confirmă dacă Xtractive se poate monta pe rama aleasă.',
                'De confirmat de laborator dacă Xtractive există în materialul pentru găurire.'],
        ] as [$name, $effect, $priority, $treatment, $message, $source]) {
            if (DB::table('compatibility_rules')->where('name', $name)->exists()) {
                continue; // păstrăm eventualele modificări din panou
            }
            DB::table('compatibility_rules')->insert([
                'name' => $name, 'effect' => $effect, 'required_field' => null, 'priority' => $priority, 'is_active' => true,
                'conditions' => json_encode([[['field' => 'frame.rim_type', 'op' => 'eq', 'value' => 'rimless_screw'], ['field' => 'treatment', 'op' => 'has', 'value' => $treatment]]]),
                'message_customer' => $message, 'source' => $source, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
