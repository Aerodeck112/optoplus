<?php

namespace App\Modules\Crm;

use App\Core\Modules\CountWidget;
use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Task;
use App\Modules\Identity\Roles;
use Illuminate\Support\Facades\Auth;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'crm';
    }

    public function label(): string
    {
        return 'CRM';
    }

    public function permissions(): array
    {
        return [
            'crm.view' => 'Vede cererile primite (lead-uri), notele și sarcinile',
            'crm.manage' => 'Preia cereri, adaugă note și sarcini',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['crm.view', 'crm.manage'],
            Roles::VANZARI => ['crm.view', 'crm.manage'],
            Roles::SPECIALIST_OPTIC => ['crm.view', 'crm.manage'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Cereri primite', 'panou.crm.leads', 'crm.view', 'Clienți', 'inbox', 1, 'panou.crm.lead*'),
            new NavItem('Sarcinile mele', 'panou.crm.tasks', 'crm.view', 'Clienți', 'check', 2),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [
            new CountWidget('Cereri noi', 'crm.view', fn () => Lead::where('status', 'noua')->count(), '/panou/crm/cereri?stare=noua', 'Ajutor lentile, contact, configurator, asistent', 15),
            new CountWidget('Sarcinile mele', 'crm.view', fn () => Task::whereNull('completed_at')->where('assignee_id', Auth::id())->count(), '/panou/crm/sarcini', null, 16),
        ];
    }
}
