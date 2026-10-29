# Backlog pe etape, cu criterii de acceptare

Legendă stare: ✅ livrat și testat local · 🟡 parțial · ⏳ neînceput · 🔒 blocat de date/acces (vezi
`docs/ACCESE-SI-INFORMATII-LIPSA.md`). Starea reală, detaliată, e în `docs/STATUS.md`.

## Etapa 0 — Audit și planificare

| Element | Criteriu de acceptare |
|---|---|
| Audit site + concurență | tabelele din `docs/audit/` cu sursa fiecărei constatări (directă / export / presupunere / indexare) |
| Accese și informații lipsă | listă concretă, fără cereri de parole în fișiere |
| Arhitectură, model de date, migrare, structură, direcție vizuală | documentele din `docs/` aprobate de client |

## Etapa 1 — Fundație

| Element | Criteriu de acceptare |
|---|---|
| Proiect Laravel modular, nucleu reutilizat | `php artisan test` verde; instalare din pachet pe un director gol |
| Roluri: administrator, manager, vânzări, specialist optic, producție, editor conținut (+ partener B2B în portal) | matricea permisiunilor generată (`docs/ROLURI-PERMISIUNI.md`); un rol fără permisiune primește 403 pe fiecare rută testată |
| Conturi clienți separate de personal | gardă `client`; un client nu poate accesa `/panou`; limitare la 5 încercări |

## Etapa 2 — Catalog și migrare

| Element | Criteriu de acceptare |
|---|---|
| Model catalog (produse, variante, mărci, categorii, atribute, dimensiuni) | editabil din panou, cu audit |
| Importator WooCommerce (simulare, loturi, reluare, mapare, fără duplicate, raport) | rulare dublă = 0 produse noi; întreruperea și `--resume` termină importul; raportul listează lipsurile |
| Reconciliere | 405/405 produse, 2/2 variante, imaginile numărate; 0 diferențe de preț nejustificate |
| Redirecturi 301 individuale | toate URL-urile din sitemapul vechi răspund 200 sau 301 spre echivalent (test automat) |

## Etapa 3 — Lentile, matrice, preț

| Element | Criteriu de acceptare |
|---|---|
| Produse-lentilă cu pagini proprii | familie, material/indice, tratamente, intervale, unitate (bucată/pereche), preț sau cerere de ofertă, disponibilitate, fișă tehnică |
| Matricea de fabricație și regulile editabile | o combinație în afara intervalului e blocată pe server cu mesaj explicit; regulă nouă adăugată din panou are efect imediat, fără cod |
| Motor de preț | testele de preț pentru configurațiile reprezentative și cazurile-limită (`docs/TESTE.md`) trec; prețul din client nu e niciodată folosit |
| Promoții și cupoane | reducere doar pe lentile, pe categorie, cu/fără cumul, cu perioadă; dovadă pentru beneficiari |

## Etapa 4 — Site public și configurator

| Element | Criteriu de acceptare |
|---|---|
| Homepage, catalog cu filtre, pagina ramei, pagina lentilei, producția noastră, parteneri, ghiduri, contact | HTML randat pe server; date structurate valide; Lighthouse mobil raportat cu condiții |
| Căutare (nume, SKU, termeni uzuali, toleranță la greșeli) | „rayban”, „ray ban”, „0RX6550”, „progresiv”, „ochelari copii” găsesc rezultatele așteptate |
| Configurator în 5 pași, 4 intrări | progres vizibil; revenire fără pierderi; total pe parcurs; OD/OS separat; DP binoculară/monoculară; blocări explicate; salvare în cont și link securizat |
| Rețete private | URL-ul fișierului nu e predictibil; acces doar proprietar + personal autorizat, auditat; acces anonim → 404 |

## Etapa 5 — Coș, checkout, plăți, livrare

| Element | Criteriu de acceptare |
|---|---|
| Coș + checkout pe mobil | comandă completă pe 390 px lățime, testată automat în browser |
| Snapshot în comandă | modificarea ulterioară a prețurilor nu schimbă comanda |
| Plată card (adaptor procesator) | semnătura webhook verificată; notificarea repetată nu dublează plata; revenirea pe pagina de succes **nu** marchează plătit |
| Transfer bancar | marcat plătit doar prin acțiune explicită, auditată |
| Rambursări, plăți eșuate, reconciliere | teste pentru fiecare caz |
| Livrare | reguli (prag, zonă, greutate) din panou; AWB prin adaptor sau manual; timp de producție separat de transport |
| Facturare | adaptor + stare sincronizare; fără „factură” PDF generică |

## Etapa 6 — Cont client, producție, CRM

| Element | Criteriu de acceptare |
|---|---|
| Cont client | comenzi cu etape explicate, configurații salvate, rețete, export/ștergere date, acord marketing separat |
| Producție | flux configurabil; specialistul aprobă rețeta înainte de „aprobată pentru producție”; fișă de lucru tipăribilă; responsabil, termen, lot, refaceri, jurnal |
| CRM | clienți, firme, contacte, cereri, oportunități, oferte, comenzi, plăți, documente, sarcini, notițe, comunicări |
| Rapoarte | vânzări ≠ încasări ≠ anulări ≠ rambursări; marja doar cu cost real |

## Etapa 7 — B2B

| Element | Criteriu de acceptare |
|---|---|
| Cerere parteneriat → validare manuală → organizație cu roluri | utilizatorul unei organizații nu vede datele, prețurile, ofertele, comenzile sau documentele alteia (teste pe rute, API, căutare, cache) |
| Prețuri partener cu/fără TVA, praguri cantitative | testate |
| Comandă rapidă, import CSV cu validare, comandă din nou | rândurile invalide sunt raportate individual, fără comandă parțială neintenționată |
| Oferte cu versiuni → acceptare → comandă | versiunea acceptată devine snapshotul comenzii |
| Termene de plată și credit | doar pentru organizațiile aprobate explicit |
| Reclamații / refaceri | legate de comandă și de producție |

## Etapa 8 — Asistentul Optoplus

| Element | Criteriu de acceptare |
|---|---|
| Asistent pe reguli + catalog (fără AI extern) | răspunde la întrebări frecvente, compară lentile, propune produse compatibile cu prețuri din motor |
| Adaptor AI opțional | dezactivat implicit; activarea cere cheie, buget, acord; prețurile vin din motor; instrucțiunile din documente sunt tratate ca date |
| Status comandă | numai după verificarea identității (cont autentificat sau cod din email) |
| Predare către consultant | creează o cerere în CRM |
| Magazinul fără AI | toate fluxurile funcționează cu asistentul oprit |

## Etapa 9 — CMS și administrare

| Element | Criteriu de acceptare |
|---|---|
| Pagini din blocuri reutilizabile, previzualizare, ciorne, revizii | restaurarea unei revizii; previzualizarea nu e publică |
| Homepage, meniuri, bannere, galerii, FAQ, ghiduri, politici, formulare, emailuri | editabile fără programator |
| SEO și redirecturi | titlu, descriere, canonical, `noindex` per pagină/produs; redirecturi cu contor |
| Integrări | ecran cu starea fiecărei integrări (activă / test / neconfigurată) |

## Etapa 10 — Livrare

| Element | Criteriu de acceptare |
|---|---|
| Pachet inițial cPanel + pachet ZIP de actualizare | instalare pe director gol; actualizare 1.0.0 → 1.0.1 fără pierderi de comenzi, clienți, media, conținut |
| Recuperare | eșec simulat → revenire cod; procedura fără panou (`recovery.php`) testată |
| Documentație | instalare, recuperare, manual de administrare, integrări și costuri, limitări |

## Etape ulterioare (evaluate, neplanificate încă)

- **Limba maghiară** (siteul actual o are): traduceri pentru produse, pagini și interfață; `hreflang`.
- **Probă virtuală**: doar cu imagini frontale calibrate ale ramelor și un serviciu specializat; prezentată ca
  ajutor estetic, **separată** de măsurătorile pentru lentile (DP, înălțime), care rămân cele din rețetă sau
  măsurate de specialist.
- **OCR pentru rețete**: propune valori, nu le aplică; necesită serviciu configurat explicit și acordul
  clientului.
- **Măsurarea experienței reale (RUM)**: Core Web Vitals din browserele vizitatorilor (cu consimțământ), separat
  de testele de laborator.
