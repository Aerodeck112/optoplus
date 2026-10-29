# OPTO PLUS — instrucțiuni de proiect

Platforma Optoplus (optoplus.ro): magazin B2C (rame, ochelari compleți, lentile), lentile produse în laboratorul
propriu, configurator ghidat, portal B2B pentru clinici/cabinete/magazine de optică, CRM, producție, asistent,
CMS și actualizări ZIP semnate. Rulează pe hosting cPanel.

Citește întâi: `docs/ARHITECTURA.md`, `docs/SCHEMA-BD.md`, `docs/STATUS.md`, `docs/BACKLOG.md`,
`docs/INTREBARI.md`, `docs/audit/`.

## Reguli de neîncălcat

- Limba interfeței, mesajelor, documentației și comentariilor: **română cu diacritice** (ș, ț cu virgulă).
- **Nu inventa** prețuri, intervale de fabricație, termene, certificări, recenzii, garanții, număr de clienți,
  date de firmă, endpointuri sau scheme de furnizori. Ce nu e confirmat rămâne configurabil, marcat
  „neconfirmat” în date și în `docs/STATUS.md`.
- Lentilele **produse de Optoplus** (`made_in_house = true`) se separă de ramele și produsele comercializate.
  Nu sugera că Optoplus produce ramele sau mărcile din catalog.
- **Prețul vine numai din `PriceEngine`, validarea numai din `ConfigurationValidator`** (server). Nimic din
  browser sau din asistent nu stabilește un preț sau o compatibilitate.
- Rețetele sunt date privind sănătatea: criptate, fișiere private (în afara webroot, nume aleatoare), acces
  doar proprietar + personal cu permisiune, audit la consultare/descărcare. Nu ajung în loguri, analytics,
  marketing sau AI extern fără acord explicit configurat.
- O comandă cu rețetă nu devine „aprobată pentru producție” fără acțiunea unui specialist optic.
- Plata se marchează doar din webhook verificat / interogare la procesator / acțiune explicită auditată —
  niciodată din revenirea pe pagina de succes.
- Izolare strictă între organizațiile B2B (politici pe obiect, interogări filtrate pe organizație, fără cache
  public pentru prețuri private).
- Date fictive în teste și demo (`@demo.optoplus.invalid`, nume evident fictive). Seederul demo refuză producția.
- Nu modifica siteul vechi (WordPress) — doar citire.
- Autorizare pe server pentru fiecare acțiune (`permission:` pe rute + politici). Audit
  (`App\Core\Audit\Audit::log`) pentru date sensibile, prețuri, aprobări, exporturi. Fără parole, tokenuri,
  date de card sau conținutul rețetei în context/loguri.
- Fără Docker, root, Redis, Node pe server sau worker permanent; taskurile de fundal din cron
  (`ModuleServiceProvider::schedule()`, `withoutOverlapping()`, loturi limitate).
- HTML din CMS sanitizat cu HTMLPurifier; fără PHP/JS arbitrar.

## Structură și convenții

- Monolit modular: `app/Modules/<Modul>/` cu `ModuleServiceProvider.php` (descoperit automat),
  `routes/public.php`, `routes/panel.php` (prefix `/panou`, nume `panou.`, middleware `auth` + `panou`),
  `Views/` (namespace = cheia modulului), `Models/`, `Services/`, `Http/Controllers/`, `Policies/`, `Console/`.
- Modulul declară `permissions()`, `roleGrants()`, `navigation()`, `dashboardWidgets()`, `schedule()`.
  După permisiuni noi: `php artisan optoplus:sync-permissions` și `php artisan optoplus:permissions-matrix`.
- Bani: întregi în bani (`App\Core\Support\Money`), RON. Procente în puncte de bază.
- Migrări în `database/migrations/`, prefix de dată pe modul (ordinea contează): `2026_01_01` core ·
  `2026_01_05` customers · `2026_02_*` cms · `2026_03_*` catalog · `2026_04_*` lenses · `2026_05_*` pricing ·
  `2026_06_*` configurator · `2026_07_*` notifications, shop, orders, payments, shipping, invoicing ·
  `2026_08_*` production · `2026_09_*` b2b · `2026_10_0*` crm, assistant, import · `2026_10_20` updates/backup.
  Numele de indici ≤ 64 caractere (MariaDB).
- Panou: `@extends('layouts.panou')`, componente `x-panou.*`. Site public: `@extends('layouts.site')`,
  CSS `resources/css/site.css` (tokeni pe `:root`), JS pe pagină în `resources/js/site/<nume>.js`
  (`data-module`). CSP cu nonce: scripturile inline au `nonce="{{ app('csp-nonce') }}"`; preferă fișiere JS.
- Teste: PHPUnit pe MariaDB (`optoplus_test`), `tests/Feature/<Modul>/`. Rulează `php artisan test` înainte de
  fiecare commit.

## Mediu local

```
mysqld_safe --user=mysql &           # MariaDB 10.11 (optoplus / optoplus_dev; baze optoplus, optoplus_test)
composer install && npm ci && npm run build
php artisan migrate:fresh --seed
php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder'
php artisan optoplus:import:woocommerce database/content/woocommerce/wc-product-export-2026-10-29.csv --commit
php artisan serve                    # / site, /panou/autentificare — conturi demo în docs/DEMO.md
```
