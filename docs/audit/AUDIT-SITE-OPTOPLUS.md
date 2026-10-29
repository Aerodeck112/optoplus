# Auditul siteului actual optoplus.ro

Data auditului: 2026-10-29. Autor: echipa de dezvoltare (asistată de Claude).

## Metodă și limite

Fiecare constatare are o sursă marcată:

- **[D]** observație directă: am descărcat pagina publică sau am apelat un endpoint public, numai cu cereri de
  citire (GET), din containerul de dezvoltare;
- **[E]** exportul WooCommerce primit de la client (`database/content/woocommerce/wc-product-export-2026-10-29.csv`,
  407 rânduri);
- **[P]** presupunere sau deducție care trebuie confirmată de client;
- **[I]** informație din indexarea motoarelor de căutare (nu am folosit-o pentru optoplus.ro).

Ce **nu** am făcut, intenționat: nu am adăugat produse în coș, nu am plasat comenzi, nu am creat cont, nu am
trimis formulare și nu am apelat endpointuri care modifică date (`POST`). Motivul: cerința „nu modifica magazinul
live”, iar pluginurile de marketing (Mailchimp, Meta, Google) pot înregistra coșuri abandonate. Prin urmare
checkoutul, regulile de transport, cupoanele, emailurile tranzacționale și contul de client **nu au fost
verificate funcțional**; sunt în lista de accese (`docs/ACCESE-SI-INFORMATII-LIPSA.md`).

Datele brute (HTML descărcat, rezultatele Lighthouse) au rămas în mediul de lucru; în repository sunt păstrate
exportul, regulile extrase ale configuratorului (`database/content/configurator-actual-apf.json`) și tabelele
de mai jos.

## 1. Platformă și integrări

| Element | Constatare | Sursă |
|---|---|---|
| CMS / magazin | WordPress + WooCommerce, tema Woodmart, Elementor, Slider Revolution | [D] scripturi încărcate |
| Configurator lentile | **Advanced Product Fields for WooCommerce Pro** (APF/WAPF), un singur grup de câmpuri (`21069`) aplicat tuturor ramelor | [D] HTML produs; hash identic pe 20/20 produse verificate |
| Disponibilitate | plugin propriu `optoplus-reserve-availability` (mesaj după adăugarea în coș, `#opra-cart-feedback`) | [D] `warnings.js`; regulile lui nu sunt publice |
| Plăți | `woocommerce_payments` (WooPayments) și `bt-ipay` (Banca Transilvania iPay; endpoint webhook `/wp-json/bt-ipay/v1/webhook`) | [D] Store API `/cart` → `payment_methods` |
| Ramburs | nu apare printre metodele active, deși Termenii îl menționează | [D] + [D] termeni |
| Limbi | română + maghiară prin TranslatePress (`/hu/`, `hreflang` ro/hu) | [D] |
| Cookie | CookieYes (`cky/v1`) | [D] |
| Marketing | Google Site Kit, Google Listings & Ads (sincronizare Merchant Center), Meta Pixel + Conversions API (`facebook-for-woocommerce`), Mailchimp for WP, feed CTX Feed | [D] |
| Asistent AI | widget extern `https://ai.vitim.ro/widget/v1/loader.js` | [D] |
| Formulare | Contact Form 7 (contact), formular propriu „Recomandare lentile în 1 minut” (`admin-post.php`, câmpuri nume, email, telefon, utilizare, subțiere, lumină albastră, rețetă fișier, acord GDPR) | [D] |
| Alte pluginuri | Code Snippets (posibile reguli în fragmente de cod), All-in-One WP Migration, Jetpack, WooCommerce POS, Safe SVG, Elementor AI, endpoint MCP `/wp-json/mcp/...`, endpoint `vitim/v1/command` | [D] lista de rute REST |
| Firmă | JSON-LD Organization: „SC Optica Optofarm SRL”; Termeni: „SC Ofa SRL”, CUI RO29223337, sediu Sat Crăciunești nr. 235/A; pagina Fabrica: „SC OFA SRL”; contact: Str. Poștei nr. 3, Târgu Mureș | [D] — **două denumiri de firmă, de clarificat** |

### Observații de securitate (fără testare activă)

- Rutele REST publice listează pluginuri și endpointuri administrative (`code-snippets`, `mcp`, `vitim/v1/command`,
  `ai1wm`). Nu le-am apelat. Recomand verificarea de către administrator că toate cer autentificare. [D]/[P]
- Sitemapul publică paginile de autor (`/author/<utilizator>/`), deci numele de utilizator ale conturilor
  WordPress (3 conturi). [D]
- Cookie-ul `_fbp` (Meta) este setat de server încă din primul răspuns (inclusiv pe redirecturi), înainte de
  orice consimțământ. [D] — de verificat cu responsabilul GDPR.

## 2. Navigație, pagini și categorii

Meniul principal [D]: ACASĂ · PRODUSE · Ochelari de vedere (mega-meniu: Tip ochelari: Bărbați/Femei/Copii;
Producători: 13 mărci; Forma ramei: Aviator, Cateye, Geometric, Rotund, Square) · Blog · Despre Noi ·
Contactează-ne · Hungarian.

Probleme găsite [D]:

- „Aviator” trimite la filtrul de brand Optofarm (`?filter_product_brand=81`), iar Cateye/Geometric/Rotund/Square
  nu au link; în catalog nu există atribut de formă (doar 1 produs are „Forma ramei”).
- Unele linkuri de meniu conțin parametrul tehnic `?v=…` (ex. MOTOEYE), la fel paginarea din shop.
- Meniul mobil de categorii conține textele demonstrative ale temei („New York”, „London SF”, „Edinburgh”...).
- Paginile „Producția noastră”, „Pentru clinici” și „Ghiduri” nu există ca trasee; informația B2B stă în
  pagina `/fabrica-de-lentile/`, care nu apare în meniu.

Pagini publice în sitemap (21) [D]: `/`, `/shop/`, `/fabrica-de-lentile/`, `/about-us-3/`, `/contact-us/`,
`/blog/`, `/cos/`, `/finalizare/`, `/contul-meu/`, `/wishlist/`, `/compare/`, politici (confidențialitate,
2 pagini de cookie aproape duble, rambursări, termeni, livrare UE), `/ochelari-de-vedere-romana/`,
`/ochelari-de-vedere-magyar/` (pagini goale), `/sample-page/` (pagina demonstrativă WordPress), plus 9 pagini
`/portfolio/...` cu text „lorem ipsum” ale temei, 2 articole de blog și 3 pagini de autor.

Categorii de produs (12) [D][E]: `rame` (404 produse), `rame/femei` (184), `rame/barbati` (149), `rame/metal`
(128), `rame/acetat` (101), `rame/plastic` (96), `rame/copii` (68), `rame/unisex-rame` (34), `rame/titan` (11),
`clip-on-acetate` (29), `clip-on-metal` (23), `clip-on-plastic` (10). Categoriile amestecă **public țintă**
(femei/bărbați/copii), **material** și **tip constructiv** (clip-on) — în noul catalog devin atribute filtrabile
separate, iar URL-urile vechi primesc redirect individual.

Branduri (14) [E]: Moonlight 73, SUCCESS 52, POINT 45, Prime 45, Mondoo 39, Ray-Ban 27, OPTOFARM 21, Gucci 21,
Armani Exchange 20, Simpleye 16, Saint Laurent 14, MOTOEYE 12, Sonata 12, Puma 7. „OPTOFARM” e descris pe site
drept „marcă proprie Optoplus” [D]; **nu am dovezi că Optoplus produce ramele** — noul site nu afirmă asta.

## 3. Produse, variante, atribute, imagini (export + Store API)

| Indicator | Valoare | Sursă |
|---|---|---|
| Rânduri export | 407: 404 simple, 1 variabil, 2 variații | [E] |
| Produse publice (Store API, sitemap) | 405 = 404 simple + 1 variabil | [D] |
| Reconciliere export ↔ Store API | 405/405 ID-uri găsite; 0 diferențe de preț la produsele simple | [D]+[E] |
| Lentile ca produse | **0**. Lentilele există doar ca opțiuni APF pe pagina ramei | [E][D] |
| SKU | 265 prezente, unice; **140 produse fără SKU** (Store API afișează în JSON-LD ID-ul în loc de SKU) | [E][D] |
| EAN/GTIN | 0 | [E] |
| Atribute | doar „Culoare” (produsul variabil Ray-Ban 0RX6550, 2 variante) și 1 „Forma ramei” | [E] |
| Dimensiuni ramă | numai în textul descrierii: lățime lentilă la 395/405, punte la 323, braț la 237; formate inconsistente („Lățime Lentilă - 54”, „Lungime Brațe -” fără valoare) | [E] — importatorul le extrage și raportează lipsurile |
| Greutate / dimensiuni colet | 0 | [E] |
| Stoc | `În stoc = 1` la toate, fără cantități (stoc negestionat); Store API: toate „in stock” | [E][D] — **disponibilitatea afișată nu reflectă un stoc real** |
| Prețuri | 160–1.481 lei; 21 promoții (OPTOFARM 210 → 99 lei) **fără dată de început/sfârșit** | [E] |
| Imagini | 992 referințe, 990 URL-uri unice, 1–5 pe produs; JPG 727, PNG 242, JPEG 23; multe `-scaled` (2560 px) | [E] |
| Descrieri | 378 conțin secvența literală `\n` (artefact de import); 2 descrieri scurte conțin HTML lipit dintr-o interfață Gemini (`user-query-content`, `ng-star-inserted`) | [E] |
| Etichete | dublează categoriile și mărcile, cu variante de scriere (SUCCES/SUCCESS, CLIP ON ACETAT/ACETATE) | [E] |
| Produs fără brand | 1 (ID 21213 „PRIME GLP 5460 C2”) | [E] |
| Metadate Yoast | prezente doar parțial (scoruri), fără titluri/descrieri SEO exportate | [E] |

## 4. Configuratorul actual (APF) — reguli observate

Regulile complete, cu condițiile decodate, sunt în `database/content/configurator-actual-apf.json` (45 de
câmpuri). Rezumat [D]:

1. „Adaugi lentile?” Da/Nu (Nu = doar rama).
2. Tip ochelari: Ochelari de vedere · Ochelari de citit · Multifocali.
3. Vedere/citit → Tip lentile: **Standard +200 lei** sau **Premium +280 lei**.
4. Premium → Grosime: 1.5 (+0) · 1.6 (+120) · 1.67 (+250) · 1.74 (+400).
5. Multifocali → Progresive / Degresive (grosime 1.5 +1.020 · 1.6 +1.130 · 1.67 +1.340 · 1.74 +1.790) sau
   Bifocal rotund (1.5 +830 · 1.6 +930 · 1.67 +1.040 · 1.74 +1.780).
6. Tratamente (doar Premium sau multifocal): Antireflex super hidrofob +60 · Blue-Light +90 · Fotocromatice +250;
   la citit, fără fotocromatic. Fotocromatic → culoare: Maro/Gri 65%/85% (la 1.6 și 1.74 doar 85%).
7. Rețetă: SPH OD/OS −6.00…+6.00 (pas 0.25, obligatoriu), CYL −2.50…+2.50 (pas 0.25, opțional), AX 0–180
   (afișat dacă CYL e completat), ADD 0.75–3.75 (doar multifocal), **o singură DP** 50–80 mm (obligatorie),
   încărcare rețetă (opțională).
8. Totaluri afișate: „Preț ramă”, „Preț lentilă configurată”, „Total comandă”. Prețurile sunt sume fixe
   adăugate o dată per produs (deci per pereche, presupunere [P]).

Probleme de logică găsite [D]:

- Textul informativ pentru 1.74 se afișează când e ales **1.67** (condiție greșită pe câmpul `ad91441`).
- A treia regulă a fotocromaticului 1.74 pentru multifocali verifică grosimea monofocală (`Grosime lentilă`),
  nu pe cea multifocală.
- CYL acceptă atât valori negative cât și pozitive (două convenții de notare); nu există transpunere și nici
  limită combinată SPH+CYL. AX nu e obligatoriu când CYL ≠ 0.
- Nu există DP monoculară, înălțime de montaj (necesară la progresive) sau alte măsurători.
- „Standard” nu are tratamente; nu e explicat ce conține (antireflex inclus sau nu) — **de confirmat**.
- Intervalele SPH/CYL sunt aceleași pentru toate indicii și designurile; nu reflectă o matrice de fabricație.
- Recomandările de indice din texte diferă între familii (ex. 1.67: „6–9D” la progresive, „3–6D” la degresive).

Aceste reguli sunt **punctul de plecare** al noii matrice (preluate ca date editabile, marcate „preluat din
siteul actual, neconfirmat”), nu adevărul de fabricație.

## 5. Disponibilitate proprie vs. furnizor

Nu există în export câmpuri pentru furnizor, termen de aprovizionare sau stoc propriu [E]. Pluginul
`optoplus-reserve-availability` sugerează o regulă de rezervare/avertizare la coș [D], dar logica nu e publică.
Avem nevoie de descrierea regulii sau de codul pluginului (vezi lista de accese).

## 6. Coș, checkout, cont

- Coș și checkout: clasice WooCommerce (nu blocurile), texte nelocalizate („Before proceed to checkout you must
  add some products...”). [D]
- Cont: autentificare + înregistrare cu email, „Anti-spam”, texte parțial în engleză („Log in”, „Lost your
  password?”, „Password”). [D]
- Store API: monedă RON, 2 zecimale, prețuri cu TVA inclus. [D]
- Transport, cupoane, emailuri: **neverificate** (ar fi cerut un coș). Termenii spun „1–9 zile lucrătoare”,
  costul afișat la checkout. [D]

## 7. Formulare, cupoane, promoții

- Formularul „Recomandare lentile” cere rețeta prin upload public la `admin-post.php`; destinația fișierelor și
  retenția nu sunt documentate. [D]/[P]
- Cupoane, reguli APF de reducere, reduceri doar pe lentile, cupoane pentru beneficiari (ex. pensionari,
  studenți): **nu pot fi văzute public**. Exportul WooCommerce de produse nu le conține. Cerem exportul
  cupoanelor (`shop_coupon`) și setările pluginurilor (vezi accese). Nu am presupus procente sau cumulări.

## 8. SEO tehnic, URL-uri, metadate

| Element | Constatare | Sursă |
|---|---|---|
| Domeniu canonic | `https://optoplus.ro/` (www → 301 → fără www) | [D] |
| Pagina principală | răspunde **307** către `/?v=abf76b6c8cd2`, apoi 200; parametrul apare și în linkuri interne | [D] — risc de URL-uri duplicate |
| Sitemap | `wp-sitemap.xml` (nucleul WordPress); include portofoliu demo, sample page, autori | [D] |
| Robots | blochează `add-to-cart`, `wp-admin`; fără reguli pentru filtre | [D] |
| URL produs | `/produs/<slug>/` (405); categorie `/categorie-produs/<cale>/`; brand `/brand/<slug>/` | [D] |
| Titluri | „Nume – Optoplus”; descrieri generate din descriere („Lățime Lentilă - 54 ...”) | [D] |
| Date structurate | Organization (acasă), BreadcrumbList, Product (WooCommerce): `sku` = ID numeric, ofertă fără variante | [D] |
| hreflang | ro/hu pe acasă; paginile `ochelari-de-vedere-romana/magyar` au `lang=ro-RO` | [D] |
| 404 | status corect 404, text în engleză („This is somewhat embarrassing, isn’t it?”), fără sugestii de produse | [D] |
| Imagini | 4 imagini fără `alt` pe fiecare pagină; imagini PNG de 2560 px în galerie | [D] |

### Performanță — test de laborator

Condiții: Lighthouse 12.6.1, Chrome for Testing 153 headless, din containerul de dezvoltare (cloud, rețea și CPU
neconfigurate de noi; profilul „mobile” aplică simularea standard Lighthouse — Moto G Power, 4G lent, CPU ×4;
„desktop” = preset desktop). O singură rulare per pagină, 2026-10-29 12:53–13:00 UTC. Sunt date de **laborator**, nu
date reale ale utilizatorilor.

| Pagină | Profil | Performanță | Accesibilitate | Bune practici | SEO | LCP | TBT | CLS | Greutate | Cereri |
|---|---|---|---|---|---|---|---|---|---|---|
| Acasă | mobil | 34 | 82 | 79 | 92 | 5,8 s | 4.410 ms | 0,002 | 2,56 MB | 222 |
| Acasă | desktop | 63 | 86 | 78 | 92 | 2,9 s | 160 ms | 0,032 | 2,56 MB | 224 |
| Categorie Femei | mobil | 33 | 82 | 79 | 85 | 7,9 s | 3.010 ms | 0 | 2,92 MB | 218 |
| Categorie Femei | desktop | 61 | 86 | 78 | 85 | 3,8 s | 120 ms | 0,009 | 3,82 MB | 220 |
| Produs Ray-Ban 0RX6550 | mobil | 33 | 88 | 79 | 92 | 5,8 s | 2.810 ms | 0,015 | 6,79 MB | 338 |
| Produs Ray-Ban 0RX6550 | desktop | 84 | 89 | 78 | 92 | 1,5 s | 100 ms | 0,01 | 7,14 MB | 355 |

Cauze vizibile [D]: 99–153 de scripturi și 58–95 de foi de stil per pagină (jQuery, Woodmart, Elementor,
Slider Revolution, pixeli de marketing), pagina de produs include toate cele 45 de câmpuri APF (inclusiv
3 liste de 181 de opțiuni pentru AX) și imagini PNG mari.

## 9. Conținut și promisiuni comerciale — contradicții

Detaliate în `docs/audit/OBSERVATII-CONTINUT.md`. Pe scurt: termenul de execuție apare ca „48h” (titlul
siteului), „1–2 zile / 3–5 zile + 24–48h” (FAQ), „1–9 zile lucrătoare” (Termeni), „5–15 zile lucrătoare”
(tabul Livrare al produselor) și „1–3 zile + 3–10 zile” (politica UE). Noul site **nu** afișează niciun termen
până la confirmare; termenele devin date configurabile per produs/serviciu, separate pentru producție și
transport.

## 10. Ce preluăm, ce schimbăm

- Preluăm: catalogul (cu ID-urile vechi mapate), imaginile, prețurile, URL-urile `/produs/...` (păstrate
  identic), textele informative ale lentilelor (ca ciornă de revizuit), formularul de recomandare (ca traseu
  „Am nevoie de ajutor”), limba maghiară ca a doua limbă (etapă ulterioară, vezi backlog).
- Schimbăm: lentilele devin produse proprii cu pagini, matrice de fabricație și reguli editabile; categoriile
  amestecate devin atribute; disponibilitatea afișată numai din date reale; configuratorul validează pe server;
  rețetele devin documente private.
