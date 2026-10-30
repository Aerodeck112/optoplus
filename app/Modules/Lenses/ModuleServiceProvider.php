<?php

namespace App\Modules\Lenses;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'lenses';
    }

    public function label(): string
    {
        return 'Lentile și matrice';
    }

    public function permissions(): array
    {
        return [
            'lenses.view' => 'Vede lentilele, matricea de fabricație și regulile de compatibilitate',
            'lenses.edit' => 'Editează familiile, indicii, tratamentele, ofertele (intervale, prețuri) și regulile',
            'lenses.confirm' => 'Marchează datele de lentile ca „confirmate de laborator”',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => array_keys($this->permissions()),
            Roles::SPECIALIST_OPTIC => array_keys($this->permissions()),
            Roles::PRODUCTIE => ['lenses.view'],
            Roles::VANZARI => ['lenses.view'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Lentile și matrice', 'panou.lenses.index', 'lenses.view', 'Catalog', 'activity', 20),
            new NavItem('Reguli de compatibilitate', 'panou.lenses.rules.index', 'lenses.view', 'Catalog', 'shield', 21),
        ];
    }
}
