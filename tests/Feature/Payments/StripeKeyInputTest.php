<?php

namespace Tests\Feature\Payments;

use App\Core\Settings\Settings;
use App\Modules\Identity\Roles;
use App\Modules\Payments\Gateways\StripeGateway;
use Tests\TestCase;

/** 1.0.27: cheile Stripe lipite cu spații, cheia restricționată rk_ și cheile puse în câmpul greșit. Date fictive. */
class StripeKeyInputTest extends TestCase
{
    public function test_keys_are_cleaned_and_misplaced_keys_are_explained(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->withRecentReauth();
        $s = app(Settings::class);

        $this->post('/panou/setari/plati', ['payments__stripe__secret_key' => " rk_live_FictivDemo1234567890\n", 'payments__stripe__webhook_secret' => 'whsec_FictivDemo1234567890 '])
            ->assertSessionHasNoErrors();
        $this->assertSame('rk_live_FictivDemo1234567890', $s->secret('payments.stripe.secret_key'));
        $this->assertSame('whsec_FictivDemo1234567890', $s->secret('payments.stripe.webhook_secret'));
        $this->assertNull(app(StripeGateway::class)->unavailableReason());
        $this->assertSame('live', app(StripeGateway::class)->mode());

        $this->post('/panou/setari/plati', ['payments__stripe__secret_key' => 'pk_live_FictivDemo1234567890'])->assertSessionHasErrors('payments.stripe.secret_key');
        $this->post('/panou/setari/plati', ['payments__stripe__webhook_secret' => 'sk_live_FictivDemo1234567890'])->assertSessionHasErrors('payments.stripe.webhook_secret');
        $this->assertSame('rk_live_FictivDemo1234567890', $s->secret('payments.stripe.secret_key'), 'cheia bună rămâne');

        // o cheie salvată înainte cu spații funcționează și ea
        $s->setSecret('payments.stripe.secret_key', "sk_live_FictivDemo1234567890 \n");
        $this->assertNull(app(StripeGateway::class)->unavailableReason());
    }
}
