<?php

namespace App\Modules\Legal\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Legal\Models\ContractWithdrawal;
use Illuminate\Http\Request;

/** Declarațiile de retragere primite online, în panou (Comenzi → Retrageri din contract). */
class WithdrawalAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = ContractWithdrawal::with('order:id,uuid,number', 'handler:id,name')->latest('submitted_at');
        if ($status = $request->query('stare')) {
            $q->where('status', $status);
        }

        return view('legal::panel.withdrawals', ['items' => $q->paginate(30)->withQueryString(), 'status' => $status]);
    }

    public function update(Request $request, int $id)
    {
        $w = ContractWithdrawal::findOrFail($id);
        $data = $request->validate(['stare' => ['required', 'in:'.implode(',', array_keys(ContractWithdrawal::STATUSES))], 'nota' => ['nullable', 'string', 'max:2000']]);
        $w->update(['status' => $data['stare'], 'staff_note' => $data['nota'] ?? $w->staff_note, 'handled_by' => $request->user()->id]);
        Audit::log('legal.withdrawal_updated', $w, ['stare' => $data['stare']], $w->order?->customer_id);

        return back()->with('status', 'Declarația a fost actualizată.');
    }
}
