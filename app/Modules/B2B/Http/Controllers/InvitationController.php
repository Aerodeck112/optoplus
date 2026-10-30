<?php

namespace App\Modules\B2B\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\B2B\Services\Invitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Acceptarea invitației: clientul se autentifică (sau își creează cont) cu adresa invitată. */
class InvitationController extends Controller
{
    public function show(Request $request, string $token, Invitations $invitations)
    {
        $inv = $invitations->find($token);
        if (! $inv) {
            return response()->view('b2b::portal.invitation', ['inv' => null], 410)->header('Cache-Control', 'no-store');
        }
        $customer = Auth::guard('client')->user();

        return response()->view('b2b::portal.invitation', ['inv' => $inv, 'token' => $token, 'customer' => $customer])->header('Cache-Control', 'no-store');
    }

    public function accept(Request $request, string $token, Invitations $invitations)
    {
        $inv = $invitations->find($token);
        abort_unless($inv, 410);
        $customer = Auth::guard('client')->user();
        if (! $customer) {
            $request->session()->put('client.intended', '/portal/invitatie/'.$token.'/');

            return redirect('/cont/autentificare/');
        }
        $invitations->accept($inv, $customer);

        return redirect('/portal/')->with('status', 'Bun venit în portalul pentru parteneri.');
    }
}
