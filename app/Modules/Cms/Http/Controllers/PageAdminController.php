<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Core\Audit\Audit;
use App\Core\Support\Text;
use App\Http\Controllers\Controller;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Models\PageVersion;
use App\Modules\Cms\Models\ReusableBlock;
use App\Modules\Cms\Services\BlockRegistry;
use App\Modules\Cms\Services\PageRenderer;
use App\Modules\Cms\Services\PageService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Paginile siteului în panou: ciorne, previzualizare, publicare, istoric și restaurare. Editorul lucrează pe
 * blocuri tipizate (BlockRegistry); nu acceptă cod. Textele juridice se publică doar cu permisiunea „cms.legal”.
 */
class PageAdminController extends Controller
{
    public function index(Request $request)
    {
        $kind = $request->query('tip');

        return view('cms::panel.pages', [
            'pages' => Page::with('publishedVersion:id,created_at', 'currentVersion:id,created_at,note')->when($kind, fn ($q) => $q->where('kind', $kind))->orderBy('kind')->orderBy('path')->get(),
            'kind' => $kind,
        ]);
    }

    public function create(Request $request, PageService $svc)
    {
        $data = $request->validate(['titlu' => ['required', 'string', 'max:255'], 'tip' => ['required', 'in:page,guide'], 'cale' => ['nullable', 'string', 'max:200']]);
        $path = $data['cale'] ?: ($data['tip'] === 'guide' ? '/ghiduri/' : '/').Text::slug($data['titlu']).'/';
        $path = '/'.trim($path, '/').'/';
        if (! preg_match('#^/[a-z0-9/-]+/$#', $path) || Page::where('path', $path)->exists()) {
            throw ValidationException::withMessages(['cale' => 'Calea este invalidă sau există deja.']);
        }
        $page = Page::create(['path' => $path, 'kind' => $data['tip'], 'title' => $data['titlu'], 'status' => 'draft', 'created_by' => $request->user()->id]);
        $svc->saveDraft($page, $data['titlu'], [['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p></p>']]], [], 'Pagină nouă');

        return redirect()->route('panou.cms.pages.edit', $page->id);
    }

    public function edit(int $id, ?int $versionId = null)
    {
        $page = Page::with('currentVersion', 'publishedVersion')->findOrFail($id);
        $version = $versionId ? $page->versions()->findOrFail($versionId) : $page->currentVersion;

        return view('cms::panel.page', [
            'page' => $page, 'version' => $version, 'types' => BlockRegistry::types(),
            'versions' => $page->versions()->latest('id')->limit(30)->get(['id', 'title', 'note', 'created_at', 'created_by']),
            'reusable' => ReusableBlock::orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function save(Request $request, int $id, PageService $svc)
    {
        $page = Page::findOrFail($id);
        $data = $request->validate([
            'titlu' => ['required', 'string', 'max:255'], 'blocks' => ['array'], 'blocks.*.type' => ['required', 'string'],
            'seo_titlu' => ['nullable', 'string', 'max:255'], 'seo_descriere' => ['nullable', 'string', 'max:320'], 'noindex' => ['nullable', 'boolean'],
            'nota' => ['nullable', 'string', 'max:255'], 'lock' => ['required', 'integer'], 'bloc_nou' => ['nullable', 'string'],
        ]);
        // validate() păstrează doar cheile cu reguli; conținutul blocurilor se ia întreg și se curăță de BlockRegistry.
        $blocks = array_values(array_filter((array) $request->input('blocks', []), fn ($b) => is_array($b) && empty($b['_sterge'])));
        // Mutarea blocurilor (sus/jos) și adăugarea unui bloc nou, fără JavaScript.
        if (preg_match('/^(sus|jos):(\d+)$/', (string) $request->input('actiune'), $m)) {
            $i = (int) $m[2];
            $j = $m[1] === 'sus' ? $i - 1 : $i + 1;
            if (isset($blocks[$i], $blocks[$j])) {
                [$blocks[$i], $blocks[$j]] = [$blocks[$j], $blocks[$i]];
            }
        }
        if (! empty($data['bloc_nou']) && BlockRegistry::exists($data['bloc_nou'])) {
            $blocks[] = ['type' => $data['bloc_nou'], 'data' => []];
        }
        $blocks = array_map(fn ($b) => ['type' => $b['type'], 'data' => $this->dropEmptyListItems((array) ($b['data'] ?? []))], $blocks);
        $svc->saveDraft($page, $data['titlu'], $blocks, ['title' => $data['seo_titlu'] ?? null, 'description' => $data['seo_descriere'] ?? null, 'noindex' => (bool) ($data['noindex'] ?? false)], $data['nota'] ?? null, (int) $data['lock']);
        Audit::log('cms.page_draft', $page);

        return redirect()->route('panou.cms.pages.edit', $page->id)->with('status', 'Ciorna a fost salvată. Pagina publică nu s-a schimbat încă.');
    }

    public function preview(int $id, int $versionId, PageRenderer $renderer)
    {
        $page = Page::findOrFail($id);
        $version = $page->versions()->findOrFail($versionId);

        return response($renderer->render($page, $version, true))->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    public function publish(Request $request, int $id, PageService $svc)
    {
        $page = Page::findOrFail($id);
        if (($page->kind === 'policy' || $page->needs_legal_review) && ! $request->user()->can('cms.legal')) {
            abort(403, 'Textele juridice se publică doar de cine are permisiunea „cms.legal”.');
        }
        $version = $request->filled('versiune') ? $page->versions()->findOrFail((int) $request->input('versiune')) : null;
        if ($page->kind === 'policy') {
            $request->validate(['verificat' => ['accepted']], ['verificat.accepted' => 'Confirmați că ați verificat textul juridic.']);
        }
        $svc->publish($page, $version);
        if ($page->kind === 'policy') {
            $page->update(['needs_legal_review' => false]);
        }
        Audit::log('cms.page_published', $page, ['version' => $version?->id ?? $page->current_version_id]);

        return back()->with('status', 'Pagina a fost publicată.');
    }

    public function unpublish(int $id, PageService $svc)
    {
        $page = Page::findOrFail($id);
        abort_if($page->kind === 'home', 422, 'Prima pagină nu se poate retrage.');
        $svc->unpublish($page);
        Audit::log('cms.page_unpublished', $page);

        return back()->with('status', 'Pagina a fost retrasă (răspunde cu 404 până la republicare).');
    }

    public function restore(int $id, int $versionId, PageService $svc)
    {
        $page = Page::findOrFail($id);
        $svc->restore($page, $page->versions()->findOrFail($versionId));
        Audit::log('cms.page_restored', $page, ['from_version' => $versionId]);

        return redirect()->route('panou.cms.pages.edit', $page->id)->with('status', 'Versiunea a fost copiată ca ciornă nouă. Publicați-o pentru a o pune pe site.');
    }

    /** Elementele de listă complet goale (rândurile libere din formular) nu se salvează. */
    private function dropEmptyListItems(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v) && array_is_list($v)) {
                $data[$k] = array_values(array_filter($v, fn ($item) => ! is_array($item) || trim(implode('', array_map(fn ($x) => is_array($x) ? json_encode($x) : (string) $x, $item))) !== ''));
            } elseif (is_array($v)) {
                $data[$k] = $this->dropEmptyListItems($v);
            }
        }

        return $data;
    }
}
