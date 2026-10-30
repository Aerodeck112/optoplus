<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Storefront\Services\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servește din cache paginile publice pentru vizitatorii anonimi, fără coș și fără mesaje în sesiune.
 * Nu se cache-uiesc niciodată: conturi, coș, checkout, configurații, B2B, previzualizări, căutări cu filtre.
 */
class CachePublicPages
{
    private const ALLOWED_QUERY = ['pagina'];

    public function __construct(private PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cacheable($request)) {
            return $next($request);
        }
        $key = $this->cache->key('/'.ltrim($request->path(), '/'), $request->query());
        if ($html = $this->cache->get($key)) {
            return response(str_replace([PageCache::TOKEN_MARK, PageCache::NONCE_MARK], [csrf_token(), app('csp-nonce')], $html), 200, [
                'Content-Type' => 'text/html; charset=UTF-8', 'X-Page-Cache' => 'HIT',
                'Cache-Control' => 'no-cache, private',
            ]);
        }
        $response = $next($request);
        if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $response->headers->has('X-No-Page-Cache')) {
            $token = csrf_token();
            $this->cache->put($key, str_replace([$token, app('csp-nonce')], [PageCache::TOKEN_MARK, PageCache::NONCE_MARK], (string) $response->getContent()));
            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }

    private function cacheable(Request $request): bool
    {
        return $this->cache->enabled()
            && $request->isMethod('GET')
            && array_diff(array_keys($request->query()), self::ALLOWED_QUERY) === []
            && ! Auth::guard('client')->check()
            && ! Auth::guard('web')->check()
            && ! $request->cookie('optoplus_cos')
            && ! $request->session()->has('status')
            && ! $request->session()->has('errors');
    }
}
