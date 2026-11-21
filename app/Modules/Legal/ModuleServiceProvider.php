<?php

namespace App\Modules\Legal;

use App\Core\Modules\CountWidget;
use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Legal\Models\ContractWithdrawal;

/**
 * Obligațiile legale ale magazinului online: funcția de retragere din contract, formularul tip de retragere și
 * notificarea armonizată privind garanția legală de conformitate. Textele juridice se editează în Site → Pagini.
 */
class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'legal';
    }

    public function label(): string
    {
        return 'Conformitate';
    }

    public function navigation(): array
    {
        return [new NavItem('Retrageri din contract', 'panou.legal.withdrawals', 'orders.view', 'Comenzi', 'refresh', 3)];
    }

    public function dashboardWidgets(): array
    {
        return [new CountWidget('Retrageri de rezolvat', 'orders.view', fn () => ContractWithdrawal::where('status', 'primita')->count(), '/panou/retrageri?stare=primita', 'Declarații de retragere trimise online', 14)];
    }
}
