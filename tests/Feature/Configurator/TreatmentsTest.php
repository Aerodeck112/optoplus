<?php

namespace Tests\Feature\Configurator;

use App\Modules\Configurator\Models\Configuration;
use App\Modules\Identity\Roles;
use App\Modules\Lenses\Models\LensOffering;
use App\Modules\Lenses\Models\LensOfferingTreatment;
use App\Modules\Lenses\Models\LensTreatment;
use App\Modules\Lenses\Support\TreatmentLine;
use App\Modules\Orders\Models\Order;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** 1.0.9: pasul „Tratamente”, nuanțele în comandă și administrarea tratamentelor din panou; 1.0.16: gama fotocromatică. */
class TreatmentsTest extends TestCase
{
    use BuildsOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->enableCheckout();
    }

    /** Configurația fără ramă până la pasul 4, cu utilizarea și lentila date. */
    private function toStep4(string $usage, string $offering): Configuration
    {
        $this->post('/configurator/start/', ['intrare' => 'fara_rama'])->assertRedirect();
        $cfg = Configuration::latest('id')->firstOrFail();
        $base = "/configurator/{$cfg->uuid}/pas/";
        $this->post($base.'1/', ['utilizare' => $usage, 'ochi' => 'ambii'])->assertSessionHasNoErrors();
        $this->post($base.'2/', ['od' => ['sph' => '-2,00'], 'os' => ['sph' => '-1,75'], 'dp_mod' => 'binocular', 'dp' => '63'])->assertSessionHasNoErrors();
        $this->post($base.'3/', ['oferta' => $this->offering($offering)->id])->assertSessionHasNoErrors();

        return $cfg->fresh();
    }

    public function test_photochromic_is_shown_disabled_for_reading_glasses(): void
    {
        $cfg = $this->toStep4('aproape', 'monofocal-premium-160');
        $foto = $this->treatmentId('transitions');
        $html = $this->get("/configurator/{$cfg->uuid}/pas/4/")->assertOk()
            ->assertSee('Nu este disponibil pentru alegerea ta')
            ->assertSee('Pentru ochelarii de citit nu oferim momentan lentile fotocromatice')
            ->getContent();
        $this->assertMatchesRegularExpression('~<input[^>]*value="'.$foto.'"[^>]*disabled~', $html);
        // la ochelarii pentru distanță rămâne disponibil
        $cfg2 = $this->toStep4('distanta', 'monofocal-premium-160');
        $this->get("/configurator/{$cfg2->uuid}/pas/4/")->assertOk()->assertDontSee('Nu este disponibil pentru alegerea ta');
    }

    public function test_every_treatment_adds_its_price_to_the_live_total_and_is_clearly_selectable(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160');
        $html = $this->get("/configurator/{$cfg->uuid}/pas/4/")->assertOk()->getContent();
        // 1.0.13: cardul întreg se poate apăsa și arată „Adaugă” / „Adăugat” lângă preț
        $this->assertStringContainsString('data-tx-card', $html);
        $this->assertStringContainsString('tx-state__on', $html);

        $base = $this->postJson("/configurator/{$cfg->uuid}/calcul/", ['pas' => 4])->assertOk()->json('total');
        $toLei = fn (string $t) => (int) round((float) str_replace(['.', ',', ' lei'], ['', '.', ''], $t) * 100);
        foreach (['antireflex-hidrofob', 'filtru-lumina-albastra'] as $code) {
            $id = $this->treatmentId($code);
            $price = (int) \Illuminate\Support\Facades\DB::table('lens_offering_treatments')->where('offering_id', $cfg->lens_offering_id)->where('treatment_id', $id)->value('price');
            $this->assertGreaterThan(0, $price);
            $with = $this->postJson("/configurator/{$cfg->uuid}/calcul/", ['pas' => 4, 'tratamente' => [$id]])->json('total');
            $this->assertSame($toLei($base) + $price, $toLei($with), "prețul tratamentului {$code} intră în total");
        }
    }

    public function test_live_price_does_not_shout_for_missing_shade_but_submit_requires_it(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160');
        $foto = $this->treatmentId('transitions');
        $json = $this->postJson("/configurator/{$cfg->uuid}/calcul/", ['pas' => 4, 'tratamente' => [$foto]])->assertOk()->json();
        $this->assertSame([], $json['errors']);
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$foto]])->assertSessionHasErrors();
    }

    public function test_shade_not_allowed_on_this_lens_is_rejected_by_the_server(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160'); // Transitions: verde, gri, maro; PhotoFast: albastru, mov
        $foto = $this->treatmentId('transitions');
        $fast = $this->treatmentId('photofast');
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$foto], 'optiune' => [$foto => 'mov']])->assertSessionHasErrors();
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$fast], 'optiune' => [$fast => 'gri']])->assertSessionHasErrors();
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$fast], 'optiune' => [$fast => 'mov']])->assertSessionHasNoErrors();
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$foto], 'optiune' => [$foto => 'maro']])->assertSessionHasNoErrors();
    }

    public function test_order_keeps_shade_name_for_the_lab_and_the_customer(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160');
        $foto = $this->treatmentId('transitions');
        $ar = $this->treatmentId('antireflex-hidrofob');
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$ar, $foto], 'optiune' => [$foto => 'gri']])->assertSessionHasNoErrors();
        $this->post("/configurator/{$cfg->uuid}/in-cos/", ['acord_reteta' => '1'])->assertRedirect('/cos/');
        $page = $this->get('/comanda/')->getContent();
        preg_match('/name="total_afisat" value="(\d+)"/', $page, $m);
        $this->post('/comanda/', $this->checkoutData(['plata' => 'ramburs', 'total_afisat' => $m[1]]))->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $snap = $order->items()->first()->snapshot;
        $this->assertSame('Gri', collect($snap['treatments'])->firstWhere('treatment_id', $foto)['option_label']);
        $this->assertSame('Antireflex super hidrofob, Fotocromatic Transitions (Gri)', TreatmentLine::format($snap['treatments']));
        // comenzile vechi (fără option_label) primesc numele după cod, și pentru culorile Helio de dinainte
        $helio = $this->treatmentId('fotocromatic');
        $this->assertSame('Fotocromatic (Maro 85%)', TreatmentLine::one(['treatment_id' => $helio, 'name' => 'Fotocromatic', 'option' => 'maro_85']));

        $this->actingAsRole(Roles::MANAGER);
        $this->get('/panou/comenzi/'.$order->uuid.'/')->assertSee('Fotocromatic Transitions (Gri)')->assertDontSee('(gri)');
    }

    public function test_panel_edits_treatment_texts_and_shades(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $foto = LensTreatment::where('code', 'fotocromatic')->with('options')->firstOrFail();
        $this->get('/panou/lentile/tratamente/')->assertOk()->assertSee('Fotocromatic Heliomatic')->assertSee('Xtractive')->assertSee('PhotoFast')->assertSee('Maro 85%');
        $this->get('/panou/lentile/tratamente/'.$foto->id.'/')->assertOk();

        $rows = $foto->options->mapWithKeys(fn ($o) => [$o->id => ['eticheta' => $o->label, 'culoare' => $o->swatch, 'activ' => $o->is_active ? '1' : '0']])->all();
        $verde = $foto->options->firstWhere('code', 'verde');
        $rows[$verde->id]['activ'] = '1';
        $this->post('/panou/lentile/tratamente/'.$foto->id.'/', [
            'nume' => 'Fotocromatic Helio', 'ce_face' => '<b>Text</b> nou de test, fictiv.', 'limite' => 'Limite de test.', 'activ' => '1', 'cu_nuanta' => '1', 'ordine' => 3,
            'nuante' => $rows, 'nuanta_noua' => ['eticheta' => 'Gri 75% test', 'culoare' => '#555555'],
        ])->assertSessionHasNoErrors();
        $foto->refresh();
        $this->assertSame('Text nou de test, fictiv.', $foto->benefits, 'fără HTML');
        $this->assertTrue($foto->options()->where('code', 'verde')->value('is_active'));
        $this->assertTrue($foto->options()->where('label', 'Gri 75% test')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'lenses.treatment_updated', 'subject_id' => $foto->id]);
    }

    public function test_panel_sets_allowed_shades_per_lens_and_adds_treatment(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $o = LensOffering::where('code', 'monofocal-premium-160')->firstOrFail();
        $standard = LensOffering::where('code', 'monofocal-standard-150')->firstOrFail();
        $fotoId = $this->treatmentId('transitions');
        $ot = LensOfferingTreatment::where('offering_id', $o->id)->where('treatment_id', $fotoId)->firstOrFail();
        $base = ['sph_min' => '-6,00', 'sph_max' => '+6,00', 'cyl_min' => '-2,50', 'cyl_max' => '0', 'pret' => '400', 'stare' => 'neconfirmat', 'verificare' => '1'];

        $this->post('/panou/lentile/oferte/'.$o->id.'/', $base + ['t' => [$ot->id => ['pret' => '250', 'stare' => 'neconfirmat', 'nuante' => ['gri']]]])->assertSessionHasNoErrors();
        $this->assertSame(['gri'], $ot->fresh()->option_codes);

        $this->post('/panou/lentile/oferte/'.$o->id.'/', $base + ['t' => [$ot->id => ['pret' => '250', 'stare' => 'neconfirmat', 'nuante' => []]]])->assertSessionHasErrors();

        $this->post('/panou/lentile/oferte/'.$standard->id.'/', array_merge($base, ['pret' => '200', 'adauga' => ['tratament' => $this->treatmentId('antireflex-hidrofob'), 'pret' => '60']]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lens_offering_treatments', ['offering_id' => $standard->id, 'treatment_id' => $this->treatmentId('antireflex-hidrofob'), 'price' => 6000, 'status' => 'neconfirmat']);
    }

    public function test_sales_role_can_view_but_not_edit_treatments(): void
    {
        $this->actingAsRole(Roles::VANZARI);
        $foto = $this->treatmentId('fotocromatic');
        $this->get('/panou/lentile/tratamente/')->assertOk();
        $this->post('/panou/lentile/tratamente/'.$foto.'/', ['nume' => 'X', 'ce_face' => 'x', 'limite' => 'x'])->assertForbidden();
    }

    private function xtractiveId(): int
    {
        return LensTreatment::where('code', 'transitions-xtractive')->value('id');
    }

    /** @return array<int, list<string>> indicele lentilei => codurile fotocromaticelor active pe ea */
    private function photochromicByIndex(): array
    {
        $rows = LensOfferingTreatment::with('offering.material', 'treatment')->where('status', '!=', 'inactiv')->get()
            ->filter(fn ($r) => $r->treatment->is_active && str_starts_with((string) $r->treatment->kind, 'fotocromatic'));
        $out = [];
        foreach ($rows as $r) {
            $out[$r->offering->material->refractive_index][] = $r->treatment->code;
        }
        ksort($out);

        return array_map(function ($c) { $c = array_values(array_unique($c)); sort($c); return $c; }, $out);
    }

    public function test_photochromic_lineup_follows_the_lab_indices_colors_and_price(): void
    {
        // 2026-11-13: Transitions 1,50/1,60/1,74; Xtractive 1,50/1,60/1,67; PhotoFast 1,60; Heliomatic doar 1,56 (lipsă azi).
        $this->assertSame([
            1500 => ['transitions', 'transitions-xtractive'],
            1600 => ['photofast', 'transitions', 'transitions-xtractive'],
            1670 => ['transitions-xtractive'],
            1740 => ['transitions'],
        ], $this->photochromicByIndex());
        $this->assertTrue(LensOfferingTreatment::whereIn('treatment_id', [$this->treatmentId('transitions'), $this->treatmentId('photofast'), $this->xtractiveId()])
            ->where('status', '!=', 'inactiv')->get()->every(fn ($r) => $r->price === 25000));
        $colors = fn (string $code) => LensTreatment::where('code', $code)->firstOrFail()->options()->where('is_active', true)->orderBy('sort')->pluck('label')->all();
        $this->assertSame(['Verde', 'Gri', 'Maro'], $colors('transitions'));
        $this->assertSame(['Verde', 'Gri', 'Maro'], $colors('transitions-xtractive'));
        $this->assertSame(['Albastru', 'Mov'], $colors('photofast'));
        $this->assertSame('Fotocromatic Heliomatic', LensTreatment::where('code', 'fotocromatic')->value('name'));
        $this->assertStringContainsString('50–60%', LensTreatment::where('code', 'fotocromatic')->value('benefits'));

        // a doua rulare (migrare după seeder) nu dublează și nu schimbă nimic
        $before = LensOfferingTreatment::count();
        \App\Modules\Lenses\Support\PhotochromicLineup::run();
        $this->assertSame($before, LensOfferingTreatment::count());
        $this->assertSame(1, \App\Modules\Lenses\Models\CompatibilityRule::where('name', \App\Modules\Lenses\Support\PhotochromicLineup::RIMLESS_RULE)->count());
    }

    public function test_step4_groups_all_photochromic_variants_in_one_card(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160');
        $html = $this->get("/configurator/{$cfg->uuid}/pas/4/")->assertOk()
            ->assertSeeInOrder(['Lentile fotocromatice', '+250,00 lei', 'Pentru fiecare zi', 'Transitions', 'Și pentru condus', 'Xtractive', 'Culori la modă', 'PhotoFast'])
            ->assertSee('data-group="1"', false)->assertDontSee('Heliomatic')->getContent();
        $this->assertSame(1, substr_count($html, 'data-photo-group'));
        $this->assertSame(1, substr_count($html, 'data-explainer="photo"'), 'o singură ilustrație pentru toate variantele');
        $this->assertSame(1, substr_count($html, '+250,00 lei'), 'prețul comun, o singură dată');
        $this->assertSame(3, substr_count($html, 'data-exclusive="fotocromatic"'));

        // la 1,74 doar Transitions; Xtractive și PhotoFast apar ca „se pot alege cu alt indice”
        $cfg = $this->toStep4('distanta', 'monofocal-premium-174');
        $this->get("/configurator/{$cfg->uuid}/pas/4/")->assertOk()->assertSee('Transitions')
            ->assertSee('Fotocromatic Xtractive (și pentru condus): se poate alege cu lentilele cu indicele 1,50, 1,60, 1,67')
            ->assertSee('Fotocromatic PhotoFast: se poate alege cu lentilele cu indicele 1,60')
            ->assertDontSee('name="optiune['.$this->xtractiveId().']"', false);
    }

    public function test_only_one_photochromic_can_be_chosen(): void
    {
        $xId = $this->xtractiveId();
        $cfg = $this->toStep4('distanta', 'monofocal-premium-150');
        $tr = $this->treatmentId('transitions');
        $this->get("/configurator/{$cfg->uuid}/pas/4/")->assertSee('În mașină')->assertSee('data-exclusive="fotocromatic"', false);
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$tr, $xId], 'optiune' => [$tr => 'gri', $xId => 'gri']])->assertSessionHasErrors();
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$xId], 'optiune' => [$xId => 'verde']])->assertSessionHasNoErrors();
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$tr], 'optiune' => [$tr => 'maro']])->assertSessionHasNoErrors();
        // variantele care nu există pe 1,50 sunt respinse de server
        foreach (['photofast' => 'mov', 'fotocromatic' => 'gri_85'] as $code => $opt) {
            $id = $this->treatmentId($code);
            $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$id], 'optiune' => [$id => $opt]])->assertSessionHasErrors();
        }
    }

    public function test_rimless_drilled_frame_takes_no_150_or_156_lenses_but_photochromic_is_fine(): void
    {
        $frame = $this->frame(30000, [], ['rim_type' => 'rimless_screw']);
        $this->post('/configurator/start/', ['intrare' => 'cu_rama', 'varianta' => $frame->id])->assertRedirect();
        $cfg = Configuration::latest('id')->firstOrFail();
        $base = "/configurator/{$cfg->uuid}/pas/";
        $this->post($base.'1/', ['utilizare' => 'distanta'])->assertSessionHasNoErrors();
        $this->post($base.'2/', ['od' => ['sph' => '-2,00'], 'os' => ['sph' => '-1,75'], 'dp_mod' => 'binocular', 'dp' => '63'])->assertSessionHasNoErrors();

        // pasul 3: lentilele 1,50 apar indisponibile, cu motivul
        $html = $this->get($base.'3/')->assertOk()->assertSee('nu se pot monta lentile cu indicele 1,50 sau 1,56')->getContent();
        $this->assertMatchesRegularExpression('~<input[^>]*value="'.$this->offering('monofocal-premium-150')->id.'"[^>]*disabled~', $html);
        $this->post($base.'3/', ['oferta' => $this->offering('monofocal-premium-150')->id])->assertSessionHasErrors();

        // 1,60 și 1,67: se pot monta, inclusiv cu fotocromatic
        foreach (['monofocal-premium-160' => 'transitions', 'monofocal-premium-167' => 'transitions-xtractive'] as $offering => $code) {
            $this->post($base.'3/', ['oferta' => $this->offering($offering)->id])->assertSessionHasNoErrors();
            $id = $this->treatmentId($code);
            $this->get($base.'4/')->assertOk()->assertDontSee('Nu este disponibil pentru alegerea ta');
            $this->post($base.'4/', ['tratamente' => [$id], 'optiune' => [$id => 'gri']])->assertSessionHasNoErrors();
        }
    }

    public function test_reading_glasses_block_both_photochromic_variants(): void
    {
        $xId = $this->xtractiveId();
        $cfg = $this->toStep4('aproape', 'monofocal-premium-150');
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$xId], 'optiune' => [$xId => 'gri']])->assertSessionHasErrors();
    }

    public function test_treatment_cannot_be_activated_on_a_lens_without_price(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $o = LensOffering::where('code', 'monofocal-premium-150')->firstOrFail();
        $ot = LensOfferingTreatment::where('offering_id', $o->id)->where('treatment_id', $this->xtractiveId())->firstOrFail();
        $base = ['sph_min' => '-6,00', 'sph_max' => '+6,00', 'cyl_min' => '-2,50', 'cyl_max' => '0', 'pret' => '280', 'stare' => 'neconfirmat', 'verificare' => '1'];
        $this->post('/panou/lentile/oferte/'.$o->id.'/', $base + ['t' => [$ot->id => ['pret' => '0', 'stare' => 'neconfirmat', 'nuante' => ['gri']]]])->assertSessionHasErrors();
        $this->assertSame(25000, $ot->fresh()->price);
    }
}
