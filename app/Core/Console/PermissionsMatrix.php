<?php

namespace App\Core\Console;

use App\Core\Modules\ModuleRegistry;
use App\Modules\Identity\Roles;
use Illuminate\Console\Command;

/**
 * Generează docs/ROLURI-PERMISIUNI.md din permisiunile și acordările implicite declarate de module.
 */
class PermissionsMatrix extends Command
{
    protected $signature = 'optoplus:permissions-matrix {--path=docs/ROLURI-PERMISIUNI.md}';

    protected $description = 'Generează matricea rolurilor și permisiunilor';

    public function handle(ModuleRegistry $registry): int
    {
        $roles = Roles::all();
        $grants = $registry->roleGrants();
        $lines = [
            '# Matricea rolurilor și permisiunilor',
            '',
            'Document generat de `php artisan optoplus:permissions-matrix` din declarațiile modulelor. Nu editați manual.',
            '',
            'Acordările de mai jos sunt cele **implicite**. Administratorul poate ajusta rolurile din panou; verificarea',
            'se face pe server la fiecare acțiune; politicile pe obiect limitează în plus accesul la datele unui client sau',
            'ale unei organizații B2B (vezi `SECURITATE.md`). Administratorul nu are implicit acces la rețete.',
            '',
            '| Modul | Permisiune | Descriere | '.implode(' | ', $roles).' |',
            '|---|---|---|'.str_repeat('---|', count($roles)),
        ];
        foreach ($registry->permissions() as $name => $info) {
            $cells = [];
            foreach (array_keys($roles) as $role) {
                $cells[] = in_array($name, $grants[$role] ?? [], true) ? '✔' : '';
            }
            $lines[] = "| {$info['module']} | `{$name}` | {$info['label']} | ".implode(' | ', $cells).' |';
        }

        file_put_contents(base_path($this->option('path')), implode("\n", $lines)."\n");
        $this->info('Scris: '.$this->option('path'));

        return self::SUCCESS;
    }
}
