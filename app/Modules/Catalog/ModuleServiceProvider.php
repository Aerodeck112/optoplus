<?php

namespace App\Modules\Catalog;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'catalog';
    }

    public function label(): string
    {
        return 'Catalog';
    }

    public function permissions(): array
    {
        return [
            'catalog.view' => 'Vede produsele, variantele, mărcile și categoriile',
            'catalog.edit' => 'Editează produsele, variantele, imaginile, mărcile, categoriile și atributele',
            'catalog.prices' => 'Modifică prețurile și costurile produselor (auditat)',
            'catalog.publish' => 'Publică și retrage produse',
        ];
    }

    public function roleGrants(): array
    {
        $all = array_keys($this->permissions());

        return [
            Roles::MANAGER => $all,
            Roles::VANZARI => ['catalog.view', 'catalog.edit'],
            Roles::EDITOR_SITE => ['catalog.view', 'catalog.edit', 'catalog.publish'],
            Roles::SPECIALIST_OPTIC => ['catalog.view'],
            Roles::PRODUCTIE => ['catalog.view'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Produse', 'panou.catalog.products', 'catalog.view', 'Catalog', 'layers', 10, 'panou.catalog.product*'),
            new NavItem('Mărci', 'panou.catalog.brands.index', 'catalog.edit', 'Catalog', 'clipboard', 12),
            new NavItem('Categorii', 'panou.catalog.categories.index', 'catalog.edit', 'Catalog', 'menu', 13),
            new NavItem('Atribute și filtre', 'panou.catalog.attributes.index', 'catalog.edit', 'Catalog', 'settings', 14),
        ];
    }
}
