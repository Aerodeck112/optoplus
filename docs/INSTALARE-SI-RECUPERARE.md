# Instalare pe cPanel, actualizări și recuperare

## 1. Ce primiți

| Fișier | Ce este |
|---|---|
| `optoplus-<versiune>-install-cpanel.zip` | Pachetul de instalare: se încarcă în cPanel și se dezarhivează în directorul contului. Conține `public_html/install.php`, codul aplicației în `optoplus/releases/<versiune>/` și `CITESTE-MA-INSTALARE.txt`. |
| `optoplus-<versiune>-update.zip` | Pachet de actualizare semnat. Se încarcă din **Panou → Administrare → Actualizări**. Nu se dezarhivează manual. |
| `optoplus-release-<id>.key` | **Cheia privată de semnare.** Nu se pune pe hosting și nu se trimite nimănui. Se păstrează offline (stick criptat, manager de parole). Fără ea nu se pot semna actualizări noi. |

## 2. Cerințe de hosting

PHP 8.3+, extensiile standard Laravel (pdo_mysql, mbstring, openssl, intl, gd sau imagick, zip, sodium, fileinfo),
MariaDB 10.6+ sau MySQL 8, HTTPS (AutoSSL), cron la fiecare minut. Fără Docker, fără Node, fără proces permanent.
Instalatorul verifică singur toate acestea și spune exact ce lipsește.

## 3. Instalarea (fără Terminal)

Pașii detaliați sunt în `CITESTE-MA-INSTALARE.txt` din arhivă. Pe scurt:

1. cPanel → **MySQL Database Wizard**: bază nouă, utilizator, ALL PRIVILEGES.
2. cPanel → **MultiPHP Manager**: PHP 8.3; **SSL/TLS Status**: AutoSSL activ.
3. cPanel → **File Manager**, în directorul contului (cel care conține `public_html`): Upload arhiva → Extract.
4. Deschideți `https://<domeniu>/install.php`; codul de instalare este în `optoplus/shared/install/cod-instalare.txt`.
5. Instalatorul: verificarea serverului → datele bazei de date și ale administratorului → instalare (tabele,
   chei, date de bază: lentilele, setările confirmate, ciornele textelor juridice) → **importul catalogului**
   din exportul WooCommerce inclus în pachet și al imaginilor → finalizare.
6. La final: notați **cheia de recuperare** (afișată o singură dată), adăugați linia de **cron** afișată,
   descărcați offline `optoplus/shared/.env` și `optoplus/shared/keys/`.

### Imaginile produselor la instalare

Imaginile se iau, în ordine, din `public_html/wp-content/uploads` (dacă vechiul WordPress e pe același cont) sau
de pe `optoplus.ro`, cât timp vechiul site răspunde încă. **Recomandat:** instalați întâi pe un subdomeniu
de test (ex. `nou.optoplus.ro`) cât timp siteul vechi funcționează; după verificare, mutați domeniul. O imagine
care nu se poate prelua nu oprește instalarea; se reia din panou sau cu `php artisan optoplus:import:images --all`.

### După instalare (în panou)

1. Prima autentificare: configurați autentificarea în doi pași (Microsoft/Google Authenticator, 2FAS).
2. **Setări → Plăți**: utilizatorul și parola API BT iPay, mediul (test → producție), adresele serverelor conform
   documentației BT; apăsați „Testează conexiunea”; abia apoi bifați „Plata cu cardul este activă”.
   Comunicați la BT adresa de notificare afișată acolo (`https://<domeniu>/webhook/plati/btipay`).
3. **Setări → Plăți → Transfer bancar**: IBAN-ul (se verifică cifrele de control), banca, bifa de activare.
4. **Setări → Firma**: verificați datele și statutul de plătitor de TVA (vezi STATUS.md).
5. **Site → Pagini → Juridice**: citiți ciornele textelor juridice, corectați, publicați.
6. **Lentile**: laboratorul confirmă intervalele și prețurile ofertelor marcate „neconfirmat”.

## 4. Actualizări

Panou → Administrare → **Actualizări** → încărcați ZIP-ul. Aplicația verifică semnătura (cheia publică din
`config/update-keys/`), versiunea de pornire acceptată, integritatea fiecărui fișier și protecțiile (căi
„../”, legături simbolice, arhive-bombă). Înainte de aplicare face backup criptat al bazei de date; datele,
fișierele încărcate, configurarea (`shared/.env`) și media nu sunt atinse. Codul nou se instalează într-un
director separat (`releases/<versiune>`) și se activează doar după verificări; versiunea anterioară rămâne
disponibilă pentru revenire.

Pachetele mari: cPanel → MultiPHP INI Editor → `upload_max_filesize` și `post_max_size` = 256M.

## 5. Recuperare

| Situație | Ce faceți |
|---|---|
| După o actualizare, siteul nu mai pornește | Deschideți `https://<domeniu>/recovery.php`, introduceți cheia de recuperare, alegeți versiunea anterioară. Nu necesită aplicația funcțională. |
| Actualizare rămasă „în curs”, site în mentenanță | Încărcați `deploy/reparatii/repara-actualizare.php` în `public_html`, deschideți-l, cheia de recuperare → „Finalizează” sau „Revino”. Scriptul se șterge singur. |
| Paginile dau 404 după ce cineva a modificat `.htaccess` | `deploy/reparatii/repara-htaccess.php` (aceeași procedură); cronul repară și el fișierul în câteva minute. |
| Restaurarea bazei de date dintr-un backup | Panou → Administrare → Backup (cheia de backup din `shared/keys/` este necesară). Operație cu risc: o face administratorul tehnic. |
| Ați pierdut cheia de recuperare | `php artisan optoplus:install --rotate-recovery-key` (din Terminal sau o sarcină cron rulată o singură dată). |

Recuperarea a fost testată pe un mediu cPanel simulat (vezi `docs/TESTE.md`).
