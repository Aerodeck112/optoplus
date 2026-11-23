# Starea sinceră a funcționalităților

Coloane: **implementat** · **testat local** (teste automate și/sau parcurgere în browser) · **testat staging**
· **verificat cu serviciul extern** · **blocat de acces/date**. „Testat staging” rămâne „nu” până la existența
unui staging pe hostingul Optoplus.

## Etapa 0 — audit și planificare (2026-10-29)

| Livrabil | Stare | Observații |
|---|---|---|
| Audit optoplus.ro | ✅ `docs/audit/AUDIT-SITE-OPTOPLUS.md` | checkout, transport, cupoane, cont: neverificate (ar fi cerut scrieri în magazinul live) |
| Audit concurență | ✅ `docs/audit/AUDIT-CONCURENTA.md` | Lensa și Optiblu blochează accesul automat: doar informații din indexare |
| Reguli configurator actual | ✅ `database/content/configurator-actual-apf.json` | extrase din HTML public; regulile interne APF necesită exportul (A5) |
| Accese și informații lipsă | ✅ `docs/ACCESE-SI-INFORMATII-LIPSA.md` | — |
| Arhitectură, model de date | ✅ `docs/ARHITECTURA.md`, `docs/SCHEMA-BD.md` | — |
| Plan de migrare | ✅ `docs/MIGRARE.md` | — |
| Structură site și fluxuri | ✅ `docs/STRUCTURA-SITE-SI-FLUXURI.md` | — |
| Direcție vizuală | ✅ `docs/DIRECTIE-VIZUALA.md` | ecranele reprezentative vin cu implementarea |
| Backlog cu criterii | ✅ `docs/BACKLOG.md` | — |

## Etapa 1 — implementare (actualizat 2026-11-01)

Legendă: ✅ da · ⚠️ parțial / cu condiții · ❌ nu · 🔒 blocat de acces sau date.

| Funcționalitate | Implementat | Testat local | Staging | Serviciu extern | Observații |
|---|---|---|---|---|---|
| Import WooCommerce (405 produse, 990 imagini, URL-uri, promoții) | ✅ | ✅ reconciliere 0 diferențe | ❌ | — | la instalare: din exportul inclus; imaginile din uploads local sau optoplus.ro |
| Catalog, filtre, căutare, pagini produs, SEO de bază | ✅ | ✅ | ❌ | — | Lighthouse pe noul site: de măsurat pe staging |
| Lentile, matrice de fabricație, reguli | ✅ | ✅ | ❌ | — | ⚠️ ofertele sunt „neconfirmat”: laboratorul confirmă intervalele și prețurile |
| Configurator 5 pași + ilustrații interactive | ✅ | ✅ desktop + mobil | ❌ | — | |
| Coș, finalizare, comenzi (3 stări), snapshot | ✅ | ✅ | ❌ | — | |
| Plată ramburs | ✅ | ✅ | ❌ | — | activă implicit (confirmat) |
| Plată transfer bancar | ✅ | ✅ | ❌ | — | se activează după completarea IBAN în Setări |
| Plată card BT iPay | ✅ | ✅ cu răspunsuri BT simulate | ❌ | 🔒 netestat cu BT | adrese după biblioteca publică stev/btipay; de verificat cu documentația BT a comerciantului și în sandbox („Testează conexiunea”) |
| Livrare curier 26 lei (România; 22 lei până la 1.0.23) | ✅ | ✅ | ❌ | — | fără integrare AWB automată: AWB-ul se introduce manual |
| Facturare | ⚠️ referință manuală | ✅ | ❌ | 🔒 | aplicația nu emite facturi; integrarea cu sistemul de facturare nu e configurată |
| TVA 21% modificabil | ✅ | ✅ | ❌ | — | ⚠️ ANAF (01.11.2026): OFA SRL nu figurează înregistrată în scopuri de TVA → implicit „neplătitor” (cotă efectivă 0, același preț). Se schimbă din Setări → Firma |
| Termen execuție 2–5 zile lucrătoare | ✅ | ✅ | ❌ | — | |
| Conturi client, parole WordPress | ✅ | ✅ (phpass, $wp bcrypt) | ❌ | — | conturile vechi se importă cu exportul de utilizatori (A3), încă neprimit |
| Portal B2B, izolare, comandă rapidă, CSV, oferte | ✅ | ✅ | ❌ | — | |
| Producție, aprobare specialist | ✅ | ✅ | ❌ | — | |
| Panou: comenzi, producție, B2B, CRM, catalog, lentile, setări, pagini, FAQ, meniuri, redirecturi | ✅ | ✅ | ❌ | — | lipsesc încă: promoții/cupoane/liste de preț (editor), biblioteca media (upload), import din panou |
| Datele firmei (OFA SRL) în subsol și setări | ✅ 1.0.12 | ✅ | ❌ | ✅ registrul ANAF, 2026-11-07 | firma confirmată de client; locul laboratorului încă neconfirmat (`INTREBARI.md` #21) |
| Stil de magazin (prima pagină, catalog, produs, lentile, configurator) | ✅ 1.0.12 | ✅ desktop + mobil | ❌ | — | persoanele din bannere sunt ilustrații; se pot înlocui cu fotografii proprii din panou |
| Coduri de reducere LENTILE25 / STUDENT10 (protocol MAPN), dovadă la comandă | ✅ 1.0.14 | ✅ | ❌ | — | refăcute după modulul WordPress primit de la client (v1.2.0); STUDENT10 a expirat la 31.10.2026 (se prelungește din panou) |
| Siteul în engleză și maghiară (/en/, /hu/), panou Traduceri | ✅ 1.0.15 | ✅ | ❌ | — | ⚠️ traduceri automate, de verificat; maghiara se completează cu exportul TranslatePress (comanda optoplus:i18n:import-translatepress); textele juridice rămân în română |
| Lentile fotocromatice: Transitions, Xtractive, PhotoFast, Heliomatic (un singur card, pe indici) + găurire fără 1,50 / 1,56 | ✅ 1.0.16 | ✅ desktop + mobil | ❌ | — | ⚠️ Heliomatic doar pe 1,56: lentile 1,56 nu există încă în configurator (prețuri și intervale de primit), culorile Heliomatic neconfirmate; rândurile noi sunt „neconfirmat” (250 lei) |
| Limbă și valută după țară (HU → maghiară + forinți, alte țări → engleză + euro), curs BNR | ✅ 1.0.17 | ✅ | ❌ | ✅ curs BNR real citit de pe curs.bnr.ro; DB-IP descărcat | ⚠️ textele juridice nu menționează încă plata în valută și livrarea în străinătate (de completat de Optoplus) |
| Livrare Ungaria 20 € / alte țări 25 €, fără ramburs în afara României | ✅ 1.0.17 | ✅ | ❌ | — | lista țărilor: implicit Europa (UE, SEE, CH, UK, MD), editabilă |
| Plată card Stripe (EUR, HUF) | ✅ 1.0.17 | ✅ cu răspunsuri Stripe simulate | ❌ | 🔒 netestat cu un cont Stripe real | cheile și webhookul se completează în Setări → Plăți |
| Notificări interne pe mai multe adrese | ✅ 1.0.17 | ✅ | ❌ | 🔒 SMTP | comandă nouă, plată, anulare, cereri de pe site |
| Utilizatori: editare, drepturi suplimentare | ✅ 1.0.17 | ✅ | ❌ | — | |
| Retragere online din contract (buton, confirmare, email cu data și ora), formular tip, panou „Retrageri” | ✅ 1.0.22 | ✅ desktop + mobil | ❌ | 🔒 SMTP | OUG 18/2026, aplicabil din 19.06.2026 |
| Garanția legală: notificarea armonizată UE (RO/EN/HU), caseta „Înainte să comandați”, butonul „Comandă cu obligație de plată” | ✅ 1.0.22 | ✅ | ❌ | — | Regulamentul (UE) 2025/1960, din 27.09.2026 |
| Fără redirecționare după țară (doar propunere), SOL scos, date producător pe produs (GPSR) | ✅ 1.0.22 | ✅ | ❌ | — | ⚠️ datele producătorilor se completează la Catalog → Mărci; ⚠️ pictograma oficială SAL (ANPC) de primit |
| Poza ramei după culoarea aleasă (pagina produsului, coș, catalog) + legarea pozelor de culori în panou | ✅ 1.0.23 | ✅ | ❌ | — | la ramele importate, pozele fără culoare sunt arătate la culorile fără poze proprii |
| Texte juridice | ⚠️ ciorne (actualizate 1.0.22) | ✅ | ❌ | — | de verificat și publicat de Optoplus (Site → Pagini → Juridice) |
| Emailuri comenzi | ✅ | ✅ | ❌ | 🔒 | serverul SMTP se configurează în `shared/.env` la instalare |
| Asistent AI | ❌ | ❌ | ❌ | — | urmează |
| Pachet instalare cPanel + actualizare semnată | ✅ | ✅ pe cPanel simulat | ❌ | — | vezi `docs/INSTALARE-SI-RECUPERARE.md` |
