<?php

namespace Tests\Feature\Crm;

use App\Core\Audit\AuditLog;
use App\Modules\Configurator\Services\PrescriptionStore;
use App\Modules\Crm\Models\Lead;
use App\Modules\Identity\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 1.0.18: rețeta trimisă prin formularul „Ajutor la alegerea lentilelor” se vede în pagina cererii din CRM,
 * doar pentru cine are dreptul „Consultă rețetele” (specialistul optic), cu audit. Date fictive.
 */
class LeadPrescriptionTest extends TestCase
{
    private function sendHelpRequest(): Lead
    {
        Storage::fake(PrescriptionStore::DISK);
        $this->post('/formular/ajutor/', [
            'name' => 'Persoană Fictivă', 'phone' => '0700 000 000', 'email' => 'ajutor@demo.optoplus.invalid', 'usage' => 'distanta',
            'message' => 'Nu știu ce lentile să aleg.', 'consent' => '1', 'consent_rx' => '1',
            'reteta' => UploadedFile::fake()->image('reteta.jpg', 900, 600),
        ])->assertSessionHasNoErrors();

        return Lead::where('source', 'ajutor_lentile')->latest('id')->firstOrFail();
    }

    public function test_help_request_keeps_the_prescription_and_the_specialist_opens_it_with_audit(): void
    {
        $lead = $this->sendHelpRequest();
        $this->assertNotNull($lead->prescription_id);
        $file = $lead->prescription->files()->firstOrFail();

        $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $this->get('/panou/crm/cereri')->assertOk()->assertSee('cu rețetă');
        $this->get('/panou/crm/cereri/'.$lead->id)->assertOk()->assertSee('Rețeta trimisă de client')->assertSee('Deschide rețeta')
            ->assertSee(route('panou.prescriptions.file', $file->uuid), false);
        $this->assertTrue(AuditLog::where('action', 'prescription.viewed')->exists());
        $this->get(route('panou.prescriptions.file', $file->uuid))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_sales_see_that_a_prescription_exists_but_not_its_content(): void
    {
        $lead = $this->sendHelpRequest();
        $file = $lead->prescription->files()->firstOrFail();
        $this->actingAsRole(Roles::VANZARI);
        $this->get('/panou/crm/cereri/'.$lead->id)->assertOk()->assertSee('Clientul a trimis o rețetă')->assertDontSee('Deschide rețeta');
        $this->get(route('panou.prescriptions.file', $file->uuid))->assertForbidden();
        $this->assertFalse(AuditLog::where('action', 'prescription.viewed')->exists());
    }

    public function test_migration_links_older_requests_from_their_summary(): void
    {
        $lead = $this->sendHelpRequest();
        $pid = $lead->prescription_id;
        $lead->update(['prescription_id' => null, 'summary' => 'Utilizare: Distanță · rețetă încărcată (#'.$pid.')']);
        $migration = require base_path('database/migrations/2026_11_20_000100_crm_lead_prescription.php');
        $migration->up();
        $this->assertSame($pid, (int) DB::table('crm_leads')->where('id', $lead->id)->value('prescription_id'));
    }
}
