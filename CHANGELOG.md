# Jurnalul modificărilor

Formatul: o secțiune `## [versiune] — dată` pentru fiecare release. Textul secțiunii este inclus în pachetul de
actualizare și afișat administratorului înainte de instalare.

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
