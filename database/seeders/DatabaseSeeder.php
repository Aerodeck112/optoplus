<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seederul de instalare: roluri, permisiuni, structura catalogului, lentilele preluate din configuratorul actual
 * (neconfirmate), etapele de producție și metodele de livrare (inactive). Idempotent.
 * Datele demonstrative (fictive) sunt separate: `php artisan db:seed --class=Database\\Seeders\\Demo\\DemoSeeder`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('optoplus:sync-permissions');
        $this->call([
            CatalogBaseSeeder::class,
            CurrentConfiguratorSeeder::class,
            ProductionStagesSeeder::class,
            ShippingPaymentSeeder::class,
            BusinessSettingsSeeder::class,
            SiteContentSeeder::class,
            LegalDraftsSeeder::class,
            ContentRefreshSeeder::class,
        ]);
    }
}
