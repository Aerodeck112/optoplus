<?php

namespace Tests\Feature\Panel;

use App\Core\Audit\AuditLog;
use App\Modules\Identity\Roles;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Production\Models\ProductionJob;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Comenzile și producția în panou: permisiuni, acțiuni, audit, rețeta vizibilă doar cu permisiune. */
class OrderPanelTest extends TestCase
{
    use BuildsOrders;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->enableCheckout();
        $cfg = $this->configureGlasses($this->frame(30000));
        $total = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())], PricingContext::b2c(['shippingMethodCode' => 'curier']))->totalGross;
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $total, 'plata' => 'ramburs']));
        $this->order = Order::firstOrFail();
        $this->flushSession();
    }

    public function test_sales_sees_order_without_prescription_values(): void
    {
        $this->actingAsRole(Roles::VANZARI);
        $this->get('/panou/comenzi')->assertOk()->assertSee($this->order->number);
        $this->get('/panou/comenzi/'.$this->order->uuid)->assertOk()->assertSee('Confirmă comanda')->assertDontSee('−2,00');
        $this->get('/panou/productie/'.ProductionJob::first()->id)->assertOk()->assertSee('Nu aveți permisiunea de a vedea rețetele')->assertDontSee('−2,00');
    }

    public function test_specialist_sees_prescription_and_view_is_audited(): void
    {
        $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $this->get('/panou/productie/'.ProductionJob::first()->id)->assertOk()->assertSee('−2,00');
        $this->assertTrue(AuditLog::where('action', 'prescription.viewed')->exists());
    }

    public function test_content_editor_has_no_access_to_orders(): void
    {
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->get('/panou/comenzi')->assertForbidden();
        $this->get('/panou/productie')->assertForbidden();
    }

    public function test_confirm_then_specialist_approves_and_production_starts(): void
    {
        $this->actingAsRole(Roles::VANZARI);
        $this->post('/panou/comenzi/'.$this->order->uuid.'/confirma')->assertRedirect();
        $this->assertSame('confirmata', $this->order->fresh()->commercial_status);

        $job = ProductionJob::first();
        $this->actingAsRole(Roles::PRODUCTIE);
        $this->post('/panou/productie/'.$job->id.'/etapa', ['etapa' => 'aprobata_productie', 'versiune' => $job->version, 'verificat' => '1'])->assertForbidden();

        $specialist = $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $this->post('/panou/productie/'.$job->id.'/etapa', ['etapa' => 'aprobata_productie', 'versiune' => $job->version])->assertSessionHasErrors('verificat');
        $this->post('/panou/productie/'.$job->id.'/etapa', ['etapa' => 'aprobata_productie', 'versiune' => $job->version, 'verificat' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($specialist->id, $job->fresh()->approved_by);
        $this->assertSame($specialist->id, $job->item->prescription->fresh()->verified_by);
    }

    public function test_cash_on_delivery_confirmation_requires_reauth_and_evidence(): void
    {
        $payment = $this->order->payments()->first();
        $this->actingAsRole(Roles::VANZARI);
        $this->post('/panou/comenzi/'.$this->order->uuid.'/plati/'.$payment->id.'/confirma', ['nota' => 'chitanța 12'])->assertRedirect(route('panou.reauth'));
        $this->withRecentReauth()->post('/panou/comenzi/'.$this->order->uuid.'/plati/'.$payment->id.'/confirma', ['nota' => ''])->assertSessionHasErrors('nota');
        $this->withRecentReauth()->post('/panou/comenzi/'.$this->order->uuid.'/plati/'.$payment->id.'/confirma', ['nota' => 'Încasat de curier, borderou 55'])->assertSessionHasNoErrors();
        $this->assertSame('platita', $this->order->fresh()->payment_status);
        $this->assertTrue(AuditLog::where('action', 'payment.confirmed_manual')->exists());
    }

    public function test_shipment_marks_production_shipped_and_cancel_needs_reason(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/comenzi/'.$this->order->uuid.'/livrare', ['curier' => 'manual', 'awb' => 'AWB123', 'stare' => 'predata'])->assertSessionHasNoErrors();
        $this->assertSame('expediata', $this->order->fresh()->production_status);
        $this->withRecentReauth()->post('/panou/comenzi/'.$this->order->uuid.'/anuleaza', ['motiv' => ''])->assertSessionHasErrors('motiv');
    }

    public function test_prescription_file_requires_permission(): void
    {
        $p = ProductionJob::first()->item->prescription;
        $file = $p->files()->create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'disk_path' => 'x.bin', 'mime' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('0', 64), 'created_at' => now()]);
        $this->actingAsRole(Roles::VANZARI);
        $this->get('/panou/retete/fisier/'.$file->uuid)->assertForbidden();
    }
}
