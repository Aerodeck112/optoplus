<?php

namespace App\Modules\Reviews;

use App\Core\Modules\CountWidget;
use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Reviews\Models\Review;

/**
 * Recenziile clienților: pe produse și despre magazin (prima pagină), lăsate fără cont. Se publică după moderare
 * (Clienți → Recenzii); moderarea folosește dreptul „cms.submissions” (cererile primite de pe site).
 */
class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'reviews';
    }

    public function label(): string
    {
        return 'Recenzii';
    }

    public function navigation(): array
    {
        return [new NavItem('Recenzii', 'panou.reviews.index', 'cms.submissions', 'Clienți', 'star', 3)];
    }

    public function dashboardWidgets(): array
    {
        return [new CountWidget('Recenzii de verificat', 'cms.submissions', fn () => Review::where('status', 'noua')->count(), '/panou/recenzii?stare=noua', 'Recenzii noi, nepublicate încă', 15)];
    }
}
