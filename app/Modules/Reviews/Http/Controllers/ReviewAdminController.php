<?php

namespace App\Modules\Reviews\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Reviews\Models\Review;
use App\Modules\Storefront\Services\PageCache;
use Illuminate\Http\Request;

/** Clienți → Recenzii: publicare, respingere (cu motiv), răspunsul Optoplus, ștergere. Toate auditate. */
class ReviewAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('stare');
        $kind = $request->query('tip');
        $items = Review::with('product:id,name,slug,type')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($kind === 'magazin', fn ($q) => $q->whereNull('product_id'))
            ->when($kind === 'produse', fn ($q) => $q->whereNotNull('product_id'))
            ->latest()->paginate(30)->withQueryString();

        return view('reviews::panel.index', ['items' => $items, 'status' => $status, 'kind' => $kind,
            'counts' => Review::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')]);
    }

    public function update(Request $request, int $id, PageCache $cache)
    {
        $review = Review::findOrFail($id);
        $data = $request->validate([
            'actiune' => ['required', 'in:publica,respinge,noua,raspuns'],
            'motiv' => ['nullable', 'string', 'max:160'], 'raspuns' => ['nullable', 'string', 'max:2000'],
        ]);
        match ($data['actiune']) {
            'publica' => $review->update(['status' => 'publicata', 'reject_reason' => null]),
            'respinge' => $review->update(['status' => 'respinsa', 'reject_reason' => $data['motiv'] ?? null]),
            'noua' => $review->update(['status' => 'noua']),
            'raspuns' => $review->update(['reply' => trim((string) ($data['raspuns'] ?? '')) ?: null, 'replied_at' => trim((string) ($data['raspuns'] ?? '')) !== '' ? now() : null]),
        };
        $review->update(['moderated_by' => $request->user()->id, 'moderated_at' => now()]);
        $cache->flush();
        Audit::log('reviews.'.$data['actiune'], $review, ['stare' => $review->status]);

        return back()->with('status', match ($data['actiune']) {
            'publica' => 'Recenzia este publicată pe site.',
            'respinge' => 'Recenzia a fost respinsă (nu apare pe site).',
            'noua' => 'Recenzia a revenit la „nouă” (nu apare pe site).',
            'raspuns' => 'Răspunsul a fost salvat.',
        });
    }

    public function destroy(Request $request, int $id, PageCache $cache)
    {
        $review = Review::findOrFail($id);
        Audit::log('reviews.deleted', $review, ['produs' => $review->product_id, 'nota' => $review->rating]);
        $review->delete();
        $cache->flush();

        return back()->with('status', 'Recenzia a fost ștearsă.');
    }
}
