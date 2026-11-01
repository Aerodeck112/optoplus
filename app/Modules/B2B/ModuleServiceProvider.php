<?php

namespace App\Modules\B2B;

use App\Core\Modules\CountWidget;
use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\B2B\Models\Complaint;
use App\Modules\B2B\Models\PartnershipRequest;
use App\Modules\B2B\Models\Quote;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'b2b';
    }

    public function label(): string
    {
        return 'Parteneri B2B';
    }

    public function permissions(): array
    {
        return [
            'b2b.view' => 'Vede partenerii, cererile de parteneriat, ofertele și sesizările',
            'b2b.approve' => 'Aprobă sau respinge cereri de parteneriat; suspendă parteneri — auditat',
            'b2b.terms' => 'Aprobă termene de plată și limite de credit — auditat',
            'b2b.quotes' => 'Pregătește și trimite oferte B2B',
            'b2b.documents' => 'Încarcă documente pentru parteneri',
            'b2b.complaints' => 'Tratează reclamațiile și refacerile',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['b2b.view', 'b2b.approve', 'b2b.terms', 'b2b.quotes', 'b2b.documents', 'b2b.complaints'],
            Roles::VANZARI => ['b2b.view', 'b2b.quotes', 'b2b.documents', 'b2b.complaints'],
            Roles::SPECIALIST_OPTIC => ['b2b.view', 'b2b.complaints'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Parteneri', 'panou.b2b.orgs', 'b2b.view', 'B2B', 'users', 1, 'panou.b2b.org*'),
            new NavItem('Cereri de parteneriat', 'panou.b2b.requests', 'b2b.view', 'B2B', 'inbox', 2),
            new NavItem('Oferte', 'panou.b2b.quotes', 'b2b.view', 'B2B', 'file', 3, 'panou.b2b.quote*'),
            new NavItem('Sesizări și refaceri', 'panou.b2b.complaints', 'b2b.view', 'B2B', 'warn', 4),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [
            new CountWidget('Cereri de parteneriat noi', 'b2b.view', fn () => PartnershipRequest::where('status', 'noua')->count(), '/panou/parteneri/cereri', null, 30),
            new CountWidget('Oferte de pregătit', 'b2b.quotes', fn () => Quote::whereIn('status', ['ceruta', 'ciorna'])->count(), '/panou/parteneri/oferte?stare=ceruta', null, 31),
            new CountWidget('Sesizări noi', 'b2b.complaints', fn () => Complaint::where('status', 'noua')->count(), '/panou/parteneri/sesizari', null, 32),
        ];
    }
}
