<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Models\Redirect;
use App\Modules\Cms\Services\PageRenderer;
use App\Modules\Storefront\Services\ProductShowcase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ruta de rezervă: pagini CMS publicate, apoi redirecturile individuale (301/410), apoi 404 util
 * (căutare, categorii, contact) și jurnalul URL-urilor negăsite, pentru redirecturi noi.
 */
class PageController extends Controller
{
    public function __invoke(Request $request, PageRenderer $renderer, ProductShowcase $showcase)
    {
        $path = Redirect::normalizePath('/'.$request->path());

        if ($request->isMethod('GET') && ($page = Page::published()->where('path', $path)->first())) {
            return $renderer->render($page);
        }

        $redirect = Redirect::where('source_path', $path)->where('is_active', true)->first();
        if ($redirect) {
            $redirect->increment('hits', 1, ['last_hit_at' => now()]);
            if ($redirect->status_code === 410) {
                return response()->view('storefront::errors.gone', [], 410);
            }

            return redirect($redirect->target, $redirect->status_code);
        }

        if ($request->isMethod('GET') && ! str_starts_with($path, '/panou')) {
            DB::table('cms_not_found_log')->upsert(
                [['path' => mb_substr($path, 0, 255), 'hits' => 1, 'last_referer' => mb_substr((string) $request->headers->get('referer'), 0, 255), 'last_seen_at' => now()]],
                ['path'], ['hits' => DB::raw('hits + 1'), 'last_referer' => DB::raw('VALUES(last_referer)'), 'last_seen_at' => DB::raw('VALUES(last_seen_at)')],
            );
        }

        return response()->view('storefront::errors.404', [
            'cards' => $showcase->frames(null, 4),
            'q' => trim(str_replace(['-', '/'], ' ', basename($path))),
        ], 404);
    }
}
