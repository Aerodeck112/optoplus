# Modelul de date

Convenții: sumele de bani sunt **întregi în bani** (1 leu = 100), moneda RON; procentele în puncte de bază
(1% = 100); datele personale sensibile sunt criptate cu cheia aplicației (`encrypted` / `encrypted:array`);
fiecare tabel are `created_at`/`updated_at`; ștergerile de date comerciale sunt logice (`archived_at`) — comenzile
nu se șterg. Migrările sunt în `database/migrations/` cu prefixe pe modul (vezi `CLAUDE.md`).

## Nucleu (din SEVALIA)

`users` (personal), `roles`/`permissions` (Spatie), `audit_log`, `settings`, `media`, `jobs`, `cache`,
`sessions`, `updates`, `update_steps`, `backups`.

## Clienți

- `customers` — cont client: email (unic), parolă (bcrypt/argon), nume, telefon, `type` (persoana_fizica |
  persoana_juridica), `email_verified_at`, `marketing_consent_at` + `marketing_consent_source` (separat de
  operațiunile comenzii), `legacy_wc_id`, `legacy_password_hash` (doar dacă migrarea o permite, vezi MIGRARE §5),
  `last_login_at`, `anonymized_at`.
- `customer_addresses` — livrare/facturare, CUI pentru factură pe firmă.
- `customer_consents` — jurnal versionat al acordurilor (termeni, confidențialitate, prelucrarea rețetei, AI).
- `data_requests` — cereri de export / ștergere (GDPR), cu stare și termen.

## Catalog

- `brands` (nume, slug, descriere, `is_own_brand`, logo media).
- `categories` (arbore: `parent_id`, slug, cale, titlu, SEO, `kind`: rame | lentile | accesorii | clip_on).
- `attributes` (cod, etichetă, tip: select | multiselect | number | text, `is_filterable`, unitate) și
  `attribute_values` (cod, etichetă, ordine, culoare hex pentru „culoare”).
- `products` — `type`: frame | lens | accessory | service; `name`, `slug` (URL), `brand_id`, `status`
  (draft | published | archived), descrieri (HTML sanitizat), `made_in_house` (**doar lentilele produse de
  Optoplus**), `sale_unit` (piece | pair), `availability_mode` (stoc_propriu | furnizor | la_comanda |
  indisponibil), `lead_time_days_min/max` (producție/aprovizionare, separate de transport), `seo_title`,
  `seo_description`, `noindex`, `legacy_wc_id`, `legacy_url`.
- `product_categories`, `product_attribute_values` (pivot).
- `product_variants` — `sku` (unic), `gtin`, `price` (B2C cu TVA), `compare_at_price` (preț vechi doar dacă e
  susținut de o promoție datată), `vat_rate_bp`, `stock_qty` (null = negestionat), `stock_policy`, greutate,
  dimensiuni colet, atribute de variantă (JSON), `legacy_wc_id`.
- `frame_specs` — 1:1 cu varianta/produsul ramă: `lens_width_mm`, `bridge_mm`, `temple_mm`, `lens_height_mm`,
  `total_width_mm`, `rim_type` (full | semi_nylon | rimless_screw), `material`, `shape`, `gender`, `size_label`,
  `sources` (de unde provine fiecare valoare: export, descriere, manual).
- `product_images` — `media_id`, ordine, `variant_id` opțional, `alt`.
- `search_index` — text normalizat (fără diacritice) pentru căutare FULLTEXT + `search_synonyms` editabile.

## Lentile și matricea de fabricație

- `lens_families` — monofocal, progresiv, degresiv (office), bifocal; descriere pentru nespecialiști.
- `lens_materials` — cod, indice (ex. 1.50, 1.56, 1.60, 1.67, 1.74), denumire comercială, densitate/Abbe doar
  din fișa tehnică, `is_active`.
- `lens_treatments` — antireflex, filtru lumină albastră, fotocromatic, durificare etc.; `kind`; beneficii și
  **limite** (texte aprobate); `datasheet_media_id`; `is_confirmed`.
- `lens_treatment_options` — ex. culoare fotocromatică + intensitate (maro 65%, gri 85%...).
- `lens_products` — extensie 1:1 a `products` (type = lens): `family_id`, `design` (ex. Flat Top, Round Top),
  `supply_form` (bruta | semifinisata | finita_nedebitata | personalizata | montata), `made_in_house`,
  `supplier_name` (pentru mărci terțe), `requires_measurements` (JSON: înălțime montaj, DP monoculară...),
  `quote_only` (cerere de ofertă în loc de preț).
- `lens_offerings` — **rândurile matricei**: produs-lentilă × material/indice × (opțional) tratament inclus, cu:
  `sph_min/max`, `cyl_min/max`, `max_combined` (|SPH| + |CYL| sau regula aleasă), `add_min/max`, `diameters`,
  `cyl_notation` (minus | plus | oricare, cu transpunere), `price` (B2C, per `sale_unit`), `cost` (opțional, pentru
  marjă), `lead_time_days_min/max`, `requires_review`, `status` (activ | neconfirmat | inactiv), `source`
  (ex. „preluat din configuratorul actual 2026-10-29”).
- `lens_offering_treatments` — tratamentele permise pentru oferta respectivă, cu preț și compatibilități.
- `compatibility_rules` — reguli editabile: `scope` (oferta, familie, tratament, ramă), `conditions` (JSON: listă
  de condiții `câmp operator valoare`, cu ȘI/SAU), `effect` (block | require | warn | require_review),
  `message_customer`, `message_staff`, `priority`, `is_active`. Exemple: „fotocromatic indisponibil la
  ochelari de citit”, „rame pe fir: indice minim 1.6”, „progresive: înălțimea de montaj este obligatorie”.

## Prețuri și promoții

- `price_lists` — B2C implicit + liste B2B; `prices_include_vat`; `currency`.
- `price_list_items` — preț pe variantă / ofertă de lentilă / tratament; `min_qty` (praguri cantitative).
- `promotions` — `scope` (comandă, categorie, marcă, rame, lentile, tratamente, produs), `type` (procent | sumă |
  preț fix), valoare, `starts_at`/`ends_at`, `stackable` + `stack_group`, `priority`, `requires_coupon`,
  `beneficiary_category` (ex. pensionari) + `requires_proof`, `max_uses`, `max_uses_per_customer`,
  `channel` (b2c | b2b | ambele).
- `coupons` — cod (unic, normalizat), `promotion_id`, utilizări; `coupon_redemptions` (comandă, client).
- `vat_rates` — cote configurabile (nu presupunem cota; implicit setarea din panou).

## Configurator și rețete

- `prescriptions` — proprietar (client sau organizație), `source` (manual | upload | ocr_confirmat), valorile pe
  ochi **criptate** (`encrypted:array`: SPH, CYL, AX, ADD, prismă, bază), `pd_mode` (binocular | monocular),
  DP, înălțimi, data rețetei, emitent (opțional), `confirmed_by_customer_at`, `verified_by` / `verified_at`
  (specialist), `retention_until`.
- `prescription_files` — fișier privat (cale aleatoare în afara webroot), mime real, sha256, dimensiune.
- `ocr_suggestions` — valori propuse + încredere; **nu** modifică rețeta; se aplică doar după confirmarea
  utilizatorului.
- `configurations` — `uuid`, proprietar (client / sesiune / organizație), `entry` (cu_rama | fara_rama |
  rama_mea | ajutor), `frame_variant_id`, `usage`, `lens_offering_id`, tratamente alese (JSON), `prescription_id`,
  măsurători, `validation` (JSON: erori/avertismente), `price_snapshot` (JSON), `status` (draft | valid |
  necesita_verificare | folosita_in_comanda), `share_token_hash` + `share_expires_at`, `version`.

## Coș, comenzi, plăți, livrare, facturare

- `carts` / `cart_items` — un item poate fi variantă simplă sau o configurație (rame + lentile + tratamente).
- `orders` — `number` (secvență anuală unică), client sau organizație, `channel`, **`commercial_status`**
  (noua | confirmata | anulata | finalizata), **`payment_status`** (neplatita | in_asteptare | autorizata |
  platita | partial_rambursata | rambursata | esuata), **`production_status`** (nu_se_aplica | primita |
  verificare_reteta | clarificari | aprobata_productie | in_lucru | control_calitate | montaj | pregatita |
  expediata | livrata), totaluri (subtotal, reduceri, transport, TVA, total), `prices_include_vat`, adrese
  (snapshot), `placed_ip_hash`, `idempotency_key` (unic, previne dubla plasare).
- `order_items` — snapshot complet: nume, SKU, preț unitar, reduceri, TVA, `configuration_snapshot` (rama,
  lentila, tratamente, matricea și regulile aplicate, versiunea motorului de preț).
- `order_events` — jurnalul tuturor schimbărilor de status (cine, când, din ce în ce, motiv).
- `payments` — metodă, furnizor, `provider_reference`, sumă, stare, `idempotency_key`; `payment_events` —
  fiecare notificare primită (`provider_event_id` unic → procesare o singură dată), semnătură verificată
  (da/nu), corp redus (fără date de card); `refunds`.
- `shipping_methods` / `shipping_rules` (zonă, prag, greutate, cost) și `shipments` (AWB, curier, stare,
  evenimente tracking).
- `invoices` — referința din soluția de facturare externă (serie, număr, URL/PDF primit de la ea), stare
  sincronizare. Aplicația **nu emite** facturi fiscale proprii.

## Producție

- `production_stages` — definițiile configurabile (cod, etichetă internă, etichetă pentru client, ordine,
  `visible_to_customer`, `requires_permission`).
- `production_jobs` — per comandă/item: etapa curentă, responsabil, termen, prioritate, `lot_reference`,
  `work_sheet` (JSON: rețetă verificată, lentilă, tratamente, centrare, rama), `remake_of_id`.
- `production_events` — istoricul etapelor; `remakes` — motiv, cine suportă costul, legătura cu reclamația.

## B2B

- `organizations` — denumire, CUI, Reg. Com., tip (clinică | cabinet | magazin optică | altul), `status`
  (cerere | aprobata | suspendata | respinsa), `price_list_id`, `payment_terms_days`, `credit_limit`,
  `credit_approved_by`, `vat_display` (cu/fără TVA).
- `organization_members` — `customer_id`, rol: `admin_organizatie` | `comenzi` | `vizualizare` | `financiar`.
- `partnership_requests` — formularul public, documente, decizie, motiv.
- `quotes` / `quote_versions` / `quote_items` — oferte versionate, stare (ciorna | trimisa | acceptata | respinsa
  | expirata), `accepted_version_id`, conversia în comandă.
- `b2b_imports` — fișierele CSV de comandă și rezultatul validării pe rânduri.
- `complaints` — reclamații / cereri de refacere (comandă, item, motiv, fotografii, decizie).
- Comenzile B2B folosesc `patient_reference` (cod intern al partenerului), **nu** numele pacientului.

## CRM

- `crm_leads` (cereri: recomandare lentile, contact, parteneriat), `crm_opportunities` (etapă, valoare estimată,
  responsabil), `crm_tasks` (termen, responsabil, legătură polimorfă), `crm_notes`, `crm_communications`
  (email/telefon/WhatsApp înregistrate manual sau automat), `crm_documents`.

## Asistent

- `assistant_knowledge` — articole aprobate (FAQ, politici, explicații), cu `approved_by`/`approved_at`, versiune.
- `assistant_conversations` — sesiune, client (opțional), canal, `handoff_requested_at`; **fără** rețete.
- `assistant_messages` — text redus (date personale mascate), instrumentele apelate.
- `assistant_usage` — cereri și cost estimat pe zi, pentru limite.

## Import

- `import_runs` — sursă, mod (simulare | import | sincronizare), stare, contori, raport.
- `import_items` — `source_type`, `source_id`, `target_type`, `target_id`, `source_hash` (detectează
  schimbările la rerulare), stare, mesaje.
- `legacy_redirects` → intră în `cms_redirects` cu `source = import`.
