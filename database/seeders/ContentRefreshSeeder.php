<?php

namespace Database\Seeders;

use App\Modules\Cms\Services\ContentRefresh;
use Illuminate\Database\Seeder;

/** La instalarea de la zero: aceleași îmbunătățiri de conținut ca migrările 1.0.5, 1.0.6 și 1.0.12 (rulează după SiteContentSeeder). */
class ContentRefreshSeeder extends Seeder
{
    public function run(): void
    {
        $refresh = new ContentRefresh;
        $refresh->home();
        $refresh->production();
        $refresh->cookies();
        (new \App\Modules\Cms\Services\PartnersPageRefresh)->run();
        (new \App\Modules\Cms\Services\HomeShopRefresh)->run(); // 1.0.12
    }
}
