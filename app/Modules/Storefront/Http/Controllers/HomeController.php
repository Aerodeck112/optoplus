<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\PageRenderer;

class HomeController extends Controller
{
    public function __invoke(PageRenderer $renderer)
    {
        $page = Page::published()->where('path', '/')->first();
        abort_unless($page, 503, 'Prima pagină nu a fost publicată încă.');

        return $renderer->render($page);
    }
}
