<?php

namespace Tests\Feature\Customers;

use App\Core\Audit\AuditLog;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Customers\Models\Consent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Notifications\CustomerResetPassword;
use App\Modules\Customers\Services\LegacyPasswordVerifier;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Conturile clienților: autentificare (inclusiv parole WordPress), izolare, date personale. Date fictive. */
class CustomerAccountTest extends TestCase
{
    private function customer(array $attrs = [], ?string $password = 'parola-test-1234'): Customer
    {
        $c = new Customer(array_merge(['email' => 'client'.Str::random(6).'@demo.optoplus.invalid', 'first_name' => 'Ana', 'last_name' => 'Testescu'], $attrs));
        $c->forceFill(['password' => $password])->save();

        return $c;
    }

    public function test_phpass_reference_vector_and_wordpress_bcrypt_are_accepted(): void
    {
        $v = new LegacyPasswordVerifier;
        // Vectorul de test din distribuția phpass (openwall).
        $this->assertTrue($v->check('test12345', '$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'));
        $this->assertFalse($v->check('test12346', '$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'));
        // WordPress ≥ 6.8: „$wp” + bcrypt peste HMAC-SHA384 (base64).
        $wp = '$wp'.password_hash(base64_encode(hash_hmac('sha384', 'Parolă veche!', 'wp-sha384', true)), PASSWORD_BCRYPT);
        $this->assertTrue($v->check('Parolă veche!', $wp));
        $this->assertFalse($v->check('alta', $wp));
    }

    public function test_migrated_customer_logs_in_with_old_password_which_is_rehashed(): void
    {
        $c = $this->customer([], null);
        $c->forceFill(['legacy_password_hash' => '$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'])->save();
        $this->post('/cont/autentificare/', ['email' => $c->email, 'parola' => 'test12345'])->assertRedirect('/cont/');
        $this->assertAuthenticatedAs($c, 'client');
        $c->refresh();
        $this->assertNull($c->legacy_password_hash);
        $this->assertTrue(Hash::check('test12345', $c->password));
        $this->assertGuest('web');
    }

    public function test_wrong_password_gives_generic_message_and_is_throttled(): void
    {
        $c = $this->customer();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/cont/autentificare/', ['email' => $c->email, 'parola' => 'gresita'])->assertSessionHasErrors(['email' => 'Emailul sau parola nu sunt corecte.']);
        }
        $this->post('/cont/autentificare/', ['email' => $c->email, 'parola' => 'parola-test-1234'])->assertSessionHasErrors('email');
        $this->assertGuest('client');
    }

    public function test_registration_records_consents_separately(): void
    {
        $this->post('/cont/inregistrare/', [
            'prenume' => 'Ion', 'nume' => 'Fictivescu', 'email' => 'ion.nou@demo.optoplus.invalid',
            'parola' => 'o-parola-lunga', 'parola_confirmation' => 'o-parola-lunga', 'acord_termeni' => '1',
        ])->assertRedirect('/cont/');
        $c = Customer::where('email', 'ion.nou@demo.optoplus.invalid')->firstOrFail();
        $this->assertNull($c->marketing_consent_at, 'Marketingul nu se bifează implicit.');
        $this->assertTrue(Consent::where('customer_id', $c->id)->where('type', 'termeni')->exists());
        $this->assertFalse(Consent::where('customer_id', $c->id)->where('type', 'marketing')->exists());
    }

    public function test_registration_with_existing_email_does_not_reveal_the_account(): void
    {
        Notification::fake();
        $c = $this->customer();
        $this->post('/cont/inregistrare/', [
            'prenume' => 'X', 'nume' => 'Y', 'email' => $c->email, 'parola' => 'o-parola-lunga', 'parola_confirmation' => 'o-parola-lunga', 'acord_termeni' => '1',
        ])->assertRedirect('/cont/autentificare/')->assertSessionHas('status');
        $this->assertGuest('client');
        Notification::assertSentTo($c, CustomerResetPassword::class);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $c = $this->customer();
        $this->post('/cont/parola/', ['email' => $c->email])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($c, CustomerResetPassword::class, function ($n) use (&$token, $c) {
            $token = (fn () => $this->token)->call($n);

            return str_contains($n->toMail($c)->actionUrl, '/cont/parola/');
        });
        $this->post('/cont/parola/reset/', ['token' => $token, 'email' => $c->email, 'parola' => 'parola-noua-123', 'parola_confirmation' => 'parola-noua-123'])
            ->assertRedirect('/cont/autentificare/');
        $this->assertTrue(Hash::check('parola-noua-123', $c->fresh()->password));
    }

    public function test_account_pages_require_customer_login(): void
    {
        $this->get('/cont/')->assertRedirect('/cont/autentificare/');
        $this->get('/cont/retete/')->assertRedirect('/cont/autentificare/');
        $this->actingAsRole('administrator');
        $this->get('/cont/')->assertRedirect('/cont/autentificare/'); // personalul nu are cont de client
    }

    public function test_return_url_cannot_point_to_another_site(): void
    {
        $c = $this->customer();
        $this->get('/cont/autentificare/?inapoi=//evil.example/x')->assertOk();
        $this->post('/cont/autentificare/', ['email' => $c->email, 'parola' => 'parola-test-1234'])->assertRedirect('/cont/');
    }

    public function test_customer_sees_only_own_orders_and_configurations(): void
    {
        $a = $this->customer();
        $b = $this->customer();
        $orderB = Order::create([
            'number' => 'OP-2026-99999', 'uuid' => (string) Str::uuid(), 'customer_id' => $b->id, 'email' => $b->email,
            'payment_method' => 'ramburs', 'subtotal' => 1000, 'total' => 1000, 'billing_address' => [], 'idempotency_key' => Str::random(40), 'pricing_version' => '1.0',
        ]);
        $cfgB = Configuration::create(['uuid' => (string) Str::uuid(), 'customer_id' => $b->id, 'entry' => 'fara_rama']);
        $this->actingAs($a, 'client');
        $this->get('/cont/')->assertOk()->assertDontSee('OP-2026-99999');
        $this->get('/comanda/'.$orderB->uuid.'/')->assertNotFound();
        $this->get($cfgB->stepUrl(1))->assertNotFound();
        $this->post('/cont/configuratii/'.$cfgB->uuid.'/sterge/')->assertNotFound();
        $this->assertNotNull($cfgB->fresh());
    }

    public function test_data_export_is_audited_and_contains_prescriptions(): void
    {
        $c = $this->customer();
        \App\Modules\Configurator\Models\Prescription::create(['customer_id' => $c->id, 'values' => ['od' => ['sph' => -200], 'os' => ['sph' => -150]]]);
        $this->actingAs($c, 'client');
        $res = $this->get('/cont/date/export/')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(-200, $res->json('retete.0.valori.od.sph'));
        $this->assertTrue(AuditLog::where('action', 'customer.data_exported')->exists());
        $this->get('/cont/retete/')->assertOk()->assertSee('−2,00');
        $this->assertTrue(AuditLog::where('action', 'prescription.viewed')->where('customer_id', $c->id)->exists());
    }

    public function test_login_claims_guest_configurations(): void
    {
        $c = $this->customer();
        $cfg = Configuration::create(['uuid' => (string) Str::uuid(), 'entry' => 'fara_rama']);
        $this->withSession(['configuratii' => [$cfg->uuid]])
            ->post('/cont/autentificare/', ['email' => $c->email, 'parola' => 'parola-test-1234']);
        $this->assertSame($c->id, $cfg->fresh()->customer_id);
    }
}
