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
| Livrare curier 22 lei (România) | ✅ | ✅ | ❌ | — | fără integrare AWB automată: AWB-ul se introduce manual |
| Facturare | ⚠️ referință manuală | ✅ | ❌ | 🔒 | aplicația nu emite facturi; integrarea cu sistemul de facturare nu e configurată |
| TVA 21% modificabil | ✅ | ✅ | ❌ | — | ⚠️ ANAF (01.11.2026): OFA SRL nu figurează înregistrată în scopuri de TVA → implicit „neplătitor” (cotă efectivă 0, același preț). Se schimbă din Setări → Firma |
| Termen execuție 2–5 zile lucrătoare | ✅ | ✅ | ❌ | — | |
| Conturi client, parole WordPress | ✅ | ✅ (phpass, $wp bcrypt) | ❌ | — | conturile vechi se importă cu exportul de utilizatori (A3), încă neprimit |
| Portal B2B, izolare, comandă rapidă, CSV, oferte | ✅ | ✅ | ❌ | — | |
| Producție, aprobare specialist | ✅ | ✅ | ❌ | — | |
| Panou: comenzi, producție, B2B, CRM, catalog, lentile, setări, pagini, FAQ, meniuri, redirecturi | ✅ | ✅ | ❌ | — | lipsesc încă: promoții/cupoane/liste de preț (editor), biblioteca media (upload), import din panou |
| Texte juridice | ⚠️ ciorne | ✅ | ❌ | — | de verificat și publicat de Optoplus (Site → Pagini → Juridice) |
| Emailuri comenzi | ✅ | ✅ | ❌ | 🔒 | serverul SMTP se configurează în `shared/.env` la instalare |
| Asistent AI | ❌ | ❌ | ❌ | — | urmează |
| Pachet instalare cPanel + actualizare semnată | ✅ | ✅ pe cPanel simulat | ❌ | — | vezi `docs/INSTALARE-SI-RECUPERARE.md` |
