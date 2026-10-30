<?php

namespace App\Modules\Shipping;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'shipping';
    }

    public function label(): string
    {
        return 'Livrare';
    }

    public function permissions(): array
    {
        return [
            'shipping.manage' => 'Configurează metodele și tarifele de livrare',
            'shipping.ship' => 'Creează expedieri și introduce AWB-uri',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['shipping.manage', 'shipping.ship'],
            Roles::PRODUCTIE => ['shipping.ship'],
            Roles::VANZARI => ['shipping.ship'],
        ];
    }

    public function navigation(): array
    {
        return [new NavItem('Livrare', 'panou.shipping.methods.index', 'shipping.manage', 'Administrare', 'send', 40)];
    }
}
