<?php

namespace App\Modules\I18n\Console;

use App\Modules\I18n\Services\SiteScanner;
use Illuminate\Console\Command;

class ScanTranslations extends Command
{
    protected $signature = 'optoplus:i18n:scan {--limit=40 : Câte produse se parcurg la o rulare}';

    protected $description = 'Notează textele noi din conținutul siteului care nu au încă traducere (engleză, maghiară)';

    public function handle(SiteScanner $scanner): int
    {
        $found = $scanner->run(max(1, (int) $this->option('limit')));
        $this->info("Texte noi de tradus: {$found}.");

        return self::SUCCESS;
    }
}
