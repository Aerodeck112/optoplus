<?php

namespace App\Modules\Import\Console;

use App\Modules\Import\Services\ImageDownloader;
use Illuminate\Console\Command;

class DownloadImages extends Command
{
    protected $signature = 'optoplus:import:images {--limit=50 : Imagini per lot} {--uploads-dir= : Directorul wp-content/uploads primit (preferat)} {--all : Repetă până la terminare} {--buget= : Repetă loturi mici până la acest număr de secunde (pentru cron)}';

    protected $description = 'Descarcă și optimizează imaginile produselor importate, în loturi.';

    public function handle(ImageDownloader $downloader): int
    {
        $deadline = $this->option('buget') ? microtime(true) + (int) $this->option('buget') : null;
        do {
            $r = $downloader->process($deadline ? 5 : (int) $this->option('limit'), $this->option('uploads-dir') ?: null);
            $this->line("descărcate {$r['ready']}, eșuate {$r['failed']}, rămase {$r['remaining']}");
            $again = $r['remaining'] > 0 && ($r['ready'] + $r['failed']) > 0 && ($this->option('all') || ($deadline && microtime(true) < $deadline - 8));
        } while ($again);

        return self::SUCCESS;
    }
}
