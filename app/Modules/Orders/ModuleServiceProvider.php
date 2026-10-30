<?php

namespace App\Modules\Orders;

use App\Core\Modules\CountWidget;
use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Orders\Models\Order;
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

    public function navigation(): array
    {
        return [new NavItem('Comenzi', 'panou.orders.index', 'orders.view', 'Comenzi', 'inbox', 1)];
    }

    public function dashboardWidgets(): array
    {
        return [
            new CountWidget('Comenzi noi de confirmat', 'orders.view', fn () => Order::where('commercial_status', 'noua')->count(), '/panou/comenzi?commercial_status=noua'),
            new CountWidget('Transferuri în așteptare', 'orders.view', fn () => Order::where('payment_method', 'transfer_bancar')->where('payment_status', 'in_asteptare')->count(), '/panou/comenzi?payment_method=transfer_bancar&payment_status=in_asteptare'),
        ];
    }
}
