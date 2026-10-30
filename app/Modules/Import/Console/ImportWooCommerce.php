<?php

namespace App\Modules\Import\Console;

use App\Modules\Import\Services\WooCommerce\WooCommerceImporter;
use Illuminate\Console\Command;

class ImportWooCommerce extends Command
{
    protected $signature = 'optoplus:import:woocommerce
        {csv : Exportul de produse WooCommerce (CSV)}
        {--commit : Scrie în catalog (fără această opțiune: doar simulare)}
        {--batch=50 : Produse per lot (o tranzacție per lot)}
        {--resume : Continuă ultima rulare întreruptă pentru același fișier}
        {--urls=database/content/woocommerce/store-api-urls-2026-10-29.json : Harta ID → URL vechi}
        {--max-batches= : Oprește după N loturi (pentru limitele de timp ale hostingului)}
        {--force : Reimportă și produsele neschimbate}';

    protected $description = 'Importă produsele dintr-un export WooCommerce: simulare, loturi, reluare, raport.';

    public function handle(WooCommerceImporter $importer): int
    {
        $run = $importer->run($this->argument('csv'), [
            'commit' => (bool) $this->option('commit'),
            'batch' => (int) $this->option('batch'),
            'resume' => (bool) $this->option('resume'),
            'urls' => $this->option('urls') ? base_path($this->option('urls')) : null,
            'max_batches' => $this->option('max-batches') ? (int) $this->option('max-batches') : null,
            'force' => (bool) $this->option('force'),
        ]);
        $mode = $run->mode === 'simulare' ? 'SIMULARE (nimic nu a fost scris)' : 'IMPORT';
        $this->info("{$mode} — rularea #{$run->id}, stare: {$run->status}, {$run->cursor}/{$run->total} produse procesate.");
        $this->table(['Rezultat', 'Număr'], collect($run->counters)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $errors = collect($run->report['produse'] ?? [])->filter(fn ($p) => $p['errors']);
        foreach ($errors->take(20) as $id => $p) {
            $this->error("ID {$id} {$p['nume']}: ".implode(' ', $p['errors']));
        }
        if ($run->status === 'intrerupt') {
            $this->warn('Rulare întreruptă după numărul de loturi cerut. Continuați cu --resume.');
        }

        return $errors->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
