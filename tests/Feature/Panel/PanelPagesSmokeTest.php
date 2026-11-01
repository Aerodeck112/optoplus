<?php

namespace Tests\Feature\Panel;

use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\PartnershipRequest;
use App\Modules\B2B\Models\Quote;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Crm\Models\Lead;
use App\Modules\Identity\Roles;
use App\Modules\Lenses\Models\CompatibilityRule;
use App\Modules\Lenses\Models\LensOffering;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** Fiecare ecran de panou se deschide pentru managerul care are dreptul și refuză rolurile fără drept. */
class PanelPagesSmokeTest extends TestCase
{
    use BuildsCatalog;

    public function test_manager_opens_every_panel_screen(): void
    {
        $this->seedLenses();
        $frame = $this->frame(30000);
        Brand::create(['name' => 'Marcă Fictivă', 'slug' => 'marca-fictiva']);
        $org = Organization::create(['name' => 'Clinica Test', 'kind' => 'clinica', 'status' => 'aprobata']);
        PartnershipRequest::create(['company_name' => 'Optica Test', 'kind' => 'magazin_optica', 'contact_name' => 'Ion Test', 'email' => 'p@demo.optoplus.invalid']);
        $quote = Quote::create(['number' => 'OF-T-1', 'organization_id' => $org->id, 'status' => 'ceruta', 'request_text' => 'Cerere de test fictivă']);
        $lead = Lead::create(['source' => 'contact', 'name' => 'Client Fictiv']);
        $this->actingAsRole([Roles::MANAGER, Roles::SPECIALIST_OPTIC]);
        foreach ([
            '/panou', '/panou/comenzi', '/panou/productie', '/panou/productie/de-verificat',
            '/panou/parteneri', '/panou/parteneri/cereri', '/panou/parteneri/'.$org->id, '/panou/parteneri/oferte', '/panou/parteneri/oferte/'.$quote->id, '/panou/parteneri/sesizari',
            '/panou/crm/cereri', '/panou/crm/cereri/'.$lead->id, '/panou/crm/sarcini',
            '/panou/catalog/produse', '/panou/catalog/produse/'.$frame->product_id,
            '/panou/lentile', '/panou/lentile/oferte/'.LensOffering::first()->id, '/panou/lentile/reguli', '/panou/lentile/reguli/noua', '/panou/lentile/reguli/'.CompatibilityRule::first()->id,
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_offering_update_requires_confirm_permission_to_activate(): void
    {
        $this->seedLenses();
        $o = LensOffering::where('status', 'neconfirmat')->firstOrFail();
        $this->actingAsRole(Roles::MANAGER); // manager: lenses.edit fără lenses.confirm?
        $payload = ['sph_min' => '−6,00', 'sph_max' => '+6,00', 'cyl_min' => '-2,50', 'cyl_max' => '0', 'pret' => '210,00', 'stare' => 'activ'];
        $res = $this->post('/panou/lentile/oferte/'.$o->id, $payload);
        if (auth()->user()->can('lenses.confirm')) {
            $res->assertSessionHasNoErrors();
            $this->assertSame(21000, $o->fresh()->price);
            $this->assertSame(-600, $o->fresh()->sph_min);
        } else {
            $res->assertSessionHasErrors('stare');
        }
    }

    public function test_rule_with_unknown_field_is_rejected(): void
    {
        $this->seedLenses();
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/lentile/reguli', ['nume' => 'x', 'efect' => 'warn', 'conditii' => '[[{"field":"nu_exista","op":"eq","value":1}]]', 'mesaj_client' => 'x', 'prioritate' => 10])
            ->assertSessionHasErrors('conditii');
    }

    public function test_content_editor_cannot_touch_prices_or_lenses(): void
    {
        $this->seedLenses();
        $frame = $this->frame(30000);
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->post('/panou/catalog/produse/'.$frame->product_id.'/variante', ['v' => [$frame->id => ['pret' => '1,00']]])->assertForbidden();
        $this->post('/panou/lentile/oferte/'.LensOffering::first()->id, [])->assertForbidden();
        $this->get('/panou/parteneri')->assertForbidden();
    }
}
