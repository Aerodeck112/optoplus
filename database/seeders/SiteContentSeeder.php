<?php

namespace Database\Seeders;

use App\Core\Settings\Settings;
use App\Modules\Cms\Models\FaqItem;
use App\Modules\Cms\Models\Media;
use App\Modules\Cms\Models\MenuItem;
use App\Modules\Cms\Services\MediaLibrary;
use App\Modules\Cms\Services\PageService;
use Illuminate\Database\Seeder;

/**
 * Conținutul inițial al siteului, editabil apoi din panou: meniuri, prima pagină, paginile de bază, ghidurile
 * preluate din blogul vechi, întrebări frecvente și fotografiile de lentile primite.
 *
 * Reguli: nu publicăm termene, garanții, certificări, recenzii sau date de firmă neconfirmate; textele juridice
 * vechi sunt păstrate ca ciorne marcate „necesită revizie juridică”, iar paginile publice spun asta deschis.
 * Idempotent: rularea repetată nu dublează nimic și nu suprascrie paginile editate deja în panou.
 */
class SiteContentSeeder extends Seeder
{
    private array $media = [];

    public function run(): void
    {
        $this->settings();
        $this->photos();
        $this->menus();
        $this->faq();
        $this->pages();
    }

    private function settings(): void
    {
        $s = app(Settings::class);
        // Datele de contact publicate azi pe optoplus.ro (pagina Contact și pagina Fabrica de lentile).
        $defaults = [
            'contact.phone_b2c' => '+40 771 653 905',
            'contact.email_b2c' => 'contact@optoplus.ro',
            'contact.address' => 'Str. Poștei nr. 3, Târgu Mureș',
            'contact.phone_b2b' => '+40 771 048 936',
            'contact.email_b2b' => 'ofalentile@gmail.com',
            'contact.hours' => null,
            // Datele firmei sunt contradictorii pe siteul vechi (docs/audit/OBSERVATII-CONTINUT.md #6–7): goale.
            'company.name' => null, 'company.vat_code' => null, 'company.registry' => null, 'company.address' => null,
            'tax.confirmed' => false,
        ];
        foreach ($defaults as $key => $value) {
            if ($s->get($key) === null) {
                $s->set($key, $value);
            }
        }
    }

    private function photos(): void
    {
        $dir = base_path('database/content/lentile');
        $alts = [
            'lentila-progresiva-marcaje.webp' => 'Lentilă progresivă nedebitată, cu marcajele de centrare imprimate',
            'lentila-progresiva-marcaje-2.webp' => 'Lentilă progresivă rotundă, cu marcajele zonelor de vedere',
            'lentila-minus-fata.webp' => 'Lentilă cu dioptrii negative, văzută din față',
            'lentila-plus-profil.jpg' => 'Lentilă cu dioptrii pozitive mari, văzută din profil',
            'fotocromatica-maro-interior.jpg' => 'Lentilă fotocromatică maro, în interior',
            'fotocromatica-maro-exterior.jpg' => 'Lentilă fotocromatică maro, fotografiată afară',
            'fotocromatica-albastra-interior.jpg' => 'Lentilă fotocromatică albastră, în interior',
            'fotocromatica-albastra-exterior.jpg' => 'Lentilă fotocromatică albastră, fotografiată afară',
            'fotocromatica-gri.jpg' => 'Lentilă fotocromatică gri',
            'fotocromatica-verde-exterior.jpg' => 'Lentilă fotocromatică verde, fotografiată afară',
        ];
        foreach ($alts as $file => $alt) {
            if (! is_file($dir.'/'.$file)) {
                continue;
            }
            $m = app(MediaLibrary::class)->importFile($dir.'/'.$file, 'content:lentile/'.$file, $alt, true, 'lentile');
            if (! $m->alt) {
                $m->update(['alt' => $alt]);
            }
            $this->media[$file] = $m->id;
        }
    }

    private function menus(): void
    {
        if (MenuItem::exists()) {
            return;
        }
        $header = [
            ['Ochelari și rame', '/rame/', [
                ['Rame pentru femei', '/rame/femei/'], ['Rame pentru bărbați', '/rame/barbati/'], ['Rame pentru copii', '/rame/copii/'],
                ['Rame clip-on', '/rame/clip-on/', 'Cu lentile de soare atașabile'], ['Optofarm', '/marci/optofarm/', 'Marca noastră proprie'],
            ]],
            ['Lentile', '/lentile/', [
                ['Monofocale', '/lentile/#monofocal'], ['Progresive', '/lentile/#progresiv'], ['Degresive (birou)', '/lentile/#degresiv'], ['Bifocale', '/lentile/#bifocal'],
            ]],
            ['Configurează ochelarii', '/configurator/', []],
            ['Producția noastră', '/productia-noastra/', []],
            ['Pentru clinici și parteneri', '/parteneri/', []],
            ['Ghiduri', '/ghiduri/', []],
            ['Contact', '/contact/', []],
        ];
        foreach ($header as $i => [$label, $url, $children]) {
            $parent = MenuItem::create(['menu' => 'header', 'label' => $label, 'url' => $url, 'sort' => $i]);
            foreach ($children as $j => $c) {
                MenuItem::create(['menu' => 'header', 'parent_id' => $parent->id, 'label' => $c[0], 'url' => $c[1], 'description' => $c[2] ?? null, 'sort' => $j]);
            }
        }
        foreach ([['Despre noi', '/despre-noi/'], ['Producția noastră', '/productia-noastra/'], ['Ghiduri', '/ghiduri/'], ['Pentru parteneri', '/parteneri/'], ['Contul meu', '/cont/'], ['Contact', '/contact/']] as $i => [$l, $u]) {
            MenuItem::create(['menu' => 'footer', 'label' => $l, 'url' => $u, 'sort' => $i]);
        }
        foreach ([['Termeni și condiții', '/termeni-si-conditii/'], ['Politica de confidențialitate', '/politica-de-confidentialitate/'], ['Politica de cookie-uri', '/politica-de-cookie-uri/'], ['Retur și rambursare', '/politica-ramburs-retur/'], ['Livrare și retur în UE', '/politica-de-livrare-si-retur-ue-vanzari-intracomunitare/']] as $i => [$l, $u]) {
            MenuItem::create(['menu' => 'footer_legal', 'label' => $l, 'url' => $u, 'sort' => $i]);
        }
    }

    private function faq(): void
    {
        if (FaqItem::exists()) {
            return;
        }
        // Răspunsuri despre funcționarea siteului (verificabile în aplicație). Cele despre termene, retur și garanție
        // rămân nepublicate până la confirmarea datelor și revizia juridică.
        $items = [
            ['reteta', 'Pot comanda ochelari de vedere online, fără să vin la magazin?', '<p>Da, dacă aveți o rețetă de la medicul oftalmolog sau de la optometrist. Alegeți rama, introduceți valorile din rețetă (sau încărcați o fotografie a ei) și alegeți lentilele. Înainte de producție, un specialist optic verifică rețeta și lentila aleasă.</p>', true],
            ['reteta', 'Nu înțeleg rețeta. Ce fac?', '<p>În configurator, la pasul „Rețeta”, alegeți „Nu înțeleg rețeta”: încărcați o fotografie a ei și un specialist vă contactează. Nu completăm și nu modificăm noi valorile rețetei; le confirmăm împreună cu dumneavoastră.</p>', true],
            ['reteta', 'Ce este distanța pupilară (DP) și unde o găsesc?', '<p>Distanța pupilară este distanța dintre centrele pupilelor, în milimetri. Apare adesea pe rețetă ca „DP” sau „PD”: fie o singură valoare (ex. 63), fie câte una pe ochi (ex. 31,5 / 31,5). Dacă nu o aveți, bifați în configurator că doriți să fiți contactat pentru măsurare.</p>', true],
            ['lentile', 'Ce înseamnă indicele lentilei (1,50, 1,60, 1,67, 1,74)?', '<p>Indicele arată cât de mult „frânge” lumina materialul. La aceeași rețetă, un indice mai mare înseamnă o lentilă mai subțire și mai ușoară. Pentru dioptrii mici, indicele standard este de obicei suficient; pentru dioptrii mari sau rame pe fir, lentilele subțiate arată și se montează mai bine.</p>', true],
            ['lentile', 'Prețul lentilelor este pentru o lentilă sau pentru pereche?', '<p>Fiecare lentilă din catalog arată clar unitatea de vânzare: „pereche” (ambii ochi) sau „bucată”. În configurator vedeți la fiecare pas prețul ramei, al lentilelor și al tratamentelor, separat.</p>', true],
            ['lentile', 'Ce lentile produceți voi și ce comercializați?', '<p>Lentilele marcate „Produsă în laboratorul Optoplus” sunt făcute de noi, după rețeta dumneavoastră. Ramele din catalog sunt produse de mărcile afișate pe fiecare produs; Optofarm este marca noastră proprie de rame.</p>', true],
            ['comanda', 'Când intră comanda mea în producție?', '<p>După ce un specialist optic verifică rețeta și lentila aleasă. Dacă lipsește o informație (de exemplu o măsurătoare), vă contactăm înainte de producție. Etapele le vedeți în contul dumneavoastră.</p>', true],
            ['rame', 'Cum aleg mărimea ramei?', '<p>Cel mai simplu: comparați cu ochelarii pe care îi purtați. Pe brațul lor sunt gravate trei numere (ex. 52-18-140): lățimea lentilei, puntea și lungimea brațului, în milimetri. Pe pagina fiecărei rame găsiți aceleași dimensiuni, desenate. Puteți filtra catalogul după lățimea lentilei.</p>', true],
            ['b2b', 'Cum devin partener Optoplus?', '<p>Completați cererea de parteneriat de pe pagina „Pentru clinici și parteneri”. Verificăm manual datele firmei; după aprobare primiți acces la portal, cu prețurile și condițiile convenite.</p>', true],
            ['b2b', 'Trebuie să trec numele pacientului pe comenzile B2B?', '<p>Nu. Este suficient un cod intern al pacientului, folosit de dumneavoastră pentru evidență.</p>', true],
            ['livrare', 'Cât durează livrarea?', '<p>Termenele de producție și de livrare se afișează pe fiecare produs și în coș, după confirmarea lor. Timpul de producție al lentilelor se adaugă timpului de transport.</p>', false],
            ['retur', 'Pot returna ochelarii cu lentile la comandă?', '<p>Produsele realizate după specificațiile clientului (de exemplu lentilele după rețetă) sunt exceptate de la dreptul de retragere, conform legislației privind contractele la distanță. Ramele fără lentile personalizate pot fi returnate în 14 zile. Detaliile complete sunt în politica de retur.</p>', false],
        ];
        foreach ($items as $i => [$topic, $q, $a, $published]) {
            FaqItem::create(['topic' => $topic, 'question' => $q, 'answer' => $a, 'is_published' => $published, 'use_in_assistant' => false, 'sort' => $i]);
        }
    }

    private function pages(): void
    {
        $svc = app(PageService::class);
        $m = fn (string $file) => $this->media[$file] ?? null;
        $link = fn (string $label, string $url) => ['label' => $label, 'url' => $url];

        $svc->upsert('/', 'home', 'Lentile produse de noi. Ochelari configurați pentru tine.', [
            ['type' => 'hero_lens', 'data' => [
                'title' => 'Lentile produse de noi. Ochelari configurați pentru tine.',
                'subtitle' => 'Alegi rama, introduci rețeta și vezi prețul final la fiecare pas. Lentilele le facem în laboratorul nostru, iar un specialist verifică rețeta înainte de producție.',
                'primary' => $link('Configurează ochelarii', '/configurator/'),
                'secondary' => $link('Vezi ramele', '/rame/'),
                'b2b_text' => 'Ai o clinică, un cabinet sau un magazin de optică?',
                'b2b_link' => $link('Comandă lentile ca partener', '/parteneri/'),
                'image' => $m('lentila-progresiva-marcaje.webp'),
                'caption' => 'Lentilă progresivă din laboratorul nostru, înainte de debitare. Marcajele colorate sunt temporare.',
                'pins' => [
                    ['x' => 51, 'y' => 37, 'title' => 'Zona pentru distanță', 'text' => 'Prin partea de sus a lentilei privești în depărtare: drumul, televizorul, oamenii din jur.'],
                    ['x' => 28, 'y' => 54, 'title' => 'Marcajele de centrare', 'text' => 'Linia și punctele ne ajută să așezăm lentila exact în dreptul pupilei tale. Sunt imprimate temporar și se îndepărtează după montaj.'],
                    ['x' => 54, 'y' => 72, 'title' => 'Zona pentru aproape', 'text' => 'Partea de jos e pentru citit și telefon. Între zone, puterea lentilei se schimbă treptat, fără linie vizibilă.'],
                ],
            ]],
            ['type' => 'steps', 'data' => [
                'title' => 'Cum comanzi',
                'intro' => 'Cinci pași scurți. Te poți opri oricând: configurația rămâne salvată.',
                'items' => [
                    ['title' => 'Alegi rama', 'text' => 'Din catalog. Ai deja ochelari? Poți comanda și doar lentilele.'],
                    ['title' => 'Introduci rețeta', 'text' => 'Valorile de pe rețetă sau o fotografie a ei. Te ajutăm dacă nu o înțelegi.'],
                    ['title' => 'Alegi lentila', 'text' => 'Îți arătăm doar variantele potrivite rețetei, cu diferențele și prețul fiecăreia.'],
                    ['title' => 'Specialistul verifică', 'text' => 'Înainte de producție, un specialist optic verifică rețeta și lentila aleasă.'],
                    ['title' => 'Producem și livrăm', 'text' => 'Urmărești în cont fiecare etapă, de la laborator până la curier.'],
                ],
            ]],
            ['type' => 'lens_families', 'data' => [
                'title' => 'Lentilele pe care le producem',
                'intro' => 'Patru familii de lentile, fiecare pentru alt fel de a privi. Apoi alegi grosimea (indicele) și tratamentele.',
            ]],
            ['type' => 'production_story', 'data' => [
                'title' => 'Din laboratorul nostru până la tine',
                'text' => '<p>Lentilele Optoplus se fac în laboratorul nostru, după rețeta fiecărui client. Le producem, le verificăm și, la ochelarii compleți, le montăm în rama aleasă.</p><p>Pentru că lucrăm direct, fără intermediari pentru lentile, urmărești comanda pas cu pas în contul tău și vorbești cu oamenii care o realizează.</p>',
                'compare_a' => $m('fotocromatica-maro-interior.jpg'), 'compare_a_caption' => 'În interior: lentila fotocromatică rămâne deschisă.',
                'compare_b' => $m('fotocromatica-maro-exterior.jpg'), 'compare_b_caption' => 'Afară, în lumina zilei: aceeași lentilă se închide.',
                'compare_note' => 'Fotografii ilustrative ale unor lentile reale. Cât de mult se închid depinde de lumină și de temperatură.',
                'cta' => $link('Despre producția noastră', '/productia-noastra/'),
            ]],
            ['type' => 'products', 'data' => [
                'title' => 'Rame din catalog',
                'intro' => 'Ramele sunt produse de mărcile afișate; Optofarm este marca noastră proprie. Lentilele pentru ele le facem noi.',
                'brand' => '', 'limit' => 8, 'cta' => $link('Toate ramele', '/rame/'),
            ]],
            ['type' => 'b2b_band', 'data' => [
                'title' => 'Pentru clinici, cabinete și magazine de optică',
                'text' => 'Comandați lentile direct de la laborator: prețuri de partener, comenzi repetate și urmărirea producției pentru fiecare lucrare.',
                'bullets' => [['text' => 'Cont de organizație, cu mai mulți utilizatori și roluri'], ['text' => 'Comandă rapidă în tabel sau din fișier CSV'], ['text' => 'Oferte cu versiuni și istoric de documente'], ['text' => 'Cod intern al pacientului în loc de nume']],
                'primary' => $link('Cerere de parteneriat', '/parteneri/#cerere'),
                'secondary' => $link('Autentificare parteneri', '/b2b/'),
            ]],
            ['type' => 'cta', 'data' => [
                'title' => 'Nu știi ce lentile ți se potrivesc?',
                'text' => 'Spune-ne cum folosești ochelarii și trimite rețeta. Un specialist te contactează cu o recomandare, fără să alegi tu termeni tehnici.',
                'primary' => $link('Cere ajutorul unui specialist', '/configurator/ajutor/'),
                'secondary' => $link('Citește ghidurile', '/ghiduri/'),
            ]],
        ], ['description' => 'Optoplus produce lentile de vedere în laboratorul propriu. Alegi rama, introduci rețeta, vezi prețul final la fiecare pas; un specialist verifică rețeta înainte de producție.']);

        $svc->upsert('/productia-noastra/', 'page', 'Producția noastră', [
            ['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p class="lead">Lentilele Optoplus sunt proiectate și realizate în laboratorul nostru, pentru rețeta fiecărui client. Aici vedeți ce producem, cum lucrăm și ce nu producem.</p>']],
            ['type' => 'steps', 'data' => [
                'title' => 'Cum se face o lentilă la comandă',
                'intro' => 'Fiecare comandă trece prin aceleași etape; pe cele care vă privesc le vedeți în cont.',
                'items' => [
                    ['title' => 'Verificarea rețetei', 'text' => 'Un specialist optic verifică valorile și compatibilitatea lentilei alese cu rama.'],
                    ['title' => 'Proiectarea', 'text' => 'Lentila este proiectată digital înainte de prelucrare, ținând cont de ramă și de centrare.'],
                    ['title' => 'Prelucrarea și tratamentele', 'text' => 'Lentila este prelucrată, apoi primește tratamentele alese (de exemplu antireflex).'],
                    ['title' => 'Controlul calității', 'text' => 'Verificăm lentila finită înainte de montaj sau de expediere.'],
                    ['title' => 'Montajul', 'text' => 'La ochelarii compleți, lentilele sunt debitate și montate în rama aleasă.'],
                ],
            ]],
            ['type' => 'photo_row', 'data' => [
                'title' => 'Lentile din laboratorul nostru',
                'intro' => 'Fotografii de produs ale unor lentile realizate de noi.',
                'items' => array_values(array_filter([
                    ['media' => $m('lentila-progresiva-marcaje-2.webp'), 'caption' => 'Lentilă progresivă nedebitată, cu marcajele de centrare.'],
                    ['media' => $m('lentila-minus-fata.webp'), 'caption' => 'Lentilă cu dioptrii negative (pentru miopie).'],
                    ['media' => $m('lentila-plus-profil.jpg'), 'caption' => 'Lentilă cu dioptrii pozitive mari, văzută din profil.'],
                    ['media' => $m('fotocromatica-verde-exterior.jpg'), 'caption' => 'Lentilă fotocromatică verde, fotografiată afară.'],
                ], fn ($i) => $i['media'])),
            ]],
            ['type' => 'lens_families', 'data' => ['title' => 'Ce producem', 'intro' => 'Lentile monofocale, progresive, degresive și bifocale, în mai mulți indici de subțiere, cu tratamentele disponibile pentru fiecare.']],
            ['type' => 'rich_text', 'data' => ['title' => 'Ce nu producem', 'html' => '<p>Ramele din catalog sunt produse de mărcile afișate pe fiecare produs (Ray-Ban, Gucci, Moonlight și altele). Optofarm este marca noastră proprie de rame. Tratamentele sau lentilele cu mărci înregistrate ale altor producători sunt prezentate ca atare.</p>']],
            ['type' => 'cta', 'data' => ['title' => 'Comandați lentile', 'text' => 'Pentru ochelarii dumneavoastră sau, dacă sunteți optician ori medic, pentru pacienții dumneavoastră.', 'primary' => $link('Configurează ochelarii', '/configurator/'), 'secondary' => $link('Pentru parteneri', '/parteneri/')]],
        ], ['description' => 'Lentile de vedere produse în laboratorul Optoplus: monofocale, progresive, degresive și bifocale, cu verificarea rețetei de către un specialist.']);

        $svc->upsert('/parteneri/', 'page', 'Pentru clinici și parteneri', [
            ['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p class="lead">Sunteți optometrist, aveți un cabinet oftalmologic, o clinică sau un magazin de optică? Comandați lentile direct de la laboratorul Optoplus, cu condiții de partener.</p>']],
            ['type' => 'b2b_band', 'data' => [
                'title' => 'Ce găsiți în portalul partenerilor',
                'text' => 'Un cont pentru organizația dumneavoastră, în care colegii comandă, urmăresc producția și găsesc documentele.',
                'bullets' => [['text' => 'Prețuri de partener, afișate cu sau fără TVA'], ['text' => 'Comandă rapidă în tabel și import din fișier CSV'], ['text' => '„Comandă din nou” pentru lucrările repetate'], ['text' => 'Cereri de ofertă, versiuni și acceptare online'], ['text' => 'Urmărirea producției și a expedierii'], ['text' => 'Solicitări de refacere și reclamații, legate de comandă']],
                'primary' => $link('Autentificare parteneri', '/b2b/'), 'secondary' => ['label' => '', 'url' => ''],
            ]],
            ['type' => 'partner_form', 'data' => ['title' => 'Cerere de parteneriat', 'text' => 'Verificăm manual fiecare cerere. După aprobare, administratorul organizației primește invitația în portal și își poate adăuga colegii.']],
            ['type' => 'faq', 'data' => ['title' => 'Întrebări despre colaborare', 'topic' => 'b2b']],
        ], ['description' => 'Portal B2B Optoplus pentru clinici, cabinete și magazine de optică: lentile de la producător, prețuri de partener, comenzi repetate, urmărirea producției.']);

        $svc->upsert('/ghiduri/', 'page', 'Ghiduri', [
            ['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p class="lead">Explicații scurte, în limbaj simplu, ca să alegeți rama și lentilele potrivite.</p>']],
            ['type' => 'guides_list', 'data' => ['title' => '']],
        ], ['description' => 'Ghiduri Optoplus: cum citești rețeta, ce este distanța pupilară, cum alegi rama și lentilele.']);

        $guides = [
            ['/ghiduri/cum-citesti-reteta/', 'Cum citești rețeta de ochelari', '<p>Rețeta are de obicei un rând pentru fiecare ochi: <strong>OD</strong> (ochiul drept) și <strong>OS</strong> sau <strong>OG</strong> (ochiul stâng). Uneori apar rânduri separate pentru distanță (DEP, „departe”) și pentru aproape (APR).</p><h2>Ce înseamnă fiecare coloană</h2><ul><li><strong>SPH (sferă)</strong> — puterea principală, în dioptrii. Cu minus (−) pentru miopie, cu plus (+) pentru hipermetropie.</li><li><strong>CYL (cilindru)</strong> — corecția pentru astigmatism. Poate lipsi.</li><li><strong>AX (ax)</strong> — orientarea cilindrului, în grade, de la 0 la 180. Apare doar împreună cu CYL.</li><li><strong>ADD (adiție)</strong> — puterea suplimentară pentru aproape, la lentilele progresive, degresive și bifocale.</li><li><strong>DP (distanța pupilară)</strong> — în milimetri; vezi ghidul despre DP.</li></ul><h2>Dacă nu sunteți sigur</h2><p>Nu ghiciți valorile. În configurator puteți încărca o fotografie a rețetei și alege „Nu înțeleg rețeta”; un specialist vă contactează. Nu modificăm valorile rețetei.</p><p><a href="/configurator/">Configurează ochelarii</a> · <a href="/lentile/">Vezi lentilele</a></p>'],
            ['/ghiduri/distanta-pupilara/', 'Distanța pupilară (DP): ce este și unde o găsești', '<p>Distanța pupilară este distanța dintre centrele pupilelor, în milimetri. Lentilele se centrează după ea, de aceea o cerem la fiecare comandă.</p><h2>Unde o găsiți</h2><p>Pe rețetă, ca „DP” sau „PD”: fie o singură valoare (de exemplu 63), fie câte una pe ochi (de exemplu 31,5 și 31,5). În configurator puteți introduce oricare dintre variante.</p><h2>Dacă lipsește</h2><p>Bifați în configurator că doriți să fiți contactat pentru măsurare. Pentru lentilele progresive cerem și înălțimea de montaj, care se măsoară cu rama pe față.</p><p><a href="/configurator/">Configurează ochelarii</a></p>'],
            ['/ghiduri/ce-lentile-mi-se-potrivesc/', 'Ce lentile mi se potrivesc?', '<p>Alegerea pornește de la cum folosiți ochelarii, nu de la termenii tehnici. Recomandarea finală o face medicul sau optometristul care v-a scris rețeta; noi vă explicăm opțiunile.</p><h2>Doar pentru o distanță</h2><p>Dacă rețeta are o singură serie de valori (fără ADD), lentilele <a href="/lentile/#monofocal">monofocale</a> sunt de obicei alegerea.</p><h2>Pentru toate distanțele</h2><p>Dacă rețeta are adiție (ADD) și vreți o singură pereche pentru tot: <a href="/lentile/#progresiv">progresive</a> (fără linie vizibilă, cu perioadă de obișnuire) sau <a href="/lentile/#bifocal">bifocale</a> (două zone distincte).</p><h2>Pentru birou</h2><p>Pentru citit și calculator, în interior: <a href="/lentile/#degresiv">degresive</a>. Nu sunt pentru condus.</p><h2>Grosimea</h2><p>La dioptrii mari, un indice mai mare (1,60, 1,67, 1,74) face lentila mai subțire și mai ușoară.</p>'],
        ];
        foreach ($guides as [$path, $title, $html]) {
            $svc->upsert($path, 'guide', $title, [['type' => 'rich_text', 'data' => ['title' => '', 'html' => $html]]], ['description' => \App\Core\Support\Html::plain($html, 155)]);
        }
        foreach ([['cum-alegi-rama-dupa-forma-fetei', 'Cum alegi rama după forma feței'], ['cum-sa-intretii-corect-ochelarii-de-vedere', 'Cum să întreții corect ochelarii de vedere']] as [$slug, $title]) {
            $file = base_path('database/content/ghiduri/'.$slug.'.html');
            if (is_file($file)) {
                $svc->upsert('/ghiduri/'.$slug.'/', 'guide', $title, [['type' => 'rich_text', 'data' => ['title' => '', 'html' => (string) file_get_contents($file)]]], ['description' => \App\Core\Support\Html::plain((string) file_get_contents($file), 155)]);
            }
        }

        $svc->upsert('/despre-noi/', 'page', 'Despre noi', [
            ['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p class="lead">Optoplus produce lentile de vedere în laboratorul propriu și le montează în ramele alese de clienți.</p><h2>Ce facem</h2><ul><li>Producem lentile monofocale, progresive, degresive și bifocale, după rețetă.</li><li>Comercializăm rame de la mai multe mărci și marca proprie Optofarm.</li><li>Lucrăm cu persoane fizice și cu clinici, cabinete și magazine de optică.</li></ul><h2>Cum lucrăm</h2><p>Fiecare rețetă este verificată de un specialist înainte de producție, iar clienții urmăresc etapele comenzii în contul lor.</p>']],
            ['type' => 'cta', 'data' => ['title' => 'Vorbiți cu noi', 'text' => 'Pentru întrebări despre lentile, rame sau colaborări.', 'primary' => $link('Contact', '/contact/'), 'secondary' => $link('Producția noastră', '/productia-noastra/')]],
        ], ['description' => 'Despre Optoplus: producem lentile de vedere în laboratorul propriu și le montăm în ramele alese de clienți.']);

        $svc->upsert('/contact/', 'page', 'Contact', [
            ['type' => 'contact', 'data' => ['title' => '', 'text' => 'Ne puteți scrie sau suna pentru întrebări despre comenzi, rețete, lentile și rame. Pentru colaborări B2B, folosiți datele de pe pagina pentru parteneri.']],
            ['type' => 'faq', 'data' => ['title' => 'Întrebări frecvente', 'topic' => '']],
        ], ['description' => 'Contact Optoplus: telefon, email și formular pentru întrebări despre lentile, rame și comenzi.']);

        // Textele juridice: siteul vechi le are incomplete (ex. „[emailul tău de contact]”) și cu date de firmă
        // contradictorii. Publicăm o pagină onestă; textul vechi rămâne ciornă pentru revizia juridică (cms.legal).
        $legal = [
            ['/termeni-si-conditii/', 'Termeni și condiții'],
            ['/politica-de-confidentialitate/', 'Politica de confidențialitate'],
            ['/politica-de-cookie-uri/', 'Politica de cookie-uri'],
            ['/politica-ramburs-retur/', 'Politica de retur și rambursare'],
            ['/politica-de-livrare-si-retur-ue-vanzari-intracomunitare/', 'Livrare și retur în Uniunea Europeană'],
        ];
        foreach ($legal as [$path, $title]) {
            $svc->upsert($path, 'policy', $title, [['type' => 'rich_text', 'data' => ['title' => '', 'html' => '<p>Textul acestei pagini este în curs de actualizare împreună cu noul site, pentru a reflecta datele complete ale firmei și legislația în vigoare.</p><p>Până la publicare, pentru orice întrebare ne puteți contacta folosind datele de pe <a href="/contact/">pagina de contact</a>.</p>']]], ['noindex' => true], true, true);
        }
    }
}
