<?php

namespace App\Modules\Regions\Console;

use App\Modules\Regions\Services\GeoCountry;
use Illuminate\Console\Command;

class UpdateGeoDb extends Command
{
    protected $signature = 'optoplus:regions:geoip {--month= : Luna bazei (AAAA-LL); implicit luna curentă sau precedentă}';

    protected $description = 'Descarcă baza DB-IP „IP to Country Lite” (țara vizitatorului, pentru limba și valuta implicite)';

    public function handle(): int
    {
        $result = GeoCountry::download($this->option('month') ?: null);
        $result['ok'] ? $this->info($result['message']) : $this->warn($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
