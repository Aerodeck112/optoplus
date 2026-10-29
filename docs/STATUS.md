# Starea sinceră a funcționalităților

Coloane: **implementat** · **testat local** (teste automate și/sau parcurgere în browser) · **testat staging**
· **verificat cu serviciul extern** · **blocat de acces/date**. „Testat staging” rămâne „nu” până la existența
unui staging pe hostingul Optoplus.

## Etapa 0 — audit și planificare (2026-10-29)

| Livrabil | Stare | Observații |
|---|---|---|
| Audit optoplus.ro | ✅ `docs/audit/AUDIT-SITE-OPTOPLUS.md` | checkout, transport, cupoane, cont: neverificate (ar fi cerut scrieri în magazinul live) |
| Audit concurență | ✅ `docs/audit/AUDIT-CONCURENTA.md` | Lensa și Optiblu blochează accesul automat: doar informații din indexare |
| Reguli configurator actual | ✅ `database/content/configurator-actual-apf.json` | extrase din HTML public; regulile interne APF necesită exportul (A5) |
| Accese și informații lipsă | ✅ `docs/ACCESE-SI-INFORMATII-LIPSA.md` | — |
| Arhitectură, model de date | ✅ `docs/ARHITECTURA.md`, `docs/SCHEMA-BD.md` | — |
| Plan de migrare | ✅ `docs/MIGRARE.md` | — |
| Structură site și fluxuri | ✅ `docs/STRUCTURA-SITE-SI-FLUXURI.md` | — |
| Direcție vizuală | ✅ `docs/DIRECTIE-VIZUALA.md` | ecranele reprezentative vin cu implementarea |
| Backlog cu criterii | ✅ `docs/BACKLOG.md` | — |
