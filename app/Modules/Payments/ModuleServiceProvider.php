<?php

namespace App\Modules\Payments;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Modules\Identity\Roles;
use Illuminate\Console\Scheduling\Schedule;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'payments';
    }

    public function label(): string
    {
        return 'Plăți';
    }

    public function permissions(): array
    {
        return [
            'payments.view' => 'Vede plățile și notificările procesatorului',
            'payments.confirm' => 'Confirmă manual încasări (transfer, ramburs, ridicare) — auditat',
            'payments.refund' => 'Inițiază rambursări — auditat, cu reautentificare',
            'payments.settings' => 'Configurează metodele de plată',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['payments.view', 'payments.confirm', 'payments.refund', 'payments.settings'],
            Roles::VANZARI => ['payments.view', 'payments.confirm'],
        ];
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->command('optoplus:payments:reconcile')->everyTenMinutes()->withoutOverlapping(10)->name('plati:reconciliere');
    }
}
