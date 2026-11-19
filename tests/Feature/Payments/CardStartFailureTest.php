<?php

namespace Tests\Feature\Payments;

use App\Core\Notifications\StaffAlert;
use App\Core\Settings\Settings;
use App\Modules\Orders\Models\Order;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * 1.0.18: când BT refuză pornirea plății (ex. date de producție cu mediul „Test”), clientul vede „Plata nu a
 * reușit” cu reîncercare, echipa primește motivul pe email, iar Setări → Plăți arată ultima eroare. Date fictive.
 */
class CardStartFailureTest extends TestCase
{
    use BuildsOrders;

    public function test_bt_refusal_is_visible_to_customer_team_and_settings(): void
    {
        Notification::fake();
        Http::fake(['*register.do' => Http::response(['errorCode' => '5', 'errorMessage' => 'Access denied'])]);
        $this->seedLenses();
        $this->enableCheckout(['payments.card_gateway' => 'btipay', 'payments.btipay.username' => 'utilizator_fictiv_API', 'payments.btipay.environment' => 'test', 'payments.btipay.base_url_test' => 'https://ecclients-sandbox.btrl.ro']);
        app(Settings::class)->setSecret('payments.btipay.password', 'parola-fictiva');
        $cfg = $this->configureGlasses($this->frame(30000));
        $total = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())], PricingContext::b2c(['shippingMethodCode' => 'curier']))->totalGross;

        $response = $this->post('/comanda/', $this->checkoutData(['total_afisat' => $total, 'plata' => 'card']));
        $order = Order::firstOrFail();
        $response->assertRedirect();
        $this->assertSame('esuata', $order->payment_status);
        $payment = $order->payments()->firstOrFail();
        $this->assertStringContainsString('cod 5', (string) $payment->failure_reason);
        $this->assertStringContainsString('mediul Test', (string) $payment->failure_reason);

        $this->get('/comanda/'.$order->uuid.'/')->assertOk()->assertSee('Plata nu a reușit')->assertSee('Încearcă din nou plata');
        Notification::assertSentTo(new \Illuminate\Notifications\AnonymousNotifiable, StaffAlert::class, fn (StaffAlert $n) => str_contains($n->subject, $order->number) && str_contains(implode(' ', $n->lines), 'Access denied'));

        $this->actingAsRole(\App\Modules\Identity\Roles::MANAGER);
        $this->withRecentReauth();
        $this->get('/panou/setari/plati')->assertOk()->assertSee('Ultima încercare de plată cu cardul')->assertSee('Access denied');
    }
}
