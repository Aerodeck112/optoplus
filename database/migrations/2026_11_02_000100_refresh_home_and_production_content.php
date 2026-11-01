<?php

use App\Modules\Cms\Services\ContentRefresh;
use Illuminate\Database\Migrations\Migration;

/**
 * 1.0.5: video pe pagina de producție, rame animate și fotografii luminoase pe prima pagină, mențiunea YouTube
 * în politica de cookie-uri (ciornă). Vezi ContentRefresh.
 *
 * Actualizatorul rulează migrările cu codul versiunii vechi încărcat în memorie, deci autoloaderul nu cunoaște
 * clasele noi: încărcăm explicit fișierul din pachetul nou (aflat lângă această migrare).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(ContentRefresh::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Cms/Services/ContentRefresh.php';
        }
        $refresh = new ContentRefresh;
        $refresh->home();
        $refresh->production();
        $refresh->cookies();
    }

    public function down(): void {}
};
