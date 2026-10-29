# Structura siteului și fluxurile B2C / B2B

## 1. Navigația principală

| Traseu | URL | Conținut |
|---|---|---|
| Ochelari și rame | `/rame/` (+ `/produs/<slug>/` păstrat) | catalog rame cu filtre; pagina ramei cu dimensiuni desenate și „Configurează cu lentile” |
| Lentile | `/lentile/`, `/lentile/<slug>/` | produsele-lentilă ca pagini proprii, grupate pe familii; producție proprie marcată distinct de mărcile terțe |
| Configurează ochelarii | `/configurator/` | 4 intrări: am ales rama · doar lentile · lentile pentru rama mea (dacă e confirmat) · am nevoie de ajutor |
| Producția noastră | `/productia-noastra/` | „Din laborator până la tine”: etape, fotografii reale, ce producem și ce nu |
| Pentru clinici și parteneri | `/parteneri/` → `/b2b/` (portal) | ofertă B2B, cerere de parteneriat, autentificare portal |
| Ghiduri | `/ghiduri/`, `/ghiduri/<slug>/` | cum citești rețeta, DP, alegerea ramei, tipuri de lentile, tratamente — fiecare cu linkuri spre produse |
| Contact | `/contact/` | telefon, email, adrese, program (din setări), formular |

Antet pe mobil: logo · căutare · cont · coș, iar sub antet o bară cu „Sună” (dacă e setat telefonul) și
„Configurează”. Fără ecran care blochează accesul (fără pop-up la intrare, fără alegere obligatorie B2C/B2B).

## 2. Hartă URL și redirecturi de la siteul vechi

| Vechi | Nou | Tip |
|---|---|---|
| `/produs/<slug>/` | identic | păstrat (200) |
| `/shop/` | `/rame/` | 301 |
| `/categorie-produs/rame/` | `/rame/` | 301 |
| `/categorie-produs/rame/femei/` | `/rame/femei/` (filtru indexabil: gen) | 301 |
| `/categorie-produs/rame/barbati/`, `/copii/`, `/unisex-rame/` | `/rame/barbati/`, `/rame/copii/`, `/rame/unisex/` | 301 |
| `/categorie-produs/rame/metal/`, `/acetat/`, `/plastic/`, `/titan/` | `/rame/metal/` etc. (filtru indexabil: material) | 301 |
| `/categorie-produs/clip-on-acetate/` (și metal/plastic) | `/rame/clip-on/` (+ material) | 301 |
| `/brand/<slug>/` | `/marci/<slug>/` | 301 |
| `/fabrica-de-lentile/` | `/productia-noastra/` | 301 |
| `/about-us-3/` | `/despre-noi/` | 301 |
| `/contact-us/` | `/contact/` | 301 |
| `/cos/`, `/finalizare/`, `/contul-meu/` | `/cos/`, `/finalizare-comanda/`, `/cont/` | 301 |
| `/termeni-si-conditii/`, `/politica-*` | aceleași căi | păstrat |
| `/2025/05/26/<slug>/` (blog) | `/ghiduri/<slug>/` | 301 |
| `/hu/...` | `/hu/...` (etapa limbii maghiare) sau 302 temporar spre RO | de decis |
| `/portfolio/*`, `/sample-page/`, `/wishlist/`, `/compare/`, `/author/*` | 410 Gone (conținut demonstrativ) | 410 |

Filtre: doar combinațiile cu valoare de căutare (gen, material, marcă, formă) au URL curat indexabil; restul
parametrilor (`?pret=`, `?sort=`, filtre multiple) au `noindex, follow` și canonical spre pagina de bază.

## 3. Homepage (ordinea secțiunilor)

1. **Prima ecranare**: mesajul principal + fotografia reală a unei lentile progresive nedebitate, cu marcajele
   explicate; butoane „Configurează ochelarii” și „Vezi ramele”; o linie distinctă pentru parteneri.
2. **Cum comanzi** (secvență reală, numerotată): alegi rama (sau trimiți una, dacă serviciul e confirmat) →
   introduci rețeta → alegi lentila → specialistul verifică → producem și livrăm.
3. **Lentilele pe care le producem**: familiile, cu diagrame simple (zone de vedere), prețul „de la” calculat.
4. **Din laborator până la tine**: fotografii reale (fotocromatice interior/exterior, lentilă plus/minus) —
   comparații marcate „fotografie ilustrativă”.
5. **Rame**: selecție din catalog, cu mențiunea „rame comercializate de noi, de la mărcile ...; marca proprie
   Optofarm”.
6. **Pentru clinici și magazine de optică**: comenzi recurente, prețuri de partener, urmărirea producției.
7. **Ajutor**: ghiduri, telefon, „am nevoie de ajutor”.

### Mesaje propuse (originale; de ales de client)

- „Lentile produse de noi. Ochelari configurați pentru tine.” (ideea clientului — titlu)
- „Lentilele tale se fac în laboratorul nostru, după rețeta ta.”
- „De la rețetă la lentilă, fără intermediari.”
- „Alegi rama, noi facem lentilele.”
- Subtitlu: „Introduci rețeta, alegi lentila potrivită, iar un specialist o verifică înainte să intre în
  producție. Vezi prețul final la fiecare pas.”
- B2B: „Lentile de laborator pentru cabinete și magazine de optică — comandă, urmărește producția, repetă
  comenzile.”

Nicio afirmație despre termene, ani de experiență, număr de clienți, certificări sau garanții până la
confirmarea datelor (`docs/audit/OBSERVATII-CONTINUT.md`).

## 4. Flux B2C — ochelari compleți

```
Ramă (pagina produsului) ─► „Configurează cu lentile”
  Pas 1  Pentru ce folosești ochelarii?   distanță · citit · calculator/birou · toate distanțele · nu știu
  Pas 2  Rețeta                          manual (OD/OS separat, DP binoculară/monoculară) · încărcare foto/PDF ·
                                         „nu înțeleg rețeta” → specialistul mă contactează
  Pas 3  Lentila                         2–4 variante compatibile, explicate (diferențe, cost, pentru cine);
                                         incompatibilele nu apar sau apar dezactivate cu motivul
  Pas 4  Tratamente                      beneficii + limite, din texte aprobate
  Pas 5  Rezumat                         ramă, lentile (pereche/bucată), tratamente, reduceri, transport, total
  ─► Coș ─► Checkout (date, livrare, plată) ─► Plată (procesator) / transfer / ramburs dacă e activ
  ─► Confirmare: comanda e „Primită”; dacă are rețetă → „Verificare rețetă” de către specialist
```

- Prețul total e vizibil pe tot parcursul (bară fixă jos pe mobil).
- Fiecare pas se salvează pe server (configurație `draft`); revenirea nu pierde datele; linkul securizat
  „Continuă mai târziu” se poate trimite pe email.
- Comenzile cu măsurători lipsă sau reguli „require_review” intră în „Clarificări”, nu în producție.

## 5. Flux B2C — alte intrări

- **Doar lentile** (fără ramă): alegi familia → rețeta → lentila → tratamente → rezumat (bucată/pereche).
- **Lentile pentru rama mea**: apare doar dacă serviciul e activat în panou; adaugă instrucțiuni de trimitere a
  ramei și un pas de verificare la recepție.
- **Am nevoie de ajutor**: formular scurt (contact, utilizare, rețetă opțională) → cerere în CRM → specialistul
  sună; poate pregăti o configurație pe care clientul o confirmă din link.

## 6. Flux B2B

```
/parteneri ─► Cerere de parteneriat (firmă, CUI, tip, persoană de contact, documente)
   ─► Validare manuală în panou (vânzări/manager) ─► organizație aprobată + listă de preț + condiții
   ─► Invitație pentru administratorul organizației ─► acesta își invită colegii (roluri)
Portal /b2b:
   Catalog cu prețuri de partener (cu/fără TVA) · Comandă rapidă tabelară (cod intern pacient, OD/OS, lentilă,
   tratamente, cantitate) · Import CSV cu validare pe rând · Comandă din nou · Cereri de ofertă și versiuni →
   acceptare → comandă · Producție și expediere · Documente · Reclamații / refaceri · Utilizatori
```

Plata la termen și limita de credit apar doar pentru organizațiile cu condiții aprobate explicit.

## 7. Cont client (B2C)

Comenzi (cu etapele de producție explicate simplu), configurații salvate, rețete (vizualizare, ștergere),
adrese, date personale, acorduri (marketing separat), export și ștergere date, „Comandă din nou”.

Etape vizibile clientului (texte editabile): „Comanda a fost primită” → „Verificăm rețeta” → „Avem nevoie de
o clarificare” → „Lentilele sunt în producție” → „Control de calitate” → „Montăm lentilele în ramă” →
„Comanda e pregătită” → „Am predat coletul curierului”.
