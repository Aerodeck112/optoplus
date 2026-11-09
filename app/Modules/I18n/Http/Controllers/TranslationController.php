<?php

namespace App\Modules\I18n\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\I18n\Services\Dictionary;
use App\Modules\I18n\Services\SiteScanner;
use App\Modules\I18n\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Panou → Site → Traduceri: textele siteului în engleză și maghiară, de verificat și corectat. */
class TranslationController extends Controller
{
    public const STATUSES = ['lipsa' => 'Lipsă', 'automat' => 'De verificat', 'import' => 'Din siteul vechi', 'verificat' => 'Verificată'];

    public function index(Request $request)
    {
        $locale = in_array($request->query('limba'), Locales::translated(), true) ? $request->query('limba') : 'en';
        $status = array_key_exists((string) $request->query('stare'), self::STATUSES) ? $request->query('stare') : null;
        $q = trim((string) $request->query('q'));
        $rows = DB::table('translations')->where('locale', $locale)
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('source', 'like', '%'.$q.'%')->orWhere('target', 'like', '%'.$q.'%')))
            ->orderByRaw("FIELD(status, 'lipsa', 'automat', 'import', 'verificat')")->orderBy('id')
            ->paginate(40)->withQueryString();
        $counts = DB::table('translations')->where('locale', $locale)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('i18n::index', ['rows' => $rows, 'locale' => $locale, 'status' => $status, 'q' => $q, 'counts' => $counts]);
    }

    public function update(Request $request, int $id)
    {
        $row = DB::table('translations')->where('id', $id)->first() ?? abort(404);
        $data = $request->validate(['traducere' => ['nullable', 'string', 'max:5000'], 'verificat' => ['nullable', 'boolean']]);
        $target = trim((string) ($data['traducere'] ?? ''));
        preg_match_all('/\{\d+\}|\{t\}/', $row->source, $need);
        preg_match_all('/\{\d+\}|\{t\}/', $target, $have);
        if ($target !== '' && array_diff($need[0], $have[0])) {
            return back()->withErrors(['traducere' => 'Traducerea trebuie să păstreze marcajele '.implode(', ', array_unique($need[0])).' (acolo apar cifrele sau numele).']);
        }
        DB::table('translations')->where('id', $id)->update([
            'target' => $target === '' ? null : $target,
            'status' => $target === '' ? 'lipsa' : (($data['verificat'] ?? false) ? 'verificat' : 'automat'),
            'updated_by' => $request->user()->id, 'updated_at' => now(),
        ]);
        Dictionary::forget();
        Audit::log('i18n.translation_updated', null, ['id' => $id, 'limba' => $row->locale]);

        return back()->with('status', 'Traducerea a fost salvată.');
    }

    public function scan(SiteScanner $scanner)
    {
        $found = $scanner->run(120);

        return back()->with('status', $found ? "Am găsit {$found} texte noi de tradus." : 'Nu am găsit texte noi de tradus.');
    }
}
