<?php

namespace Tests\Feature\Production;

use App\Modules\Identity\Roles;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderStatus;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Services\ProductionFlow;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** O comandă cu rețetă nu intră în producție fără specialist optic și fără confirmarea comenzii. */
class ProductionFlowTest extends TestCase
{
    use BuildsOrders;

    private ProductionJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->enableCheckout();
        $cfg = $this->configureGlasses($this->frame(30000));
        $total = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())], PricingContext::b2c(['shippingMethodCode' => 'curier']))->totalGross;
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $total, 'plata' => 'ramburs']));
        $this->job = ProductionJob::firstOrFail();
    }

    public function test_production_staff_cannot_approve_for_production(): void
    {
        $user = $this->actingAsRole(Roles::PRODUCTIE);
        $flow = app(ProductionFlow::class);
        $flow->moveTo($this->job, 'verificare_reteta', $user);
        $this->expectException(ValidationException::class);
        $flow->moveTo($this->job, 'aprobata_productie', $user);
    }

    public function test_work_cannot_skip_specialist_approval(): void
    {
        $user = $this->actingAsRole(Roles::PRODUCTIE);
        app(OrderStatus::class)->commercial(Order::first(), 'confirmata');
        $this->expectException(ValidationException::class);
        app(ProductionFlow::class)->moveTo($this->job, 'in_lucru', $user);
    }

    public function test_approved_work_waits_for_order_confirmation(): void
    {
        $specialist = $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $flow = app(ProductionFlow::class);
        $job = $flow->moveTo($this->job, 'aprobata_productie', $specialist);
        $this->assertSame($specialist->id, $job->approved_by);
        try {
            $flow->moveTo($job, 'in_lucru', $specialist);
            $this->fail('Comanda neconfirmată nu trebuia să intre în lucru.');
        } catch (ValidationException) {
        }
        app(OrderStatus::class)->commercial(Order::first(), 'confirmata');
        $job = $flow->moveTo($job->fresh(), 'in_lucru', $specialist);
        $this->assertSame('in_lucru', $job->stage);
        $this->assertSame('in_lucru', Order::first()->production_status);
        $this->assertSame(3, $job->events()->count());
    }

    public function test_stale_version_is_rejected(): void
    {
        $user = $this->actingAsRole(Roles::PRODUCTIE);
        $this->expectException(ValidationException::class);
        app(ProductionFlow::class)->moveTo($this->job, 'verificare_reteta', $user, null, $this->job->version + 5);
    }

    public function test_remake_creates_linked_job_back_in_review(): void
    {
        $specialist = $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $new = app(ProductionFlow::class)->remake($this->job, 'eroare_productie', 'optoplus', 'Zgârietură', $specialist);
        $this->assertSame($this->job->id, $new->remake_of_id);
        $this->assertSame('verificare_reteta', $new->stage);
        $this->assertNull($new->approved_by);
    }
}
