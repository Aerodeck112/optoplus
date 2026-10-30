<?php

namespace App\Modules\Orders;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'orders';
    }

    public function label(): string
    {
        return 'Comenzi';
    }

    public function permissions(): array
    {
        return [
            'orders.view' => 'Vede comenzile (fără valorile rețetei)',
            'orders.manage' => 'Confirmă comenzile, adaugă note, gestionează livrările și facturile',
            'orders.cancel' => 'Anulează comenzi — auditat',
            'orders.export' => 'Exportă comenzi — auditat',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['orders.view', 'orders.manage', 'orders.cancel', 'orders.export'],
            Roles::VANZARI => ['orders.view', 'orders.manage'],
            Roles::SPECIALIST_OPTIC => ['orders.view'],
            Roles::PRODUCTIE => ['orders.view'],
        ];
    }
}
