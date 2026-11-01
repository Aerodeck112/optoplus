<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pornește „modul lucru” la aplicarea actualizării 1.0.2 (cerut de Optoplus: siteul rămâne în pregătire
 * până la lansare). Nu suprascrie o alegere făcută deja din panou.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'site.prelaunch')->exists()) {
            DB::table('settings')->insert(['key' => 'site.prelaunch', 'value' => json_encode(true), 'updated_at' => now()]);
        }
        Cache::forget('optoplus.settings');
    }

    public function down(): void
    {
        // Setarea rămâne; se schimbă din Panou → Setări → Lansare.
    }
};
