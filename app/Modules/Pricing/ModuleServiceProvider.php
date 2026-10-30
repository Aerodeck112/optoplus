<?php

namespace App\Modules\Pricing;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'pricing';
    }

    public function label(): string
    {
        return 'Prețuri și promoții';
    }

    public function permissions(): array
    {
        return [
            'pricing.view' => 'Vede promoțiile, cupoanele și listele de preț',
            'pricing.manage' => 'Creează și modifică promoții, cupoane, liste de preț B2B și cotele de TVA (auditat)',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['pricing.view', 'pricing.manage'],
            Roles::VANZARI => ['pricing.view'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Promoții și cupoane', 'panou.pricing.promotions.index', 'pricing.view', 'Catalog', 'money', 30),
            new NavItem('Liste de preț B2B', 'panou.pricing.lists.index', 'pricing.view', 'B2B', 'money', 20),
        ];
    }
}
