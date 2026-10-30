<?php

namespace Tests\Feature\Payments;

use App\Modules\Identity\Roles;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Gateways\SimulationGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentEvent;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/** Plata se confirmă numai din notificări semnate, o singură dată, cu suma corectă. */
class PaymentNotificationTest extends TestCase
{
    use BuildsOrders;

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        $this->enableCheckout();
        $frame = $this->frame(30000);
        $cfg = $this->configureGlasses($frame);
        $total = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())], PricingContext::b2c(['shippingMethodCode' => 'curier']))->totalGross;
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $total]));
        $this->order = Order::firstOrFail();
        $this->payment = $this->order->payments()->firstOrFail();
    }

    private function notify(array $body, ?string $signature = null)
    {
        $json = json_encode($body);

        return $this->call('POST', '/webhook/plati/simulare', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIMULARE_SEMNATURA' => $signature ?? SimulationGateway::sign($json),
        ], $json);
    }

    private function paid(string $eventId = 'evt_1', ?int $amount = null): array
    {
        return ['event_id' => $eventId, 'reference' => $this->payment->provider_reference, 'status' => 'paid', 'amount' => $amount ?? $this->payment->amount];
    }

    public function test_signed_notification_marks_order_paid_and_confirmed(): void
    {
        $this->assertSame('in_asteptare', $this->payment->status);
        $this->notify($this->paid())->assertOk();
        $order = $this->order->fresh();
        $this->assertSame('platita', $order->payment_status);
        $this->assertSame('confirmata', $order->commercial_status);
        $this->assertSame($order->total, $order->paid_total);
        $this->assertTrue($order->events()->where('axis', 'payment')->where('to_status', 'platita')->where('actor', 'webhook:simulare')->exists());
    }

    public function test_invalid_signature_is_recorded_but_changes_nothing(): void
    {
        $this->notify($this->paid(), str_repeat('0', 64))->assertStatus(400);
        $this->assertSame('in_asteptare', $this->order->fresh()->payment_status);
        $this->assertSame('respins', PaymentEvent::first()->outcome);
        $this->assertFalse(PaymentEvent::first()->signature_valid);
    }

    public function test_duplicate_notification_is_processed_once(): void
    {
        $this->notify($this->paid('evt_dup'))->assertOk();
        $this->notify($this->paid('evt_dup'))->assertOk();
        $this->notify($this->paid('evt_other'))->assertOk(); // alt eveniment pentru aceeași plată: ignorat, nu dublează suma
        $order = $this->order->fresh();
        $this->assertSame($order->total, $order->paid_total);
        $this->assertSame(2, PaymentEvent::count());
        $this->assertSame(1, $order->events()->where('axis', 'payment')->where('to_status', 'platita')->count());
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->notify($this->paid('evt_x', $this->payment->amount - 100))->assertStatus(400);
        $this->assertSame('in_asteptare', $this->order->fresh()->payment_status);
        $this->assertTrue($this->order->events()->where('axis', 'note')->exists());
    }

    public function test_unknown_reference_is_ignored(): void
    {
        $this->notify(['event_id' => 'evt_u', 'reference' => 'SIM-NOPE', 'status' => 'paid', 'amount' => 100])->assertOk();
        $this->assertSame('ignorat', PaymentEvent::first()->outcome);
    }

    public function test_failed_payment_allows_retry(): void
    {
        $this->notify(['event_id' => 'evt_f', 'reference' => $this->payment->provider_reference, 'status' => 'failed'])->assertOk();
        $this->assertSame('esuata', $this->order->fresh()->payment_status);
        $this->post('/comanda/'.$this->order->uuid.'/plateste/')->assertRedirect();
        $this->assertSame(2, $this->order->payments()->count());
    }

    public function test_simulator_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->assertFalse(app(SimulationGateway::class)->isConfigured());
        $this->notify($this->paid())->assertStatus(503);
        $this->assertSame('in_asteptare', $this->order->fresh()->payment_status);
    }

    public function test_card_payment_cannot_be_confirmed_manually(): void
    {
        $user = $this->actingAsRole(Roles::MANAGER);
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->confirmManual($this->payment, $user, 'test');
    }

    public function test_refund_updates_order_totals(): void
    {
        $this->notify($this->paid())->assertOk();
        $user = $this->actingAsRole(Roles::MANAGER);
        app(PaymentService::class)->refund($this->payment->fresh(), 5000, 'Retur ramă', $user);
        $order = $this->order->fresh();
        $this->assertSame(5000, $order->refunded_total);
        $this->assertSame('partial_rambursata', $order->payment_status);
    }
}
