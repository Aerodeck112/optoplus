<?php

namespace App\Modules\Cms\Services;

use App\Core\Settings\Settings;

/**
 * 1.0.6: pagina „Pentru clinici și parteneri” devine o pagină de prezentare: deschidere cu macheta animată a
 * portalului, „pentru cine”, turul interactiv al portalului, formularul și întrebările.
 *
 * Clasă separată (nu o metodă nouă în ContentRefresh): în timpul actualizării de la 1.0.5, în memorie e încă
 * ContentRefresh din 1.0.5, fără metode noi. Folosește doar ContentRefresh::apply(), neschimbată din 1.0.5.
 * Ca la ContentRefresh, o pagină editată de echipă nu se suprascrie: varianta nouă rămâne ciornă.
 */
class PartnersPageRefresh
{
    public function run(): ?string
    {
        return (new ContentRefresh)->apply('/parteneri/', fn (array $blocks) => self::transform($blocks), 'Actualizare 1.0.6: pagină nouă pentru parteneri');
    }

    public static function transform(array $blocks): array
    {
        if (collect($blocks)->contains('type', 'b2b_hero')) {
            return $blocks;
        }
        $keep = array_values(array_filter($blocks, fn ($b) => ! in_array($b['type'] ?? '', ['b2b_band'], true)));
        // Paragraful de deschidere (primul bloc de text) este preluat de noua deschidere.
        if (($keep[0]['type'] ?? '') === 'rich_text') {
            array_shift($keep);
        }

        return array_merge([self::hero(), self::audience(), self::tour()], $keep);
    }

    public static function hero(): array
    {
        $s = app(Settings::class);
        $min = $s->get('orders.production_days_min');
        $max = $s->get('orders.production_days_max');
        $points = [
            ['text' => 'Lentile produse în laboratorul propriu'],
            ['text' => 'Fiecare rețetă verificată de un specialist optic'],
            ['text' => 'Cod intern al pacientului în loc de nume'],
        ];
        if ($min && $max) {
            $points[] = ['text' => "Producție în {$min}–{$max} zile lucrătoare"];
        }

        return ['type' => 'b2b_hero', 'data' => [
            'eyebrow' => 'Pentru clinici, cabinete și magazine de optică',
            'title' => 'Lentile din laboratorul nostru, comandate simplu pentru pacienții dumneavoastră.',
            'subtitle' => 'Un portal pentru toată echipa: comandați mai multe lucrări odată, urmăriți fiecare lucrare de la verificarea rețetei până la expediere și găsiți ofertele și documentele într-un singur loc.',
            'primary' => ['label' => 'Devino partener', 'url' => '/parteneri/#cerere'],
            'secondary' => ['label' => 'Autentificare parteneri', 'url' => '/portal/autentificare/'],
            'points' => $points,
            'caption' => 'Ilustrație a portalului, cu date fictive.',
        ]];
    }

    public static function audience(): array
    {
        return ['type' => 'b2b_audience', 'data' => [
            'title' => 'Pentru cine este portalul',
            'intro' => 'Pentru profesioniștii care recomandă, comandă și montează lentile de vedere.',
            'items' => [
                ['icon' => 'building', 'title' => 'Clinici oftalmologice', 'text' => 'Medicul prescrie, colegii comandă lentilele direct din portal, cu codul intern al pacientului.'],
                ['icon' => 'eye', 'title' => 'Cabinete de optometrie', 'text' => 'Trimiteți valorile măsurate și primiți lentilele realizate după rețetă, verificate de specialist.'],
                ['icon' => 'frame', 'title' => 'Magazine de optică', 'text' => 'Comenzi repetate, comandă rapidă pentru mai multe lucrări și urmărirea fiecăreia până la livrare.'],
                ['icon' => 'user', 'title' => 'Echipe cu mai mulți colegi', 'text' => 'Administratorul adaugă colegii și alege ce poate face fiecare în contul organizației.'],
            ],
        ]];
    }

    public static function tour(): array
    {
        return ['type' => 'b2b_tour', 'data' => [
            'title' => 'Portalul, pe scurt',
            'intro' => 'Tot ce faceți zilnic cu laboratorul, într-un singur cont. Alegeți o funcție sau lăsați turul să ruleze.',
            'tabs' => [
                ['screen' => 'quick', 'title' => 'Comandă rapidă', 'text' => 'Completați mai multe lucrări într-un tabel; fiecare rând se validează pe loc, cu prețul din lista dumneavoastră.'],
                ['screen' => 'csv', 'title' => 'Import din CSV', 'text' => 'Încărcați un fișier după model. Vedeți imediat rândurile corecte și ce trebuie corectat la celelalte.'],
                ['screen' => 'tracking', 'title' => 'Urmărirea producției', 'text' => 'Fiecare lucrare arată etapa curentă: verificarea rețetei, producția în laborator, controlul de calitate, expedierea.'],
                ['screen' => 'quotes', 'title' => 'Oferte cu versiuni', 'text' => 'Cereți o ofertă pentru volume sau lucrări speciale; primiți versiunile în cont și o acceptați online.'],
                ['screen' => 'documents', 'title' => 'Documente', 'text' => 'Contractul, ofertele, facturile și certificatele organizației, descărcabile oricând.'],
                ['screen' => 'team', 'title' => 'Echipa dumneavoastră', 'text' => 'Invitați colegii și alegeți ce poate face fiecare: comenzi și oferte, documente financiare sau doar vizualizare.'],
            ],
            'caption' => 'Machete ilustrative, cu date fictive.',
        ]];
    }
}
