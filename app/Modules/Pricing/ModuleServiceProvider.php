<?php

namespace App\Modules\Pricing;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Roles;
use App\Modules\Pricing\Services\ReducedPrice;
use Illuminate\Support\Facades\Schema;

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
            new NavItem('Gestionare prețuri', 'panou.pricing.tools', 'catalog.prices', 'Catalog', 'tag', 29),
            new NavItem('Liste de preț B2B', 'panou.pricing.lists.index', 'pricing.view', 'B2B', 'money', 20),
        ];
    }

    public function boot(): void
    {
        parent::boot();
        // 1.0.27: istoricul prețurilor de bază (pentru „cel mai mic preț din ultimele 30 de zile” la reduceri)
        ProductVariant::saved(function (ProductVariant $variant) {
            static $ready = null;
            if (($variant->wasRecentlyCreated || $variant->wasChanged('price')) && ($ready ??= Schema::hasTable('variant_price_history'))) {
                ReducedPrice::record($variant, app()->runningInConsole() ? 'import / consolă' : 'panou');
            }
        });
    }
}
