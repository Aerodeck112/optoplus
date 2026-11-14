<?php

namespace App\Modules\Cms\Services;

use App\Core\Audit\Audit;
use App\Core\Settings\Settings;
use App\Core\Support\Money;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Models\PageVersion;
use Illuminate\Support\Facades\DB;

/**
 * 1.0.12: prima pagină în stil de magazin — bannere mari (cu ilustrații de persoane, nu fotografii de oameni
 * reali), scurtături colorate, categorii cu fotografii, carusele de rame; lentila 3D coboară în secțiunea
 * laboratorului. Pagina editată de echipă nu se suprascrie (ContentRefresh::apply: varianta nouă rămâne ciornă).
 *
 * Clasă nouă, de sine stătătoare (în timpul actualizării, în memorie e codul vechi — vezi CLAUDE.md): folosește
 * doar modelele Page / PageVersion, Settings, Audit și DB, neschimbate din 1.0.0.
 * „Pagină needitată” = nicio versiune salvată de un om din panou (created_by gol) și versiunea curentă publicată;
 * actualizările automate anterioare (1.0.5) nu contează ca editare.
 */
class HomeShopRefresh
{
    public function run(): ?string
    {
        if (! Page::where('path', '/')->exists()) {
            return null; // instalare de la zero: rulează din nou ContentRefreshSeeder, după setările de livrare
        }
        $this->topbar();

        return DB::transaction(function () {
            $page = Page::where('path', '/')->lockForUpdate()->first();
            $base = $page ? PageVersion::find($page->published_version_id ?? $page->current_version_id) : null;
            if (! $page || ! $base) {
                return null;
            }
            $blocks = self::transform((array) $base->blocks);
            if (json_encode($blocks) === json_encode($base->blocks)) {
                return 'neschimbat';
            }
            $untouched = ! PageVersion::where('page_id', $page->id)->whereNotNull('created_by')->exists()
                && $page->current_version_id === $page->published_version_id;
            $version = PageVersion::create([
                'page_id' => $page->id, 'number' => (int) PageVersion::where('page_id', $page->id)->max('number') + 1,
                'title' => $base->title, 'seo' => $base->seo, 'blocks' => $blocks,
                'note' => 'Actualizare 1.0.12: prima pagină în stil de magazin', 'created_by' => null, 'created_at' => now(),
            ]);
            $page->update(['current_version_id' => $version->id, 'lock_version' => $page->lock_version + 1]);
            if ($untouched) {
                $version->update(['published_at' => now()]);
                $page->update(['published_version_id' => $version->id, 'status' => 'published']);
            }
            Audit::log($untouched ? 'cms.page.published' : 'cms.page.saved', $page, ['versiune' => $version->number, 'sursa' => 'actualizare 1.0.12']);

            return $untouched ? 'publicat' : 'ciornă';
        });
    }

    private function topbar(): void
    {
        $s = app(Settings::class);
        if ($s->get('site.topbar_text') !== null) {
            return; // păstrăm ce a scris echipa (și gol = fără bandă)
        }
        $price = $s->get('shipping.courier_price');
        $s->set('site.topbar_text', 'Lentile produse în laboratorul nostru.'
            .($price !== null ? ' Livrare prin curier în toată România: '.Money::format((int) $price).'.' : ''));
    }

    public static function transform(array $blocks): array
    {
        if (collect($blocks)->contains('type', 'banner_carousel')) {
            return $blocks;
        }
        $by = collect($blocks)->keyBy('type');
        $lens = fn (string $file) => DB::table('media')->where('source_url', 'content:lentile/'.$file)->value('id');
        $women = self::frameImage(gender: 'femei', notGender: 'barbati', plainOnly: true);
        $men = self::frameImage(gender: 'barbati', notGender: 'femei', plainOnly: true);
        $kids = self::frameImage(gender: 'copii', plainOnly: true);
        $clip = self::frameImage(construction: 'clip-on');
        $own = self::frameImage(brand: 'optofarm');
        $rx = $lens('lentila-progresiva-marcaje.webp');

        $out = [];
        $out[] = ['type' => 'banner_carousel', 'data' => ['slides' => [
            ['badge' => '', 'title' => 'Lentile produse de noi. Ochelari configurați pentru tine.',
                'text' => 'Alegi rama, introduci rețeta și vezi prețul final la fiecare pas. Un specialist optic verifică rețeta înainte de producție.',
                'primary' => ['label' => 'Configurează ochelarii', 'url' => '/configurator/'], 'secondary' => ['label' => 'Vezi ramele', 'url' => '/rame/'],
                'illustration' => 'femeie', 'photo' => null, 'product' => $women, 'product2' => null, 'background' => 'bleu'],
            ['badge' => 'Lentile fotocromatice', 'title' => 'Lentile care se închid la soare', // textul din 1.0.16 (PhotochromicLineup)
                'text' => 'Transitions se închide afară până la 80%. Xtractive se închide și în mașină, deci e potrivit pentru condus. PhotoFast vine în albastru sau mov.',
                'primary' => ['label' => 'Alege lentilele', 'url' => '/configurator/'], 'secondary' => ['label' => 'Despre lentile', 'url' => '/lentile/'],
                'illustration' => 'fotocromatic', 'photo' => null, 'product' => $lens('fotocromatica-maro-exterior.jpg'), 'product2' => $lens('fotocromatica-albastra-exterior.jpg'), 'background' => 'bleumarin'],
            ['badge' => '', 'title' => 'Progresive: o singură pereche pentru toate distanțele',
                'text' => 'Departe, la calculator și la citit, fără să schimbi ochelarii. Le facem în laboratorul nostru, după rețeta ta.',
                'primary' => ['label' => 'Vezi lentilele progresive', 'url' => '/lentile/#progresiv'], 'secondary' => ['label' => 'Ce lentile mi se potrivesc?', 'url' => '/ghiduri/ce-lentile-mi-se-potrivesc/'],
                'illustration' => 'senior', 'photo' => null, 'product' => $rx, 'product2' => null, 'background' => 'piersica'],
            ['badge' => '', 'title' => 'Ochelari pentru copii',
                'text' => 'Rame pentru copii, cu lentile făcute după rețetă în laboratorul nostru.',
                'primary' => ['label' => 'Vezi ramele pentru copii', 'url' => '/rame/copii/'], 'secondary' => ['label' => 'Cum citești rețeta', 'url' => '/ghiduri/cum-citesti-reteta/'],
                'illustration' => 'copil', 'photo' => null, 'product' => $kids, 'product2' => null, 'background' => 'menta'],
        ]]];
        $out[] = ['type' => 'quick_links', 'data' => ['items' => [
            ['icon' => 'lens', 'color' => 'azur', 'title' => 'Configurează ochelarii', 'text' => 'pas cu pas, cu prețul la vedere', 'url' => '/configurator/'],
            ['icon' => 'lab', 'color' => 'verde', 'title' => 'Lentile din laboratorul nostru', 'text' => 'făcute după rețeta ta', 'url' => '/productia-noastra/'],
            ['icon' => 'shield', 'color' => 'mov', 'title' => 'Verificare de specialist', 'text' => 'pentru fiecare rețetă', 'url' => '/ghiduri/cum-citesti-reteta/'],
            ['icon' => 'truck', 'color' => 'portocaliu', 'title' => 'Livrare prin curier', 'text' => 'în toată România', 'url' => '/politica-ramburs-retur/'],
            ['icon' => 'ruler', 'color' => 'rosu', 'title' => 'Distanța pupilară', 'text' => 'cum o măsori', 'url' => '/ghiduri/distanta-pupilara/'],
            ['icon' => 'building', 'color' => 'bleumarin', 'title' => 'Pentru clinici și optici', 'text' => 'portalul partenerilor', 'url' => '/parteneri/'],
        ]]];
        $out[] = ['type' => 'category_tiles', 'data' => ['title' => 'Alege după categorie', 'items' => array_values(array_filter([
            ['title' => 'Femei', 'url' => '/rame/femei/', 'image' => $women, 'background' => 'piersica'],
            ['title' => 'Bărbați', 'url' => '/rame/barbati/', 'image' => $men, 'background' => 'bleu'],
            ['title' => 'Copii', 'url' => '/rame/copii/', 'image' => $kids, 'background' => 'menta'],
            ['title' => 'Clip-on (cu soare)', 'url' => '/rame/clip-on/', 'image' => $clip, 'background' => 'galben'],
            ['title' => 'Optofarm, marca noastră', 'url' => '/marci/optofarm/', 'image' => $own, 'background' => 'lavanda'],
            ['title' => 'Lentile', 'url' => '/lentile/', 'image' => $rx, 'background' => 'bleumarin'],
        ], fn ($t) => $t['image'] !== null))]];
        $out[] = ['type' => 'products', 'data' => ['title' => 'Cele mai noi rame', 'intro' => 'Alege rama, iar lentilele le facem noi.', 'brand' => '', 'gen' => '', 'layout' => 'carusel', 'limit' => 12,
            'cta' => ['label' => 'Toate ramele', 'url' => '/rame/']]];
        if ($hero = $by->get('hero_lens')) {
            $hero['data']['title'] = 'Lentilele le facem în laboratorul nostru';
            $hero['data']['subtitle'] = 'Fiecare pereche e produsă după rețeta ta și verificată de un specialist optic. Mișcă lentila: marcajele de pe ea sunt cele de pe lentilele progresive reale.';
            $hero['data']['primary'] = ['label' => 'Vezi lentilele', 'url' => '/lentile/'];
            $hero['data']['secondary'] = ['label' => 'Producția noastră', 'url' => '/productia-noastra/'];
            $hero['data']['b2b_text'] = '';
            $out[] = $hero;
        }
        foreach (['lens_families', 'steps', 'video'] as $type) {
            if ($b = $by->get($type)) {
                $out[] = $b;
            }
        }
        $out[] = ['type' => 'banner_carousel', 'data' => ['slides' => [
            ['badge' => 'Pentru profesioniști', 'title' => 'Ai o clinică, un cabinet sau un magazin de optică?',
                'text' => 'Comandă lentile direct de la laborator, într-un portal pentru toată echipa: comandă rapidă, urmărirea fiecărei lucrări, oferte și documente.',
                'primary' => ['label' => 'Devino partener', 'url' => '/parteneri/'], 'secondary' => ['label' => 'Autentificare parteneri', 'url' => '/portal/autentificare/'],
                'illustration' => 'barbat', 'photo' => null, 'product' => $men, 'product2' => null, 'background' => 'lavanda'],
        ]]];
        if ($own) {
            $out[] = ['type' => 'products', 'data' => ['title' => 'Optofarm, marca noastră de rame', 'intro' => '', 'brand' => 'optofarm', 'gen' => '', 'layout' => 'carusel', 'limit' => 12,
                'cta' => ['label' => 'Toate ramele Optofarm', 'url' => '/marci/optofarm/']]];
        }
        if ($b = $by->get('cta')) {
            $out[] = $b;
        }
        // Blocurile adăugate de echipă și necunoscute aici rămân, la final.
        foreach ($blocks as $b) {
            if (! in_array($b['type'], ['hero_lens', 'frames_marquee', 'steps', 'lens_families', 'production_story', 'video', 'products', 'b2b_band', 'cta'], true)) {
                $out[] = $b;
            }
        }

        return $out;
    }

    /** O fotografie de ramă (media), după gen / construcție / marcă; null dacă nu există. */
    private static function frameImage(?string $gender = null, ?string $notGender = null, ?string $construction = null, ?string $brand = null, bool $plainOnly = false): ?int
    {
        $attr = fn ($q, string $code, string $slug) => $q->from('product_attribute_values as pav')
            ->join('attribute_values as av', 'av.id', '=', 'pav.attribute_value_id')->join('attributes as a', 'a.id', '=', 'av.attribute_id')
            ->whereColumn('pav.product_id', 'products.id')->where('a.code', $code)->where('av.slug', $slug);
        $q = DB::table('product_images')->join('products', 'products.id', '=', 'product_images.product_id')
            ->where('products.status', 'published')->where('products.type', 'frame')->where('product_images.status', 'ready')
            ->when($gender, fn ($x) => $x->whereExists(fn ($e) => $attr($e, 'gen', $gender)))
            ->when($notGender, fn ($x) => $x->whereNotExists(fn ($e) => $attr($e, 'gen', $notGender)))
            ->when($construction, fn ($x) => $x->whereExists(fn ($e) => $attr($e, 'constructie', $construction)))
            // fără clip-on (lentile de soare atașate) când vrem o ramă de vedere simplă
            ->when($plainOnly, fn ($x) => $x->whereNotExists(fn ($e) => $attr($e, 'constructie', 'clip-on')))
            ->when($brand, fn ($x) => $x->whereExists(fn ($e) => $e->from('brands')->whereColumn('brands.id', 'products.brand_id')->where('brands.slug', $brand)))
            ->orderByDesc('products.legacy_wc_id')->orderBy('product_images.sort');

        return $q->value('product_images.media_id');
    }
}
