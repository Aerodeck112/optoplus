# Jurnalul modificărilor

Formatul: o secțiune `## [versiune] — dată` pentru fiecare release. Textul secțiunii este inclus în pachetul de
actualizare și afișat administratorului înainte de instalare.

## [1.0.9] — 2026-11-05

Se poate instala direct peste 1.0.0 – 1.0.8.

- **Configurator, pasul „Tratamente”:** un tratament pe care regulile nu îl permit pentru alegerea clientului
  (ex. fotocromatic la ochelarii de citit) apare inactiv, cu motivul, în loc să fie oferit cu preț și respins
  abia după bifare. Nuanța fotocromaticului e cerută la trimitere, fără mesaj roșu în timp ce clientul alege.
- **Nuanța în comandă:** fișa de producție, comanda din panou, pagina comenzii pentru client și portalul
  partenerilor afișează „Fotocromatic (Gri 85%)”, nu codul intern („gri_85”). Clientul vede acum pe pagina
  comenzii și tratamentele alese.
- **Panou → Catalog → Tratamente lentile, nou:** numele și textele „Ce face” / „Limite” afișate clientului,
  activ/inactiv, nuanțele (etichetă, culoare, activă) și tratamente noi.
- **La fiecare lentilă:** se bifează nuanțele permise (ex. la indicele 1.60 doar 85%) și se poate adăuga un
  tratament care lipsește, cu prețul lui.
- Prețul lentilei din panou se numește „Preț B2C final” când firma nu e plătitoare de TVA. În meniu se
  evidențiază doar secțiunea deschisă.

## [1.0.8] — 2026-11-03

Se poate instala direct peste 1.0.0 – 1.0.7.

- **Utilizatori, acțiuni direct în listă:** „Retrimite invitația” (pentru cine nu s-a autentificat încă) sau
  „Resetare parolă”, plus „Șterge” / „Dezactivează”. Starea „invitat, neautentificat” arată cine nu și-a
  activat încă contul. Aceleași acțiuni sunt și în fișa utilizatorului.
- **Ștergerea** e definitivă doar pentru conturile care nu s-au autentificat niciodată (invitații neacceptate,
  conturi create din greșeală). Un coleg care a lucrat deja apare în istoricul comenzilor, al producției și
  în jurnalul de audit: contul lui se **dezactivează** (nu se mai poate autentifica, sesiunile se închid),
  ca istoricul să rămână corect; se poate reactiva oricând.
- Protecții: nu vă puteți șterge propriul cont, ultimul administrator tehnic activ nu poate fi șters sau
  dezactivat, iar un administrator tehnic poate fi șters doar de alt administrator tehnic. Toate acțiunile
  cer reconfirmarea identității și se înregistrează în jurnalul de audit.

## [1.0.7] — 2026-11-03

Se poate instala direct peste 1.0.0 – 1.0.6.

- **Setări → Email (SMTP), nou:** serverul prin care siteul trimite emailuri (confirmări și stări de comandă,
  resetări de parolă, invitații). Se completează serverul, portul, criptarea, utilizatorul și parola (păstrată
  criptat), adresa și numele expeditorului. Butonul „Trimite un email de test” arată exact ce răspunde serverul.
  Secțiunea e vizibilă pentru administratorul tehnic.
- Corectură: **crearea unui utilizator nou dădea eroare** când emailul nu putea fi trimis (la instalare,
  adresa expeditorului rămăsese goală). Acum contul se creează, iar panoul spune clar că emailul nu a plecat
  și ce e de făcut. Adresa expeditorului are mereu o valoare (setarea, apoi emailul de contact).
- Resetările de parolă (personal și clienți) nu mai dau eroare dacă emailul nu pleacă; invitațiile pentru
  parteneri spun clar dacă emailul nu a putut fi trimis.
- Detaliul comenzii din panou nu mai afișează „din care TVA 0,00 lei” când firma nu e plătitoare de TVA.

## [1.0.6] — 2026-11-02

Se poate instala direct peste 1.0.0 – 1.0.5.

- **Pagină nouă „Pentru clinici și parteneri”:** deschidere cu macheta animată a portalului (comandă rapidă,
  urmărirea unei lucrări pas cu pas, „rețetă verificată de specialist”), carduri „Pentru cine este portalul”,
  **turul interactiv al portalului** (comandă rapidă, import CSV, urmărirea producției, oferte, documente,
  echipa) care se derulează singur sau la apăsare, formularul de parteneriat cu „Ce urmează după cerere” și
  datele de contact pentru parteneri. Machetele folosesc date evident fictive și nu afișează prețuri.
- **Autentificare dedicată partenerilor** (`/portal/autentificare/`). Corectură: un partener neautentificat
  care deschidea `/portal/` ajungea la autentificarea personalului din panou.
- **Portalul partenerilor, mai prietenos:** bandă de întâmpinare, meniu tip file, carduri cu pictograme,
  „Cerere de ofertă” direct din panou și, până la prima comandă, o explicație ilustrată a etapelor.
- Banda pentru parteneri de pe prima pagină: card luminos cu urmărirea animată a unei lucrări.
- Corectură: nota despre TVA din portal ține cont că firma nu este plătitoare de TVA.
- Dacă ați editat deja pagina „Pentru clinici și parteneri” în panou, nu este suprascrisă: varianta nouă
  rămâne ciornă în Panou → Site → Pagini. Blocurile noi („Deschidere pentru parteneri”, „Pentru cine”,
  „Turul portalului”) se pot edita ca oricare altele.

## [1.0.5] — 2026-11-01

Se poate instala direct peste 1.0.0 – 1.0.4.

- **Site mai luminos:** fundaluri deschise (bleu, crem, piersică) în locul secțiunilor bleumarin închise, pete de
  culoare animate în prima secțiune, fotografii colorate de lentile din laboratorul nostru în locul celor maro.
- **Animații:** titlurile, cardurile, pașii și fotografiile apar lin la derulare; cardurile se ridică la trecerea
  mouse-ului; lentila din prima secțiune „plutește”. Vizitatorii care au cerut din sistem mai puțină mișcare
  văd pagina fără animații.
- **Bandă cu rame** pe prima pagină („Rame alese pentru tine”), care derulează continuu fotografiile ramelor din
  catalog și se oprește la trecerea mouse-ului.
- **Videoclipul nostru de pe YouTube** („Video Prezentare | Fabrica de lentile”) pe pagina „Producția noastră”:
  pornește singur, fără sunet, când ajunge pe ecran. Pe prima pagină pornește la apăsare. Se încarcă de pe
  youtube-nocookie.com; dacă YouTube nu îl poate reda, rămâne imaginea clipului cu link spre YouTube.
  Videoclipul, textele și pornirea automată se schimbă din Panou → Site → Pagini (blocul „Video YouTube”).
- Paginile pe care le-ați modificat deja în panou **nu sunt suprascrise**: varianta nouă rămâne ciornă, de
  publicat dacă vă convine. Politica de cookie-uri primește un paragraf despre YouTube, tot ca ciornă de verificat.
- Corectură: linkul vechi `/b2b/` duce acum la autentificarea partenerilor (`/portal/`).

## [1.0.4] — 2026-11-01

Se poate instala direct peste 1.0.0 – 1.0.3.

- Corectură: filtrele de la „Ochelari și rame” (pentru cine, material, construcție, tip ramă, formă, marcă,
  culoare, mărime, preț) nu se vedeau pe calculator; pe telefon erau sub butonul „Filtrează și ordonează”.
- Corectură: după o actualizare, paginile păstrate în cache puteau folosi stilurile vechi. Acum cache-ul
  paginilor se reînnoiește automat la fiecare versiune nouă.

## [1.0.3] — 2026-11-01

Se poate instala direct peste 1.0.0 – 1.0.2.

- Corectură: pe telefon, cu meniul deschis, linkurile nu se puteau apăsa (fundalul întunecat al meniului
  stătea deasupra lor). Acum toate paginile și submeniurile se deschid la atingere; pagina din spate nu mai
  derulează cât timp meniul e deschis.

## [1.0.2] — 2026-11-01

Se poate instala direct peste 1.0.0 sau 1.0.1.

- **Mod lucru (site în pregătire), pornit automat la această actualizare:** vizitatorii văd pagina „Noul magazin
  Optoplus prinde contur”, cu telefonul și emailul; panoul rămâne accesibil, iar personalul autentificat vede
  siteul normal. Pagina răspunde „503 — revine curând”, ca Google să nu scoată siteul din căutări.
  Se oprește la lansare din Panou → Setări → Lansare.
- **Imaginile produselor:** după înlocuirea WordPress-ului pe același domeniu, imaginile nu se mai puteau descărca
  de la vechile adrese (404). Aplicația le ia acum din pachetul de imagini (două arhive ZIP, dezarhivate în
  `optoplus/shared/`), iar cronul le procesează în lot de ~45 de secunde pe minut (aprox. 150 de imagini pe
  minut). Imaginile eșuate se repun automat la coadă la instalarea acestei actualizări.

- Panou → Prețuri: editorul de promoții (procent, sumă fixă, preț fix; pe comandă, rame, lentile, tratamente,
  categorii, mărci sau produse; perioadă, canal, limite de utilizare) și cupoane.
- Panou → Liste de preț B2B: prețuri pe cod (SKU ramă sau cod lentilă), cu praguri cantitative; adăugare în bloc.
- Panou → Site → Bibliotecă media: încărcare de imagini (reencodate, fără metadate; SVG respins), text alternativ.
- Panou → Administrare → Import WooCommerce: simulare fără scriere, import pe bucăți, reconciliere, starea
  imaginilor și reîncercarea celor eșuate.

## [1.0.1] — 2026-11-01

Se poate instala direct peste 1.0.0.

- Instalatorul și pagina de recuperare folosesc culorile Optoplus.
- Construirea pachetelor nu mai copiază pachetele de dezvoltare.

## [1.0.0] — 2026-11-01

Prima versiune a noii platforme Optoplus (instalare inițială).

- Siteul public: catalogul de rame importat din WooCommerce, lentilele produse în laboratorul propriu,
  configuratorul ghidat cu ilustrații interactive, coș, finalizarea comenzii, conturi de client.
- Plăți: numerar la livrare, transfer bancar, card prin Banca Transilvania iPay (după completarea datelor în
  Setări → Plăți).
- Portalul pentru parteneri B2B, fluxul de producție cu aprobarea specialistului optic, CRM, panoul de
  administrare, actualizări ZIP semnate, backup criptat.
