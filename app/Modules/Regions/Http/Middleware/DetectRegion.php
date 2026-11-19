<?php

namespace App\Modules\Regions\Http\Middleware;

use App\Modules\I18n\Support\Locales;
use App\Modules\Regions\Services\GeoCountry;
use App\Modules\Regions\Services\Regions;
use App\Modules\Regions\Support\Countries;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * La prima vizită, siteul se deschide în limba (și valuta) țării vizitatorului: din Ungaria în maghiară, cu
 * prețuri în forinți; din altă țară decât România în engleză, cu prețuri în euro; din România în română, în lei.
 *
 * Doar o dată: alegerea se ține minte într-un cookie tehnic („optoplus_regiune”, fără date personale), iar
 * comutatorul de limbă funcționează apoi normal. Roboții motoarelor de căutare nu sunt redirecționați (fiecare
 * limbă își păstrează adresele, cu hreflang). Panoul, plățile și webhookurile nu sunt atinse.
 */
class DetectRegion
{
    public const COOKIE = 'optoplus_regiune';

    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|bingpreview|mediapartners|lighthouse|headless|curl|wget|python|monitor|preview/i';

    public function __construct(private Regions $regions, private GeoCountry $geo) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->applies($request)) {
            return $next($request);
        }
        $detected = $this->geo->detect($request)['country'];
        [$current, $path] = Locales::split('/'.ltrim($request->getPathInfo(), '/'));
        $target = $detected ? Countries::localeFor($detected) : null;
        $cookie = Cookie::create(self::COOKIE, $target ?? $current, now()->addYear(), '/', null, $request->isSecure(), true, false, 'Lax');

        if ($target !== null && $target !== $current) {
            $query = $request->getQueryString();
            $response = redirect(Locales::path($target, $path).($query !== null ? '?'.$query : ''), 302);
            $response->headers->set('Vary', 'Cookie');
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->setCookie($cookie);

            return $response;
        }
        $response = $next($request);
        $response->headers->setCookie($cookie);

        return $response;
    }

    private function applies(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->cookies->has(self::COOKIE) || ! $this->regions->autoDetect()) {
            return false;
        }
        if (preg_match(self::BOTS, (string) $request->userAgent()) || (string) $request->userAgent() === '') {
            return false;
        }
        if (! str_contains((string) $request->header('Accept'), 'text/html')) {
            return false;
        }
        $path = ltrim(Locales::split('/'.ltrim($request->getPathInfo(), '/'))[1], '/');

        return ! preg_match('#^(panou|portal|webhook|plati|api|build|media|up|comanda|cont/(resetare|confirmare)|robots\.txt|sitemap)#', $path);
    }
}
