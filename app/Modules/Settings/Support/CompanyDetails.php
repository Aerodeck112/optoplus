<?php

namespace App\Modules\Settings\Support;

use App\Core\Audit\Audit;
use App\Core\Settings\Settings;

/**
 * Datele firmei care vinde pe optoplus.ro: OFA SRL, confirmată de client (2026-11-07). Datele de identificare
 * sunt cele din registrul public ANAF (serviciul PlatitorTvaRest, interogat pe 2026-11-07): CUI 29223337,
 * J2011001019267, sediul social în sat Crăciunești nr. 235/A, C5, jud. Mureș, neînregistrată în scopuri de TVA.
 *
 * Completează doar câmpurile goale; adresa de contact se înlocuiește numai dacă e încă cea preluată de pe
 * siteul vechi (Str. Poștei nr. 3), pe care clientul a semnalat-o ca greșită. Ce a scris echipa în panou rămâne.
 * Clasă nouă, de sine stătătoare (rulează din migrare, cu codul vechi în memorie): doar Settings și Audit.
 */
class CompanyDetails
{
    public const NAME = 'OFA SRL';
    public const VAT_CODE = '29223337';
    public const REGISTRY = 'J2011001019267';
    public const ADDRESS = 'Sat Crăciunești nr. 235/A, C5, comuna Crăciunești, județul Mureș';
    public const OLD_CONTACT_ADDRESS = 'Str. Poștei nr. 3, Târgu Mureș';

    /** @return list<string> cheile modificate */
    public static function run(): array
    {
        $s = app(Settings::class);
        $changed = [];
        foreach (['company.name' => self::NAME, 'company.vat_code' => self::VAT_CODE, 'company.registry' => self::REGISTRY, 'company.address' => self::ADDRESS] as $key => $value) {
            if (trim((string) $s->get($key)) === '') {
                $s->set($key, $value);
                $changed[] = $key;
            }
        }
        $contact = trim((string) $s->get('contact.address'));
        if ($contact === '' || $contact === self::OLD_CONTACT_ADDRESS) {
            $s->set('contact.address', self::ADDRESS);
            $changed[] = 'contact.address';
        }
        // Banda de sus (1.0.12): fără localitate pentru laborator până la confirmare.
        $topbar = (string) $s->get('site.topbar_text');
        if (str_starts_with($topbar, 'Lentile produse în laboratorul nostru, din Târgu Mureș.')) {
            $s->set('site.topbar_text', str_replace(', din Târgu Mureș.', '.', $topbar));
            $changed[] = 'site.topbar_text';
        }
        if ($changed !== []) {
            Audit::log('settings.updated', null, ['chei' => $changed, 'sursa' => 'actualizare 1.0.12: datele firmei (ANAF)']);
        }

        return $changed;
    }
}
