<?php

namespace App\Modules\Configurator\Console;

use App\Core\Audit\Audit;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Configurator\Models\Prescription;
use App\Modules\Configurator\Services\PrescriptionStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retenția: șterge rețetele expirate care nu sunt legate de o comandă (cele din comenzi urmează retenția
 * documentelor comenzii) și configurațiile anonime abandonate. Lucrează în loturi limitate (cron).
 */
class PurgePrescriptions extends Command
{
    protected $signature = 'optoplus:prescriptions:purge {--limit=200} {--dry-run}';

    protected $description = 'Șterge rețetele expirate fără comandă și configurațiile anonime abandonate';

    public function handle(PrescriptionStore $store): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        $prescriptions = Prescription::query()
            ->whereNotNull('retention_until')->where('retention_until', '<', now()->toDateString())
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('order_items')->whereColumn('order_items.prescription_id', 'prescriptions.id'))
            ->with('files')->limit($limit)->get();

        $days = (int) config('optoplus.retention.guest_configuration_days', 60);
        $configs = Configuration::query()
            ->whereNull('customer_id')->where('status', '!=', 'in_comanda')->where('updated_at', '<', now()->subDays($days))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('cart_items')->whereColumn('cart_items.configuration_id', 'configurations.id'))
            ->limit($limit)->get();

        if ($dry) {
            $this->info("De șters: {$prescriptions->count()} rețete, {$configs->count()} configurații anonime.");

            return self::SUCCESS;
        }

        foreach ($configs as $cfg) {
            $p = $cfg->prescription;
            $cfg->delete();
            if ($p && ! $p->configurations()->exists() && ! DB::table('order_items')->where('prescription_id', $p->id)->exists()) {
                $prescriptions->push($p->load('files'));
            }
        }
        $count = 0;
        foreach ($prescriptions->unique('id') as $p) {
            foreach ($p->files as $file) {
                $store->delete($file);
            }
            $p->delete();
            $count++;
        }
        if ($count || $configs->count()) {
            Audit::log('prescriptions.purged', null, ['prescriptions' => $count, 'configurations' => $configs->count()]);
        }
        $this->info("Șterse: {$count} rețete, {$configs->count()} configurații anonime.");

        return self::SUCCESS;
    }
}
