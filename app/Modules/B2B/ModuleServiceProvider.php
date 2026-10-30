<?php

namespace App\Modules\B2B;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
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
}
