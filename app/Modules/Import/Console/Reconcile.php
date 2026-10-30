<?php

namespace App\Modules\Import\Console;

use App\Modules\Import\Services\WooCommerce\Reconciler;
use Illuminate\Console\Command;

class Reconcile extends Command
{
    protected $signature = 'optoplus:import:reconcile {csv} {--urls=database/content/woocommerce/store-api-urls-2026-10-29.json} {--json= : Scrie raportul complet în acest fișier}';

    protected $description = 'Reconciliază catalogul importat cu exportul WooCommerce (numere, prețuri, imagini, URL-uri).';

    public function handle(Reconciler $reconciler): int
    {
        $r = $reconciler->reconcile($this->argument('csv'), $this->option('urls') ? base_path($this->option('urls')) : null);
        $rows = [];
        foreach ($r as $name => $check) {
            if ($check === null) {
                continue;
            }
            $rows[] = [$name, $check['sursa'] ?? $check['verificate'] ?? $check['total'] ?? '', $check['tinta'] ?? ($check['pastrate'] ?? count($check['diferente'] ?? [])), isset($check['ok']) ? ($check['ok'] ? 'da' : 'NU') : (empty($check['diferente']) ? 'da' : 'NU')];
        }
        $this->table(['Verificare', 'Sursă', 'Țintă / diferențe', 'Corespunde'], $rows);
        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Raport complet: {$path}");
        }

        return self::SUCCESS;
    }
}
