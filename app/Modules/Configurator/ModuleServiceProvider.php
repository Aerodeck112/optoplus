<?php

namespace App\Modules\Configurator;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Identity\Roles;
use Illuminate\Console\Scheduling\Schedule;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'configurator';
    }

    public function label(): string
    {
        return 'Configurator și rețete';
    }

    public function permissions(): array
    {
        return [
            'prescriptions.view' => 'Consultă rețetele și fișierele lor (auditat)',
            'prescriptions.verify' => 'Verifică rețetele și aprobă configurațiile pentru producție (specialist optic)',
            'configurations.view' => 'Vede configurațiile salvate de clienți (fără valorile rețetei)',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::SPECIALIST_OPTIC => ['prescriptions.view', 'prescriptions.verify', 'configurations.view'],
            Roles::PRODUCTIE => ['prescriptions.view', 'configurations.view'],
            Roles::VANZARI => ['configurations.view'],
            Roles::MANAGER => ['configurations.view'],
        ];
    }

    public function navigation(): array
    {
        return []; // Verificarea rețetelor se face din fluxul de producție („Rețete de verificat”).
    }

    public function schedule(Schedule $schedule): void
    {
        // Retenția: rețetele neatașate unei comenzi, expirate, se șterg (inclusiv fișierele).
        $schedule->command('optoplus:prescriptions:purge')->dailyAt('03:20')->withoutOverlapping(30)->name('retete:retentie');
    }
}
