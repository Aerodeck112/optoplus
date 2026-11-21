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
 * La prima vizită, siteul PROPUNE limba (și valuta) țării vizitatorului: din Ungaria maghiara, cu prețuri în forinți;
 * din altă țară decât România engleza, cu prețuri în euro; din România româna, în lei.
 *
 * 1.0.22: nu mai redirecționează singur. Regulamentul (UE) 2018/302 (geo-blocking), art. 3 alin. (2), interzice
 * redirecționarea către altă versiune a interfeței pe criteriul locului de reședință fără consimțământul explicit al
 * clientului. Middleware-ul doar notează propunerea într-un cookie scurt („optoplus_sugestie”), iar pagina afișează
 * întrebarea; clientul alege. Doar o dată (cookie tehnic „optoplus_regiune”, fără date personale). Roboții, panoul,
 * plățile și webhookurile nu sunt atinse.
 */
class DetectRegion
{
    public const COOKIE = 'optoplus_regiune';

    public const SUGGESTION = 'optoplus_sugestie';

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

        $response = $next($request);
        $response->headers->setCookie($cookie);
        if ($target !== null && $target !== $current) {
            // propunerea, citită de JavaScript (bannerul „Vedeți siteul în …?”); expiră repede
            $response->headers->setCookie(Cookie::create(self::SUGGESTION, $target, now()->addHour(), '/', null, $request->isSecure(), false, false, 'Lax'));
        }

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
