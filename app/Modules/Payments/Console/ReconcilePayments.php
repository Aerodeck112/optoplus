<?php

namespace App\Modules\Payments\Console;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Console\Command;

/** Interoghează procesatorul pentru plățile cu cardul rămase în așteptare (notificare pierdută). */
class ReconcilePayments extends Command
{
    protected $signature = 'optoplus:payments:reconcile {--limit=50}';

    protected $description = 'Reconciliază plățile cu cardul în așteptare, prin interogare la procesator';

    public function handle(PaymentService $service): int
    {
        $payments = Payment::where('method', 'card')->whereIn('status', ['initiata', 'in_asteptare', 'autorizata'])
            ->where('created_at', '<', now()->subMinutes(10))->where('created_at', '>', now()->subDays(7))
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(15)))
            ->orderBy('id')->limit((int) $this->option('limit'))->get();
        $done = 0;
        foreach ($payments as $p) {
            $done += $service->reconcile($p) === 'procesat' ? 1 : 0;
        }
        $this->info("Verificate: {$payments->count()}, actualizate: {$done}.");

        return self::SUCCESS;
    }
}
