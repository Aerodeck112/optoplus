<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Roles;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** 1.0.8: retrimiterea invitației, ștergerea conturilor nefolosite și dezactivarea celor care au lucrat. */
class UserLifecycleTest extends TestCase
{
    private function colleague(bool $loggedIn, string $role = Roles::VANZARI): User
    {
        $u = User::factory()->create(['email' => uniqid('coleg.').'@demo.optoplus.invalid', 'is_active' => true, 'last_login_at' => $loggedIn ? now()->subDay() : null]);
        $u->syncRoles([$role]);

        return $u;
    }

    public function test_list_shows_resend_and_delete_actions(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $new = $this->colleague(false);
        $old = $this->colleague(true);
        $this->get('/panou/utilizatori/')->assertOk()
            ->assertSee('Retrimite invitația')
            ->assertSee('invitat, neautentificat')
            ->assertSee('data-confirm="Ștergeți definitiv contul pentru '.$new->name, false)
            ->assertSee('/panou/utilizatori/'.$old->id.'/dezactiveaza', false)
            ->assertDontSee('data-confirm="Ștergeți definitiv contul pentru '.$old->name, false);
    }

    public function test_resend_invitation_sends_password_email(): void
    {
        Notification::fake();
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $u = $this->colleague(false);
        $this->withRecentReauth()->post(route('panou.users.send-reset', $u))->assertSessionHas('status');
        Notification::assertSentTo($u, ResetPasswordNotification::class);
    }

    public function test_never_used_account_can_be_deleted(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $u = $this->colleague(false);
        $this->withRecentReauth()->delete(route('panou.users.destroy', $u))->assertRedirect(route('panou.users.index'))->assertSessionHas('status');
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.deleted', 'subject_id' => $u->id]);
    }

    public function test_account_with_history_is_deactivated_not_deleted(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $u = $this->colleague(true);
        $this->withRecentReauth()->delete(route('panou.users.destroy', $u))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $u->id]);

        $this->withRecentReauth()->post(route('panou.users.deactivate', $u))->assertSessionHas('status');
        $this->assertFalse($u->fresh()->is_active);
    }

    public function test_own_account_and_last_technical_admin_are_protected(): void
    {
        $me = $this->actingAsRole(Roles::ADMIN_TEHNIC, ['last_login_at' => null]);
        $this->withRecentReauth()->delete(route('panou.users.destroy', $me))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $me->id]);

        // un manager nu poate șterge un administrator tehnic
        $admin = $this->colleague(false, Roles::ADMIN_TEHNIC);
        $this->actingAsRole(Roles::MANAGER);
        $this->withRecentReauth()->delete(route('panou.users.destroy', $admin))->assertSessionMissing('status');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_actions_require_recent_reauthentication(): void
    {
        $this->actingAsRole(Roles::ADMIN_TEHNIC);
        $u = $this->colleague(false);
        $this->delete(route('panou.users.destroy', $u))->assertRedirect(route('panou.reauth'));
        $this->assertDatabaseHas('users', ['id' => $u->id]);
    }
}
