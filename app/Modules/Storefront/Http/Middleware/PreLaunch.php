<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Core\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * „Mod lucru” (site în pregătire): până la lansare, vizitatorii văd pagina de pregătire, cu datele de contact.
 * Panoul, webhookurile de plată și fișierele statice rămân accesibile; personalul autentificat (cu MFA) vede
 * siteul normal, ca să-l poată verifica. Răspunsul este 503 + Retry-After, ca motoarele de căutare să nu
 * scoată siteul din index. Se oprește din Panou → Setări → Lansare.
 */
class PreLaunch
{
    private const OPEN = ['panou', 'panou/*', 'webhook/*', 'plati/*', 'up', 'build/*', 'media/*', 'images/*', 'robots.txt', 'favicon.ico'];

    public function __construct(private Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->get('site.prelaunch') || $request->is(self::OPEN) || $this->isStaff($request)) {
            return $next($request);
        }

        return response()->view('storefront::prelaunch', [
            'phone' => $this->settings->get('contact.phone_b2c'),
            'email' => $this->settings->get('contact.email_b2c'),
            'address' => $this->settings->get('contact.address'),
            'hours' => $this->settings->get('contact.hours'),
            'message' => $this->settings->get('site.prelaunch_message'),
            'launch' => $this->settings->get('site.prelaunch_date'),
        ], 503, ['Retry-After' => '86400', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }

    private function isStaff(Request $request): bool
    {
        return Auth::guard('web')->check() && $request->hasSession() && $request->session()->get('mfa.passed');
    }
}
