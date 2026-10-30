<?php

namespace App\Modules\Production\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configurator\Models\PrescriptionFile;
use App\Modules\Configurator\Services\PrescriptionStore;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\Remake;
use App\Modules\Production\Services\ProductionFlow;
use Illuminate\Http\Request;

/**
 * Producția în panou: tabla pe etape, fișa de lucru și acțiunile. Valorile rețetei apar doar pentru cine are
 * „prescriptions.view”, iar fiecare deschidere a fișei cu rețeta și fiecare descărcare de fișier se auditează.
 */
class ProductionController extends Controller
{
    public function index(Request $request, ProductionFlow $flow)
    {
        $stages = $flow->stages();
        $q = ProductionJob::query()->with('order:id,uuid,number,commercial_status,organization_id', 'assignee:id,name', 'item:id,name,patient_reference')
            ->whereHas('order', fn ($o) => $o->where('commercial_status', '!=', 'anulata'));
        if ($request->boolean('ale_mele')) {
            $q->where('assignee_id', $request->user()->id);
        }
        if ($stage = $request->query('etapa')) {
            $q->where('stage', $stage);
        } else {
            $q->where(fn ($w) => $w->where('stage', '!=', 'expediata')->orWhere('updated_at', '>', now()->subDays(3)));
        }
        $jobs = $q->orderBy('priority')->orderByRaw('due_on is null, due_on')->limit(400)->get()->groupBy('stage');

        return view('production::panel.board', ['stages' => $stages, 'jobs' => $jobs, 'filter' => $request->only('etapa', 'ale_mele')]);
    }

    public function review(Request $request, ProductionFlow $flow)
    {
        $request->query->set('etapa', 'verificare_reteta');

        return $this->index($request, $flow);
    }

    public function show(Request $request, int $id, ProductionFlow $flow)
    {
        $job = ProductionJob::with('order.organization', 'item.prescription.files', 'item.prescription.verifier', 'events.user', 'assignee', 'approver', 'remakeOf')->findOrFail($id);
        $canRx = $request->user()->can('prescriptions.view');
        if ($canRx && $job->item->prescription) {
            Audit::log('prescription.viewed', $job->item->prescription, ['context' => 'fisa_productie', 'job' => $job->number], $job->order->customer_id);
        }

        return view('production::panel.show', [
            'job' => $job, 'stages' => $flow->stages($job->requires_mounting), 'canRx' => $canRx,
            'users' => User::where('is_active', true)->permission('production.view')->orderBy('name')->pluck('name', 'id'),
            'remakes' => Remake::where('production_job_id', $job->id)->get(),
        ]);
    }

    public function move(Request $request, int $id, ProductionFlow $flow)
    {
        $job = ProductionJob::findOrFail($id);
        $data = $request->validate(['etapa' => ['required', 'string', 'max:30'], 'nota' => ['nullable', 'string', 'max:500'], 'versiune' => ['required', 'integer']]);
        if ($data['etapa'] === ProductionFlow::APPROVAL_STAGE) {
            abort_unless($request->user()->can('production.approve'), 403);
            $request->validate(['verificat' => ['accepted']], ['verificat.accepted' => 'Confirmați că ați verificat rețeta, lentila și măsurătorile.']);
        }
        $flow->moveTo($job, $data['etapa'], $request->user(), $data['nota'] ?? null, (int) $data['versiune']);
        if ($data['etapa'] === ProductionFlow::APPROVAL_STAGE && ($p = $job->item->prescription)) {
            $p->update(['verified_by' => $request->user()->id, 'verified_at' => now(), 'verification_note' => $data['nota'] ?? null]);
            Audit::log('prescription.verified', $p, ['job' => $job->number], $job->order->customer_id);
        }

        return back()->with('status', 'Lucrarea a fost mutată.');
    }

    public function assign(Request $request, int $id, ProductionFlow $flow)
    {
        $job = ProductionJob::findOrFail($id);
        $data = $request->validate(['responsabil' => ['nullable', 'integer'], 'termen' => ['nullable', 'date'], 'lot' => ['nullable', 'string', 'max:80'], 'prioritate' => ['required', 'in:1,2,3']]);
        $assignee = $data['responsabil'] ? User::where('is_active', true)->findOrFail($data['responsabil']) : null;
        $flow->assign($job, $assignee, $data['termen'] ?? null, $data['lot'] ?? null, $request->user());
        $job->update(['priority' => (int) $data['prioritate']]);

        return back()->with('status', 'Lucrarea a fost actualizată.');
    }

    public function remake(Request $request, int $id, ProductionFlow $flow)
    {
        $job = ProductionJob::findOrFail($id);
        $data = $request->validate(['motiv' => ['required', 'in:'.implode(',', array_keys(Remake::REASONS))], 'cost' => ['required', 'in:optoplus,client,partener,de_stabilit'], 'detalii' => ['nullable', 'string', 'max:2000']]);
        $new = $flow->remake($job, $data['motiv'], $data['cost'], $data['detalii'] ?? null, $request->user());

        return redirect()->route('panou.production.show', $new->id)->with('status', 'Refacerea '.$new->number.' a fost deschisă și intră din nou în verificare.');
    }

    /** Fișierul rețetei, din stocarea privată; nu are URL public. */
    public function file(string $uuid, PrescriptionStore $store)
    {
        $file = PrescriptionFile::where('uuid', $uuid)->with('prescription')->firstOrFail();
        Audit::log('prescription.file_downloaded', $file->prescription, ['file' => $file->uuid], $file->prescription->customer_id);

        return response($store->contents($file), 200, [
            'Content-Type' => $file->mime, 'Content-Disposition' => 'inline; filename="reteta-'.$file->prescription_id.'.'.($file->mime === 'application/pdf' ? 'pdf' : 'jpg').'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ]);
    }
}
