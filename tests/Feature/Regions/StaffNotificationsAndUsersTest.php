<?php

namespace Tests\Feature\Regions;

use App\Core\Notifications\StaffAlert;
use App\Core\Notifications\StaffRecipients;
use App\Core\Settings\Settings;
use App\Models\User;
use App\Modules\Identity\Roles;
use App\Modules\Orders\Notifications\NewOrderForStaff;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * 1.0.17: notificările interne merg la toate adresele din setări (implicit office@optoplus.ro și
 * jeremias@optica-optofarm.ro); utilizatorii se editează din panou, cu drepturi suplimentare față de roluri.
 */
class StaffNotificationsAndUsersTest extends TestCase
{
    use BuildsOrders;

    public function test_migration_sets_both_internal_addresses(): void
    {
        $this->assertSame(['office@optoplus.ro', 'jeremias@optica-optofarm.ro'], array_slice(StaffRecipients::emails(), 0, 2));
        $this->assertSame(['a@demo.optoplus.invalid', 'b@demo.optoplus.invalid'], StaffRecipients::parse('a@demo.optoplus.invalid, b@demo.optoplus.invalid; nu-e-email'));
    }

    public function test_new_order_and_site_requests_reach_every_internal_address(): void
    {
        Notification::fake();
        app(Settings::class)->set('notifications.orders_email', 'echipa1@demo.optoplus.invalid, echipa2@demo.optoplus.invalid');
        $this->seedLenses();
        $this->enableCheckout();
        $cfg = $this->configureGlasses($this->frame(30000));
        $total = app(PriceEngine::class)->quote([PriceLine::configuration('x', $cfg->toInput())], PricingContext::b2c(['shippingMethodCode' => 'curier']))->totalGross;
        $this->post('/comanda/', $this->checkoutData(['total_afisat' => $total, 'plata' => 'ramburs']))->assertSessionHasNoErrors();

        Notification::assertSentTo(new AnonymousNotifiable, NewOrderForStaff::class, function ($n, $channels, $notifiable) {
            return $notifiable->routes['mail'] === ['echipa1@demo.optoplus.invalid', 'echipa2@demo.optoplus.invalid'];
        });

        $this->post('/formular/contact/', ['name' => 'Persoană Fictivă', 'email' => 'vizitator@demo.optoplus.invalid', 'subject' => 'Întrebare',
            'message' => 'Text fictiv.', 'consent' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(\App\Modules\Crm\Models\Lead::where('source', 'contact')->exists(), 'cererea s-a salvat');
        Notification::assertSentTo(new AnonymousNotifiable, StaffAlert::class, function (StaffAlert $n, $channels, $notifiable) {
            return str_contains($n->subject, 'Formular de contact') && $notifiable->routes['mail'] === ['echipa1@demo.optoplus.invalid', 'echipa2@demo.optoplus.invalid'];
        });
    }

    public function test_settings_accept_several_addresses_and_reject_an_invalid_one(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $this->withRecentReauth();
        $base = ['contact__phone_b2c' => '+40 700 000 000', 'contact__email_b2c' => 'contact@demo.optoplus.invalid'];
        $this->post('/panou/setari/contact', $base + ['notifications__orders_email' => 'unu@demo.optoplus.invalid; doi@demo.optoplus.invalid'])->assertSessionHasNoErrors();
        $this->assertSame('unu@demo.optoplus.invalid, doi@demo.optoplus.invalid', app(Settings::class)->get('notifications.orders_email'));
        $this->post('/panou/setari/contact', $base + ['notifications__orders_email' => 'unu@demo.optoplus.invalid, gresit'])->assertSessionHasErrors();
    }

    public function test_users_page_has_edit_and_extra_rights_beyond_roles(): void
    {
        $admin = $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $this->withRecentReauth();
        $colleague = User::factory()->create(['name' => 'Coleg Fictiv', 'email' => 'coleg@demo.optoplus.invalid', 'is_active' => true]);
        $colleague->assignRole(Roles::VANZARI);
        $this->get('/panou/utilizatori')->assertOk()->assertSee('Editează');
        $this->get('/panou/utilizatori/'.$colleague->id)->assertOk()->assertSee('Drepturi')->assertSee('(din rol)', false);

        $this->put('/panou/utilizatori/'.$colleague->id, ['name' => 'Coleg Fictiv', 'email' => 'coleg@demo.optoplus.invalid', 'is_active' => '1',
            'roles' => [Roles::VANZARI], 'permissions' => ['i18n.manage']])->assertSessionHasNoErrors();
        $colleague->refresh();
        $this->assertTrue($colleague->hasDirectPermission('i18n.manage'));
        $this->assertTrue($colleague->can('i18n.manage'));
        $this->assertTrue(\App\Core\Audit\AuditLog::where('action', 'users.updated')->exists());

        // retragere
        $this->put('/panou/utilizatori/'.$colleague->id, ['name' => 'Coleg Fictiv', 'email' => 'coleg@demo.optoplus.invalid', 'is_active' => '1',
            'roles' => [Roles::VANZARI], 'permissions' => []])->assertSessionHasNoErrors();
        $this->assertFalse($colleague->fresh()->hasDirectPermission('i18n.manage'));
    }

    public function test_a_manager_cannot_grant_rights_they_do_not_have(): void
    {
        $manager = $this->actingAsRole(Roles::MANAGER);
        $manager->givePermissionTo('users.manage');
        $this->withRecentReauth();
        $colleague = User::factory()->create(['name' => 'Alt Coleg', 'email' => 'alt.coleg@demo.optoplus.invalid', 'is_active' => true]);
        $missing = collect(array_keys(app(\App\Core\Modules\ModuleRegistry::class)->permissions()))->first(fn ($p) => ! $manager->can($p));
        $this->assertNotNull($missing);
        $this->put('/panou/utilizatori/'.$colleague->id, ['name' => 'Alt Coleg', 'email' => 'alt.coleg@demo.optoplus.invalid', 'is_active' => '1',
            'roles' => [], 'permissions' => [$missing]])->assertForbidden();
        $this->assertFalse($colleague->fresh()->hasDirectPermission($missing));
    }
}
