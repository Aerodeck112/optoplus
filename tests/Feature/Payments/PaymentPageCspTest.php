<?php

namespace Tests\Feature\Payments;

use Tests\TestCase;

/**
 * 1.0.19: browserul aplică „form-action” și redirecționării de după trimiterea formularului. Pagina de finalizare
 * trebuie să permită paginile de plată BT (test și producție) și Stripe; altfel „Plasează comanda” nu face nimic.
 * (Verificat și în Chrome: cu „form-action 'self'” navigarea spre BT este blocată.)
 */
class PaymentPageCspTest extends TestCase
{
    public function test_checkout_pages_allow_the_payment_pages_as_form_targets(): void
    {
        $csp = (string) $this->get('/cos/')->headers->get('Content-Security-Policy');
        preg_match('/form-action ([^;]+)/', $csp, $m);
        $targets = explode(' ', trim($m[1] ?? ''));
        foreach (["'self'", 'https://ecclients.btrl.ro', 'https://ecclients-sandbox.btrl.ro', 'https://checkout.stripe.com'] as $host) {
            $this->assertContains($host, $targets, "form-action trebuie să conțină {$host}");
        }
        $this->assertNotContains('*', $targets);
        $this->assertNotContains('https:', $targets);
    }

    public function test_bt_server_address_from_settings_is_allowed_too(): void
    {
        app(\App\Core\Settings\Settings::class)->set('payments.btipay.base_url_production', 'https://plati.exemplu-bt.invalid');
        $this->assertStringContainsString('https://plati.exemplu-bt.invalid', (string) $this->get('/cos/')->headers->get('Content-Security-Policy'));
    }
}
