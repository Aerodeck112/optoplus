<?php

namespace App\Modules\Import\Console;

use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Import\Models\ImportRun;
use App\Modules\Import\Services\ImageDownloader;
use App\Modules\Import\Services\WooCommerce\WooCommerceImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Importul conținutului la instalare (pasul 4 din install.php), pe bucăți care încap în limita de timp a
 * hostingului: întâi catalogul din exportul WooCommerce inclus în pachet, apoi imaginile produselor.
 * Ieșire: cod 3 + „INCOMPLET x/y” cât timp mai e de lucru; cod 0 la final. Se poate relua oricând.
 *
 * Imaginile se iau, în ordine: din directorul local wp-content/uploads al vechiului site (dacă există pe același
 * cont, ex. public_html/wp-content/uploads) sau prin HTTP de la optoplus.ro, cât timp vechiul site mai răspunde.
 * Instalarea se încheie după catalog; imaginile rămase se procesează în fundal de cron (câteva pe minut).
 * O imagine care nu se poate prelua nu oprește nimic; se reia cu „php artisan optoplus:import:images --all”.
 */
class ImportSite extends Command
{
    protected $signature = 'optoplus:import-site {--buget=20 : Secunde de lucru per rulare} {--uploads-dir= : Directorul wp-content/uploads al vechiului site}';

    protected $description = 'Importă catalogul (WooCommerce) și imaginile, pe bucăți, pentru instalare';

    public function handle(WooCommerceImporter $importer, ImageDownloader $images): int
    {
        $deadline = microtime(true) + max(3, (int) $this->option('buget'));
        $csv = collect(glob(database_path('content/woocommerce/wc-product-export-*.csv')))->sort()->last();
        $urls = collect(glob(database_path('content/woocommerce/store-api-urls-*.json')))->sort()->last();
        if (! $csv) {
            $this->warn('Nu există un export WooCommerce în pachet; importul catalogului este omis.');

            return self::SUCCESS;
        }
        $sha = hash_file('sha256', $csv);
        $done = fn () => ImportRun::where('source_sha256', $sha)->where('mode', 'import')->where('status', 'finalizat')->exists();

        // 1. Catalogul: câte un lot pe rând, cu reluare, până se termină timpul.
        while (! $done() && microtime(true) < $deadline) {
            $run = $importer->run($csv, ['commit' => true, 'resume' => true, 'batch' => 20, 'max_batches' => 1, 'urls' => $urls]);
            if ($run->status === 'finalizat') {
                break;
            }
        }

        // 2. Imaginile, câte puține pe rând.
        $uploads = $this->option('uploads-dir') ?: $this->guessUploadsDir();
        while ($done() && microtime(true) < $deadline - 2) {
            try {
                $r = $images->process(3, $uploads);
            } catch (Throwable $e) {
                $this->warn('Imaginile nu au putut fi procesate acum: '.$e->getMessage());
                break;
            }
            if ($r['remaining'] === 0) {
                break;
            }
        }

        $run = ImportRun::where('source_sha256', $sha)->where('mode', 'import')->latest('id')->first();
        $productsTotal = (int) ($run?->total ?? 0);
        $productsDone = $done() ? $productsTotal : (int) ($run?->cursor ?? 0);
        $imgTotal = ProductImage::count();
        $imgDone = ProductImage::where('status', '!=', 'pending')->count();
        if (! $done()) {
            $this->line('INCOMPLET '.$productsDone.'/'.max(1, $productsTotal));

            return 3;
        }
        // Imaginile rămase nu țin instalarea în loc: le procesează cronul, în loturi mici (vezi ModuleServiceProvider).
        $pending = ProductImage::where('status', 'pending')->count();
        $failed = ProductImage::where('status', 'failed')->count();
        $this->info("Catalog importat: {$productsTotal} produse; imagini: {$imgDone} procesate, {$pending} în curs (în fundal, prin cron), {$failed} nepreluate.");
        if ($failed) {
            $this->warn('Imaginile nepreluate se pot relua din panou sau cu „php artisan optoplus:import:images --all”.');
        }

        return self::SUCCESS;
    }

    private function guessUploadsDir(): ?string
    {
        foreach ([config('updates.public_path'), dirname(base_path(), 3).'/public_html', public_path()] as $root) {
            if ($root && is_dir($root.'/wp-content/uploads')) {
                return $root.'/wp-content/uploads';
            }
        }

        return null;
    }
}
