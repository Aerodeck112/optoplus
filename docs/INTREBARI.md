# Întrebări pentru Optoplus

Răspunsurile deblochează date marcate „neconfirmat” în aplicație. Până la răspuns, implementarea folosește
decizia provizorie din coloana a treia (reversibilă din panou).

| # | Întrebare | Decizie provizorie |
|---|---|---|
| 1 | Care firmă vinde pe site și care produce lentilele (Optica Optofarm SRL / OFA SRL)? Datele complete ale fiecăreia? | **Răspuns 2026-11-07:** firma este **OFA SRL**. Datele vin din registrul ANAF (CUI 29223337, J2011001019267, sediul: sat Crăciunești nr. 235/A, C5, com. Crăciunești, jud. Mureș); listafirme.ro blochează accesul automat. Textele juridice rămân ciornă până la revizuire. Deschis: unde e laboratorul (vezi #21) |
| 2 | Ce produse-lentilă fabricați efectiv (familii, designuri, indici, tratamente, culori)? Care sunt de la alți producători? | Pornim de la ce vinde azi configuratorul, marcat „neconfirmat” |
| 3 | Ce forme de livrare oferiți (brută, semifinisată, finită nedebitată, personalizată, montată) și cui? | Nomenclatorul propus în `OBSERVATII-CONTINUT.md`; doar „personalizată” și „montată” active |
| 4 | Prețurile din configuratorul actual sunt per pereche? Ce include „Standard” (antireflex? durificare?) | Per pereche; „Standard” = fără tratamente adiționale |
| 5 | Intervalele reale de fabricație (SPH, CYL, ADD, combinat, diametre, prisme) pe produs și indice? | Intervalele actuale APF: SPH ±6, CYL ±2,5, ADD 0,75–3,75 |
| 6 | Notați cilindrul în minus, în plus, sau acceptați ambele cu transpunere? | Acceptăm ambele, transpunem automat în notația minus pentru producție, arătăm clientului ambele |
| 7 | Ce măsurători cereți pentru progresive (înălțime de montaj, DP monoculară)? Cine le ia la comenzile online? | Obligatorii pentru progresive; dacă lipsesc → „Clarificări” |
| 8 | Termenele reale de producție pe familie și de livrare pe metodă? | Nu afișăm termene |
| 9 | Ce stoc real au ramele? Afișăm „în stoc” sau „disponibil la comandă”? | „Disponibil la comandă” până la inventar |
| 10 | Promoția Optofarm 210 → 99 lei: are perioadă? Ce cupoane există și cum se cumulează? | **Parțial răspuns 2026-11-09:** cupoanele sunt cele din modulul WordPress „protocol MAPN” (LENTILE25 permanent, STUDENT10 până la 31.10.2026, cumulabile, doar lentile și tratamente, cu dovadă) — implementate în 1.0.14. Deschis: perioada promoției Optofarm 99 lei; prelungim STUDENT10? |
| 11 | Ce procesator de card păstrați (BT iPay, WooPayments, altul)? Avem credențiale de test? | Simulator semnat + contract de adaptor; BT iPay blocat până la documentația oficială; card online inactiv |
| 12 | Oferiți ramburs sau plata la ridicare? Pentru produse personalizate cereți avans? | Inactive până la confirmare |
| 13 | Ce curier folosiți și ce soluție de facturare? | Expediere manuală (AWB introdus de mână); facturare: stare „neconectată” |
| 14 | Serviciul „lentile pentru rama mea” există? | Intrarea e ascunsă până la activare |
| 15 | Păstrăm limba maghiară la lansare sau într-o etapă ulterioară? | Etapă ulterioară; `/hu/` → redirect temporar |
| 16 | Widgetul `ai.vitim.ro` rămâne? Ce furnizor AI preferați și ce buget lunar? | Asistent propriu fără AI extern; adaptor pregătit |
| 17 | Cine sunt specialiștii optici care validează rețetele (rol în panou)? | Rol „specialist optic” fără utilizatori reali în demo |
| 18 | Condițiile B2B: liste de preț, praguri, termene de plată, credit, cine aprobă? | Liste și praguri configurabile, goale |
| 19 | Putem folosi fotografiile primite ca „fotografii reale din laborator”? Sunt editate digital? | Folosite cu mențiunea „fotografie de produs”; comparațiile marcate „ilustrativ” |
| 20 | Ramele „OPTOFARM”: marcă proprie produsă de un furnizor? | Prezentată ca „marcă proprie”, fără „producție proprie” |
| 21 | Unde este laboratorul (și magazinul, dacă există)? Adresa „Str. Poștei nr. 3, Târgu Mureș” de pe siteul vechi este greșită, deci și „laboratorul nostru din Târgu Mureș” trebuie verificat. | 1.0.12: banda de sus nu mai pomenește localitatea. Textele publicate deja în 1.0.5 (filmul de pe prima pagină și de pe „Producția noastră”) încă spun „din Târgu Mureș” — se corectează după răspuns |
