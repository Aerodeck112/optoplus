<?php

namespace App\Modules\B2B\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autentificarea partenerilor: pagină proprie, cu formularul care trimite la autentificarea clienților
 * (/cont/autentificare/), apoi revine în portal. Un cont fără organizație aprobată ajunge la /parteneri/.
 */
class PartnerLoginController
{
    public function show(Request $request)
    {
        $customer = Auth::guard('client')->user();
        if ($customer && $customer->activeMembership()) {
            return redirect('/portal/');
        }
        $intended = (string) $request->session()->get('url.intended', '');
        $path = parse_url($intended, PHP_URL_PATH) ?: '';
        $request->session()->put('client.intended', str_starts_with($path, '/portal/') && ! str_starts_with($path, '/portal/autentificare') ? $path : '/portal/');

        return response()->view('b2b::portal.login', ['loggedIn' => (bool) $customer])->header('Cache-Control', 'no-store, private');
    }
}
