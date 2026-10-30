<?php

namespace App\Modules\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paginile publice au o singură formă de URL, cu „/” la final (ca pe siteul vechi: /produs/<slug>/), ca să nu
 * existe duplicate. /contact → 301 → /contact/. Panoul, API-urile, webhookurile și fișierele nu sunt atinse.
 */
class TrailingSlash
{
    private const SKIP = ['panou', 'webhook', 'api', 'build', 'media', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        if ($request->isMethodSafe() && $path !== '/' && ! str_ends_with($path, '/')
            && ! preg_match('/\.[a-z0-9]{2,5}$/i', $path)
            && ! in_array(explode('/', ltrim($path, '/'))[0], self::SKIP, true)) {
            $query = $request->getQueryString();

            return redirect()->away($request->getSchemeAndHttpHost().$path.'/'.($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
