<?php

namespace App\Modules\Production\Services;

use App\Core\Audit\Audit;
use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderStatus;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\ProductionStage;
use App\Modules\Production\Models\Remake;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fluxul de producție configurabil. Reguli fixe (nu se pot dezactiva din panou):
 *  - nicio lucrare nu trece de „verificare rețetă” fără aprobarea unui specialist optic (permisiunea
 *    production.approve), care rămâne consemnată (cine, când);
 *  - nicio lucrare nu intră în producție până când comanda nu este confirmată comercial (plată încasată sau
 *    confirmare explicită din panou);
 *  - fiecare schimbare (etapă, responsabil, termen, lot) se jurnalizează.
 */
class ProductionFlow
{
    public const APPROVAL_STAGE = 'aprobata_productie';

    public function __construct(private OrderStatus $orders) {}

    /** @return Collection<int, ProductionStage> */
    public function stages(bool $mounting = true): Collection
    {
        return ProductionStage::where('is_active', true)->orderBy('sort')->get()
            ->filter(fn (ProductionStage $s) => $mounting || ! $s->only_if_mounting)->values();
    }

    public function createJobs(Order $order): void
    {
        $n = 0;
        foreach ($order->items()->where('requires_production', true)->get() as $item) {
            $n++;
            $job = ProductionJob::create([
                'number' => $order->number.'-'.$n, 'order_id' => $order->id, 'order_item_id' => $item->id,
                'stage' => 'primita', 'requires_mounting' => ($item->snapshot['entry'] ?? null) === 'cu_rama',
                'due_on' => $order->production_days_max ? now()->addWeekdays($order->production_days_max)->toDateString() : null,
            ]);
            $job->events()->create(['to_stage' => 'primita', 'change' => 'etapa', 'note' => 'Lucrare creată la plasarea comenzii', 'created_at' => now()]);
        }
        if ($n > 0) {
            $this->orders->production($order, 'primita', 'Comanda a fost primită în laborator.', 'sistem');
        }
    }

    public function moveTo(ProductionJob $job, string $stage, User $user, ?string $note = null, ?int $expectedVersion = null): ProductionJob
    {
        return DB::transaction(function () use ($job, $stage, $user, $note, $expectedVersion) {
            $job = ProductionJob::whereKey($job->id)->lockForUpdate()->with('order')->first();
            if ($expectedVersion !== null && $job->version !== $expectedVersion) {
                throw ValidationException::withMessages(['stage' => 'Lucrarea a fost modificată între timp de altcineva. Reîncărcați pagina.']);
            }
            $stages = $this->stages($job->requires_mounting)->pluck('code')->all();
            $target = array_search($stage, $stages, true);
            if ($target === false) {
                throw ValidationException::withMessages(['stage' => 'Etapă necunoscută sau inactivă.']);
            }
            $approvalIndex = array_search(self::APPROVAL_STAGE, $stages, true);
            if ($stage === self::APPROVAL_STAGE) {
                if (! $user->can('production.approve')) {
                    throw ValidationException::withMessages(['stage' => 'Doar un specialist optic poate aproba lucrarea pentru producție.']);
                }
                $job->approved_by = $user->id;
                $job->approved_at = now();
            }
            if ($approvalIndex !== false && $target > $approvalIndex) {
                if (! $job->approved_by) {
                    throw ValidationException::withMessages(['stage' => 'Lucrarea nu a fost aprobată de un specialist optic.']);
                }
                if ($job->order->commercial_status !== 'confirmata') {
                    throw ValidationException::withMessages(['stage' => 'Comanda nu este confirmată (plata nu a fost încasată sau confirmată).']);
                }
            }
            $from = $job->stage;
            $job->stage = $stage;
            $job->version++;
            $job->save();
            $job->events()->create(['from_stage' => $from, 'to_stage' => $stage, 'change' => 'etapa', 'note' => $note ? mb_substr($note, 0, 500) : null, 'user_id' => $user->id, 'created_at' => now()]);
            Audit::log('production.stage', $job, ['from' => $from, 'to' => $stage], $job->order->customer_id, $user->id);
            $this->syncOrder($job->order);

            return $job;
        });
    }

    public function assign(ProductionJob $job, ?User $assignee, ?string $dueOn, ?string $lot, User $user): void
    {
        $changes = [];
        foreach (['assignee_id' => $assignee?->id, 'due_on' => $dueOn, 'lot_reference' => $lot] as $field => $value) {
            $current = $field === 'due_on' ? $job->due_on?->toDateString() : $job->{$field};
            if ($current != $value) {
                $changes[$field] = $value;
            }
        }
        if (! $changes) {
            return;
        }
        $job->update($changes + ['version' => $job->version + 1]);
        foreach (array_keys($changes) as $field) {
            $job->events()->create(['change' => ['assignee_id' => 'responsabil', 'due_on' => 'termen', 'lot_reference' => 'lot'][$field], 'note' => (string) $changes[$field], 'user_id' => $user->id, 'created_at' => now()]);
        }
    }

    /** Refacerea unei lucrări: lucrare nouă, legată de cea inițială; reintră în verificare. */
    public function remake(ProductionJob $job, string $reason, string $costBorneBy, ?string $details, User $user): ProductionJob
    {
        return DB::transaction(function () use ($job, $reason, $costBorneBy, $details, $user) {
            $count = ProductionJob::where('order_id', $job->order_id)->count();
            $new = ProductionJob::create([
                'number' => $job->order->number.'-R'.($count + 1), 'order_id' => $job->order_id, 'order_item_id' => $job->order_item_id,
                'stage' => 'verificare_reteta', 'requires_mounting' => $job->requires_mounting, 'remake_of_id' => $job->id,
                'priority' => 1, 'work_sheet' => $job->work_sheet,
            ]);
            Remake::create(['production_job_id' => $job->id, 'new_job_id' => $new->id, 'reason' => $reason, 'cost_borne_by' => $costBorneBy, 'details' => $details, 'created_by' => $user->id]);
            $new->events()->create(['to_stage' => 'verificare_reteta', 'change' => 'etapa', 'note' => 'Refacere: '.(Remake::REASONS[$reason] ?? $reason), 'user_id' => $user->id, 'created_at' => now()]);
            Audit::log('production.remake', $new, ['of' => $job->number, 'reason' => $reason], $job->order->customer_id, $user->id);
            $this->syncOrder($job->order);

            return $new;
        });
    }

    /** Starea de producție a comenzii = etapa cea mai puțin avansată dintre lucrările ei active. */
    public function syncOrder(Order $order): void
    {
        $order = $order->fresh();
        $order->setRelation('productionJobs', $order->productionJobs()->get());
        $codes = $this->stages()->pluck('code')->values()->all();
        $replaced = Remake::whereIn('production_job_id', $order->productionJobs->pluck('id'))->pluck('production_job_id')->all();
        $active = $order->productionJobs->reject(fn ($j) => in_array($j->id, $replaced, true));
        if ($active->isEmpty()) {
            return;
        }
        $min = $active->map(fn ($j) => array_search($j->stage, $codes, true))->filter(fn ($i) => $i !== false)->min();
        if ($min !== null) {
            $this->orders->production($order, $codes[$min], null, 'sistem');
        }
    }
}
