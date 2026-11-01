<?php

namespace App\Modules\Cms\Services;

use App\Core\Audit\Audit;
use App\Modules\Cms\Models\Media;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Models\PageVersion;
use Illuminate\Support\Facades\DB;

/**
 * Actualizări de conținut livrate odată cu o versiune (ex. 1.0.5: video, rame animate, fotografii luminoase).
 * O pagină pe care echipa a editat-o în panou NU este suprascrisă: varianta nouă rămâne ciornă, de publicat
 * sau nu din Site → Pagini. Se publică direct doar paginile încă în forma livrată inițial.
 *
 * IMPORTANT: clasa rulează și din migrare, în timpul actualizării, când în memorie este încă încărcat codul
 * versiunii VECHI (actualizatorul aplică migrările în procesul curent). De aceea depinde doar de modele care
 * există neschimbate de la 1.0.0 (Page, PageVersion, Media, Audit) și scrie versiunile direct, fără
 * PageService/BlockRegistry (care, în versiunea veche, nu cunosc blocurile noi și le-ar elimina).
 * Conținutul adăugat aici este fix, scris de noi; blocurile existente au fost deja curățate la salvare.
 */
class ContentRefresh
{
    public const VIDEO_PRODUCTIE = 'https://www.youtube.com/watch?v=kB3VeYY-V50';


    public static function lensPhoto(string $file): ?int
    {
        return Media::where('source_url', 'content:lentile/'.$file)->value('id');
    }

    /** @param callable(array): array $transform primește blocurile publicate și întoarce blocurile noi */
    public function apply(string $path, callable $transform, string $note): ?string
    {
        $page = Page::with('publishedVersion', 'currentVersion')->where('path', $path)->first();
        if (! $page || ! ($base = $page->publishedVersion ?? $page->currentVersion)) {
            return null;
        }
        $blocks = $transform((array) $base->blocks);
        if (json_encode($blocks) === json_encode($base->blocks)) {
            return 'neschimbat';
        }
        $untouched = $page->versions()->count() === 1 && $page->current_version_id === $page->published_version_id;
        $version = $this->saveVersion($page, $base, $blocks, $note);
        if ($untouched) {
            $this->publishVersion($page, $version);

            return 'publicat';
        }

        return 'ciornă';
    }

    public function home(): ?string
    {
        return $this->apply('/', function (array $blocks) {
            $out = [];
            $hasMarquee = collect($blocks)->contains('type', 'frames_marquee');
            $hasVideo = collect($blocks)->contains('type', 'video');
            foreach ($blocks as $b) {
                if ($b['type'] === 'production_story') {
                    // Fotografiile fotocromaticelor maro (întunecate) → lentile colorate, luminoase.
                    $a = self::lensPhoto('lentila-minus-fata.webp');
                    $c = self::lensPhoto('lentila-plus-profil.jpg');
                    if ($a && $c) {
                        $b['data']['compare_a'] = $a;
                        $b['data']['compare_a_caption'] = 'Lentilă pentru miopie (dioptrii negative), văzută din față.';
                        $b['data']['compare_b'] = $c;
                        $b['data']['compare_b_caption'] = 'Lentilă cu dioptrii pozitive mari, văzută din profil.';
                        $b['data']['compare_note'] = 'Fotografii de produs ale unor lentile realizate în laboratorul nostru.';
                    }
                }
                if ($b['type'] === 'b2b_band') {
                    foreach (['primary', 'secondary'] as $k) {
                        if (($b['data'][$k]['url'] ?? '') === '/b2b/') {
                            $b['data'][$k]['url'] = '/portal/';
                        }
                    }
                }
                $out[] = $b;
                if ($b['type'] === 'hero_lens' && ! $hasMarquee) {
                    $out[] = ['type' => 'frames_marquee', 'data' => ['title' => 'Rame alese pentru tine', 'intro' => 'Câteva dintre ramele din catalog. Alege una și noi facem lentilele pentru ea.', 'limit' => 18, 'cta' => ['label' => 'Vezi toate ramele', 'url' => '/rame/']]];
                }
                if ($b['type'] === 'production_story' && ! $hasVideo) {
                    $out[] = ['type' => 'video', 'data' => ['title' => 'Vezi cum lucrăm', 'text' => 'Un scurt film din laboratorul nostru din Târgu Mureș: cum se prelucrează și se montează lentilele.', 'youtube_url' => self::VIDEO_PRODUCTIE, 'caption' => 'Fabrica de lentile Optoplus, Târgu Mureș', 'autoplay' => false]];
                }
            }

            return $out;
        }, 'Actualizare 1.0.5: rame animate, video, fotografii luminoase');
    }

    public function production(): ?string
    {
        return $this->apply('/productia-noastra/', function (array $blocks) {
            if (collect($blocks)->contains('type', 'video')) {
                return $blocks;
            }
            $out = [];
            foreach ($blocks as $i => $b) {
                $out[] = $b;
                if ($i === 0) {
                    $out[] = ['type' => 'video', 'data' => ['title' => 'Fabrica de lentile, în imagini', 'text' => 'Lentilele se fac aici, în laboratorul nostru din Târgu Mureș: de la lentila semifinisată la ochelarii montați.', 'youtube_url' => self::VIDEO_PRODUCTIE, 'caption' => 'Video de prezentare — fabrica de lentile Optoplus', 'autoplay' => true]];
                }
            }

            return $out;
        }, 'Actualizare 1.0.5: video de prezentare');
    }

    public const PARAGRAF_YOUTUBE = '<p><strong>Videoclipuri YouTube.</strong> Unele pagini (de exemplu „Producția noastră”) includ un videoclip găzduit pe YouTube, afișat prin domeniul youtube-nocookie.com (modul cu confidențialitate îmbunătățită). Pe „Producția noastră” videoclipul pornește automat, fără sunet, când ajunge pe ecran; pe celelalte pagini se încarcă numai dacă îl porniți. La redare, YouTube (Google) poate stoca sau citi informații în browserul dumneavoastră, potrivit propriei politici de confidențialitate. Videoclipul poate fi urmărit și direct pe YouTube.</p>';

    /**
     * Politica de cookie-uri: adaugă paragraful despre videoclipurile YouTube în ultima versiune a textului.
     * Textele juridice nu se publică automat niciodată: rezultatul este o ciornă de verificat.
     */
    public function cookies(): ?string
    {
        $page = Page::with('currentVersion')->where('path', '/politica-de-cookie-uri/')->first();
        $base = $page?->currentVersion;
        if (! $base) {
            return null;
        }
        $blocks = (array) $base->blocks;
        foreach ($blocks as $b) {
            if (str_contains((string) ($b['data']['html'] ?? ''), 'YouTube')) {
                return 'neschimbat';
            }
        }
        $done = false;
        foreach ($blocks as &$b) {
            if (! $done && ($b['type'] ?? '') === 'rich_text') {
                $html = (string) ($b['data']['html'] ?? '');
                $pos = strpos($html, '<p>Nu folosim în prezent cookie-uri');
                $b['data']['html'] = $pos === false ? $html."\n".self::PARAGRAF_YOUTUBE : substr_replace($html, self::PARAGRAF_YOUTUBE."\n", $pos, 0);
                $done = true;
            }
        }
        unset($b);
        if (! $done) {
            return null;
        }
        $this->saveVersion($page, $base, $blocks, 'Actualizare 1.0.5: mențiune video YouTube — de verificat înainte de publicare');

        return 'ciornă';
    }

    private function saveVersion(Page $page, PageVersion $base, array $blocks, string $note): PageVersion
    {
        return DB::transaction(function () use ($page, $base, $blocks, $note) {
            $locked = Page::whereKey($page->id)->lockForUpdate()->first();
            $version = PageVersion::create([
                'page_id' => $locked->id,
                'number' => (int) PageVersion::where('page_id', $locked->id)->max('number') + 1,
                'title' => $base->title,
                'seo' => $base->seo,
                'blocks' => array_values($blocks),
                'note' => $note,
                'created_by' => null,
                'created_at' => now(),
            ]);
            $locked->update(['current_version_id' => $version->id, 'lock_version' => $locked->lock_version + 1]);
            Audit::log('cms.page.saved', $locked, ['versiune' => $version->number, 'sursa' => 'actualizare']);

            return $version;
        });
    }

    private function publishVersion(Page $page, PageVersion $version): void
    {
        DB::transaction(function () use ($page, $version) {
            $version->update(['published_at' => now()]);
            Page::whereKey($page->id)->update(['published_version_id' => $version->id, 'status' => 'published', 'title' => $version->title]);
        });
        Audit::log('cms.page.published', $page, ['versiune' => $version->number, 'sursa' => 'actualizare']);
        // Cache-ul paginilor are versiunea aplicației în cheie: după activare se reconstruiește oricum.
    }
}
