# Direcția vizuală

## 1. Punctul de plecare

- **Identitatea existentă**: logo-ul Optoplus — monturi de pince-nez desenate cu linie bleumarin (`#053B5E`),
  literele romane „OPTO PLUS” în gri grafit (`#4D4D4D`), sloganul „EXPERTUL VEDERII” spațiat, linii orizontale
  sub nume. Siteul actual folosește HK Grotesk. Le păstrăm pe toate: sunt ceea ce clienții recunosc.
- **Subiectul**: un laborator care **produce lentile**. Lumea lui vizuală: lentile rotunde nedebitate, marcajele
  gravate ale lentilelor progresive (crucea de montaj, cercurile de referință, linia orizontală cu puncte),
  secțiuni transversale prin lentile plus/minus, fotocromatice care se închid afară.
- **Publicul**: persoane fizice care nu cunosc terminologia (au nevoie de calm și claritate), plus opticieni și
  clinici (au nevoie de densitate și viteză).

## 2. Sistemul de bază (tokeni)

| Token | Valoare | Rol |
|---|---|---|
| `--bleumarin` | `#053B5E` | culoarea mărcii: titluri, butoane principale, linii de desen |
| `--grafit` | `#3F4447` | text de bază (derivat din griul logo-ului, întunecat pentru contrast AA) |
| `--sticla` | `#F5F8FA` | fundal general, alb rece (nu crem) |
| `--ceata` | `#E3EBF0` | suprafețe secundare, separatoare, zone de lentilă |
| `--gravura` | `#A15F0E` | **doar** marcaje explicative: puncte de adnotare, pasul curent, focus; din culoarea marcajelor de pe lentilele reale |
| `--verde-stoc` | `#2E6B4F` | disponibilitate confirmată |
| `--rosu-blocat` | `#A23B2A` | combinații blocate, erori |

Contrast (calculat WCAG 2.1): `--grafit` pe `--sticla` 9,25:1; `--bleumarin` pe alb 11,71:1; `--gravura` pe alb
5,05:1 și pe `--sticla` 4,73:1; `--verde-stoc` 6,3:1; `--rosu-blocat` 6,57:1 pe alb. Modul întunecat nu e prioritar pentru magazin; panoul îl are.

**Tipografie**

- Titluri: **Spectral** (600/500) — serif de ecran, cu diacritice ș/ț corecte, ecou al literelor romane din
  logo fără să le copieze.
- Text și interfață: **Hanken Grotesk** (400/500/600) — varianta open-source a familiei HK Grotesk folosite
  azi, deci continuitate.
- Valori optice (SPH, CYL, AX, DP): cifre tabulare din Hanken Grotesk (`font-variant-numeric: tabular-nums`),
  nu monospace.
- Scară (raport 1,25, bază 17 px pe mobil / 18 px desktop): 14 · 17 · 21 · 27 · 33 · 42 · 52. Rânduri de
  maximum ~70 de caractere; interlinie 1,55 la text, 1,15 la titluri.
- Fonturile sunt găzduite local (WOFF2, subset latin + latin-ext), fără cereri către terți.

**Spațiere și formă**: grilă de 8 px; secțiuni separate prin spațiu, nu prin chenare; colțuri rotunjite mici
(4 px) la câmpuri, mai mari (12 px) doar la fotografii; fără umbre decorative — umbra apare numai la elemente
care plutesc (bara de total, meniul).

## 3. Elementul memorabil: marcajul de montaj

Marcajele gravate de pe lentilele progresive (linie orizontală cu două puncte, cruce de montaj, cercuri) devin
**limbajul grafic al siteului**, într-un singur loc pe pagină și cu rol explicativ:

- pe homepage, fotografia reală a lentilei progresive are adnotări desenate peste marcaje (zona pentru
  distanță, intermediar, aproape) — se deschid la atingere / tastatură;
- în configurator, bara de progres este linia de referință cu puncte: fiecare pas e un punct; pasul curent are
  crucea de montaj în culoarea `--gravura`;
- secțiunile tehnice (indice, grosime) folosesc secțiuni transversale simple ale lentilei, marcate
  „schemă ilustrativă” când nu reprezintă un calcul.

Restul paginii rămâne liniștit: fotografii mari, text scurt, butoane clare.

## 4. Schițe

Homepage (mobil):

```
┌──────────────────────────────┐
│ ≡  OPTO PLUS       🔍  👤  🛒 │
├──────────────────────────────┤
│ Lentile produse de noi.      │
│ Ochelari configurați         │
│ pentru tine.                 │
│ [ Configurează ochelarii ]   │
│ [ Vezi ramele ]              │
│  ◯───•───┼───•───◯  (foto    │
│   lentilă cu adnotări)       │
│ Cabinet sau magazin de       │
│ optică? Portalul partenerilor│
├──────────────────────────────┤
│ Cum comanzi  1 ─ 2 ─ 3 ─ 4   │
├──────────────────────────────┤
│ Lentilele noastre (familii)  │
└──────────────────────────────┘
```

Configurator (desktop):

```
┌─────────────────────────────────────────────────────────────┐
│  ●────────●────────┼────────○────────○   Rețeta (pasul 2/5) │
├───────────────────────────────────────┬─────────────────────┤
│  Rețeta ta                            │  Ramă      Moonlight │
│        SPH    CYL    AX    ADD        │            300 lei   │
│  OD   [-2.25][-0.50][ 90 ][    ]      │  Lentile   —         │
│  OS   [-2.00][     ][    ][    ]      │  Tratam.   —         │
│  DP  ( ) o valoare  ( ) pe ochi       │  ───────────────     │
│  [ Încarc rețeta ]  Nu înțeleg rețeta │  Total     300 lei   │
│  [ Înapoi ]               [ Continuă ]│  pereche de lentile  │
└───────────────────────────────────────┴─────────────────────┘
```

Aliniere: text la stânga peste tot (inclusiv titlurile homepage-ului); numerele de preț aliniate la dreapta în
rezumate.

## 5. Mișcare și accesibilitate

- O singură animație orchestrată: la încărcarea homepage-ului, adnotările lentilei apar pe rând (≤ 600 ms).
  Cu `prefers-reduced-motion: reduce` apar direct.
- Mișcarea răspunde acțiunilor: deschiderea unui pas, actualizarea totalului (o scurtă evidențiere a sumei).
- Fără scroll blocat, carusele automate sau parallax.
- Focus vizibil (contur `--gravura` 3 px), ținte de atingere ≥ 44 px, etichete reale pe câmpuri, mesaje de
  eroare lângă câmp și rezumate la începutul formularului.

## 6. Verificarea direcției față de clișee

| Risc | Verificare |
|---|---|
| fundal crem + serif + accent teracotă | fundal alb rece; accentul e folosit doar ca marcaj, iar culoarea vine din fotografiile reale ale lentilelor |
| carduri identice rotunjite cu umbre | doar catalogul folosește carduri; secțiunile folosesc fotografii, diagrame și benzi pline |
| etichete MAJUSCULE deasupra titlurilor | interzise; singurul text spațiat e sloganul din logo |
| numerotare decorativă 01/02/03 | numerotăm doar pașii reali (comandă, configurator) |
| săgeți „→” după fiecare link | nu |

## 7. Ecrane reprezentative livrate

Capturile se generează cu `tools/screenshots/capture.mjs` în `docs/capturi/` (homepage, catalog, ramă, lentilă,
configurator pe pași, coș, checkout, cont, portal B2B, panou: matrice lentile, comandă, producție).
