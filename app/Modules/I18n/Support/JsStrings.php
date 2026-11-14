<?php

namespace App\Modules\I18n\Support;

use App\Modules\I18n\Services\HtmlTranslator;

/**
 * Textele afișate de JavaScript (ilustrațiile explicative din configurator). Pagina primește traducerile lor
 * într-un <script type="application/json" id="i18n-js">; scripturile le citesc cu t('text românesc').
 * Marcajele {x} se completează în JavaScript.
 */
final class JsStrings
{
    public const ALL = [
        'departe', 'la ecran', 'aproape', 'clar: {x}',
        'Monofocale pentru {label}: vezi clar {where}, prin toată lentila.',
        'Monofocale pentru {label}: {where} imaginea nu e clară — lentila are o singură distanță de focalizare. Dacă ai nevoie de ochelari și pentru {where}, ai nevoie de o a doua pereche sau de lentile progresive.',
        'Progresive: privind prin partea de sus, vezi clar departe (condus, stradă).',
        'Progresive: prin mijlocul lentilei vezi clar la distanța unui ecran. Coridorul e îngust: privești drept înainte.',
        'Progresive: coborând privirea, vezi clar aproape (telefon, carte). Lateral, jos, imaginea e mai puțin clară — întorci puțin capul. Adaptarea durează câteva zile.',
        'Degresive (de birou): vezi clar {where}, cu o zonă largă, confortabilă pentru lucru.',
        'Degresive (de birou): departe imaginea nu e clară — nu sunt pentru condus sau pentru mers pe stradă.',
        'Bifocale: prin partea mare vezi clar departe.',
        'Bifocale: prin segmentul de jos (vizibil pe lentilă) vezi clar aproape.',
        'Bifocale: la distanța unui ecran nu există o zonă dedicată — imaginea „sare” între departe și aproape.',
        'Cu antireflex: reflexiile scad mult; ochii se văd natural, iar în lumina farurilor apar mai puține străluciri.',
        'Cu filtru: o parte din lumina albastră-violet este reținută de lentilă; restul culorilor trec. Ilustrație, nu o măsurătoare.',
        ' în nuanța aleasă (până la aproximativ {pct}%)',
        'În casă: lentila este aproape transparentă.',
        'În mașină: Xtractive se închide și în spatele parbrizului, deci vă ajută și la volan. Cât de mult depinde de lumină și de temperatură.',
        'În mașină: Helio rămâne aproape transparent, pentru că parbrizul oprește razele UV care îl închid. Pentru condus, alegeți Xtractive.',
        'În mașină: lentila rămâne aproape transparentă, pentru că parbrizul oprește razele UV care o închid. Pentru condus, alegeți Xtractive.',
        'În mașină: majoritatea lentilelor fotocromatice rămân aproape transparente, pentru că parbrizul oprește razele UV care le închid. Xtractive se închide și în mașină.',
        ' în nuanța aleasă',
        'Afară, la soare: lentila se închide{shade}. Viteza reală depinde de lumina UV și de temperatură.',
        'Ilustrație: lentilă în fața unui tabel optotip',
    ];

    /** @return array<string, string> doar textele care au traducere */
    public static function for(string $locale): array
    {
        $t = app(HtmlTranslator::class);
        $out = [];
        foreach (self::ALL as $ro) {
            $tr = $t->plain(trim($ro), $locale);
            if ($tr !== trim($ro)) {
                $out[$ro] = (str_starts_with($ro, ' ') ? ' ' : '').$tr;
            }
        }

        return $out;
    }
}
