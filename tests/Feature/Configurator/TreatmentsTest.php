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

/** 1.0.9: pasul „Tratamente”, nuanțele în comandă și administrarea tratamentelor din panou. */
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
        $foto = $this->treatmentId('fotocromatic');
        $html = $this->get("/configurator/{$cfg->uuid}/pas/4/")->assertOk()
            ->assertSee('Nu este disponibil pentru alegerea ta')
            ->assertSee('Pentru ochelarii de citit nu oferim momentan lentile fotocromatice')
            ->getContent();
        $this->assertMatchesRegularExpression('~<input[^>]*value="'.$foto.'"[^>]*disabled~', $html);
        // la ochelarii pentru distanță rămâne disponibil
        $cfg2 = $this->toStep4('distanta', 'monofocal-premium-160');
        $this->get("/configurator/{$cfg2->uuid}/pas/4/")->assertOk()->assertDontSee('Nu este disponibil pentru alegerea ta');
    }

    public function test_live_price_does_not_shout_for_missing_shade_but_submit_requires_it(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160');
        $foto = $this->treatmentId('fotocromatic');
        $json = $this->postJson("/configurator/{$cfg->uuid}/calcul/", ['pas' => 4, 'tratamente' => [$foto]])->assertOk()->json();
        $this->assertSame([], $json['errors']);
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$foto]])->assertSessionHasErrors();
    }

    public function test_shade_not_allowed_on_this_lens_is_rejected_by_the_server(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160'); // la 1.60: doar 85%
        $foto = $this->treatmentId('fotocromatic');
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$foto], 'optiune' => [$foto => 'maro_65']])->assertSessionHasErrors();
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$foto], 'optiune' => [$foto => 'maro_85']])->assertSessionHasNoErrors();
    }

    public function test_order_keeps_shade_name_for_the_lab_and_the_customer(): void
    {
        $cfg = $this->toStep4('distanta', 'monofocal-premium-160');
        $foto = $this->treatmentId('fotocromatic');
        $ar = $this->treatmentId('antireflex-hidrofob');
        $this->post("/configurator/{$cfg->uuid}/pas/4/", ['tratamente' => [$ar, $foto], 'optiune' => [$foto => 'gri_85']])->assertSessionHasNoErrors();
        $this->post("/configurator/{$cfg->uuid}/in-cos/", ['acord_reteta' => '1'])->assertRedirect('/cos/');
        $page = $this->get('/comanda/')->getContent();
        preg_match('/name="total_afisat" value="(\d+)"/', $page, $m);
        $this->post('/comanda/', $this->checkoutData(['plata' => 'ramburs', 'total_afisat' => $m[1]]))->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $snap = $order->items()->first()->snapshot;
        $this->assertSame('Gri 85%', collect($snap['treatments'])->firstWhere('treatment_id', $foto)['option_label']);
        $this->assertSame('Antireflex super hidrofob, Fotocromatic (Gri 85%)', TreatmentLine::format($snap['treatments']));
        // comenzile vechi (fără option_label) primesc numele după cod
        $this->assertSame('Fotocromatic (Maro 85%)', TreatmentLine::one(['treatment_id' => $foto, 'name' => 'Fotocromatic', 'option' => 'maro_85']));

        $this->actingAsRole(Roles::MANAGER);
        $this->get('/panou/comenzi/'.$order->uuid.'/')->assertSee('Fotocromatic (Gri 85%)')->assertDontSee('gri_85)');
    }

    public function test_panel_edits_treatment_texts_and_shades(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $foto = LensTreatment::where('code', 'fotocromatic')->with('options')->firstOrFail();
        $this->get('/panou/lentile/tratamente/')->assertOk()->assertSee('Fotocromatic')->assertSee('Maro 85%');
        $this->get('/panou/lentile/tratamente/'.$foto->id.'/')->assertOk();

        $rows = $foto->options->mapWithKeys(fn ($o) => [$o->id => ['eticheta' => $o->label, 'culoare' => $o->swatch, 'activ' => $o->is_active ? '1' : '0']])->all();
        $verde = $foto->options->firstWhere('code', 'verde');
        $rows[$verde->id]['activ'] = '1';
        $this->post('/panou/lentile/tratamente/'.$foto->id.'/', [
            'nume' => 'Fotocromatic', 'ce_face' => '<b>Text</b> nou de test, fictiv.', 'limite' => 'Limite de test.', 'activ' => '1', 'cu_nuanta' => '1', 'ordine' => 3,
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
        $fotoId = $this->treatmentId('fotocromatic');
        $ot = LensOfferingTreatment::where('offering_id', $o->id)->where('treatment_id', $fotoId)->firstOrFail();
        $base = ['sph_min' => '-6,00', 'sph_max' => '+6,00', 'cyl_min' => '-2,50', 'cyl_max' => '0', 'pret' => '400', 'stare' => 'neconfirmat', 'verificare' => '1'];

        $this->post('/panou/lentile/oferte/'.$o->id.'/', $base + ['t' => [$ot->id => ['pret' => '250', 'stare' => 'neconfirmat', 'nuante' => ['gri_85']]]])->assertSessionHasNoErrors();
        $this->assertSame(['gri_85'], $ot->fresh()->option_codes);

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
}
