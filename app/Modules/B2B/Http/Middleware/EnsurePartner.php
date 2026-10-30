<?php

namespace App\Modules\B2B\Http\Middleware;

use App\Modules\B2B\Services\Partner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Portalul partenerilor: doar membri acceptați ai unei organizații aprobate. Răspunsurile nu se cache-uiesc. */
class EnsurePartner
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $customer = Auth::guard('client')->user();
        if (! $customer) {
            return redirect()->guest('/cont/autentificare/');
        }
        $membership = $customer->activeMembership();
        if (! $membership) {
            return redirect('/parteneri/')->with('status', 'Contul dumneavoastră nu are acces la portalul pentru parteneri. Trimiteți o cerere de parteneriat sau cereți o invitație de la administratorul organizației.');
        }
        $partner = new Partner($customer, $membership);
        if ($ability && ! $partner->can($ability)) {
            abort(403, 'Rolul dumneavoastră în organizație nu permite această acțiune.');
        }
        app()->instance(Partner::class, $partner);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
