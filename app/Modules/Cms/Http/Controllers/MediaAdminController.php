<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Core\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Cms\Models\Media;
use App\Modules\Cms\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Biblioteca media: încărcare (imagini reencodate, fără metadate, fără SVG), text alternativ, ștergere doar
 * pentru imaginile nefolosite. ID-ul imaginii se folosește în blocurile paginilor.
 */
class MediaAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = Media::query();
        if ($folder = $request->query('dosar')) {
            $q->where('folder', $folder);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('original_name', 'like', '%'.addcslashes($term, '%_').'%')->orWhere('alt', 'like', '%'.addcslashes($term, '%_').'%')->orWhere('id', (int) $term));
        }

        return view('cms::panel.media', [
            'items' => $q->latest('id')->paginate(48)->withQueryString(),
            'folders' => Media::select('folder')->distinct()->pluck('folder'), 'filters' => $request->only('dosar', 'q'),
        ]);
    }

    public function upload(Request $request, MediaLibrary $library)
    {
        $request->validate(['fisiere' => ['required', 'array', 'max:20'], 'fisiere.*' => ['file', 'max:10240'], 'alt' => ['nullable', 'string', 'max:500'], 'dosar' => ['required', 'in:pagini,productie,general,catalog,lentile']],
            ['fisiere.required' => 'Alegeți cel puțin o imagine.']);
        $ids = [];
        foreach ($request->file('fisiere') as $file) {
            $media = $library->upload($file, $request->input('alt'), $request->input('dosar'));
            $media->update(['uploaded_by' => $request->user()->id]);
            $ids[] = $media->id;
        }
        Audit::log('cms.media_uploaded', null, ['ids' => $ids]);

        return back()->with('status', count($ids).' imagini încărcate (ID: '.implode(', ', $ids).'). Completați textul alternativ pentru fiecare.');
    }

    public function update(Request $request, int $id)
    {
        $media = Media::findOrFail($id);
        $data = $request->validate(['alt' => ['nullable', 'string', 'max:500'], 'legenda' => ['nullable', 'string', 'max:500']]);
        $media->update(['alt' => $data['alt'] ?? null, 'caption' => $data['legenda'] ?? null]);

        return back()->with('status', 'Imaginea a fost actualizată.');
    }

    public function destroy(int $id)
    {
        $media = Media::findOrFail($id);
        $used = ProductImage::where('media_id', $id)->exists()
            || DB::table('cms_page_versions')->where('blocks', 'like', '%'.$id.'%')->whereIn('id', DB::table('cms_pages')->select('published_version_id'))->exists();
        if ($used) {
            return back()->with('warning', 'Imaginea este folosită (produs sau pagină publicată) și nu poate fi ștearsă.');
        }
        foreach (array_merge([$media->path], array_values((array) ($media->variants['webp'] ?? []))) as $rel) {
            $full = rtrim(config('optoplus.media_path'), '/').'/'.ltrim((string) $rel, '/');
            if (is_file($full) && str_starts_with(realpath($full), realpath(config('optoplus.media_path')))) {
                @unlink($full);
            }
        }
        $media->delete();
        Audit::log('cms.media_deleted', null, ['id' => $id]);

        return back()->with('status', 'Imaginea a fost ștearsă.');
    }
}
