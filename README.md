# Optoplus

Platforma Optoplus: magazin online (rame, ochelari compleți, lentile produse în laboratorul propriu),
configurator de lentile, portal B2B, CRM și producție, asistent, CMS și actualizări prin ZIP semnat, pentru
hosting cPanel.

- Arhitectura: [`docs/ARHITECTURA.md`](docs/ARHITECTURA.md)
- Starea reală a funcționalităților: [`docs/STATUS.md`](docs/STATUS.md)
- Auditul siteului și al concurenței: [`docs/audit/`](docs/audit/)
- Accese și informații necesare: [`docs/ACCESE-SI-INFORMATII-LIPSA.md`](docs/ACCESE-SI-INFORMATII-LIPSA.md)
- Backlog: [`docs/BACKLOG.md`](docs/BACKLOG.md)

Cerințe: PHP ≥ 8.3 (extensii: pdo_mysql, mbstring, openssl, sodium, intl, gd, zip, fileinfo, curl, dom, xml),
MariaDB ≥ 10.6 / MySQL ≥ 8.0, un cron la minut. Fără Node, Redis sau procese permanente pe server.
