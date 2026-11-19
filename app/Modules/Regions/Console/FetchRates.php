<?php

namespace App\Modules\Regions\Console;

use App\Modules\Regions\Services\ExchangeRates;
use App\Modules\Storefront\Services\PageCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchRates extends Command
{
    protected $signature = 'optoplus:regions:rates {--force : Citește cursul chiar dacă cel de azi există deja}';

    protected $description = 'Citește cursul de referință BNR pentru euro și forint (prețurile în valută)';

    public function handle(ExchangeRates $rates): int
    {
        $today = now()->toDateString();
        if (! $this->option('force') && ($rates->lastBnrDate() === $today || Cache::has('regions.rates.checked'))) {
            $this->info('Cursul este la zi.');

            return self::SUCCESS;
        }
        $before = $rates->lastBnrDate();
        $result = $rates->fetchBnr();
        Cache::put('regions.rates.checked', true, now()->addHours(2));
        if ($result['ok'] && $result['date'] !== $before) {
            app(PageCache::class)->flush(); // prețurile afișate în valută s-au schimbat
        }
        $result['ok'] ? $this->info($result['message']) : $this->warn($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
