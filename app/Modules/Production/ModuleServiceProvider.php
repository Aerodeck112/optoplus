<?php

namespace App\Modules\Production;

use App\Core\Modules\CountWidget;
use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'production';
    }

    public function label(): string
    {
        return 'Producție lentile';
    }

    public function permissions(): array
    {
        return [
            'production.view' => 'Vede lucrările de producție și fișele de lucru',
            'production.manage' => 'Mută lucrările între etape, alocă responsabili și termene',
            'production.approve' => 'Aprobă lucrarea pentru producție după verificarea rețetei (specialist optic)',
            'production.remake' => 'Deschide refaceri',
            'production.settings' => 'Configurează etapele fluxului de producție',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::SPECIALIST_OPTIC => ['production.view', 'production.manage', 'production.approve', 'production.remake'],
            Roles::PRODUCTIE => ['production.view', 'production.manage'],
            Roles::MANAGER => ['production.view', 'production.manage', 'production.remake', 'production.settings'],
            Roles::VANZARI => ['production.view'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Producție', 'panou.production.index', 'production.view', 'Producție', 'activity', 1, 'panou.production.index'),
            new NavItem('Rețete de verificat', 'panou.production.review', 'production.approve', 'Producție', 'shield', 2),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [
            new CountWidget('Rețete de verificat', 'production.approve', fn () => ProductionJob::whereIn('stage', ['primita', 'verificare_reteta'])->count(), '/panou/productie/de-verificat', 'Lucrări care așteaptă specialistul optic', 10),
            new CountWidget('Lucrări întârziate', 'production.view', fn () => ProductionJob::where('stage', '!=', 'expediata')->whereNotNull('due_on')->where('due_on', '<', now()->toDateString())->count(), '/panou/productie', null, 20),
        ];
    }
}
