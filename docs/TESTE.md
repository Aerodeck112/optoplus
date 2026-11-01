
## Instalare, actualizare și recuperare pe cPanel simulat (2026-11-01)

Mediu: director de cont simulat (`public_html/` + `optoplus/`), PHP 8.3 cu serverul integrat (router care
imită `.htaccess`), MariaDB 10.11, bază și utilizator dedicate. Pașii au fost parcurși în browser (Playwright).

| Test | Rezultat |
|---|---|
| Pachet `optoplus-1.0.1-instalare-cpanel.zip` dezarhivat în directorul contului; `install.php`: cod, verificarea serverului, configurare, instalare, import, finalizare | ✅ ~2 minute; 411 produse, 83 redirecturi, setările confirmate, ciornele juridice |
| Siteul după instalare: `/`, `/rame/`, `/lentile/`, `/configurator/`, pagină de produs, `/sitemap.xml`, `/robots.txt` | ✅ 200 |
| Panou: autentificarea administratorului creat de instalator → configurarea MFA obligatorie | ✅ |
| Imaginile produselor procesate de cron în fundal (`schedule:run`) | ✅ 15 imagini/rulare; preluate de la optoplus.ro |
| `.htaccess` scris în `public_html` | ✅ |
| Actualizare semnată 1.0.0 → 1.0.1 (verificare, backup BD, migrări, comutare atomică, verificări) | ✅ produsele, jurnalul de audit și setările păstrate |
| Pachet cu un fișier modificat | ✅ respins: dimensiune diferită de manifest |
| Pachet cu manifest modificat | ✅ respins: semnătură invalidă |
| `recovery.php` cu cheia de recuperare: comutare la 1.0.0 fără aplicație funcțională | ✅ siteul răspunde 200 după comutare |

Neverificat încă: instalarea pe hostingul real al Optoplus (limitele lui de PHP, cron, e-mail), plata BT iPay
cu contul de test al băncii.

## Meniul pe mobil (2026-11-01)

Problema raportată: pe telefon, cu meniul deschis, nicio pagină nu se putea apăsa. Cauza: fundalul meniului
era adăugat în `body` cu z-index 50, deasupra antetului (z-index 40) care conține meniul. Reparat în 1.0.3.
Verificat cu Playwright (Pixel 7, iPhone 13): „Producția noastră”, „Ochelari și rame → Rame pentru femei”,
„Lentile”, „Configurează ochelarii”, „Contact” se deschid; butonul × și atingerea pe fundal închid meniul.
Meniul panoului pe mobil: funcțional (verificat pe instalarea cPanel simulată).
