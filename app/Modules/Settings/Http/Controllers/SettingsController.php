<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Audit\Audit;
use App\Core\Settings\Settings;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\Payments\Gateways\BtIpayGateway;
use App\Modules\Settings\SettingsSchema;
use App\Modules\Settings\Services\ApplySettings;
use App\Modules\Settings\Services\MailSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

/** Tabul „Setări”: fiecare secțiune are permisiunea ei; modificările se auditează (fără valorile secrete). */
class SettingsController extends Controller
{
    public function show(Request $request, Settings $settings, ?string $section = null)
    {
        $sections = array_filter(SettingsSchema::sections(), fn ($s) => $request->user()->can($s['permission']));
        abort_if(! $sections, 403);
        $section ??= array_key_first($sections);
        abort_unless(isset($sections[$section]), 404);

        return view('settings::index', [
            'sections' => $sections, 'section' => $section, 'def' => $sections[$section],
            'values' => $settings->all(), 'settings' => $settings,
            'btStatus' => $section === 'plati' ? app(BtIpayGateway::class)->unavailableReason() : null,
            'lastCardFailure' => $section === 'plati' ? \App\Modules\Payments\Models\Payment::where('method', 'card')->where('status', 'esuata')
                ->whereNotNull('failure_reason')->latest('id')->first(['provider', 'failure_reason', 'created_at']) : null,
            'stripeStatus' => $section === 'plati' ? app(\App\Modules\Payments\Gateways\StripeGateway::class)->unavailableReason() : null,
            'stripeMode' => $section === 'plati' ? app(\App\Modules\Payments\Gateways\StripeGateway::class)->mode() : null,
            'rates' => $section === 'valute' ? app(\App\Modules\Regions\Services\ExchangeRates::class) : null,
            'mailStatus' => $section === 'email' ? MailSettings::status($settings->all()) : null,
            'merchant' => $section === 'google' ? $this->merchantReport() : null,
        ]);
    }

    /** Starea fluxurilor Google Merchant, pentru tabul din Setări. */
    private function merchantReport(): array
    {
        $feed = app(\App\Modules\Merchant\Services\MerchantFeed::class);
        $out = [];
        foreach (\App\Modules\Merchant\Services\MerchantFeed::FEEDS as $key => $def) {
            $reason = $feed->unavailableReason($key);
            $data = $reason === null ? $feed->cachedBuild($key) : null;
            $out[$key] = $def + [
                'url' => \App\Modules\Merchant\Services\MerchantFeed::url($key), 'reason' => $reason,
                'items' => $data ? count($data['items']) : 0,
                'excluded' => $data ? collect($data['excluded'])->unique(fn ($x) => $x['product_id'].$x['reason'])->values()->all() : [],
                'last' => \App\Modules\Merchant\Services\MerchantFeed::lastFetch($key),
            ];
        }

        return $out;
    }

    public function save(Request $request, Settings $settings, ApplySettings $apply, string $section)
    {
        $def = SettingsSchema::sections()[$section] ?? abort(404);
        abort_unless($request->user()->can($def['permission']), 403);
        $changed = [];
        $errors = [];
        foreach (SettingsSchema::fields($section) as $key => $f) {
            [$label, $type] = $f;
            $required = ($f[2] ?? null) === 'required';
            $input = $request->input(str_replace('.', '__', $key));
            if ($key === 'merchant.verification' && is_string($input) && trim($input) !== '') {
                // se acceptă eticheta întreagă <meta name="google-site-verification" content="…"> sau doar codul
                $code = preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $input, $m) ? $m[1] : trim($input);
                if (! preg_match('/^[A-Za-z0-9_-]{10,120}$/', $code)) {
                    $errors[$key] = 'Codul de verificare Google nu este valid: copiați eticheta HTML din Merchant Center.';

                    continue;
                }
                $input = $code;
            }
            if ($type === 'secret') {
                if (is_string($input) && $input !== '') {
                    $settings->setSecret($key, $input);
                    $changed[] = $key;
                } elseif ($request->boolean(str_replace('.', '__', $key).'__sterge')) {
                    $settings->setSecret($key, null);
                    $changed[] = $key;
                }

                continue;
            }
            $value = match ($type) {
                'bool' => $request->boolean(str_replace('.', '__', $key)),
                'number' => $input === null || $input === '' ? null : (is_numeric($input) ? (int) $input : ($errors[$key] = "{$label}: introduceți un număr.")),
                'money' => $input === null || $input === '' ? null : (Money::parse($input) ?? ($errors[$key] = "{$label}: sumă invalidă.")),
                'percent' => $input === null || $input === '' ? null : (is_numeric(str_replace(',', '.', $input)) && (float) str_replace(',', '.', $input) >= 0 && (float) str_replace(',', '.', $input) <= 100
                    ? (int) round((float) str_replace(',', '.', $input) * 100) : ($errors[$key] = "{$label}: procent între 0 și 100.")),
                'email' => $input === null || $input === '' ? null : (filter_var($input, FILTER_VALIDATE_EMAIL) ? mb_strtolower($input) : ($errors[$key] = "{$label}: email invalid.")),
                'emails' => $input === null || trim((string) $input) === '' ? null : (count(preg_split('/[\s,;]+/', trim((string) $input), -1, PREG_SPLIT_NO_EMPTY)) === count(\App\Core\Notifications\StaffRecipients::parse((string) $input))
                    ? implode(', ', \App\Core\Notifications\StaffRecipients::parse((string) $input)) : ($errors[$key] = "{$label}: una dintre adrese nu este validă.")),
                'rate' => $input === null || trim((string) $input) === '' ? null : (\App\Modules\Regions\Services\ExchangeRates::parseRate((string) $input) !== null ? str_replace('.', ',', trim((string) $input)) : ($errors[$key] = "{$label}: curs invalid (ex. 4,9750).")),
                'url' => $input === null || $input === '' ? null : (preg_match('#^https://[a-z0-9.-]+(:\d+)?/?$#i', $input) ? rtrim($input, '/') : ($errors[$key] = "{$label}: adresă https:// fără cale.")),
                'select' => array_key_exists((string) $input, $f[3] ?? []) ? (string) $input : array_key_first($f[3] ?? []),
                default => $input === null ? null : mb_substr(trim(strip_tags((string) $input)), 0, $type === 'textarea' ? 2000 : 255),
            };
            if ($required && ($value === null || $value === '')) {
                $errors[$key] = "{$label}: câmp obligatoriu.";
            }
            if (isset($errors[$key])) {
                continue;
            }
            if (($settings->get($key)) !== $value) {
                $settings->set($key, $value);
                $changed[] = $key;
            }
        }
        if ($section === 'plati' && ($iban = $settings->get('company.iban'))) {
            $normalized = strtoupper(preg_replace('/\s+/', '', (string) $iban));
            if (! $this->validIban($normalized)) {
                $settings->set('company.iban', null);
                $settings->set('payments.bank_transfer_enabled', false);
                $errors['company.iban'] = 'IBAN-ul nu este valid (verificați cifrele de control). Transferul bancar a rămas inactiv.';
            } elseif ($normalized !== $iban) {
                $settings->set('company.iban', $normalized);
            }
        }
        if ($changed) {
            Audit::log('settings.updated', null, ['sectiune' => $section, 'chei' => $changed]);
            $apply->apply();
            if ($section === 'valute') {
                app(\App\Modules\Regions\Services\ExchangeRates::class)->forget();
                app(\App\Modules\Storefront\Services\PageCache::class)->flush();
            }
            if ($section === 'email') {
                MailSettings::refresh();
            }
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        return back()->with('status', $changed ? 'Setările au fost salvate.' : 'Nicio modificare.');
    }

    /** Verifică credențialele BT iPay cu o interogare de stare pentru o comandă inexistentă (nu creează plăți). */
    public function testBt(BtIpayGateway $gateway)
    {
        $result = $gateway->testConnection();

        return back()->with($result['ok'] ? 'status' : 'warning', $result['message']);
    }

    public function testStripe(\App\Modules\Payments\Gateways\StripeGateway $gateway)
    {
        $result = $gateway->testConnection();

        return back()->with($result['ok'] ? 'status' : 'warning', $result['message']);
    }

    /** Citește acum cursul BNR (altfel se citește automat, din cron, în zilele lucrătoare). */
    public function fetchRates(\App\Modules\Regions\Services\ExchangeRates $rates)
    {
        $result = $rates->fetchBnr();
        if ($result['ok']) {
            app(\App\Modules\Storefront\Services\PageCache::class)->flush();
            Audit::log('settings.rates_fetched', null, ['data' => $result['date']]);
        }

        return back()->with($result['ok'] ? 'status' : 'warning', $result['message']);
    }

    /** Descarcă acum baza DB-IP (țara după IP); altfel se descarcă automat, lunar. */
    public function updateGeo()
    {
        $result = \App\Modules\Regions\Services\GeoCountry::download();

        return back()->with($result['ok'] ? 'status' : 'warning', $result['message']);
    }

    /** Trimite un email de test la adresa utilizatorului conectat și arată exact ce răspunde serverul. */
    public function testMail(Request $request)
    {
        MailSettings::refresh();
        $to = $request->user()->email;
        try {
            Mail::raw("Acesta este un email de test trimis din panoul Optoplus.\n\nDacă l-ați primit, emailurile către clienți (confirmări de comandă, stări, resetări de parolă) vor ajunge la fel.", function ($m) use ($to) {
                $m->to($to)->subject('Email de test — Optoplus');
            });
            Audit::log('settings.mail_test', null, ['rezultat' => 'trimis']);

            return back()->with('status', 'Emailul de test a fost trimis la '.$to.'. Verificați și folderul Spam.'
                .(MailSettings::smtpEnabled(app(Settings::class)->all()) ? '' : ' Atenție: SMTP nu este activ, deci emailul a fost doar scris în jurnal, nu trimis.'));
        } catch (\Throwable $e) {
            Audit::log('settings.mail_test', null, ['rezultat' => 'eroare', 'tip' => $e::class]);

            return back()->with('warning', 'Emailul de test NU a putut fi trimis. Răspunsul serverului: '.mb_substr(strip_tags($e->getMessage()), 0, 300));
        }
    }

    private function validIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (! preg_match('/^RO\d{2}[A-Z]{4}[A-Z0-9]{16}$/', $iban)) {
            return false;
        }
        $num = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $ch) {
            $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $mod = 0;
        foreach (str_split($num, 7) as $chunk) {
            $mod = (int) (($mod.$chunk) % 97);
        }

        return $mod === 1;
    }
}
