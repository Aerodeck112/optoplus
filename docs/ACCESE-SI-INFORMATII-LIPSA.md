# Accese, exporturi și informații necesare

Regulă: **nicio parolă sau cheie nu se pune în repository, în emailuri necriptate sau în fișiere publice.**
Credențialele se transmit pe un canal separat (manager de parole partajat, apel telefonic, mesaj cu
autodistrugere) și se introduc direct în `shared/.env` pe server sau în panoul „Integrări”. Pentru WordPress se
creează un **cont temporar** dedicat (rol „Shop manager” sau „Administrator” cu expirare) și chei API **doar de
citire**, revocate după migrare.

Stare: ✅ primit · ⏳ cerut · ❌ blocant pentru o funcție.

## A. Migrarea catalogului și a comenzilor

| # | Ce avem nevoie | De ce | Format / cum | Stare |
|---|---|---|---|---|
| A1 | Export produse WooCommerce | catalog, prețuri, imagini | CSV din WooCommerce → Produse → Export (toate coloanele, inclusiv meta) | ✅ 2026-10-29 (407 rânduri) |
| A2 | Chei REST API WooCommerce **Read** | inventariere completă (atribute globale, termeni, imagini cu ID, meta), sincronizarea diferențelor înainte de lansare | WooCommerce → Setări → Avansat → REST API → cheie „Read” pe un utilizator temporar | ⏳ |
| A3 | Copie a bazei de date WordPress (sau acces phpMyAdmin doar citire) | regulile APF complete (`wapf_field_groups`), cupoane, setări transport, meta Yoast (titluri/descrieri SEO), utilizatori, comenzi | export SQL din cPanel/phpMyAdmin, transmis criptat | ⏳ |
| A4 | Fișierele media (`wp-content/uploads`) | imagini originale, fără recomprimare | arhivă ZIP din cPanel File Manager sau backup All-in-One WP Migration | ⏳ (importatorul descarcă și din URL-urile publice) |
| A5 | Exportul grupului APF 21069 | reguli de configurator, inclusiv cele invizibile în HTML (formule, reguli de coș, cantități) | APF → Field groups → Export (JSON), sau tabelul `wp_posts` (post_type `wapf_product`) din A3 | ⏳ — extras public parțial în `database/content/configurator-actual-apf.json` |
| A6 | Codul / descrierea pluginului `optoplus-reserve-availability` | regulile de disponibilitate și rezervare | ZIP al pluginului sau descriere scrisă | ⏳ |
| A7 | Fragmentele din Code Snippets | reguli ascunse (reduceri, taxe, câmpuri checkout) | Code Snippets → Export | ⏳ |
| A8 | Cupoane (`shop_coupon`) | coduri, procente, restricții (categorii, lentile), cumul, beneficiari, dovezi cerute | export din A3 sau plugin de export cupoane | ⏳ |
| A9 | Setări transport (zone, metode, costuri, praguri gratuite) | regulile de transport | capturi ale ecranelor WooCommerce → Livrare sau din A3 | ⏳ |
| A10 | Clienți și comenzi (doar dacă se migrează istoricul) | istoric în cont, CRM | din A3; parolele **nu** se transferă (vezi `docs/MIGRARE.md` §5) | ⏳ decizie client |
| A11 | Traducerile TranslatePress (maghiară) | păstrarea versiunii HU | tabelele `wp_trp_*` din A3 | ⏳ |

## B. Hosting cPanel (înainte de prima instalare)

Rulăm `php artisan optoplus:install --check` sau pagina `install.php?check=1` pe hosting și primim raportul. Până
atunci, valorile sunt **neverificate**.

| # | Ce verificăm | Minim cerut | Stare |
|---|---|---|---|
| B1 | Versiune PHP selectabilă | 8.3 sau mai nou | ⏳ |
| B2 | Extensii PHP | pdo_mysql, mbstring, openssl, sodium, intl, gd sau imagick, zip, fileinfo, curl, dom, xml, bcmath (recomandat) | ⏳ |
| B3 | Bază de date | MariaDB ≥ 10.6 sau MySQL ≥ 8.0, utilizator dedicat, `utf8mb4` | ⏳ |
| B4 | SSH / Terminal / Composer | opțional: instalarea merge și fără (pachet cu `vendor/` inclus, `install.php`) | ⏳ |
| B5 | Cron | un job la minut (`php …/artisan schedule:run`) | ⏳ |
| B6 | `memory_limit`, `max_execution_time`, `upload_max_filesize`, `post_max_size` | ≥ 256M; ≥ 60 s (pașii de actualizare au buget de 20 s); upload ≥ 64M pentru pachetele ZIP | ⏳ |
| B7 | Spațiu disponibil | ≥ 3× dimensiunea aplicației + media + backupuri (estimare inițială 3–5 GB) | ⏳ |
| B8 | Document root | se poate seta pe `public_html` (front controller propriu) — fără symlinkuri | ⏳ |
| B9 | Conexiuni externe permise (outbound HTTPS) | procesatorul de plăți, curierul, facturarea, AI (dacă se activează), SMTP | ⏳ |
| B10 | Server web | Apache sau LiteSpeed (`.htaccess`), HTTPS valid | ⏳ |
| B11 | Mediu de test | subdomeniu `staging.optoplus.ro` cu bază de date separată | ⏳ |

## C. Integrări (adaptoarele sunt construite; se activează doar cu date reale)

| # | Integrare | Ce trebuie | Stare |
|---|---|---|---|
| C1 | Card online | confirmarea procesatorului (azi: **BT iPay** și WooPayments sunt active), cont de comerciant, credențiale de test și de producție, URL webhook | ⏳ — adaptor BT iPay pregătit pe baza documentației oficiale; neactivat |
| C2 | Transfer bancar | IBAN, banca, titularul contului, regula de confirmare | ⏳ |
| C3 | Ramburs / numerar la ridicare | confirmare dacă se oferă, la ce produse (personalizate?), avans | ⏳ |
| C4 | Curier / AWB | curierul folosit (contract), cont API, servicii, tarife | ⏳ |
| C5 | Facturare | soluția folosită (ex. SmartBill, Oblio, FGO, Saga, alt ERP), cheie API, serii, cotă TVA | ⏳ |
| C6 | e-Factura (B2B) | dacă facturarea aleasă o transmite în SPV | ⏳ |
| C7 | Email tranzacțional | SMTP (cont, server, port) sau serviciu (ex. Brevo, Amazon SES), SPF/DKIM pe domeniu | ⏳ |
| C8 | SMS / WhatsApp | dacă se dorește, furnizorul | ⏳ opțional |
| C9 | Asistent AI | furnizorul și cheia API (implicit dezactivat; asistentul funcționează fără AI pe reguli și catalog), bugetul lunar, acordul pentru trimiterea datelor | ⏳ |
| C10 | Analytics / marketing | GA4 ID, Google Ads, Meta Pixel / CAPI, Merchant Center — și decizia de consimțământ | ⏳ |
| C11 | Widgetul actual `ai.vitim.ro` | păstrat, înlocuit sau integrat? | ⏳ |

## D. Date de afacere (blochează publicarea textelor)

| # | Informație | Stare |
|---|---|---|
| D1 | Firma vânzătoare (denumire, CUI, Reg. Com., sediu, punct de lucru, capital social, contact) — vezi contradicțiile din `docs/audit/OBSERVATII-CONTINUT.md` | ⏳ |
| D2 | Firma producătoare a lentilelor (aceeași?) | ⏳ |
| D3 | Lista produselor-lentilă reale: familii, designuri, materiale, indici, tratamente, culori, **forma de livrare** (brută / semifinisată / finită / personalizată / montată), unitate (bucată/pereche) | ⏳ |
| D4 | **Matricea de fabricație**: intervale SPH, CYL, ADD, combinații SPH+CYL, diametre, prisme, înălțimi minime de montaj, rame compatibile (ex. fir de nylon, capse) per produs | ⏳ — blocant pentru validări finale |
| D5 | Lista de prețuri B2C confirmată (lentile, tratamente, montaj, servicii) și politica de reduceri / cumul | ⏳ (azi: prețurile publice APF) |
| D6 | Prețuri și condiții B2B (liste pe parteneri, praguri cantitative, termene de plată, limite de credit) | ⏳ |
| D7 | Termene reale de producție per familie și de transport per metodă | ⏳ |
| D8 | Fișe tehnice ale lentilelor și tratamentelor (sursa valorilor afișate) | ⏳ |
| D9 | Certificări / autorizații (dacă există) cu documente | ⏳ — nu afișăm nimic fără document |
| D10 | Garanția comercială (dacă există, peste garanția legală) | ⏳ |
| D11 | Politica de avans pentru produse personalizate și metodele de plată permise pentru ele | ⏳ |
| D12 | Fluxul real de producție din laborator (etape, responsabili, control calitate, refaceri) | ⏳ — pornim cu fluxul din cerință, configurabil |
| D13 | Serviciul „lentile pentru rama mea” (rama trimisă de client): există? cost, transport, riscuri | ⏳ |
| D14 | Fotografii reale din producție (laborator, utilaje, echipă — cu acordul persoanelor) | ⏳ parțial (14 fotografii de lentile primite) |
| D15 | Texte juridice revizuite de jurist (termeni, confidențialitate, cookie, retur) | ⏳ |
