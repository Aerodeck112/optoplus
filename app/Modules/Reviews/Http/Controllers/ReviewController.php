<?php

namespace App\Modules\Reviews\Http\Controllers;

use App\Core\Notifications\StaffAlert;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Reviews\Models\Review;
use App\Modules\Reviews\Services\Reviews;
use Illuminate\Http\Request;

/**
 * Recenzie trimisă de pe site, fără cont. Nu se publică direct: o verifică echipa (spam, limbaj, date personale).
 * Recenziile negative nu se resping pentru că sunt negative (ar induce în eroare clienții).
 */
class ReviewController extends Controller
{
    public function store(Request $request, Reviews $reviews)
    {
        if (filled($request->input('website'))) {
            // câmp-capcană completat → robot; răspundem la fel, fără să salvăm
            return back()->with('status', 'Mulțumim! Recenzia apare pe site după ce o verificăm.')->withFragment('recenzii');
        }
        $data = $request->validate([
            'produs' => ['nullable', 'integer'],
            'nota' => ['required', 'integer', 'between:1,5'],
            'titlu' => ['nullable', 'string', 'max:120'],
            'mesaj' => ['required', 'string', 'min:10', 'max:2000'],
            'nume' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'acord' => ['accepted'],
        ], [
            'nota.required' => 'Alegeți o notă de la 1 la 5 stele.',
            'mesaj.required' => 'Scrieți câteva cuvinte despre experiența dumneavoastră.',
            'mesaj.min' => 'Mesajul este prea scurt (cel puțin 10 caractere).',
            'nume.required' => 'Completați numele.',
            'email.email' => 'Adresa de email nu este validă.',
            'acord.accepted' => 'Bifați acordul pentru publicarea recenziei.',
        ]);
        if (preg_match_all('~https?://|www\.~i', $data['mesaj'].' '.($data['titlu'] ?? '')) > 1) {
            return back()->withInput()->withErrors(['mesaj' => 'Mesajul nu poate conține linkuri.'])->withFragment('recenzii');
        }
        $product = isset($data['produs']) ? Product::published()->find($data['produs']) : null;
        abort_if(isset($data['produs']) && ! $product, 404);

        $orderId = $reviews->verifiedOrder($data['email'] ?? null, $product);
        $review = Review::create([
            'product_id' => $product?->id, 'rating' => (int) $data['nota'], 'title' => $data['titlu'] ?? null,
            'body' => trim($data['mesaj']), 'name' => trim($data['nume']), 'email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
            'locale' => in_array(app()->getLocale(), ['ro', 'en', 'hu'], true) ? app()->getLocale() : 'ro',
            'verified' => $orderId !== null, 'order_id' => $orderId,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')) : null,
        ]);
        StaffAlert::send('Recenzie nouă de verificat', array_values(array_filter([
            ($product ? 'Produs: '.$product->name : 'Despre magazin (prima pagină)').' · '.str_repeat('★', $review->rating).str_repeat('☆', 5 - $review->rating),
            $review->name.($review->verified ? ' (cumpărare verificată)' : ''),
            mb_substr($review->body, 0, 400),
            'Recenzia apare pe site doar după ce o publicați din panou.',
        ])), url('/panou/recenzii?stare=noua'));

        return back()->with('status', 'Mulțumim! Recenzia apare pe site după ce o verificăm.')->with('review_sent', true)->withFragment('recenzii');
    }
}
