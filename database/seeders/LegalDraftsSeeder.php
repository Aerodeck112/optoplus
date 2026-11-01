<?php

namespace Database\Seeders;

use App\Core\Settings\Settings;
use App\Core\Support\Money;
use App\Core\Support\Tax;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\PageService;
use Illuminate\Database\Seeder;

/**
 * Încarcă proiectele textelor juridice (database/content/legal) ca CIORNE ale paginilor juridice. Nu publică
 * nimic: publicarea se face din panou, după verificare, de cineva cu permisiunea „cms.legal”.
 * Nu suprascrie o ciornă sau o versiune publicată modificată ulterior în panou.
 */
class LegalDraftsSeeder extends Seeder
{
    public function run(): void
    {
        $s = app(Settings::class);
        $vars = [
            '{firma}' => e($s->get('company.name') ?? '[denumirea firmei]'),
            '{cui}' => e($s->get('company.vat_code') ?? '[CUI]'),
            '{regcom}' => e($s->get('company.registry') ?? '[nr. Registrul Comerțului]'),
            '{sediu}' => e($s->get('company.address') ?? '[sediul social]'),
            '{adresa_magazin}' => e($s->get('contact.address') ?? '[adresa]'),
            '{telefon}' => e($s->get('contact.phone_b2c') ?? '[telefon]'),
            '{email}' => e($s->get('contact.email_b2c') ?? '[email]'),
            '{cost_livrare}' => Money::format((int) ($s->get('shipping.courier_price') ?? 0)),
            '{termen_executie}' => ($s->get('orders.production_days_min') ?? 2).'–'.($s->get('orders.production_days_max') ?? 5),
            '{nota_tva}' => e(Tax::note()),
            '{retentie_reteta}' => (string) config('optoplus.retention.prescription_years', 3),
            '{retentie_configuratii}' => (string) config('optoplus.retention.guest_configuration_days', 60),
            '{data}' => now()->format('d.m.Y'),
        ];
        $svc = app(PageService::class);
        foreach (glob(database_path('content/legal/*.html')) as $file) {
            $page = Page::where('path', '/'.basename($file, '.html').'/')->first();
            if (! $page || $page->versions()->where('note', 'like', 'Proiect text juridic%')->exists()) {
                continue;
            }
            $html = strtr((string) file_get_contents($file), $vars);
            $svc->saveDraft($page, $page->title, [['type' => 'rich_text', 'data' => ['title' => '', 'html' => $html]]], ['noindex' => false], 'Proiect text juridic — de verificat înainte de publicare');
            $page->update(['needs_legal_review' => true]);
        }
    }
}
