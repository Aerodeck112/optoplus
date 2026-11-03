<?php

namespace Tests\Feature\Settings;

use App\Core\Settings\Settings;
use App\Models\User;
use App\Modules\B2B\Models\Organization;
use App\Modules\B2B\Models\OrganizationInvitation;
use App\Modules\B2B\Services\Invitations;
use App\Modules\Identity\Roles;
use App\Modules\Settings\Services\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** 1.0.7: serverul de email din Setări; o problemă de email nu mai blochează crearea conturilor. */
class MailSettingsTest extends TestCase
{
    /** Un server SMTP la care nu se poate conecta (portul 1 local), ca trimiterea să eșueze rapid. */
    private function brokenSmtp(): void
    {
        $s = app(Settings::class);
        foreach (['mail.enabled' => true, 'mail.host' => '127.0.0.1', 'mail.port' => 1, 'mail.encryption' => 'none'] as $k => $v) {
            $s->set($k, $v);
        }
        MailSettings::refresh();
    }

    public function test_technical_admin_configures_smtp_and_password_is_encrypted(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $this->get('/panou/setari/email/')->assertOk()->assertSee('Email (SMTP)')->assertSee('NU se trimit');
        $this->withRecentReauth()->post('/panou/setari/email/', [
            'mail__enabled' => '1', 'mail__host' => 'mail.exemplu.invalid', 'mail__port' => '465', 'mail__encryption' => 'ssl',
            'mail__username' => 'comenzi@exemplu.invalid', 'mail__password' => 'Parola-SMTP-Fictiva-1', 'mail__from_address' => 'comenzi@exemplu.invalid',
            'mail__from_name' => 'Optoplus Test',
        ])->assertSessionHasNoErrors();

        $raw = (string) DB::table('settings')->where('key', 'mail.password')->value('value');
        $this->assertStringNotContainsString('Parola-SMTP-Fictiva-1', $raw);
        $this->assertSame('Parola-SMTP-Fictiva-1', app(Settings::class)->secret('mail.password'));

        MailSettings::refresh();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('comenzi@exemplu.invalid', config('mail.from.address'));
        $this->get('/panou/setari/email/')->assertSee('mail.exemplu.invalid:465')->assertDontSee('Parola-SMTP-Fictiva-1');
    }

    public function test_other_roles_do_not_see_smtp_settings(): void
    {
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->get('/panou/setari/email/')->assertStatus(403);
    }

    public function test_sender_address_always_has_a_value(): void
    {
        config(['mail.from.address' => '']);
        app(Settings::class)->set('contact.email_b2c', 'contact@exemplu.invalid');
        MailSettings::apply();
        $this->assertSame('contact@exemplu.invalid', config('mail.from.address'));

        config(['mail.from.address' => '']);
        app(Settings::class)->set('contact.email_b2c', null);
        MailSettings::apply();
        $this->assertStringStartsWith('no-reply@', config('mail.from.address'));
    }

    public function test_test_email_reports_success_and_failure(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC, ['email' => 'admin.test@demo.optoplus.invalid']);
        $this->post('/panou/setari/email/test/')->assertSessionHas('status');

        $this->brokenSmtp();
        $this->post('/panou/setari/email/test/')->assertSessionHas('warning', fn ($m) => str_contains($m, 'NU a putut fi trimis'));
    }

    public function test_user_is_created_even_when_email_fails(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $this->brokenSmtp();
        $this->withRecentReauth()->post('/panou/utilizatori/', ['name' => 'Coleg Fictiv', 'email' => 'coleg.fictiv@demo.optoplus.invalid', 'is_active' => '1', 'roles' => [Roles::VANZARI]])
            ->assertRedirect()
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'a fost creat') && str_contains($m, 'Setări → Email'));
        $this->assertTrue(User::where('email', 'coleg.fictiv@demo.optoplus.invalid')->exists());
    }

    public function test_user_creation_says_when_emails_only_go_to_the_log(): void
    {
        config(['mail.default' => 'log']);
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $this->withRecentReauth()->post('/panou/utilizatori/', ['name' => 'Coleg Jurnal', 'email' => 'coleg.jurnal@demo.optoplus.invalid', 'is_active' => '1', 'roles' => [Roles::VANZARI]])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'nu se trimit încă'));
    }

    public function test_b2b_invitation_is_not_left_behind_when_email_fails(): void
    {
        $this->brokenSmtp();
        $org = Organization::create(['name' => 'Clinica Fictivă Mail', 'kind' => 'clinica', 'status' => 'aprobata', 'billing_address' => 'Str. Test 1']);
        try {
            app(Invitations::class)->invite($org, 'invitat@demo.optoplus.invalid', 'comenzi');
            $this->fail('Se aștepta o eroare de validare.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Setări → Email', $e->getMessage());
        }
        $this->assertSame(0, OrganizationInvitation::count());
    }
}
