<?php

use App\Modules\Cms\Services\HomeShopRefresh;
use App\Modules\Settings\Support\CompanyDetails;
use Illuminate\Database\Migrations\Migration;

/**
 * 1.0.12: prima pagină în stil de magazin (HomeShopRefresh) și datele firmei OFA SRL din registrul ANAF
 * (CompanyDetails). Clasele noi se încarcă explicit: în timpul actualizării, în memorie este codul versiunii
 * vechi (vezi CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        $app = dirname(__DIR__, 2).'/app/Modules/';
        if (! class_exists(CompanyDetails::class)) {
            require_once $app.'Settings/Support/CompanyDetails.php';
        }
        if (! class_exists(HomeShopRefresh::class)) {
            require_once $app.'Cms/Services/HomeShopRefresh.php';
        }
        CompanyDetails::run();
        (new HomeShopRefresh)->run();
    }

    public function down(): void {}
};
