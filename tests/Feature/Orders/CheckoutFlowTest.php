<?php

namespace Tests\Feature\Orders;

use App\Modules\Configurator\Models\Configuration;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use App\Modules\Production\Models\ProductionJob;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Fluxul complet de vizitator: configurare → coș → finalizare → pagina comenzii. */
class CheckoutFlowTest extends TestCase
{
    use BuildsOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->enableCheckout();
    }

    private function expectedTotal(int $frameId, Configuration $cfg): int
    {
        $q = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())], PricingContext::b2c(['shippingMethodCode' => 'curier']));

        return $q->totalGross;
    }

    public function test_frame_only_can_be_added_to_cart(): void
    {
        $frame = $this->frame(25000);
        $this->post('/cos/adauga/', ['varianta' => $frame->id])->assertRedirect('/cos/')->assertCookie('optoplus_cos');
        $this->get('/cos/')->assertOk()->assertSee('250,00')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_configured_glasses_become_an_order_with_full_snapshot(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $total = $this->expectedTotal($frame->id, $cfg);
        $this->get('/cos/')->assertOk()->assertSee('Ochelari configurați');

        $data = $this->checkoutData(['total_afisat' => $total]);
        $response = $this->post('/comanda/', $data);
        $order = Order::firstOrFail();
        $response->assertRedirect(); // spre pagina simulatorului (procesatorul)

        $this->assertSame($total, $order->total);
        $this->assertSame(2000, $order->shipping_total);
        $this->assertSame('noua', $order->commercial_status);
        $this->assertSame('in_asteptare', $order->payment_status);
        $this->assertSame('primita', $order->production_status);
        $this->assertMatchesRegularExpression('/^OP-\d{4}-\d{5}$/', $order->number);
        $item = $order->items()->first();
        $this->assertSame('configuration', $item->kind);
        $this->assertSame($frame->id, $item->snapshot['frame']['variant_id']);
        $this->assertSame('monofocal-standard-150', $item->snapshot['lens']['offering_code']);
        $this->assertTrue($item->snapshot['lens']['made_in_house']);
        $this->assertTrue($item->snapshot['validation']['requires_review']);
        $this->assertArrayNotHasKey('values', $item->snapshot['prescription'], 'Valorile rețetei nu se copiază în clar în snapshot.');
        $this->assertSame($cfg->prescription_id, $item->prescription_id);
        $this->assertSame('in_comanda', $cfg->fresh()->status);
        $this->assertSame(1, ProductionJob::count());
        $this->assertSame('primita', ProductionJob::first()->stage);
        $this->get('/cos/')->assertSee('Coșul este gol');
    }

    public function test_double_submit_creates_a_single_order(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $data = $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg)]);
        $this->post('/comanda/', $data);
        $this->post('/comanda/', $data);
        $this->assertSame(1, Order::count());
    }

    public function test_order_is_not_placed_when_total_changed_since_display(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $data = $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg)]);
        $frame->update(['price' => 32000]); // prețul se schimbă între afișare și trimitere
        $this->post('/comanda/', $data)->assertSessionHasErrors('total');
        $this->assertSame(0, Order::count());
    }

    public function test_order_is_blocked_when_configuration_became_invalid(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $data = $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg)]);
        $this->offering('monofocal-standard-150')->update(['sph_min' => -100]); // matricea s-a restrâns
        $this->post('/comanda/', $data)->assertSessionHasErrors('cos');
        $this->assertSame(0, Order::count());
    }

    public function test_unavailable_payment_method_is_rejected(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $data = $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg), 'plata' => 'transfer_bancar']);
        $this->post('/comanda/', $data)->assertSessionHasErrors('plata');
    }

    public function test_return_from_processor_does_not_mark_order_paid(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg)]));
        $order = Order::firstOrFail();
        $this->get('/comanda/'.$order->uuid.'/?status=success&paid=1')->assertOk()->assertSee('Așteptăm confirmarea plății');
        $this->assertSame('in_asteptare', $order->fresh()->payment_status);
    }

    public function test_cash_on_delivery_order_shows_confirmation(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg), 'plata' => 'ramburs']))
            ->assertRedirect();
        $order = Order::firstOrFail();
        $this->get('/comanda/'.$order->uuid.'/')->assertOk()->assertSee('Am primit comanda');
        $this->assertSame('neplatita', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->analytics_purchase_sent_at);
        $first = $order->fresh()->analytics_purchase_sent_at;
        $this->get('/comanda/'.$order->uuid.'/')->assertDontSee('data-purchase', false);
        $this->assertEquals($first, $order->fresh()->analytics_purchase_sent_at);
    }

    public function test_another_visitor_cannot_open_the_order(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg), 'plata' => 'ramburs']));
        $order = Order::firstOrFail();
        $this->flushSession();
        $this->get('/comanda/'.$order->uuid.'/')->assertNotFound();
        $this->get(\App\Modules\Orders\Http\Controllers\CheckoutController::signedLink($order))->assertOk();
    }

    public function test_ordered_configuration_can_no_longer_be_edited(): void
    {
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $this->expectedTotal($frame->id, $cfg), 'plata' => 'ramburs']));
        $this->get($cfg->stepUrl(2))->assertStatus(410);
        $this->post("/configurator/{$cfg->uuid}/pas/2/", ['od' => ['sph' => '-5,00']])->assertStatus(410);
    }

    public function test_checkout_without_active_methods_explains_how_to_order(): void
    {
        \App\Modules\Shipping\Models\ShippingMethod::query()->update(['is_active' => false]);
        $this->post('/cos/adauga/', ['varianta' => $this->frame(25000)->id]);
        $this->get('/comanda/')->assertOk()->assertSee('Comenzile online nu sunt încă deschise');
    }
}
