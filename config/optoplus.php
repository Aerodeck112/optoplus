<?php

return [
    // Versiunea aplicației; sursa de adevăr pentru actualizări este fișierul VERSION din rădăcină.
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0-dev',

    // local | staging | production
    'stage' => env('OPTOPLUS_STAGE', 'local'),

    // Directorul din afara public_html pentru date persistente (documente, backup, actualizări).
    'shared_path' => env('OPTOPLUS_SHARED_PATH') ?: storage_path('app'),

    'security' => [
        // Rolurile pentru care MFA este obligatoriu. '*' = toate rolurile cu acces în panou.
        'mfa_required_roles' => ['*'],
        // Fereastra (minute) în care o reautentificare rămâne valabilă pentru operații sensibile.
        'reauth_minutes' => 10,
        // Durata maximă absolută a unei sesiuni de panou, indiferent de activitate (minute).
        'absolute_session_minutes' => 720,
        'login_max_attempts' => 5,
        'login_decay_seconds' => 900,
        'password_min_length' => 12,
        'blind_index_key' => env('OPTOPLUS_BLIND_INDEX_KEY'),
    ],

    // Biblioteca media publică (în producție: public_html/media) și cache-ul paginilor publice.
    'media_path' => env('OPTOPLUS_MEDIA_PATH') ?: public_path('media'),
    'media_url' => '/media',
    'site' => [
        'page_cache' => env('OPTOPLUS_PAGE_CACHE', null) === null ? null : (bool) env('OPTOPLUS_PAGE_CACHE'),
        'canonical_host' => env('OPTOPLUS_CANONICAL_URL', 'https://optoplus.ro'),
    ],

    // Surse externe permise de Content-Security-Policy. Se adaugă numai servicii confirmate.
    'csp' => [
        'frame_src' => ['https://www.google.com', 'https://maps.google.com', 'https://www.youtube-nocookie.com', 'https://www.youtube.com'],
        'img_src' => ['https://i.ytimg.com'], // imaginile de previzualizare ale videoclipurilor YouTube
        'connect_src' => [],
    ],

    // Lentile: ofertele marcate „neconfirmat” (preluate din siteul vechi) pot fi vândute până la confirmarea
    // laboratorului? Pe producție se recomandă false după validarea matricei.
    'lenses' => [
        'sell_unconfirmed' => (bool) env('OPTOPLUS_SELL_UNCONFIRMED_LENSES', true),
    ],

    // Configurator: limite de plauzibilitate (nu de fabricație — acelea sunt în matrice), în zecimi de mm / sutimi dpt.
    'configurator' => [
        'pd_binocular_range' => [500, 800],
        'pd_monocular_range' => [250, 400],
        'anisometropia_review' => 300,
        'own_frame_service' => (bool) env('OPTOPLUS_OWN_FRAME_SERVICE', false),
        'share_link_days' => 30,
        'upload_max_kb' => 10240,
    ],

    // Retenția datelor (ani/luni). Valorile finale se confirmă cu responsabilul GDPR (docs/INTREBARI.md).
    'retention' => [
        // Rețetele neatașate unei comenzi se șterg după acest interval de la ultima modificare.
        'prescription_years' => (int) env('OPTOPLUS_RETENTION_PRESCRIPTION_YEARS', 3),
        // Rețetele din cererile de ajutor/contact (fără comandă).
        'prescription_lead_months' => (int) env('OPTOPLUS_RETENTION_LEAD_MONTHS', 12),
        // Configurațiile anonime abandonate (fără cont, fără comandă).
        'guest_configuration_days' => (int) env('OPTOPLUS_RETENTION_GUEST_CFG_DAYS', 60),
    ],

    // Plăți: furnizorii activați. Cardul se activează doar după configurarea contractului cu procesatorul.
    'payments' => [
        'transfer_days' => 5, // zile pentru plata prin transfer, după care comanda se anulează automat (confirmare umană)
        'simulation' => (bool) env('OPTOPLUS_PAYMENT_SIMULATION', false), // NUMAI dev/staging; refuzat în producție
    ],

    // Importul: domeniile de pe care se pot descărca imaginile vechiului magazin (protecție SSRF).
    'import' => [
        'image_hosts' => ['optoplus.ro', 'www.optoplus.ro'],
    ],
];
