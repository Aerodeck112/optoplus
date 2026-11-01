<?php

namespace App\Modules\Import\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Import\Models\ImportRun;
use App\Modules\Import\Services\ImageDownloader;
use App\Modules\Import\Services\WooCommerce\Reconciler;
use App\Modules\Import\Services\WooCommerce\WooCommerceImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importul catalogului din panou: încărcarea unui export WooCommerce (CSV), simularea (nu scrie nimic), apoi
 * importul pe bucăți (pagina continuă singură), reconcilierea și imaginile. Reluarea nu creează duplicate.
 */
class ImportController extends Controller
{
    private const DISK = 'local';

    private const DIR = 'import';

    public function index()
    {
        return view('import::index', [
            'runs' => ImportRun::latest('id')->limit(15)->get(),
            'images' => ProductImage::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'files' => collect(Storage::disk(self::DISK)->files(self::DIR))->filter(fn ($f) => str_ends_with($f, '.csv'))->sortDesc()->values()->take(10),
            'bundled' => collect(glob(database_path('content/woocommerce/wc-product-export-*.csv')))->map(fn ($f) => basename($f))->all(),
        ]);
    }

    public function upload(Request $request, WooCommerceImporter $importer)
    {
        $request->validate(['fisier' => ['required', 'file', 'max:20480', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel']]);
        $name = now()->format('Ymd-His').'-'.Str::slug(pathinfo($request->file('fisier')->getClientOriginalName(), PATHINFO_FILENAME)).'.csv';
        $path = $request->file('fisier')->storeAs(self::DIR, $name, self::DISK);
        // Simularea: citește, potrivește și raportează, fără să scrie în catalog.
        $run = $importer->run(Storage::disk(self::DISK)->path($path), ['commit' => false, 'user_id' => $request->user()->id, 'urls' => $this->urls()]);
        Audit::log('import.simulated', $run, ['file' => $name]);

        return redirect()->route('panou.import.show', $run->id)->with('status', 'Simularea s-a încheiat. Verificați raportul; nimic nu a fost scris în catalog.');
    }

    public function show(int $id)
    {
        return view('import::run', ['run' => ImportRun::findOrFail($id)]);
    }

    /** Importul efectiv, câte un lot pe cerere; pagina se reîncarcă până la final. */
    public function commit(Request $request, WooCommerceImporter $importer)
    {
        $file = basename((string) $request->input('fisier'));
        $path = Storage::disk(self::DISK)->exists(self::DIR.'/'.$file) ? Storage::disk(self::DISK)->path(self::DIR.'/'.$file) : database_path('content/woocommerce/'.$file);
        abort_unless(is_file($path) && str_ends_with($path, '.csv'), 404);
        $deadline = microtime(true) + 15;
        do {
            $run = $importer->run($path, ['commit' => true, 'resume' => true, 'batch' => 20, 'max_batches' => 1, 'user_id' => $request->user()->id, 'urls' => $this->urls()]);
        } while ($run->status !== 'finalizat' && microtime(true) < $deadline);
        if ($run->status !== 'finalizat') {
            return response()->view('import::progress', ['run' => $run, 'file' => $file]);
        }
        Audit::log('import.committed', $run, ['file' => $file]);

        return redirect()->route('panou.import.show', $run->id)->with('status', 'Importul s-a încheiat. Imaginile noi se descarcă în fundal.');
    }

    public function retryImages()
    {
        $n = ProductImage::where('status', 'failed')->update(['status' => 'pending']);
        Audit::log('import.images_retry', null, ['count' => $n]);

        return back()->with('status', "{$n} imagini au fost puse din nou la coadă; cronul le procesează în fundal.");
    }

    public function processImages(ImageDownloader $images)
    {
        $r = $images->process(10);

        return back()->with('status', "Procesate acum: {$r['ready']} reușite, {$r['failed']} eșuate; rămase {$r['remaining']}.");
    }

    public function reconcile(Request $request, Reconciler $reconciler)
    {
        $file = basename((string) $request->input('fisier'));
        $path = Storage::disk(self::DISK)->exists(self::DIR.'/'.$file) ? Storage::disk(self::DISK)->path(self::DIR.'/'.$file) : database_path('content/woocommerce/'.$file);
        abort_unless(is_file($path), 404);

        return view('import::reconcile', ['checks' => $reconciler->reconcile($path, $this->urls()), 'file' => $file]);
    }

    private function urls(): ?string
    {
        return collect(glob(database_path('content/woocommerce/store-api-urls-*.json')))->sort()->last();
    }
}
