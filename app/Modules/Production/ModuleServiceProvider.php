<?php

namespace App\Modules\Production;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
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
}
