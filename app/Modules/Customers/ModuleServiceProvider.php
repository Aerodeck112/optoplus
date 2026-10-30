<?php

namespace App\Modules\Customers;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'customers';
    }

    public function label(): string
    {
        return 'Clienți';
    }

    public function permissions(): array
    {
        return [
            'customers.view' => 'Vede clienții și istoricul lor (fără valorile rețetelor)',
            'customers.manage' => 'Editează datele clienților',
            'customers.gdpr' => 'Tratează cererile de export și ștergere a datelor — auditat',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['customers.view', 'customers.manage', 'customers.gdpr'],
            Roles::VANZARI => ['customers.view', 'customers.manage'],
            Roles::SPECIALIST_OPTIC => ['customers.view'],
        ];
    }
}
