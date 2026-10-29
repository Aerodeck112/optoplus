# Analiza concurenței

Data: 2026-10-29. Surse: **[D]** pagină deschisă de noi cu Chromium (profil mobil Pixel 7, `ro-RO`), doar
navigare, fără coș sau comandă; **[I]** informație din rezultate de căutare / presă (indexare), neverificată pe
site. Nu am copiat texte, imagini sau elemente de identitate; ideile de mai jos sunt adaptate.

Lensa.ro și Optiblu.ro au răspuns cu o verificare anti-bot („Doar un moment...”, HTTP 403) atât la `curl`, cât
și la Chromium automatizat. Nu am încercat să ocolim protecția; pentru ele avem doar informații [I], marcate ca
atare. O analiză completă a lor cere o parcurgere manuală dintr-un browser obișnuit (o putem face împreună).

## Tabel comparativ

| Site / URL | Observație | Avantaj pentru utilizator | Cum adaptăm pentru Optoplus |
|---|---|---|---|
| Lentiamo — `lentiamo.ro/ochelari-de-vedere.html` [D] | Bara de sus: „Comandați și telefonic: +40…”; ajutor cu program, email și chat „Online” | Cine ezită știe imediat că poate suna | Telefonul de comandă și programul (din setări) vizibile în antet pe mobil și în fiecare pas al configuratorului |
| Lentiamo — listă de rame [D] | Fiecare card arată „Livrare gratuită & ramă în stoc” și „Încercați virtual” | Stocul și costul de livrare se văd înainte de click | Pe card: disponibilitate reală (în stoc / la furnizor / la comandă) din date; nimic afișat dacă lipsește informația |
| Lentiamo — produs [D] (`lentiamo-tomas-deep-black.html`) | Alegerea mărimii (M 51 mm / L 54 mm) e obligatorie înainte de „Comandă cu lentile cu dioptrii”; link „Care este mărimea mea?” | Evită comanda unei rame nepotrivite | Pagina ramei arată dimensiunile desenate (lățime lentilă, punte, braț) și un ghid „compară cu ochelarii tăi” |
| Lentiamo — recenzie publică [D] | Un client scrie că e dificil să filtrezi modelele după dimensiuni | Problemă reală de descoperire | Filtre după lățimea lentilei și punte (date extrase din descrieri + completate în panou) |
| Lentiamo — produs [D] | „Specificații produs” ca tabel (formă, culoare, material, greutate, accesorii) | Comparabilitate rapidă | Atribute structurate, același tabel pe toate ramele; câmpurile lipsă nu apar |
| Lentiamo — `masurarea-distantei-pupilare.html` [D] | Ghid dedicat pentru măsurarea DP acasă | Clientul fără DP pe rețetă nu abandonează | Ghid DP + opțiunea „nu știu DP” → specialistul contactează clientul, comanda nu intră automat în producție |
| Lentiamo — `achizitionare-fara-TVA.html` (link) [D] | Pagină pentru facturare pe firmă | Firmele găsesc traseul | Separăm clar: „Factură pe firmă” (B2C cu CUI) ≠ „Cont de partener” (B2B validat) |
| Videt — `videt.ro` [D] | Link permanent „Repetă ultima comandă” în antet | Comenzi recurente într-un click | Pentru clienți și parteneri: „Comandă din nou” pe orice comandă anterioară, cu revalidare de preț și compatibilitate |
| Videt — `videt.ro` [D] | Promoție formulată ca alegere a clientului („−40% la rame și −20% la lentile sau invers”, doar în showroom) | Reducerea e transparentă | Motorul de promoții suportă reduceri separate pe ramă / lentile / tratamente și reguli de cumul explicite; nu inventăm procente |
| Videt — `videt.ro` [D] | „Găsește ochelarii perfecți după forma ramei” (Rectangulari, Pătrați, Rotunzi, Cat-Eye, Aviator, Browline) | Descoperire vizuală | Intrări vizuale după formă, alimentate de atributul „formă” — doar după ce e completat în catalog (azi lipsește) |
| Videt — `ochelari-vedere.html` [D] | Text educativ lung în categorie: tipuri de lentile, materiale, tratamente, când să mergi la medic | SEO + educație | Ghiduri separate, scurte, care trimit la lentilele reale din catalog; textul categoriei rămâne scurt |
| Videt — `videt.ro` [D] | Încredere: rating Google cu număr de recenzii, „Retur gratuit 30 de zile”, „Ray-Ban Certified Reseller” | Semnale de încredere | Afișăm doar ce Optoplus poate dovedi; spațiu în panou pentru recenzii verificate și certificări încărcate cu document doveditor |
| Videt — `videt.ro` [D] | „Probă virtuală” și „stilist virtual” | Ajută alegerea estetică | Etapă ulterioară; nu promitem precizie de măsurare (vezi backlog: proba virtuală) |
| Lensa — [I] presă și listări | Servicii în magazine (determinarea dioptriilor, montaj, reparații), comenzi telefonice și online; lentile de la producători mari (Essilor, Hoya, Zeiss) | Rețea fizică + servicii | Diferențiatorul Optoplus e invers: **producem lentilele**. Pagina „Producția noastră” arată procesul cu fotografii reale |
| Optiblu — [I] presă (2021) | Comunicare axată pe personal specializat și aparatură medicală; promoții „1+1” | Încredere prin experți | „Verificarea rețetei de către specialist” devine pas vizibil și urmărit în cont |
| Mister Spex — `misterspex.de` [D] | Probă acasă (până la 4 rame), abonament „Switch”, test de vedere gratuit în magazin | Reduce riscul alegerii online | Pentru Optoplus: ridicare / probă la punctul de lucru (dacă se confirmă), nu abonamente |
| Zenni — `zennioptical.com` [D] | Quiz „Find your frames faster”, lentile grupate pe nevoi (condus noaptea, lumină albastră, sport) | Pornește de la nevoie, nu de la terminologie | Pasul 1 al configuratorului: „Pentru ce folosești ochelarii?”, cu explicații simple, fără recomandare automată a celei mai scumpe variante |
| Zenni — `zennioptical.com` [D] | „24/7 Customer Service”, „30-Day Returns”, rating cu număr de recenzii | Încredere | Nu afișăm program sau garanții neconfirmate |
| Ace & Tate — `aceandtate.com/ro` [D] | Preț de pornire comunicat clar („from €145”), livrare gratuită pe toate comenzile de ochelari | Transparența prețului final | „De la X lei cu lentile” calculat de motorul de preț pentru cea mai ieftină combinație validă, nu scris de mână |

## Concluzii pentru Optoplus

1. **Producătorul vizibil**: în paginile parcurse nu am văzut un comerciant care să-și prezinte propria producție
   de lentile oftalmice; pentru Optoplus e diferențiatorul principal (homepage, pagini de lentile, configurator, B2B).
2. **Prețul final pe parcurs**: toate siteurile mari afișează prețul ramei separat; Optoplus va arăta în fiecare
   pas ramă + lentile + tratamente + reduceri, cu precizarea „pereche” / „bucată”.
3. **Ajutor uman la îndemână**: telefon, „am nevoie de ajutor” și verificarea specialistului în fiecare pas.
4. **Comenzi repetate**: „Comandă din nou” (inspirat de Videt) pentru B2C și B2B, cu revalidare.
5. **Filtre după dimensiuni**: răspunde unei probleme reale semnalate de clienți.
6. **B2B**: în paginile parcurse am găsit doar facturare pe firmă și afiliere, nu un portal de comandă pentru
   cabinete; pentru Optoplus portalul B2B e traseu de prim rang.
