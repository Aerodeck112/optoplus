<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configurator\Models\Configuration;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Task;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;

/** Cererile primite (lead-uri) și sarcinile echipei. Fără valori de rețetă: rețeta se vede în producție. */
class CrmController extends Controller
{
    public function leads(Request $request)
    {
        $q = Lead::query()->with('owner:id,name');
        $status = $request->query('stare', 'deschise');
        $status === 'deschise' ? $q->whereIn('status', ['noua', 'contactat', 'calificat']) : ($status !== 'toate' ? $q->where('status', $status) : null);
        if ($source = $request->query('sursa')) {
            $q->where('source', $source);
        }
        if ($request->boolean('ale_mele')) {
            $q->where('owner_id', $request->user()->id);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($term, '%_').'%')->orWhere('email', $term)->orWhere('phone', 'like', '%'.addcslashes($term, '%_').'%'));
        }

        return view('crm::panel.leads', ['leads' => $q->latest('id')->paginate(40)->withQueryString(), 'status' => $status, 'filters' => $request->only('sursa', 'q', 'ale_mele')]);
    }

    public function lead(int $id)
    {
        $lead = Lead::with('owner', 'customer', 'notes', 'tasks.assignee')->findOrFail($id);
        $configuration = $lead->configuration_id ? Configuration::with('frameVariant.product', 'offering.product', 'offering.material')->find($lead->configuration_id) : null;
        $orders = $lead->customer_id ? Order::where('customer_id', $lead->customer_id)->latest('id')->limit(10)->get()
            : ($lead->email ? Order::where('email', $lead->email)->latest('id')->limit(10)->get() : collect());

        return view('crm::panel.lead', [
            'lead' => $lead, 'configuration' => $configuration, 'orders' => $orders,
            'users' => User::where('is_active', true)->permission('crm.manage')->orderBy('name')->pluck('name', 'id'),
            'authors' => User::whereIn('id', $lead->notes->pluck('created_by')->filter())->pluck('name', 'id'),
        ]);
    }

    public function storeLead(Request $request)
    {
        $data = $request->validate([
            'nume' => ['required', 'string', 'max:160'], 'telefon' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'],
            'sursa' => ['required', 'in:telefon,manual'], 'rezumat' => ['nullable', 'string', 'max:2000'],
        ]);
        $lead = Lead::create(['name' => $data['nume'], 'phone' => $data['telefon'] ?? null, 'email' => $data['email'] ?? null, 'source' => $data['sursa'], 'summary' => $data['rezumat'] ?? null, 'owner_id' => $request->user()->id]);
        Audit::log('crm.lead_created', $lead);

        return redirect()->route('panou.crm.lead', $lead->id);
    }

    public function updateLead(Request $request, int $id)
    {
        $lead = Lead::findOrFail($id);
        $data = $request->validate(['stare' => ['required', 'in:'.implode(',', array_keys(Lead::STATUSES))], 'responsabil' => ['nullable', 'integer', 'exists:users,id']]);
        $lead->update(['status' => $data['stare'], 'owner_id' => $data['responsabil'] ?? null]);
        Audit::log('crm.lead_updated', $lead, ['status' => $data['stare']]);

        return back()->with('status', 'Cererea a fost actualizată.');
    }

    public function note(Request $request, int $id)
    {
        $lead = Lead::findOrFail($id);
        $data = $request->validate(['text' => ['required', 'string', 'max:3000']], ['text.required' => 'Scrieți nota.']);
        $lead->notes()->create(['body' => $data['text'], 'created_by' => $request->user()->id]);
        if ($lead->status === 'noua') {
            $lead->update(['status' => 'contactat']);
        }

        return back()->with('status', 'Nota a fost adăugată.');
    }

    public function storeTask(Request $request, int $id)
    {
        $lead = Lead::findOrFail($id);
        $data = $request->validate(['titlu' => ['required', 'string', 'max:190'], 'termen' => ['nullable', 'date'], 'responsabil' => ['nullable', 'integer', 'exists:users,id']]);
        $lead->tasks()->create(['title' => $data['titlu'], 'due_at' => $data['termen'] ?? null, 'assignee_id' => $data['responsabil'] ?? $request->user()->id, 'created_by' => $request->user()->id]);

        return back()->with('status', 'Sarcina a fost creată.');
    }

    public function tasks(Request $request)
    {
        $q = Task::query()->with('subject', 'assignee:id,name')->whereNull('completed_at');
        if (! $request->boolean('toate')) {
            $q->where('assignee_id', $request->user()->id);
        }

        return view('crm::panel.tasks', ['tasks' => $q->orderByRaw('due_at is null, due_at')->paginate(50)->withQueryString()]);
    }

    public function completeTask(Request $request, int $id)
    {
        $task = Task::findOrFail($id);
        abort_unless($task->assignee_id === $request->user()->id || $request->user()->can('crm.manage'), 403);
        $task->update(['completed_at' => now()]);

        return back()->with('status', 'Sarcina a fost marcată ca făcută.');
    }
}
