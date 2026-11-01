<?php

namespace Database\Seeders;

use App\Modules\Cms\Services\ContentRefresh;
use Illuminate\Database\Seeder;

/** La instalarea de la zero: aceleași îmbunătățiri de conținut ca migrarea 1.0.5 (rulează după SiteContentSeeder). */
class ContentRefreshSeeder extends Seeder
{
    public function run(): void
    {
        $refresh = new ContentRefresh;
        $refresh->home();
        $refresh->production();
        $refresh->cookies();
    }
}
