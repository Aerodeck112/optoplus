<?php

namespace App\Modules\Cms;

use App\Core\Modules\ModuleServiceProvider as BaseProvider;
use App\Core\Modules\NavItem;
use App\Modules\Identity\Roles;

class ModuleServiceProvider extends BaseProvider
{
    public function key(): string
    {
        return 'cms';
    }

    public function label(): string
    {
        return 'Site (CMS)';
    }

    public function permissions(): array
    {
        return [
            'cms.edit' => 'Editează paginile, ghidurile și blocurile refolosibile (ciorne)',
            'cms.publish' => 'Publică, retrage și restaurează versiuni ale paginilor',
            'cms.legal' => 'Publică textele juridice (termeni, confidențialitate, cookie, retur)',
            'cms.media' => 'Administrează biblioteca media',
            'cms.menus' => 'Administrează meniurile siteului',
            'cms.faq' => 'Administrează și aprobă întrebările frecvente (inclusiv pentru asistent)',
            'cms.redirects' => 'Administrează redirecturile și vede URL-urile negăsite',
            'cms.settings' => 'Modifică datele de contact și ale firmei afișate pe site',
            'cms.submissions' => 'Consultă mesajele primite prin formularele siteului',
        ];
    }

    public function roleGrants(): array
    {
        $editorial = ['cms.edit', 'cms.publish', 'cms.media', 'cms.menus', 'cms.faq', 'cms.redirects'];

        return [
            Roles::EDITOR_SITE => $editorial,
            Roles::MANAGER => [...$editorial, 'cms.legal', 'cms.settings', 'cms.submissions'],
            Roles::VANZARI => ['cms.submissions'],
            Roles::ADMIN_TEHNIC => ['cms.redirects', 'cms.settings'],
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Pagini și ghiduri', 'panou.cms.pages.index', 'cms.edit', 'Site', 'file', 10),
            new NavItem('Blocuri refolosibile', 'panou.cms.blocks.index', 'cms.edit', 'Site', 'layers', 11),
            new NavItem('Întrebări frecvente', 'panou.cms.faq.index', 'cms.faq', 'Site', 'inbox', 12),
            new NavItem('Bibliotecă media', 'panou.cms.media.index', 'cms.media', 'Site', 'image', 13),
            new NavItem('Meniuri', 'panou.cms.menus.index', 'cms.menus', 'Site', 'menu', 14),
            new NavItem('Redirecturi și 404', 'panou.cms.redirects.index', 'cms.redirects', 'Site', 'link', 15),
            new NavItem('Date de contact și firmă', 'panou.cms.settings.edit', 'cms.settings', 'Site', 'settings', 16),
            new NavItem('Mesaje din site', 'panou.cms.submissions.index', 'cms.submissions', 'Activitate', 'inbox', 40),
        ];
    }
}
