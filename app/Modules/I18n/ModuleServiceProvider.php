<?php

namespace App\Modules\I18n;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\I18n\Services\Dictionary;
use App\Modules\I18n\Services\HtmlTranslator;
use App\Modules\Identity\Roles;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;

/** Siteul în mai multe limbi: dicționarul de traduceri, prefixele /en/ și /hu/, pagina „Traduceri” din panou. */
class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'i18n';
    }

    public function label(): string
    {
        return 'Traduceri';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(Dictionary::class);
        $this->app->singleton(HtmlTranslator::class);
    }

    public function boot(): void
    {
        parent::boot();
        // Emailurile către clienți pleacă în limba comenzii (notificarea setează limba; aici se traduce textul).
        Event::listen(MessageSending::class, Mail\TranslateOutgoingMail::class);
    }

    public function permissions(): array
    {
        return [
            'i18n.manage' => 'Vede și corectează traducerile siteului (engleză, maghiară)',
        ];
    }

    public function roleGrants(): array
    {
        return [
            Roles::MANAGER => ['i18n.manage'],
            Roles::EDITOR_SITE => ['i18n.manage'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Traduceri (EN, HU)', 'panou.i18n.index', 'i18n.manage', 'Site', 'globe', 17),
        ];
    }

    public function schedule(Schedule $schedule): void
    {
        // Textele noi din pagini și produse (ex. o ramă adăugată) apar în Traduceri ca „lipsă”.
        $schedule->command('optoplus:i18n:scan --limit=40')->dailyAt('03:40')->withoutOverlapping(30);
    }
}
