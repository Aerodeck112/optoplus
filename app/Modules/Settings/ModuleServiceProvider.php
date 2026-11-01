<?php

namespace App\Modules\Settings;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'settings';
    }

    public function label(): string
    {
        return 'Setări';
    }

    public function navigation(): array
    {
        // Vizibil pentru oricine are cel puțin o secțiune; controllerul verifică permisiunea fiecărei secțiuni.
        return [
            new NavItem('Setări', 'panou.settings', 'cms.settings', 'Administrare', 'settings', 1),
            new NavItem('Setări plăți și livrare', 'panou.settings', 'payments.settings', 'Administrare', 'money', 2),
        ];
    }
}
