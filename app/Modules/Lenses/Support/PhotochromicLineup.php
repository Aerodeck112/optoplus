<?php

namespace App\Modules\Lenses\Support;

use Illuminate\Support\Facades\DB;

/**
 * 1.0.16 — gama de lentile fotocromatice, după indicațiile Optoplus din 2026-11-13 (preț: 250 lei fiecare):
 *
 *  - Transitions: se închide la culoare până la 80%; verde, gri, maro; indicii 1,50, 1,60 și 1,74;
 *  - Xtractive: se închide și în mașină; verde, gri, maro; indicii 1,50, 1,60 și 1,67 (nu 1,74);
 *  - PhotoFast: albastru sau mov; doar indicele 1,60;
 *  - Heliomatic (fostul „Helio”, codul `fotocromatic`): se închide 50–60%; doar indicele 1,56 (azi nu există
 *    lentile 1,56 în configurator, deci nu apare la client până nu se adaugă; culorile nu sunt confirmate).
 *
 * Plus regula corectată pentru ramele fără contur (prindere prin găurire): nu se pot monta lentile cu indicele
 * 1,50 sau 1,56; toate celelalte combinații (inclusiv fotocromaticele pe 1,60, 1,67, 1,74) sunt permise.
 *
 * Doar DB (rulează din migrare, cu codul versiunii vechi în memorie — vezi CLAUDE.md). Idempotent. Nu suprascrie
 * ce a schimbat echipa în panou: textele se schimbă doar dacă sunt cele livrate, prețul noilor rânduri se preia
 * de la rândul fotocromatic existent al aceleiași lentile. Nimic nu se șterge (comenzile vechi păstrează istoria):
 * ce nu mai e valabil devine inactiv.
 */
final class PhotochromicLineup
{
    public const PRICE = 25000;

    /** Indicii (×1000) pe care se oferă fiecare tratament. */
    public const INDICES = [
        'transitions' => [1500, 1600, 1740],
        'transitions-xtractive' => [1500, 1600, 1670],
        'photofast' => [1600],
        'fotocromatic' => [1560],
    ];

    /** cod tratament => [cod culoare => [etichetă, bulină]] */
    public const COLORS = [
        'transitions' => ['verde' => ['Verde', '#4f6b52'], 'gri' => ['Gri', '#4a4f55'], 'maro' => ['Maro', '#6b4a2f']],
        'transitions-xtractive' => ['verde' => ['Verde', '#4f6b52'], 'gri' => ['Gri', '#4a4f55'], 'maro' => ['Maro', '#6b4a2f']],
        'photofast' => ['albastru' => ['Albastru', '#4d6a8c'], 'mov' => ['Mov', '#6d5577']],
    ];

    public const TRANSITIONS = [
        'code' => 'transitions', 'kind' => 'fotocromatic', 'sort' => 3,
        'name' => 'Fotocromatic Transitions',
        'benefits' => 'Lentilele se închid la culoare afară, la soare, până la aproximativ 80%, și redevin aproape transparente în interior. O singură pereche pentru interior și exterior.',
        'limits' => 'În mașină se închid puțin: parbrizul oprește o mare parte din razele UV care le activează (pentru condus, alegeți Xtractive). Viteza de închidere și de deschidere depinde de lumină și de temperatură.',
        'brand_note' => 'Transitions este marcă a Transitions Optical.',
    ];

    public const PHOTOFAST = [
        'code' => 'photofast', 'kind' => 'fotocromatic', 'sort' => 5,
        'name' => 'Fotocromatic PhotoFast',
        'benefits' => 'Lentile fotocromatice în culori la modă, albastru sau mov: se închid la culoare afară, la soare, și redevin aproape transparente în interior.',
        'limits' => 'Disponibile doar cu lentilele cu indicele 1,60. Nu se închid în mașină (parbrizul oprește razele UV). Cât de mult și cât de repede se închid depinde de lumină și de temperatură.',
        'brand_note' => null,
    ];

    public const HELIO_LIMITS_1011 = 'Nu se închid în mașină: parbrizul oprește razele UV care le activează, deci nu țin loc de ochelari de soare la volan (pentru condus, alegeți Xtractive). Viteza de închidere și de deschidere depinde de temperatură. Nu se pot monta pe ramele fără contur, cu lentilele prinse prin găurire.';

    public const HELIO_BENEFITS_1010 = 'Lentilele se închid la culoare afară, la lumina soarelui, și redevin aproape transparente în interior. O singură pereche pentru interior și exterior.';

    public const XTRACTIVE_LIMITS_1011 = 'Cât de mult se închid în mașină și afară depinde de lumină și de temperatură. Nu înlocuiesc ochelarii de soare polarizați. Nu se pot monta pe ramele fără contur, cu lentilele prinse prin găurire.';

    public const XTRACTIVE_BENEFITS_1010 = 'Se închid la culoare afară, la soare, și se închid și în mașină, în spatele parbrizului, deci sunt potrivite și pentru condus. În interior se deschid la culoare.';

    public const RIMLESS_RULE = 'Rame fără contur (găurire): fără indicii 1,50 și 1,56';

    public const RIMLESS_MESSAGE = 'La ramele fără contur, cu lentilele prinse prin găurire, nu se pot monta lentile cu indicele 1,50 sau 1,56. Alegeți o lentilă cu indicele 1,60, 1,67 sau 1,74.';

    public const NYLON_MESSAGE_OLD = 'Pentru rame pe fir sau cu prindere în șuruburi recomandăm lentile subțiate (1,60 sau mai mult). Specialistul verifică dacă indicele ales se poate monta.';

    public const HOME_SLIDE_OLD = 'Lentile fotocromatice Helio și Xtractive';

    public const HOME_SLIDE = [
        'badge' => 'Lentile fotocromatice',
        'title' => 'Lentile care se închid la soare',
        'text' => 'Transitions se închide afară până la 80%. Xtractive se închide și în mașină, deci e potrivit pentru condus. PhotoFast vine în albastru sau mov.',
    ];

    /** @return array<string, mixed> ce s-a schimbat (pentru jurnal și teste) */
    public static function run(): array
    {
        if (! DB::table('lens_treatments')->where('code', 'fotocromatic')->exists()) {
            return []; // instalare nouă, înainte de seedere
        }
        $out = DB::transaction(fn () => self::apply());
        $out['home'] = self::homeSlide();

        return $out;
    }

    private static function apply(): array
    {
        $now = now();
        $out = [];
        $helio = DB::table('lens_treatments')->where('code', 'fotocromatic')->first();
        $x = DB::table('lens_treatments')->where('code', 'transitions-xtractive')->first();

        // Lentilele care au avut până acum fotocromatic (Helio): pe ele se pun noile variante, după indice.
        $photoRows = DB::table('lens_offering_treatments as ot')
            ->join('lens_offerings as o', 'o.id', '=', 'ot.offering_id')
            ->join('lens_materials as m', 'm.id', '=', 'o.material_id')
            ->where('ot.treatment_id', $helio->id)
            ->get(['ot.offering_id', 'ot.price', 'ot.status', 'm.refractive_index']);

        // 1. Heliomatic (fostul Helio): doar pe 1,56.
        $helioUpdate = ['sort' => 6, 'updated_at' => $now];
        if (in_array($helio->name, ['Fotocromatic', 'Fotocromatic Helio'], true)) {
            $helioUpdate['name'] = 'Fotocromatic Heliomatic';
        }
        if (in_array($helio->benefits, [self::HELIO_BENEFITS_1010, null, ''], true)) {
            $helioUpdate['benefits'] = 'Lentilele se închid la culoare afară, la soare, în proporție de 50–60%, și redevin aproape transparente în interior.';
        }
        if (in_array($helio->limits, [self::HELIO_LIMITS_1011, str_replace('fără contur', 'fără ramă', self::HELIO_LIMITS_1011)], true)) {
            $helioUpdate['limits'] = 'Disponibile doar cu lentilele cu indicele 1,56. Nu se închid în mașină (parbrizul oprește razele UV). Viteza de închidere și de deschidere depinde de temperatură.';
        }
        DB::table('lens_treatments')->where('id', $helio->id)->update($helioUpdate);
        $out['heliomatic_dezactivat'] = self::limitToIndices($helio->id, self::INDICES['fotocromatic'], $now);

        // 2. Transitions și PhotoFast: tratamente noi, pe lentilele cu fotocromatic de la indicii lor.
        foreach ([self::TRANSITIONS, self::PHOTOFAST] as $def) {
            $id = DB::table('lens_treatments')->where('code', $def['code'])->value('id');
            if (! $id) {
                $id = DB::table('lens_treatments')->insertGetId([
                    'code' => $def['code'], 'name' => $def['name'], 'kind' => $def['kind'], 'benefits' => $def['benefits'],
                    'limits' => $def['limits'], 'brand_note' => $def['brand_note'], 'requires_option' => true,
                    'is_confirmed' => false, 'is_active' => true, 'sort' => $def['sort'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                $out['tratamente_noi'][] = $def['code'];
            }
            self::colors($id, self::COLORS[$def['code']], $now);
            $out['randuri_noi'][$def['code']] = self::addRows($id, $photoRows, self::INDICES[$def['code']], array_keys(self::COLORS[$def['code']]), $now);
        }

        // 3. Xtractive: verde, gri, maro; pe 1,50, 1,60, 1,67 (nu pe 1,74).
        $xUpdate = ['updated_at' => $now];
        if ($x->limits === self::XTRACTIVE_LIMITS_1011) {
            $xUpdate['limits'] = 'Cât de mult se închid în mașină și afară depinde de lumină și de temperatură. Nu înlocuiesc ochelarii de soare polarizați. Nu sunt disponibile cu lentilele cu indicele 1,74.';
        }
        if ($x->benefits === self::XTRACTIVE_BENEFITS_1010) {
            $xUpdate['benefits'] = 'Lentilele se închid la culoare afară, la soare, și se închid și în mașină, în spatele parbrizului. În interior se deschid la culoare.';
        }
        if ($x->brand_note === 'Transitions și XTRActive sunt mărci ale Transitions Optical. Prețurile și culorile se confirmă de laborator.') {
            $xUpdate['brand_note'] = 'Transitions și XTRActive sunt mărci ale Transitions Optical.';
        }
        DB::table('lens_treatments')->where('id', $x->id)->update($xUpdate);
        self::colors($x->id, self::COLORS['transitions-xtractive'], $now);
        DB::table('lens_treatment_options')->where('treatment_id', $x->id)->whereIn('code', ['albastru', 'mov'])->update(['is_active' => false, 'updated_at' => $now]);
        $out['randuri_noi']['transitions-xtractive'] = self::addRows($x->id, $photoRows, self::INDICES['transitions-xtractive'], array_keys(self::COLORS['transitions-xtractive']), $now);
        foreach (DB::table('lens_offering_treatments')->where('treatment_id', $x->id)->get() as $row) {
            $codes = json_decode((string) $row->option_codes, true);
            if (is_array($codes) && array_diff($codes, ['verde', 'gri', 'maro'])) {
                DB::table('lens_offering_treatments')->where('id', $row->id)
                    ->update(['option_codes' => json_encode(array_values(array_intersect($codes, ['verde', 'gri', 'maro'])) ?: ['verde', 'gri', 'maro']), 'updated_at' => $now]);
            }
        }
        $out['xtractive_dezactivat'] = self::limitToIndices($x->id, self::INDICES['transitions-xtractive'], $now);

        // 4. Ramele fără contur (găurire): fără 1,50 / 1,56, în locul interdicției fotocromaticelor.
        DB::table('compatibility_rules')->where('name', 'Rame fără contur (găurire): fără fotocromatic')->update(['is_active' => false, 'updated_at' => $now]);
        DB::table('compatibility_rules')->where('name', 'Rame fără ramă (găurire): fără fotocromatic Helio')->update(['is_active' => false, 'updated_at' => $now]);
        if (! DB::table('compatibility_rules')->where('name', self::RIMLESS_RULE)->exists()) {
            DB::table('compatibility_rules')->insert([
                'name' => self::RIMLESS_RULE, 'effect' => 'block', 'required_field' => null,
                'conditions' => json_encode([[['field' => 'frame.rim_type', 'op' => 'eq', 'value' => 'rimless_screw'], ['field' => 'index', 'op' => 'lt', 'value' => 1600]]]),
                'message_customer' => self::RIMLESS_MESSAGE,
                'message_staff' => 'Rame cu prindere prin găurire: lentilele 1,50 și 1,56 nu se montează (indicație Optoplus, 2026-11-13).',
                'priority' => 15, 'is_active' => true, 'source' => 'Indicație Optoplus, 2026-11-13.', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $out['regula_noua'] = self::RIMLESS_RULE;
        }
        // Regula „rame pe fir sau cu șuruburi: verificare” rămâne doar pentru ramele pe fir (găurirea are acum blocare).
        $nylon = DB::table('compatibility_rules')->where('name', 'Rame pe fir sau cu șuruburi: indice minim 1,60')->first();
        if ($nylon && $nylon->message_customer === self::NYLON_MESSAGE_OLD) {
            DB::table('compatibility_rules')->where('id', $nylon->id)->update([
                'name' => 'Rame pe fir: indice minim 1,60',
                'conditions' => json_encode([[['field' => 'frame.rim_type', 'op' => 'in', 'value' => ['semi_nylon']], ['field' => 'index', 'op' => 'lt', 'value' => 1600]]]),
                'message_customer' => 'Pentru rame pe fir recomandăm lentile subțiate (1,60 sau mai mult). Specialistul verifică dacă indicele ales se poate monta.',
                'updated_at' => $now,
            ]);
        }

        return $out;
    }

    /** Culorile tratamentului: le creează pe cele lipsă și le activează. */
    private static function colors(int $treatmentId, array $colors, $now): void
    {
        $sort = 0;
        foreach ($colors as $code => [$label, $swatch]) {
            $q = DB::table('lens_treatment_options')->where('treatment_id', $treatmentId)->where('code', $code);
            if ($q->exists()) {
                $q->update(['is_active' => true, 'sort' => $sort, 'updated_at' => $now]);
            } else {
                DB::table('lens_treatment_options')->insert(['treatment_id' => $treatmentId, 'code' => $code, 'label' => $label,
                    'swatch' => $swatch, 'is_active' => true, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now]);
            }
            $sort++;
        }
    }

    /** Adaugă tratamentul la lentilele cu fotocromatic de la indicii dați (prețul și starea rândului fotocromatic existent). */
    private static function addRows(int $treatmentId, $photoRows, array $indices, array $colors, $now): int
    {
        $n = 0;
        foreach ($photoRows as $row) {
            if (! in_array((int) $row->refractive_index, $indices, true)) {
                continue;
            }
            if (DB::table('lens_offering_treatments')->where('offering_id', $row->offering_id)->where('treatment_id', $treatmentId)->exists()) {
                continue;
            }
            DB::table('lens_offering_treatments')->insert([
                'offering_id' => $row->offering_id, 'treatment_id' => $treatmentId,
                'price' => (int) $row->price > 0 ? (int) $row->price : self::PRICE, 'is_included' => false,
                'option_codes' => json_encode($colors), 'status' => $row->status === 'inactiv' ? 'neconfirmat' : $row->status,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $n++;
        }

        return $n;
    }

    /** Rândurile tratamentului de pe alți indici devin inactive. */
    private static function limitToIndices(int $treatmentId, array $indices, $now): int
    {
        $ids = DB::table('lens_offering_treatments as ot')
            ->join('lens_offerings as o', 'o.id', '=', 'ot.offering_id')
            ->join('lens_materials as m', 'm.id', '=', 'o.material_id')
            ->where('ot.treatment_id', $treatmentId)->where('ot.status', '!=', 'inactiv')
            ->whereNotIn('m.refractive_index', $indices)->pluck('ot.id');
        if ($ids->isNotEmpty()) {
            DB::table('lens_offering_treatments')->whereIn('id', $ids)->update(['status' => 'inactiv', 'updated_at' => $now]);
        }

        return $ids->count();
    }

    /**
     * Bannerul „Helio și Xtractive” din prima pagină: versiune nouă cu gama actuală. Publicată doar dacă pagina nu a
     * fost editată de echipă (altfel rămâne ciornă, de verificat în panou). Doar DB.
     */
    private static function homeSlide(): ?string
    {
        return DB::transaction(function () {
            $page = DB::table('cms_pages')->where('path', '/')->lockForUpdate()->first();
            $baseId = $page ? ($page->published_version_id ?? $page->current_version_id) : null;
            $base = $baseId ? DB::table('cms_page_versions')->where('id', $baseId)->first() : null;
            if (! $base) {
                return null;
            }
            $blocks = json_decode((string) $base->blocks, true) ?: [];
            $changed = false;
            foreach ($blocks as &$block) {
                if (($block['type'] ?? null) !== 'banner_carousel') {
                    continue;
                }
                foreach ($block['data']['slides'] ?? [] as $i => $slide) {
                    if (($slide['title'] ?? null) === self::HOME_SLIDE_OLD) {
                        $block['data']['slides'][$i] = array_merge($slide, self::HOME_SLIDE);
                        $changed = true;
                    }
                }
            }
            unset($block);
            if (! $changed) {
                return 'neschimbat';
            }
            $now = now();
            $untouched = ! DB::table('cms_page_versions')->where('page_id', $page->id)->whereNotNull('created_by')->exists()
                && $page->current_version_id === $page->published_version_id;
            $id = DB::table('cms_page_versions')->insertGetId([
                'page_id' => $page->id, 'number' => (int) DB::table('cms_page_versions')->where('page_id', $page->id)->max('number') + 1,
                'title' => $base->title, 'seo' => $base->seo, 'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'note' => 'Actualizare 1.0.16: bannerul cu lentilele fotocromatice', 'created_by' => null, 'created_at' => $now,
                'published_at' => $untouched ? $now : null,
            ]);
            $update = ['current_version_id' => $id, 'lock_version' => $page->lock_version + 1, 'updated_at' => $now];
            if ($untouched) {
                $update += ['published_version_id' => $id, 'status' => 'published'];
            }
            DB::table('cms_pages')->where('id', $page->id)->update($update);

            return $untouched ? 'publicat' : 'ciornă';
        });
    }
}
