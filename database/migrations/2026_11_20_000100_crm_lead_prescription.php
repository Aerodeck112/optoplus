<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1.0.18: cererea din CRM păstrează legătura cu rețeta trimisă de client (formularul „Ajutor la alegerea
 * lentilelor” sau „nu înțeleg rețeta” din configurator), ca specialistul să o deschidă din pagina cererii.
 * Cererile existente se leagă din rezumat („rețetă încărcată (#123)”) sau din configurația lor. Doar Schema/DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_leads', 'prescription_id')) {
            Schema::table('crm_leads', function (Blueprint $t) {
                $t->foreignId('prescription_id')->nullable()->after('configuration_id')->constrained('prescriptions')->nullOnDelete();
            });
        }
        foreach (DB::table('crm_leads')->whereNull('prescription_id')->where('source', 'ajutor_lentile')->where('summary', 'like', '%(#%')->get(['id', 'summary']) as $lead) {
            if (preg_match('/\(#(\d+)\)/u', (string) $lead->summary, $m) && DB::table('prescriptions')->where('id', (int) $m[1])->exists()) {
                DB::table('crm_leads')->where('id', $lead->id)->update(['prescription_id' => (int) $m[1]]);
            }
        }
        foreach (DB::table('crm_leads')->whereNull('prescription_id')->whereNotNull('configuration_id')->get(['id', 'configuration_id']) as $lead) {
            $pid = DB::table('configurations')->where('id', $lead->configuration_id)->value('prescription_id');
            if ($pid) {
                DB::table('crm_leads')->where('id', $lead->id)->update(['prescription_id' => $pid]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_leads', 'prescription_id')) {
            Schema::table('crm_leads', function (Blueprint $t) {
                $t->dropConstrainedForeignId('prescription_id');
            });
        }
    }
};
