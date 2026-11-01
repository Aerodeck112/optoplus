<?php

namespace App\Modules\Settings;

/**
 * Tabul „Setări” din panou: secțiunile, câmpurile și permisiunea fiecărei secțiuni. Valorile se păstrează în
 * tabela `settings`; câmpurile de tip „secret” sunt criptate și nu se mai afișează după salvare.
 * Tipuri: text, email, tel, url, textarea, number, money (lei → bani), percent (% → puncte de bază), bool, secret, select.
 */
final class SettingsSchema
{
    public static function sections(): array
    {
        return [
            'firma' => [
                'label' => 'Firma', 'permission' => 'cms.settings',
                'intro' => 'Datele juridice apar în subsolul siteului, în emailuri, pe pagina de transfer bancar și în textele juridice.',
                'fields' => [
                    'company.name' => ['Denumirea firmei', 'text', 'required'],
                    'company.vat_code' => ['CUI (cu „RO” doar dacă firma este plătitoare de TVA)', 'text'],
                    'company.registry' => ['Nr. de înregistrare la Registrul Comerțului', 'text'],
                    'company.address' => ['Sediul social', 'text'],
                    'company.vat_payer' => ['Firma este înregistrată în scopuri de TVA', 'bool'],
                ],
            ],
            'contact' => [
                'label' => 'Contact', 'permission' => 'cms.settings',
                'fields' => [
                    'contact.phone_b2c' => ['Telefon clienți', 'tel', 'required'],
                    'contact.email_b2c' => ['Email clienți', 'email', 'required'],
                    'contact.address' => ['Adresa magazinului / punctului de lucru', 'text'],
                    'contact.hours' => ['Program', 'text'],
                    'contact.phone_b2b' => ['Telefon parteneri (B2B)', 'tel'],
                    'contact.email_b2b' => ['Email parteneri (B2B)', 'email'],
                    'notifications.orders_email' => ['Email intern pentru comenzi noi', 'email'],
                ],
            ],
            'tva' => [
                'label' => 'TVA', 'permission' => 'pricing.manage',
                'intro' => 'Prețurile B2C sunt cu TVA inclus: cota schimbă doar defalcarea TVA, nu prețul final. Comenzile plasate își păstrează cota din momentul plasării.',
                'fields' => [
                    'tax.vat_lenses_bp' => ['Cota TVA pentru lentile și tratamente (%)', 'percent', 'required'],
                    'tax.vat_frames_bp' => ['Cota TVA pentru rame (%)', 'percent', 'required'],
                    'tax.vat_default_bp' => ['Cota TVA pentru alte produse (%)', 'percent', 'required'],
                    'tax.vat_shipping_bp' => ['Cota TVA pentru livrare (%)', 'percent', 'required'],
                    'tax.confirmed' => ['Cotele au fost confirmate de contabil', 'bool'],
                ],
            ],
            'plati' => [
                'label' => 'Plăți', 'permission' => 'payments.settings', 'reauth' => true,
                'intro' => 'O metodă apare la finalizarea comenzii doar dacă este activă și are toate datele completate.',
                'groups' => [
                    'Card online — Banca Transilvania iPay' => [
                        'payments.card_enabled' => ['Plata cu cardul este activă', 'bool'],
                        'payments.btipay.environment' => ['Mediul', 'select', null, ['test' => 'Test (sandbox)', 'productie' => 'Producție']],
                        'payments.btipay.username' => ['Utilizator API (de la BT)', 'text'],
                        'payments.btipay.password' => ['Parola API (de la BT)', 'secret'],
                        'payments.btipay.base_url_test' => ['Adresa serverului de test', 'url'],
                        'payments.btipay.base_url_production' => ['Adresa serverului de producție', 'url'],
                        'payments.btipay.force_3ds' => ['Cere autentificare 3-D Secure', 'bool'],
                    ],
                    'Transfer bancar' => [
                        'payments.bank_transfer_enabled' => ['Transferul bancar este activ', 'bool'],
                        'company.iban' => ['IBAN în care se virează banii', 'text'],
                        'company.bank' => ['Banca', 'text'],
                        'payments.transfer_note' => ['Instrucțiuni suplimentare pentru client', 'textarea'],
                    ],
                    'Numerar la livrare (ramburs)' => [
                        'payments.cod_enabled' => ['Plata la livrare este activă', 'bool'],
                    ],
                ],
            ],
            'livrare' => [
                'label' => 'Livrare și termene', 'permission' => 'shipping.manage',
                'fields' => [
                    'shipping.courier_enabled' => ['Livrarea prin curier este activă', 'bool'],
                    'shipping.courier_price' => ['Cost livrare în România (lei, cu TVA)', 'money', 'required'],
                    'shipping.free_from' => ['Livrare gratuită de la (lei; gol = niciodată)', 'money'],
                    'shipping.transit_min' => ['Transport: minim zile lucrătoare', 'number'],
                    'shipping.transit_max' => ['Transport: maxim zile lucrătoare', 'number'],
                    'orders.production_days_min' => ['Execuție lentile: minim zile lucrătoare', 'number', 'required'],
                    'orders.production_days_max' => ['Execuție lentile: maxim zile lucrătoare', 'number', 'required'],
                ],
            ],
        ];
    }

    /** @return array<string, array{0:string,1:string,2:?string,3:?array}> toate câmpurile unei secțiuni */
    public static function fields(string $section): array
    {
        $s = self::sections()[$section] ?? [];
        $fields = $s['fields'] ?? [];
        foreach ($s['groups'] ?? [] as $group) {
            $fields += $group;
        }

        return $fields;
    }
}
