# Arhitectura platformei Optoplus

Ultima actualizare: 2026-10-29.

## 1. Alegerea stackului

Constrângeri: prima instalare pe **hosting cPanel** (fără Docker, root, Node pe server, Redis sau procese
permanente), actualizări din panou prin **ZIP semnat**, pagini publice randate pe server, administrare de către
nespecialiști, date sensibile (rețete = date privind sănătatea, art. 9 GDPR).

| Decizie | Alegere | De ce |
|---|---|---|
| Limbaj / framework | PHP ≥ 8.3, **Laravel 13** | Matur, documentat, cu suport de securitate; rulează pe orice cPanel cu PHP-FPM/LSAPI; ecosistem pentru autentificare, cozi pe bază de date, programator cron, criptare, validare. |
| Bază de date | MariaDB ≥ 10.6 / MySQL ≥ 8.0, `utf8mb4_unicode_ci` | Disponibilă pe cPanel; tranzacții și blocări pe rânduri (stoc, plăți idempotente, numere de comandă). Dezvoltare/test: MariaDB 10.11. |
| Randare | Blade pe server; JavaScript nativ, modular, încărcat **doar** pe paginile care îl folosesc (galerie, configurator, filtre, comandă rapidă, asistent la cerere) | HTML indexabil, scoruri bune pe mobil, mentenanță simplă, fără framework SPA. |
| Asseturi | Vite (doar în dezvoltare/CI) → `public/build` cu nume cu hash, inclus în pachet | Fără Node pe server. |
| Sesiuni, cache, cozi | driver `database`; cozile procesate din cron în loturi scurte | Fără Redis/worker. |
| Roluri | `spatie/laravel-permission` + politici pe obiect | Permisiuni pe server pe fiecare rută; politicile verifică proprietarul / organizația. |
| MFA personal | TOTP (`pragmarx/google2fa`, QR local `bacon/bacon-qr-code`) | Obligatoriu pentru rolurile din panou. |
| HTML din CMS | `ezyang/htmlpurifier` cu listă albă | Editorul nu poate introduce PHP/JS. |
| Semnare actualizări | Ed25519 (`ext-sodium`) | Cheia privată stă doar pe mașina de build. |
| Backup | PHP pur, criptat (`secretstream_xchacha20poly1305`), pe bucăți, reluabil | Merge pe hosting partajat. |
| Imagini | GD/Imagick → WebP + dimensiuni multiple, generate în loturi din cron | Imagini responsive fără serviciu extern. |
| AI | adaptor opțional (implicit **dezactivat**); asistentul funcționează pe reguli + catalog | Magazinul nu depinde de un serviciu extern. |

Nucleul (autentificare + MFA, permisiuni, audit, actualizări ZIP, backup, recuperare, instalare fără
Terminal) provine din platforma SEVALIA a aceluiași dezvoltator, deja instalată și reparată pe cPanel/LiteSpeed
real. Reutilizarea reduce riscul în zonele cele mai delicate (actualizări, recuperare). Tot ce ține de clinică a
fost eliminat; modulele de comerț sunt noi.

Dependențe Composer (toate consacrate, verificate cu `composer audit` la fiecare release): `laravel/framework`,
`spatie/laravel-permission`, `pragmarx/google2fa`, `bacon/bacon-qr-code`, `ezyang/htmlpurifier`. Nu adăugăm
pachete pentru plăți/curierat: adaptoarele folosesc clientul HTTP Laravel conform documentației oficiale a
fiecărui furnizor, ca să controlăm verificarea semnăturilor și idempotența.

## 2. Medii

| Mediu | Unde | Bază de date | Plăți / curier / AI | Indexare |
|---|---|---|---|---|
| dezvoltare | local / container | `optoplus` | adaptoare „simulare” | — |
| test automat | PHPUnit | `optoplus_test` (refăcută la fiecare rulare) | simulări + fixture-uri cu semnături | — |
| staging | `staging.optoplus.ro` pe același cPanel | bază separată | conturi **sandbox** ale furnizorilor | `noindex` forțat + parolă HTTP |
| producție | `optoplus.ro` | bază de producție | conturi reale, activate explicit din panou | normală |

`OPTOPLUS_STAGE` (`local|staging|production`) controlează: seederul demo refuză producția, emailurile reale se
trimit doar dacă sunt activate explicit, staging adaugă `X-Robots-Tag: noindex`.

## 3. Monolit modular

```
app/Core/                 audit, setări, securitate (CSP, index orb), module, bani, utilitare
app/Modules/
  Identity/               personalul: utilizatori, autentificare, MFA, roluri
  Customers/              conturile clienților (gardă separată „client”), adrese, consimțăminte, export/ștergere date
  Catalog/                produse, variante, mărci, categorii, atribute, dimensiuni ramă, imagini, căutare
  Lenses/                 produse-lentilă, familii, materiale/indici, tratamente, oferte, matricea de fabricație, reguli
  Pricing/                liste de preț, promoții, cupoane, praguri, TVA, motorul de preț
  Configurator/           configurații, rețete (criptate), validare, linkuri securizate, OCR (propuneri)
  Shop/                   coș, checkout, crearea comenzilor (snapshot)
  Orders/                 comenzi, trei statusuri separate, istoric, documente
  Payments/               metode, intenții, webhookuri idempotente, rambursări, reconciliere
  Shipping/               metode, reguli, expedieri, AWB, tracking (adaptoare)
  Invoicing/              adaptor către soluția de facturare (nu generează facturi fiscale proprii)
  Production/             fișe de lucru, etape configurabile, responsabili, termene, loturi, refaceri
  B2B/                    organizații, cereri de parteneriat, utilizatori cu roluri, oferte cu versiuni,
                          comandă rapidă, import CSV, credit, reclamații
  Crm/                    oportunități, sarcini, notițe, comunicări, vederea 360 a clientului
  Storefront/             site public: acasă, catalog, produs, lentile, configurator, ghiduri, B2B, contact, SEO
  Cms/                    pagini din blocuri, revizii, previzualizare, meniuri, media, FAQ, redirecturi, formulare
  Assistant/              asistentul Optoplus: instrumente controlate, bază de cunoștințe, limite, adaptor AI
  Import/                 importatorul WooCommerce: simulare, loturi, mapare, reconciliere, redirecturi
  Reports/                vânzări, încasări, anulări, rambursări, producție, marjă (doar cu costuri reale)
  Notifications/          șabloane email, jurnal de livrări
  Updates/  Backup/  Dashboard/   (din nucleu)
```

Fiecare modul are `ModuleServiceProvider` (descoperit automat) cu `permissions()`, `roleGrants()`,
`navigation()`, `dashboardWidgets()`, `schedule()`, rute `routes/public.php` și `routes/panel.php` (prefix
`/panou`), vizualizări cu namespace propriu. Modulele comunică prin servicii publice (ex.
`PriceEngine`, `ConfigurationValidator`, `OrderService`), nu prin interogări în tabelele altui modul.

### Fluxuri cheie

```
Configurator ──► ConfigurationValidator (matrice + reguli) ──► PriceEngine ──► rezumat (server)
     │                                                                 ▲
     ▼                                                                 │
   Coș (re-validează la fiecare modificare) ──► Checkout ──► OrderService.place()
                                                            │  snapshot configurație + prețuri + reguli
                                                            ▼
                                         Order: commercial_status / payment_status / production_status
                                            │                 │                     │
                                     Payments (webhook)   Shipping (AWB)     Production (etape)
```

- **Prețul vine numai din `PriceEngine`** (server). Clientul primește prețul afișat; la plasarea comenzii totalul
  se recalculează și se compară; o diferență oprește comanda cu un mesaj clar.
- **Validarea vine numai din `ConfigurationValidator`**. JavaScriptul arată aceleași mesaje pentru confort, dar
  nu decide.
- Comenzile cu rețetă intră în `production_status = verificare_reteta`; nu ajung „aprobate pentru producție”
  fără acțiunea unui specialist optic (permisiune dedicată, auditată).

## 4. Zone și protecție

| Zonă | Prefix | Protecție |
|---|---|---|
| Site public | `/` (URL-urile `/produs/...` păstrate) | Public; cache de pagină **doar** pentru vizitatori fără sesiune de client și fără coș |
| Cont client | `/cont` | Gardă `client`, sesiune separată, limitare încercări, verificare email |
| Portal B2B | `/b2b` | Gardă `client` + membru al unei organizații **aprobate**; rol în organizație; prețuri private, fără cache public |
| Panou | `/panou` | Gardă `web` (personal), MFA, permisiuni pe rute, politici pe obiect, `noindex` |
| Linkuri securizate | `/c/{token}` (configurație), `/comanda/{token}` (status) | Token aleator 256 biți, păstrat ca hash, expirare, scop limitat, fără date medicale în pagină |
| Webhookuri | `/webhook/{furnizor}` | Fără sesiune/CSRF; verificarea semnăturii furnizorului; idempotență pe ID-ul evenimentului |
| Recuperare | `recovery.php` | Independent de framework; cheie de recuperare |

## 5. Structura pe hosting cPanel

```
/home/<cont>/
  optoplus/
    releases/<versiune>/      cod + vendor + public/build (din pachet)
    shared/
      .env                    configurare și secrete (chmod 600)
      storage/                loguri, sesiuni, cache, documente private (rețete, oferte B2B), uploaduri
      backups/                backupuri criptate
      updates/                pachete primite, staging, jurnal de pași
      keys/                   cheia de criptare backup, hash-ul cheii de recuperare
    current.json              versiunea activă (comutare atomică)
  public_html/
    index.php                 front controller: citește current.json
    .htaccess
    build/                    asseturi compilate (versiunile coexistă)
    media/                    imagini publice optimizate (catalog, CMS)
    recovery.php
```

Rețetele, documentele B2B, backupurile, logurile și `.env` nu stau niciodată sub `public_html`.

## 6. Taskuri din cron

Un singur cron la minut: `php <deploy>/artisan schedule:run`. Fiecare task are `withoutOverlapping()` și loturi
limitate: coada de joburi (emailuri, imagini), generarea variantelor de imagine, reconcilierea plăților în
așteptare (interogarea statusului la procesator pentru plățile fără webhook după N minute), actualizarea
tracking-ului AWB, expirarea coșurilor și a linkurilor, backupul programat, importul în loturi.

## 7. Cache și invalidare

- Pagini publice: cache de fișier pentru răspunsurile GET anonime (fără cookie de sesiune client, fără coș),
  cheie = cale + parametri permiși; invalidare la salvarea produsului, prețului, promoției, paginii, meniului.
- Parametrii de filtrare nepermiși nu intră în cache (evită explozia de chei).
- `Cache-Control: private, no-store` pe cont, coș, checkout, configurator salvat, B2B, panou.
- Prețurile B2B nu sunt niciodată calculate în cache-ul public; motorul de preț primește contextul (client,
  organizație) explicit.

## 8. Securitate

Detalii în `docs/SECURITATE.md`. Pe scurt: CSRF pe toate formularele, CSP cu nonce, interogări parametrizate
(Eloquent / query builder), escapare implicită Blade, sanitizare HTML CMS, limitarea autentificărilor, MFA
personal, reautentificare pentru acțiuni sensibile, audit pentru consultarea/descărcarea rețetelor, exporturi,
schimbări de preț și aprobări de producție, fișiere încărcate validate (tip real, dimensiune, re-codificare a
imaginilor, PDF verificat), stocate cu nume aleator în afara webroot-ului.
