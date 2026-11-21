<?php

namespace App\Modules\Legal\Http\Controllers;

use App\Core\Audit\Audit;
use App\Core\Notifications\StaffAlert;
use App\Http\Controllers\Controller;
use App\Modules\Legal\Models\ContractWithdrawal;
use App\Modules\Legal\Notifications\WithdrawalReceived;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Funcția de retragere din contract (OUG 34/2014 art. 11¹, introdus prin OUG 18/2026, aplicabil din 19.06.2026):
 *  1. „Retrageți-vă din contract aici” — consumatorul indică numele, comanda și emailul pentru confirmare;
 *  2. „Confirmați retragerea” — declarația se transmite;
 *  3. confirmarea de primire pleacă imediat pe email (suport durabil), cu conținutul declarației, data și ora.
 * Fără cont și fără autentificare. Dacă produsul nu are drept de retragere (ex. lentile făcute după rețetă), declarația
 * se primește oricum și echipa răspunde; nu respingem automat.
 */
class WithdrawalController extends Controller
{
    private const SESSION = 'retragere.declaratie';

    public function form(Request $request)
    {
        $draft = (array) $request->session()->get(self::SESSION, []);

        return response()->view('legal::withdrawal-form', [
            'draft' => $draft + ['comanda' => (string) $request->query('comanda', ''), 'email' => '', 'nume' => '', 'detalii' => ''],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:160'],
            'comanda' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'detalii' => ['nullable', 'string', 'max:2000'],
        ], [
            'nume.required' => 'Completați numele.', 'comanda.required' => 'Completați numărul comenzii (îl găsiți în emailul de confirmare, ex. OP-2026-00123).',
            'email.required' => 'Completați adresa de email la care primiți confirmarea.', 'email.email' => 'Adresa de email nu este validă.',
        ]);
        $request->session()->put(self::SESSION, array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data));

        return redirect('/retragere/confirmare/');
    }

    public function confirmPage(Request $request)
    {
        $draft = $request->session()->get(self::SESSION);
        if (! $draft) {
            return redirect('/retragere/');
        }

        return response()->view('legal::withdrawal-confirm', ['draft' => $draft])->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request, OrderStatus $status)
    {
        $draft = $request->session()->pull(self::SESSION);
        if (! $draft) {
            return redirect('/retragere/');
        }
        $reference = strtoupper(preg_replace('/\s+/', '', (string) $draft['comanda']));
        $order = Order::where('number', $reference)->where('email', mb_strtolower((string) $draft['email']))->first();
        $withdrawal = ContractWithdrawal::create([
            'uuid' => (string) Str::uuid(), 'order_id' => $order?->id, 'order_reference' => mb_substr((string) $draft['comanda'], 0, 60),
            'name' => $draft['nume'], 'email' => mb_strtolower((string) $draft['email']), 'details' => $draft['detalii'] ?? null,
            'locale' => in_array(app()->getLocale(), ['ro', 'en', 'hu'], true) ? app()->getLocale() : 'ro',
            'ip_hash' => $request->ip() ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')) : null,
            'submitted_at' => now(),
        ]);
        Audit::log('legal.withdrawal_received', $withdrawal, ['comanda' => $withdrawal->order_reference, 'gasita' => (bool) $order], $order?->customer_id);
        if ($order) {
            $status->note($order, 'Clientul a trimis online declarația de retragere din contract ('.$withdrawal->submitted_at->format('d.m.Y H:i').'). Verificați în Comenzi → Retrageri din contract.');
        }

        // Confirmarea de primire, pe email (suport durabil), cu conținutul declarației, data și ora transmiterii.
        try {
            Notification::route('mail', $withdrawal->email)->notifyNow((new WithdrawalReceived($withdrawal->id))->locale($withdrawal->locale));
            $withdrawal->update(['confirmation_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Confirmarea retragerii nu a putut fi trimisă', ['retragere' => $withdrawal->id, 'eroare' => $e::class]);
        }
        StaffAlert::send("Declarație de retragere: {$withdrawal->order_reference}", array_values(array_filter([
            "{$withdrawal->name} a trimis online declarația de retragere din contract pentru comanda {$withdrawal->order_reference}.",
            $order ? null : 'Atenție: comanda nu a fost găsită automat după număr și email — verificați manual.',
            $withdrawal->details ? 'Mesaj: '.mb_substr($withdrawal->details, 0, 500) : null,
            'Data și ora: '.$withdrawal->submitted_at->format('d.m.Y H:i').'. Rambursarea trebuie făcută în cel mult 14 zile de la această dată (dacă produsul are drept de retragere).',
        ])), url('/panou/retrageri'));

        return redirect('/retragere/trimisa/'.$withdrawal->uuid.'/');
    }

    public function done(string $uuid)
    {
        abort_unless(Str::isUuid($uuid), 404);
        $withdrawal = ContractWithdrawal::where('uuid', $uuid)->where('created_at', '>', now()->subDay())->firstOrFail();

        return response()->view('legal::withdrawal-done', ['withdrawal' => $withdrawal])->header('Cache-Control', 'no-store, private');
    }

    /** Formularul tip de retragere (anexa 1 lit. B din OUG 34/2014), de tipărit sau de trimis pe email. */
    public function modelForm()
    {
        return view('legal::withdrawal-model');
    }

    /** Notificarea armonizată privind garanția legală de conformitate (Regulamentul (UE) 2025/1960, anexa I). */
    public function guarantee()
    {
        return view('legal::guarantee');
    }
}
