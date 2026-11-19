<?php

namespace App\Modules\Regions;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Modules\Regions\Services\ExchangeRates;
use App\Modules\Regions\Services\GeoCountry;
use App\Modules\Regions\Services\Regions;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Țări, limbi și valute: limba și valuta după țara vizitatorului (prima vizită), prețurile în euro / forinți la
 * cursul BNR, țările de livrare. Setările sunt în Setări → Valute și țări.
 */
class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'regions';
    }

    public function label(): string
    {
        return 'Țări și valute';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(ExchangeRates::class);
        $this->app->singleton(Regions::class);
        $this->app->singleton(GeoCountry::class);
    }

    public function schedule(Schedule $schedule): void
    {
        // BNR publică cursul în zilele lucrătoare, după ora 13; comanda sare peste rulările inutile.
        $schedule->command('optoplus:regions:rates')->hourlyAt(17)->between('8:00', '18:00')->withoutOverlapping(10);
        // Baza DB-IP (țara după IP) se actualizează lunar.
        $schedule->command('optoplus:regions:geoip')->monthlyOn(3, '04:20')->withoutOverlapping(30);
    }
}
