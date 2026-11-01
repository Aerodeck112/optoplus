<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Audit\Audit;
use App\Core\Settings\Settings;
use App\Core\Support\Money;
use App\Http\Controllers\Controller;
use App\Modules\Payments\Gateways\BtIpayGateway;
use App\Modules\Settings\SettingsSchema;
use App\Modules\Settings\Services\ApplySettings;
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
        ]);
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
