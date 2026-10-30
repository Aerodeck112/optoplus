<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Configurator\Services\ConfigurationService;
use App\Modules\Customers\Models\Consent;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\LegacyPasswordVerifier;
use App\Modules\Shop\Models\Cart;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Autentificarea clienților (garda „client”), separată de personal. Parolele migrate din WordPress se verifică
 * o dată și se re-hash-uiesc nativ; mesajele nu dezvăluie dacă un email are cont.
 */
class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $this->rememberReturn($request);

        return response()->view('customers::auth.login')->header('Cache-Control', 'no-store, private');
    }

    public function login(Request $request, LegacyPasswordVerifier $legacy, ConfigurationService $configurations)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'parola' => ['required', 'string', 'max:200']]);
        $key = 'client-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Prea multe încercări. Reîncercați peste '.ceil(RateLimiter::availableIn($key) / 60).' minute.']);
        }
        $customer = Customer::where('email', Str::lower($data['email']))->where('is_active', true)->whereNull('anonymized_at')->first();
        $ok = false;
        if ($customer && $customer->password) {
            $ok = Hash::check($data['parola'], $customer->password);
        } elseif ($customer && $customer->legacy_password_hash && $legacy->supports($customer->legacy_password_hash)) {
            $ok = $legacy->check($data['parola'], $customer->legacy_password_hash);
            if ($ok) {
                // Re-hash nativ la prima autentificare; hash-ul vechi dispare.
                $customer->forceFill(['password' => $data['parola'], 'legacy_password_hash' => null])->save();
                Audit::log('customer.legacy_password_migrated', $customer, [], $customer->id);
            }
        }
        if (! $ok) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['email' => 'Emailul sau parola nu sunt corecte.']);
        }
        RateLimiter::clear($key);
        $this->signIn($request, $customer, $request->boolean('tine_minte'), $configurations);

        return redirect($this->returnUrl($request));
    }

    public function showRegister(Request $request)
    {
        $this->rememberReturn($request);

        return response()->view('customers::auth.register')->header('Cache-Control', 'no-store, private');
    }

    public function register(Request $request, ConfigurationService $configurations)
    {
        $data = $request->validate([
            'prenume' => ['required', 'string', 'max:100'],
            'nume' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'telefon' => ['nullable', 'string', 'max:40'],
            'parola' => ['required', 'confirmed', PasswordRule::min(10)],
            'acord_termeni' => ['accepted'],
            'marketing' => ['nullable', 'boolean'],
        ], ['acord_termeni.accepted' => 'Pentru a crea contul trebuie să acceptați termenii și politica de confidențialitate.']);
        $email = Str::lower($data['email']);
        if (Customer::where('email', $email)->exists()) {
            // Nu confirmăm existența contului: trimitem un link de setare a parolei pe adresa respectivă.
            Password::broker('customers')->sendResetLink(['email' => $email]);

            return redirect('/cont/autentificare/')->with('status', 'Dacă adresa are deja un cont, v-am trimis pe email un link pentru setarea parolei.');
        }
        $customer = new Customer([
            'email' => $email, 'first_name' => $data['prenume'], 'last_name' => $data['nume'], 'phone' => $data['telefon'] ?? null,
            'marketing_consent_at' => ! empty($data['marketing']) ? now() : null, 'marketing_consent_source' => ! empty($data['marketing']) ? 'inregistrare' : null,
        ]);
        $customer->forceFill(['password' => $data['parola']])->save();
        Consent::record($customer->id, 'termeni', true, 'cont');
        if (! empty($data['marketing'])) {
            Consent::record($customer->id, 'marketing', true, 'cont');
        }
        Audit::log('customer.registered', $customer, [], $customer->id);
        $this->signIn($request, $customer, false, $configurations);

        return redirect($this->returnUrl($request))->with('status', 'Contul a fost creat.');
    }

    public function showForgot()
    {
        return response()->view('customers::auth.forgot')->header('Cache-Control', 'no-store, private');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);
        Password::broker('customers')->sendResetLink(['email' => Str::lower($request->input('email'))]);

        return back()->with('status', 'Dacă adresa are un cont, v-am trimis un link pentru setarea parolei (valabil 60 de minute).');
    }

    public function showReset(Request $request, string $token)
    {
        return response()->view('customers::auth.reset', ['token' => $token, 'email' => (string) $request->query('email')])->header('Cache-Control', 'no-store, private');
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'parola' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);
        $status = Password::broker('customers')->reset(
            ['email' => Str::lower($request->input('email')), 'password' => $request->input('parola'), 'password_confirmation' => $request->input('parola_confirmation'), 'token' => $request->input('token')],
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => $password, 'legacy_password_hash' => null, 'remember_token' => Str::random(60)])->save();
                Audit::log('customer.password_reset', $customer, [], $customer->id);
                event(new PasswordReset($customer));
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Linkul nu mai este valabil. Cereți unul nou.']);
        }

        return redirect('/cont/autentificare/')->with('status', 'Parola a fost setată. Vă puteți autentifica.');
    }

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function signIn(Request $request, Customer $customer, bool $remember, ConfigurationService $configurations): void
    {
        $configs = (array) $request->session()->get('configuratii', []);
        $cart = $request->session()->get('cos_uuid');
        Auth::guard('client')->login($customer, $remember);
        $request->session()->regenerate();
        $request->session()->put('configuratii', $configs);
        $configurations->claimForCustomer($request, $customer);
        if ($cart) {
            Cart::where('uuid', $cart)->whereNull('customer_id')->update(['customer_id' => $customer->id]);
            $request->session()->put('cos_uuid', $cart);
        }
        $customer->forceFill(['last_login_at' => now()])->save();
    }

    private function rememberReturn(Request $request): void
    {
        $to = (string) $request->query('inapoi', '');
        if ($this->isSafeReturn($to)) {
            $request->session()->put('client.intended', $to);
        }
    }

    private function returnUrl(Request $request): string
    {
        $to = (string) ($request->session()->pull('client.intended') ?: $request->session()->pull('url.intended', ''));
        $path = parse_url($to, PHP_URL_PATH) ?: '';

        return $this->isSafeReturn($path) ? $path : '/cont/';
    }

    /** Doar căi locale (fără redirecționare deschisă către alt domeniu). */
    private function isSafeReturn(string $to): bool
    {
        return $to !== '' && str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\') && ! str_starts_with($to, '/panou');
    }
}
