<?php

namespace App\Modules\Import\Console;

use App\Modules\Import\Services\ImageDownloader;
use Illuminate\Console\Command;

class DownloadImages extends Command
{
    protected $signature = 'optoplus:import:images {--limit=50 : Imagini per rulare} {--uploads-dir= : Directorul wp-content/uploads primit (preferat)} {--all : Repetă până la terminare}';

    protected $description = 'Descarcă și optimizează imaginile produselor importate, în loturi.';

    public function handle(ImageDownloader $downloader): int
    {
        do {
            $r = $downloader->process((int) $this->option('limit'), $this->option('uploads-dir') ?: null);
            $this->line("descărcate {$r['ready']}, eșuate {$r['failed']}, rămase {$r['remaining']}");
        } while ($this->option('all') && $r['remaining'] > 0 && ($r['ready'] + $r['failed']) > 0);

        return self::SUCCESS;
    }
}
