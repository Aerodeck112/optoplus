# Observații despre conținut, promisiuni și date neconfirmate

Regula proiectului: nu corectăm în tăcere și nu publicăm promisiuni nesusținute. Fiecare rând de mai jos are
o decizie pentru noul site și o întrebare pentru client (centralizate în `docs/INTREBARI.md`).

## Contradicții pe siteul actual (citit 2026-10-29)

| # | Unde | Text actual (prescurtat) | Problemă | Decizie în noul site |
|---|---|---|---|---|
| 1 | Titlul siteului, homepage | „ochelari gata în 48h” | Contrazice celelalte termene | Nu afișăm termen până la confirmare; termene configurabile per produs |
| 2 | Contact → FAQ | „1–2 zile lucrătoare” simple, „3–5 zile” personalizate, livrare „24–48h” | Diferit de #1, #3, #4 | idem |
| 3 | Termeni și condiții | livrare „1–9 zile lucrătoare” | idem | idem |
| 4 | Tabul „Livrare” pe produse | expediere „5 – 15 zile lucrătoare” | idem | idem |
| 5 | Politica UE | procesare 1–3 zile, livrare 3–10 zile | idem | idem |
| 6 | Firmă | „SC Optica Optofarm SRL” (date structurate) vs „SC Ofa SRL” (termeni) vs „SC OFA SRL” (fabrica) | Cine e vânzătorul și cine e producătorul? | Datele firmei sunt câmpuri în panou, goale până la confirmare; textele juridice rămân „ciornă” |
| 7 | Termeni | „Nr. Reg. Com. J20/1100/1019267” cu sediul în județul Mureș | Formatul/prefixul nu pare să corespundă județului (de verificat la ONRC) | Nu îl republicăm până la confirmare |
| 8 | Adrese | Str. Poștei nr. 3, Târgu Mureș (contact) vs Sat Crăciunești nr. 235/A (sediu social) | Pot fi corecte ambele (punct de lucru vs sediu) | Câmpuri separate „sediu social” și „punct de lucru/ridicare” |
| 9 | Telefoane / email | +40771653905, contact@optoplus.ro (contact); +40 771 048 936, ofalentile@gmail.com (B2B) | Două canale | Contact B2C și contact B2B separate, editabile |
| 10 | Politica de retur | „prin e-mail la: **[emailul tău de contact]**” | Text-șablon publicat necompletat | Politicile importate rămân ciornă, marcate „necesită completare juridică” |
| 11 | Politica de retur | rambursare în „14 zile lucrătoare de la recepția produsului” | OUG 34/2014 vorbește de 14 zile de la informarea despre retragere | De verificat de jurist; nu publicăm ca final |
| 12 | Termeni | plata „online cu cardul sau ramburs” | Rambursul nu e activ în checkout | Metode de plată = configurare explicită în panou |
| 13 | Despre noi | „Ochelari de vedere și ochelari de soare pentru toate vârstele” | Catalogul nu are ochelari de soare | Nu preluăm până la confirmare |
| 14 | Fabrica de lentile | „Cu peste 30 de ani de experiență” | Afirmație fără sursă | Câmp editabil „ani de experiență”, gol până la confirmare |
| 15 | Fabrica de lentile | „Lentilele personalizate pot corecta calitatea vederii în proporție de 100%” | Afirmație medicală absolută | Nu o preluăm |
| 16 | Fabrica / tratament Blue-Light | „reduc oboseala oculară, durerile de cap și îmbunătățesc calitatea somnului” | Efecte medicale nevalidate | Rescris neutru: „filtrează o parte din lumina albastră”; procentul doar din fișa tehnică |
| 17 | Fabrica | „Round Top” și „Cube” la bifocale | Documentul primit numește „Flat Top” și „Round Top”; „Cube” nu apare în configurator | De clarificat ce designuri bifocale se produc |
| 18 | Homepage | „un optometrist îți verifică datele” | Bun și dorit — dar cine și în cât timp? | Păstrăm ideea (verificare de specialist), fără termen până la confirmare |
| 19 | Produse | „Optofarm – marcă proprie Optoplus” | „Marcă proprie” ≠ „produse de noi” | Afișăm „marcă proprie”, nu „produse în fabrica noastră” |
| 20 | Fotocromatice (document primit) | „protecție completă împotriva radiațiilor UV”, „închidere 15–30 s, deschidere 5–10 min” | Valori tehnice fără fișă tehnică/producător | Afișate doar după confirmarea sursei (fișă tehnică); până atunci marcate „de confirmat” în panou |
| 21 | Document primit | „Transitions XTRActive®” | Marcă înregistrată a unui terț | Prezentată ca produs al mărcii respective, nu ca producție proprie; de confirmat dacă e comercializată |
| 22 | Document primit | indice 1.56 descris | Configuratorul actual nu are 1.56 | Indicele există în nomenclator, inactiv până la confirmare |
| 23 | Document primit | culori fotocromatice: maro, verde, albastru, gri, mov („încă nu am foto”) | Configuratorul actual: doar maro și gri, 65%/85% | Matricea pornește cu ce e vândut azi; restul inactive până la confirmare |
| 24 | Configurator | „Info grosime 1.74” afișat la 1.67 | Eroare de condiție | Corectat în noul model (regulile sunt date) |

## Fotografiile primite (2 arhive, 14 imagini + 2 documente)

| Fișier | Ce arată (descriere vizuală, fără deducții tehnice) | Observație |
|---|---|---|
| `barna/kék/szürke/zöld fotokróm.png` | lentile fotocromatice rotunde, în nuanțe maro, albastră, gri, verde | Potrivite pentru comparația „interior / exterior” |
| `... fotokróm kint.png` | aceleași lentile fotografiate în exterior, cu peisaj vizibil prin lentilă | Comparația e ilustrativă (fotografie), nu o măsurătoare de transmisie |
| `szürke fotokróm dupla.png` | două lentile gri alăturate | — |
| `progresszív.png`, `progresszívek.png` | lentile rotunde nedebitate cu marcaje de centrare/progresie | **Fundalul în carouri este parte din imagine** (nu e transparență reală) — cerem exporturile originale sau decupăm |
| `minuszos.png`, `minuszos oldalról.png` | lentilă minus, din față și din profil | idem fundal în carouri; imaginea pare puternic editată — de confirmat că poate fi prezentată ca fotografie reală |
| `nagy pluszos, +11,75 dp.png` (2 variante) | lentilă plus mare, din profil | Numele fișierului indică +11,75 dpt; **nu** afirmăm că intervalul de fabricație ajunge la +11,75 până la confirmarea matricei |

Nu am dedus din fotografii materiale, indici, certificări sau capacități de producție.

## Ce înseamnă fiecare formă de produs (de confirmat cu laboratorul)

Documentele primite nu definesc formele de livrare. Folosim următorul nomenclator **propus**, iar fiecare
produs-lentilă are un câmp obligatoriu „formă de livrare”. Formele neconfirmate rămân inactive.

| Cod | Denumire | Definiție propusă (de validat) | Cui se vinde |
|---|---|---|---|
| `bruta` | Lentilă brută (blank) | semifabricat turnat, fără suprafețe optice finale | B2B, la cerere |
| `semifinisata` | Semifinisată | o față finală, cealaltă se prelucrează după rețetă | B2B, laboratoare |
| `finita_nedebitata` | Finită, nedebitată (rotundă) | ambele fețe finale, cu tratamentele cerute, diametru rotund | B2B (cabinete/magazine care montează) |
| `personalizata` | Personalizată după rețetă | calculată și produsă pentru o rețetă și, după caz, o ramă | B2C și B2B |
| `montata` | Montată în ramă | lentile debitate și montate în rama aleasă sau trimisă | B2C (ochelari compleți), B2B |

Unitatea de vânzare (bucată / pereche) este un câmp separat pe fiecare ofertă de lentilă; configuratorul o
afișează explicit.
