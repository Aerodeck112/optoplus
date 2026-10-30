<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Lenses\Models\CompatibilityRule;
use App\Modules\Lenses\Models\LensFamily;
use App\Modules\Lenses\Models\LensMaterial;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Lenses\Models\LensOfferingTreatment;
use App\Modules\Lenses\Models\LensProduct;
use App\Modules\Lenses\Models\LensTreatment;
use App\Modules\Lenses\Models\LensTreatmentOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Lentilele, indicii, tratamentele și prețurile pe care le vinde azi configuratorul public (APF, grupul 21069,
 * citit pe 2026-10-29 — `database/content/configurator-actual-apf.json`), transformate în date editabile.
 *
 * Totul este marcat „neconfirmat”: intervalele APF (SPH ±6, CYL ±2,50, ADD 0,75–3,75) sunt limitele listelor din
 * formular, nu matricea de fabricație. Laboratorul le confirmă sau le corectează din panou (Lentile → Matrice).
 * Prețurile sunt cele publice actuale, considerate per pereche (de confirmat — docs/INTREBARI.md #4).
 *
 * Idempotent: rularea repetată actualizează aceleași înregistrări (după cod), nu creează duplicate.
 */
class CurrentConfiguratorSeeder extends Seeder
{
    private const SOURCE = 'Preluat din configuratorul public optoplus.ro (APF 21069), 2026-10-29 — neconfirmat';

    public function run(): void
    {
        DB::transaction(function () {
            $families = $this->families();
            $materials = $this->materials();
            $treatments = $this->treatments();
            $category = Category::updateOrCreate(['path' => '/lentile/'], [
                'kind' => 'lentile', 'name' => 'Lentile', 'slug' => 'lentile', 'sort' => 2,
                'intro' => 'Lentile oftalmice produse în laboratorul Optoplus, după rețeta ta.',
            ]);

            $products = [
                'monofocal-standard' => ['Lentile monofocale Standard', 'monofocal', 'standard', 'esential',
                    'Lentile cu o singură zonă de vedere, pentru distanță sau pentru citit. Varianta de bază, fără tratamente suplimentare.',
                    ['distanta', 'aproape', 'calculator']],
                'monofocal-premium' => ['Lentile monofocale Premium', 'monofocal', 'premium', 'echilibrat',
                    'Lentile cu o singură zonă de vedere, cu alegerea grosimii (indicelui) și a tratamentelor.',
                    ['distanta', 'aproape', 'calculator']],
                'progresive' => ['Lentile progresive', 'progresiv', null, 'avansat',
                    'O singură pereche pentru distanță, intermediar și aproape, fără linie vizibilă pe lentilă.',
                    ['toate']],
                'degresive-birou' => ['Lentile degresive (birou)', 'degresiv', null, 'echilibrat',
                    'Pentru citit și calculator, la birou sau în interior. Nu sunt destinate vederii la distanță.',
                    ['calculator', 'aproape']],
                'bifocale-round-top' => ['Lentile bifocale Round Top', 'bifocal', 'Round Top', 'esential',
                    'Două zone distincte: sus pentru distanță, jos un segment rotund pentru citit.',
                    ['toate']],
            ];

            $lp = [];
            foreach ($products as $slug => [$name, $family, $design, $tier, $summary, $usages]) {
                $product = Product::updateOrCreate(['slug' => 'lentile-'.$slug], [
                    'type' => Product::TYPE_LENS, 'name' => $name, 'status' => Product::STATUS_PUBLISHED,
                    'made_in_house' => true, 'sale_unit' => 'pair', 'availability_mode' => 'la_comanda',
                    'primary_category_id' => $category->id, 'published_at' => now(),
                    'short_description' => '<p>'.e($summary).'</p>',
                ]);
                $product->categories()->syncWithoutDetaching([$category->id]);
                LensProduct::updateOrCreate(['product_id' => $product->id], [
                    'family_id' => $families[$family]->id, 'design' => $design, 'supply_form' => 'montata',
                    'usages' => $usages, 'tier' => $tier, 'customer_summary' => $summary,
                    'required_measurements' => $family === 'progresiv' ? ['fitting_height'] : [],
                    'source' => self::SOURCE, 'is_confirmed' => false,
                ]);
                $lp[$slug] = $product;
            }

            // Bifocalele Flat Top apar în documentația primită, dar nu în configuratorul actual: ciornă, fără oferte.
            $flat = Product::updateOrCreate(['slug' => 'lentile-bifocale-flat-top'], [
                'type' => Product::TYPE_LENS, 'name' => 'Lentile bifocale Flat Top', 'status' => Product::STATUS_DRAFT,
                'made_in_house' => true, 'sale_unit' => 'pair', 'availability_mode' => 'neconfirmat',
                'primary_category_id' => $category->id,
                'short_description' => '<p>Două zone distincte: sus pentru distanță, jos un segment cu formă de semicerc turtit pentru citit.</p>',
            ]);
            LensProduct::updateOrCreate(['product_id' => $flat->id], [
                'family_id' => $families['bifocal']->id, 'design' => 'Flat Top', 'supply_form' => 'montata',
                'usages' => ['toate'], 'source' => 'Documentul „lentile bifocale si progresive + indice” (2026-10-29) — fără preț', 'is_confirmed' => false,
            ]);

            // Prețuri APF (lei): monofocal Standard 200; Premium 280 + grosime (0/120/250/400);
            // progresive și degresive 1020/1130/1340/1790; bifocal rotund 830/930/1040/1780.
            $offerings = [
                ['monofocal-standard', '150', 20000, [], null],
                ['monofocal-premium', '150', 28000, ['ar', 'blue', 'foto'], null],
                ['monofocal-premium', '160', 40000, ['ar', 'blue', 'foto'], null],
                ['monofocal-premium', '167', 53000, ['ar', 'blue', 'foto'], null],
                ['monofocal-premium', '174', 68000, ['ar', 'blue', 'foto'], null],
                ['progresive', '150', 102000, ['ar', 'blue', 'foto'], [75, 375]],
                ['progresive', '160', 113000, ['ar', 'blue', 'foto'], [75, 375]],
                ['progresive', '167', 134000, ['ar', 'blue', 'foto'], [75, 375]],
                ['progresive', '174', 179000, ['ar', 'blue', 'foto'], [75, 375]],
                ['degresive-birou', '150', 102000, ['ar', 'blue', 'foto'], [75, 375]],
                ['degresive-birou', '160', 113000, ['ar', 'blue', 'foto'], [75, 375]],
                ['degresive-birou', '167', 134000, ['ar', 'blue', 'foto'], [75, 375]],
                ['degresive-birou', '174', 179000, ['ar', 'blue', 'foto'], [75, 375]],
                ['bifocale-round-top', '150', 83000, ['ar', 'blue', 'foto'], [75, 375]],
                ['bifocale-round-top', '160', 93000, ['ar', 'blue', 'foto'], [75, 375]],
                ['bifocale-round-top', '167', 104000, ['ar', 'blue', 'foto'], [75, 375]],
                ['bifocale-round-top', '174', 178000, ['ar', 'blue', 'foto'], [75, 375]],
            ];
            // Culorile fotocromatice afișate de APF pe indici: 1.5 și 1.67 — maro/gri 65% și 85%; 1.6 și 1.74 — doar 85%.
            $photoOptions = [
                '150' => ['maro_65', 'maro_85', 'gri_65', 'gri_85'],
                '160' => ['maro_85', 'gri_85'],
                '167' => ['maro_65', 'maro_85', 'gri_65', 'gri_85'],
                '174' => ['maro_85', 'gri_85'],
            ];
            $treatmentPrices = ['ar' => 6000, 'blue' => 9000, 'foto' => 25000];

            foreach ($offerings as $i => [$slug, $mat, $price, $allowed, $add]) {
                $offering = LensOffering::updateOrCreate(['code' => $slug.'-'.$mat], [
                    'lens_product_id' => $lp[$slug]->id, 'material_id' => $materials[$mat]->id,
                    'sph_min' => -600, 'sph_max' => 600, 'cyl_min' => -250, 'cyl_max' => 0,
                    'add_min' => $add[0] ?? null, 'add_max' => $add[1] ?? null,
                    'price' => $price, 'requires_review' => true, 'status' => LensOffering::STATUS_UNCONFIRMED,
                    'source' => self::SOURCE, 'sort' => $i,
                ]);
                foreach ($allowed as $t) {
                    LensOfferingTreatment::updateOrCreate(
                        ['offering_id' => $offering->id, 'treatment_id' => $treatments[$t]->id],
                        ['price' => $treatmentPrices[$t], 'is_included' => false, 'status' => 'neconfirmat',
                            'option_codes' => $t === 'foto' ? $photoOptions[$mat] : null],
                    );
                }
            }

            $this->rules();
        });
    }

    /** @return array<string, LensFamily> */
    private function families(): array
    {
        $data = [
            'monofocal' => ['Monofocale', 'O singură zonă de vedere pe toată lentila: fie pentru distanță, fie pentru aproape (citit). Cea mai simplă și mai des folosită lentilă.', ['distanta', 'aproape', 'calculator'], false, false, 1],
            'progresiv' => ['Progresive', 'Vederea la distanță sus, la calculator în zona din mijloc și pentru citit jos, cu trecere treptată între ele și fără linie vizibilă. Necesită o perioadă de obișnuire.', ['toate'], true, true, 2],
            'degresiv' => ['Degresive (birou)', 'Pentru aproape și distanță medie (calculator, birou, interior). Câmp mai larg la citit și ecran decât progresivele, dar nu pentru condus sau privit departe.', ['calculator', 'aproape'], true, false, 3],
            'bifocal' => ['Bifocale', 'Două zone separate de o linie vizibilă: sus pentru distanță, jos un segment pentru citit. Fără zonă pentru distanța medie.', ['toate'], true, false, 4],
        ];
        $out = [];
        foreach ($data as $code => [$name, $desc, $usages, $add, $height, $sort]) {
            $out[$code] = LensFamily::updateOrCreate(['code' => $code], [
                'name' => $name, 'customer_description' => $desc, 'usages' => $usages,
                'requires_add' => $add, 'requires_fitting_height' => $height, 'sort' => $sort, 'is_active' => true,
            ]);
        }

        return $out;
    }

    /** @return array<string, LensMaterial> */
    private function materials(): array
    {
        $data = [
            '150' => [1500, 'Indice 1,50 (standard)', 'Grosime standard. Potrivit în special pentru dioptrii mici.', true],
            '156' => [1560, 'Indice 1,56', 'Puțin mai subțire decât 1,50. Descris în documentația primită; nu este vândut azi în configurator.', false],
            '160' => [1600, 'Indice 1,60 (subțiat)', 'Mai subțire și mai ușor decât 1,50; potrivit și pentru rame pe fir sau cu prindere în capse.', true],
            '167' => [1670, 'Indice 1,67 (subțiere avansată)', 'Grosime redusă vizibil, pentru dioptrii medii și mari.', true],
            '174' => [1740, 'Indice 1,74 (subțiere maximă)', 'Cea mai mică grosime din gamă, pentru dioptrii mari.', true],
        ];
        $out = [];
        $sort = 0;
        foreach ($data as $code => [$index, $name, $desc, $active]) {
            $out[$code] = LensMaterial::updateOrCreate(['code' => $code], [
                'name' => $name, 'refractive_index' => $index, 'customer_description' => $desc,
                'is_active' => $active, 'is_confirmed' => false, 'sort' => $sort++,
                'datasheet_note' => 'Fără fișă tehnică primită',
            ]);
        }

        return $out;
    }

    /** @return array<string, LensTreatment> */
    private function treatments(): array
    {
        $ar = LensTreatment::updateOrCreate(['code' => 'antireflex-hidrofob'], [
            'name' => 'Antireflex super hidrofob', 'kind' => 'antireflex', 'sort' => 1, 'is_confirmed' => false, 'is_active' => true,
            'benefits' => 'Reduce reflexiile de pe lentile, deci mai puțină strălucire deranjantă (de exemplu de la farurile din trafic sau de la ecrane). Stratul hidrofob și oleofob face ca apa și urmele de grăsime să se prindă mai greu, iar lentilele se curăță mai ușor.',
            'limits' => 'Nu face lentilele imune la zgârieturi. Curățați-le cu laveta și soluția potrivite, nu cu șervețele de hârtie.',
        ]);
        $blue = LensTreatment::updateOrCreate(['code' => 'filtru-lumina-albastra'], [
            'name' => 'Filtru pentru lumina albastră', 'kind' => 'lumina_albastra', 'sort' => 2, 'is_confirmed' => false, 'is_active' => true,
            'benefits' => 'Filtrează o parte din lumina albastră emisă de ecrane și de soare. Mulți purtători îl aleg pentru lucrul îndelungat la calculator.',
            'limits' => 'Procentul filtrat depinde de produs și se confirmă din fișa tehnică. Filtrul nu tratează și nu previne afecțiuni ale ochilor; pentru disconfort vizual, consultați un medic oftalmolog. Lentilele pot avea o ușoară reflexie colorată.',
        ]);
        $photo = LensTreatment::updateOrCreate(['code' => 'fotocromatic'], [
            'name' => 'Fotocromatic', 'kind' => 'fotocromatic', 'sort' => 3, 'requires_option' => true, 'is_confirmed' => false, 'is_active' => true,
            'benefits' => 'Lentilele se închid la culoare afară, în lumina soarelui, și redevin aproape transparente în interior. O singură pereche pentru interior și exterior.',
            'limits' => 'Se închid sub acțiunea razelor UV, deci în general mai puțin în spatele parbrizului. Viteza de închidere și de deschidere depinde de temperatură și de produs. Nu înlocuiesc ochelarii de soare polarizați.',
        ]);
        LensTreatment::updateOrCreate(['code' => 'transitions-xtractive'], [
            'name' => 'Transitions XTRActive®', 'kind' => 'fotocromatic', 'sort' => 4, 'requires_option' => true,
            'is_confirmed' => false, 'is_active' => false, 'brand_note' => 'Marcă înregistrată a unui producător terț; de confirmat dacă este comercializată',
        ]);

        $options = [
            ['maro_65', 'Maro 65%', '#7a5236', true], ['maro_85', 'Maro 85%', '#5b3a24', true],
            ['gri_65', 'Gri 65%', '#6b6f72', true], ['gri_85', 'Gri 85%', '#45494c', true],
            ['verde', 'Verde', '#4f6b52', false], ['albastru', 'Albastru', '#4d6a8c', false], ['mov', 'Mov', '#6d5577', false],
        ];
        foreach ($options as $i => [$code, $label, $swatch, $active]) {
            LensTreatmentOption::updateOrCreate(['treatment_id' => $photo->id, 'code' => $code], [
                'label' => $label, 'swatch' => $swatch, 'is_active' => $active, 'sort' => $i,
            ]);
        }

        return ['ar' => $ar, 'blue' => $blue, 'foto' => $photo];
    }

    private function rules(): void
    {
        $rules = [
            ['Fără fotocromatic la ochelarii de citit', 'block', null,
                [[['field' => 'usage', 'op' => 'eq', 'value' => 'aproape'], ['field' => 'family', 'op' => 'eq', 'value' => 'monofocal'], ['field' => 'treatment', 'op' => 'has', 'value' => ['fotocromatic']]]],
                'Pentru ochelarii de citit nu oferim momentan lentile fotocromatice.', 10],
            ['Rame pe fir sau cu șuruburi: indice minim 1,60', 'require_review', null,
                [[['field' => 'frame.rim_type', 'op' => 'in', 'value' => ['semi_nylon', 'rimless_screw']], ['field' => 'index', 'op' => 'lt', 'value' => 1600]]],
                'Pentru rame pe fir sau cu prindere în șuruburi recomandăm lentile subțiate (1,60 sau mai mult). Specialistul verifică dacă indicele ales se poate monta.', 20],
            ['Dioptrii peste 3: recomandare de subțiere', 'warn', null,
                [[['field' => 'rx.max_power', 'op' => 'gt', 'value' => 300], ['field' => 'index', 'op' => 'eq', 'value' => 1500]]],
                'Pentru dioptrii peste 3, lentilele subțiate (1,60 sau mai mult) sunt mai subțiri și mai ușoare. Puteți păstra și varianta aleasă.', 30],
            ['Degresive: nu pentru distanță', 'warn', null,
                [[['field' => 'family', 'op' => 'eq', 'value' => 'degresiv'], ['field' => 'usage', 'op' => 'in', 'value' => ['distanta', 'toate']]]],
                'Lentilele degresive sunt pentru citit și calculator; nu sunt potrivite pentru condus sau pentru privit la distanță.', 40],
        ];
        foreach ($rules as [$name, $effect, $field, $conditions, $message, $priority]) {
            CompatibilityRule::updateOrCreate(['name' => $name], [
                'effect' => $effect, 'required_field' => $field, 'conditions' => $conditions,
                'message_customer' => $message, 'priority' => $priority, 'is_active' => true,
                'source' => 'Derivată din textele și condițiile configuratorului actual (APF) — de confirmat de laborator',
            ]);
        }
    }
}
