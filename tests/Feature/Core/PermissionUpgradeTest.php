<?php

namespace Tests\Feature\Core;

use App\Core\Security\PermissionUpgrade;
use App\Core\Settings\Settings;
use App\Modules\Identity\Roles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** După o actualizare, permisiunile modulelor noi apar automat, fără a reface acordările retrase manual. */
class PermissionUpgradeTest extends TestCase
{
    public function test_new_module_permissions_are_granted_once_per_version(): void
    {
        $this->artisan('optoplus:sync-permissions')->assertSuccessful();
        // Simulăm o instalare mai veche: permisiunea jurnalului de audit nu exista.
        Permission::where('name', 'audit.view')->delete();
        // Administratorul retrăsese intenționat o acordare implicită existentă.
        Role::findByName(Roles::ADMIN_TEHNIC)->revokePermissionTo('backups.manage');
        app(Settings::class)->set(PermissionUpgrade::SETTING, '0.9.0');

        $created = app(PermissionUpgrade::class)->runIfVersionChanged();
        $this->assertSame(['audit.view'], $created);
        $this->assertTrue(Role::findByName(Roles::MANAGER)->hasPermissionTo('audit.view'));
        $this->assertTrue(Role::findByName(Roles::ADMIN_TEHNIC)->hasPermissionTo('audit.view'));
        $this->assertFalse(Role::findByName(Roles::VANZARI)->hasPermissionTo('audit.view'));
        $this->assertFalse(Role::findByName(Roles::ADMIN_TEHNIC)->hasPermissionTo('backups.manage'), 'acordarea retrasă rămâne retrasă');
        $this->assertSame(config('optoplus.version'), app(Settings::class)->get(PermissionUpgrade::SETTING));

        // A doua rulare (cron la minut) nu mai face nimic.
        Permission::where('name', 'audit.view')->delete();
        $this->assertSame([], app(PermissionUpgrade::class)->runIfVersionChanged());
    }
}
