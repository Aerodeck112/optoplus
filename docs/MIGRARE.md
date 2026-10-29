# Planul de migrare și reconciliere

## 1. Principii

- Sursa preferată este **autorizată și completă**: exportul WooCommerce (primit), apoi API-ul REST cu cheie de
  citire și copia bazei de date + `uploads`. Scanarea publică servește doar la inventariere și verificare.
- Nimic nu se scrie în magazinul vechi. Importatorul citește.
- Fiecare rulare este **reluabilă și idempotentă**: maparea `sursă → țintă` (`import_items`) împiedică
  duplicatele; un `source_hash` detectează ce s-a schimbat de la rularea anterioară.
- Ce nu se poate mapa sigur nu se inventează: apare în raportul de date lipsă.

## 2. Importatorul (`php artisan optoplus:import:woocommerce`, panou → Import)

| Pas | Ce face | Reluare |
|---|---|---|
| 1. Citire | CSV-ul exportului (sau API REST); detectează coloanele după antet, nu după poziție | — |
| 2. Simulare (`--dry-run`, implicit) | parcurge tot, construiește planul (creează / actualizează / neschimbat / eroare) și raportul, **fără scrieri** în catalog | — |
| 3. Import în loturi (`--batch=50`) | produse → variante → mărci → categorii → atribute → dimensiuni → imagini (în coadă) → redirecturi; fiecare lot într-o tranzacție | cursor salvat în `import_runs`; `--resume` continuă după ultimul lot terminat |
| 4. Imagini | descarcă din `uploads` (arhivă A4) sau din URL public, verifică tipul real și dimensiunea, deduplică pe SHA-256, generează WebP | în coadă, din cron, limită pe minut |
| 5. Reconciliere | numără și compară: produse, variante, imagini, categorii, mărci; prețuri normale și promoționale **separat**; raportul are verdict pe fiecare indicator | se poate rula oricând |
| 6. Redirecturi | păstrează `/produs/<slug>/` identic; categoriile, mărcile și paginile cu URL schimbat primesc 301 **individual** spre echivalentul nou; nimic spre homepage | — |

Raportul de import (`storage/app/import/<run>/raport.json` + pagina din panou) conține: contori, erori pe rând
(cu ID-ul WooCommerce), date lipsă (fără SKU, fără dimensiuni, fără brand, imagini indisponibile), câmpuri
ignorate (meta Woodmart/Elementor) și diferențele față de rularea anterioară.

### Reguli de transformare (export 2026-10-29)

- `Tip = simple` → produs cu o variantă; `variable` → produs, `variation` → variante (legate prin `Părinte = id:N`).
- Produsele fără SKU primesc SKU-ul `WC-<ID>`, marcat „generat la import” (de înlocuit în panou).
- Dimensiunile ramei se extrag din descriere (lățime lentilă, punte, braț, „Mărime”, „Formă ramă”, „Tip ramă”,
  „Material”) cu expresii tolerante; valoarea extrasă e marcată `source = descriere`. Descrierile păstrează
  textul, dar secvența literală `\n` devine rând nou, iar HTML-ul e sanitizat (elimină clasele Gemini lipite).
- Categoriile mixte devin atribute: femei/bărbați/copii/unisex → `gen`; metal/acetat/plastic/titan → `material`;
  `clip-on-*` → `tip constructiv = clip-on` + material. Categoria de catalog rămâne „Rame”.
- Etichetele (`Etichete`) nu se importă ca taxonomie (dublează categoriile și mărcile); se folosesc doar ca
  sinonime de căutare.
- Promoțiile din export (preț promoțional fără date) se importă ca `compare_at_price` + preț curent **numai
  după confirmarea clientului**; implicit raportul le listează separat („21 de promoții fără perioadă”).
- `În stoc = 1` fără cantitate → `availability_mode = de confirmat`; nu afișăm „în stoc” fără date reale
  (decizie client: afișăm „disponibil la comandă” sau „în stoc” până la inventar).

## 3. Lentile și configurator

Exportul nu conține lentile. Sursa este grupul APF 21069 (extras public în
`database/content/configurator-actual-apf.json`, complet după primirea exportului A5). Pașii:

1. Seederul `CurrentConfiguratorSeeder` creează familiile, indicii, tratamentele, ofertele și regulile
   echivalente regulilor APF observate, cu `status = neconfirmat` și sursa marcată.
2. Laboratorul confirmă / corectează în panou (Lentile → Matrice) intervalele reale, prețurile, unitatea de
   vânzare și formele de livrare. Doar ofertele confirmate apar ca „produse de noi” în catalog.
3. Testele de preț comparăm noul motor cu APF pentru configurațiile reprezentative (tabel în
   `docs/TESTE.md`) — diferențele sunt fie corecturi aprobate, fie erori de import.

## 4. Cupoane, reguli APF, reduceri pe lentile, beneficiari

Nu sunt publice. După primirea exporturilor A3/A7/A8: listăm fiecare cupon (cod, tip, valoare, restricții de
produs/categorie, cumul, limită, expirare, condiție de beneficiar), fiecare fragment Code Snippets care
modifică prețuri și fiecare regulă APF de preț. Pentru fiecare: echivalentul în motorul de promoții, sau
motivul pentru care nu se poate reproduce, cu decizia clientului. Dovezile cerute beneficiarilor (ex. cupon de
pensionar) devin câmpul `requires_proof` + încărcare de document privat, verificat de vânzări.

## 5. Clienți și comenzi

- Clienți: email, nume, telefoane, adrese, acordul de marketing (doar dacă există dovada acordului în date;
  altfel `marketing_consent = null`).
- **Parole**: WordPress stochează hash-uri `phpass` (`$P$...`) sau, din WordPress 6.8, `bcrypt` cu prefix `$wp`.
  Dacă formatul din copia bazei de date e unul dintre acestea, importăm hash-ul ca `legacy_password_hash`,
  verificăm parola la prima autentificare cu algoritmul compatibil, apoi o re-hash-uim nativ și ștergem hash-ul
  vechi. Dacă formatul e altul sau există îndoieli, **nu** transferăm parolele: clienții primesc email de
  setare a parolei la prima vizită. Decizia se ia după ce vedem datele reale; nu o promitem dinainte.
- Comenzi istorice: importate ca „arhivă” (read-only), cu totaluri și linii snapshot; nu declanșează emailuri,
  producție sau plăți. Statusurile WooCommerce se mapează explicit (tabel în raport).
- Rețetele încărcate pe siteul vechi: se migrează doar dacă sunt accesibile într-un mod autorizat și numai cu
  aceeași protecție (privat, auditat); altfel rămân în arhiva clientului.

## 6. Fereastra de trecere (cutover)

| Moment | Acțiune |
|---|---|
| T−14 zile | import complet pe staging; reconciliere; corecturi; testele de acceptare |
| T−7 zile | înghețarea modificărilor de structură în magazinul vechi (produse noi doar cu notare) |
| T−1 zi | sincronizare diferențială (API REST: produse modificate după ultima rulare, `modified_after`); reconciliere |
| T−2 h | magazinul vechi: comenzile noi oprite cu mesaj („revenim în X ore”), checkout dezactivat; export final comenzi/clienți noi |
| T−1 h | sincronizare finală + import comenzi noi (arhivă); verificări |
| T | DNS / document root pe noua aplicație; redirecturile active; monitorizare 404 |
| T+1 h | test de comandă reală cu plată mică și rambursare |
| T+7 zile | magazinul vechi păstrat offline (acces doar intern) pentru verificări; apoi arhivare |

Nicio comandă nu se pierde: în fereastra T−2h…T checkoutul vechi e oprit; comenzile plasate înainte sunt
exportate și importate ca arhivă, iar cele în producție sunt reintroduse manual ca „comenzi active” cu legătura
la ID-ul vechi.

## 7. Reconciliere — criterii de acceptare

- produse publicate: sursă = țintă (405 la 2026-10-29), variante: 2 = 2, imagini unice: 990 = importate +
  raportate ca indisponibile;
- 0 diferențe de preț nejustificate între sursă și țintă (prețul normal și cel promoțional raportate separat);
- fiecare URL vechi din sitemap → 200 (păstrat) sau 301 (spre echivalent), verificat automat;
- lista datelor lipsă predată clientului.
