<?php

use App\Modules\Cms\Services\ContentRefresh;
use App\Modules\Cms\Services\PartnersPageRefresh;
use Illuminate\Database\Migrations\Migration;

/**
 * 1.0.6: pagina nouă pentru parteneri (vezi PartnersPageRefresh). Clasele noi se încarcă explicit: în timpul
 * actualizării, în memorie este codul versiunii vechi (vezi CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = dirname(__DIR__, 2).'/app/Modules/Cms/Services/';
        if (! class_exists(ContentRefresh::class)) {
            require_once $base.'ContentRefresh.php';
        }
        if (! class_exists(PartnersPageRefresh::class)) {
            require_once $base.'PartnersPageRefresh.php';
        }
        (new PartnersPageRefresh)->run();
    }

    public function down(): void {}
};
