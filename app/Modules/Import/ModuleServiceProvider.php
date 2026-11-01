<?php

namespace App\Modules\Import;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Identity\Roles;
use Illuminate\Console\Scheduling\Schedule;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'import';
    }

    public function label(): string
    {
        return 'Import și migrare';
    }

    public function permissions(): array
    {
        return ['import.run' => 'Rulează importul catalogului (simulare și import) și vede rapoartele de reconciliere'];
    }

    public function roleGrants(): array
    {
        return [Roles::ADMIN_TEHNIC => ['import.run'], Roles::MANAGER => ['import.run']];
    }

    public function navigation(): array
    {
        return [new NavItem('Import WooCommerce', 'panou.import.index', 'import.run', 'Administrare', 'upload', 30)];
    }

    public function schedule(Schedule $schedule): void
    {
        // Imaginile importate se descarcă treptat, fără a depăși limitele hostingului.
        $schedule->command('optoplus:import:images --limit=15')->everyMinute()->withoutOverlapping(10)->name('import:imagini');
    }
}
