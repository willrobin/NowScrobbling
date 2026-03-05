# NowScrobbling Roadmap

Stand: 5. Maerz 2026

## Zielbild

NowScrobbling soll ein robustes, wartbares WordPress-Plugin bleiben, das Last.fm- und Trakt-Daten stabil ausliefert, auch bei API-Ausfaellen, Caching-Sonderfaellen und unterschiedlichen Hosting-Umgebungen.

## Statusueberblick

- Architektur v2.0 (PHP 8.2+, DI, REST, Multi-Layer-Cache): `done`
- Lokale private CI via OrbStack (`make ci-test`, `make ci-check`): `done`
- Inkrementelles Linting fuer geaenderte Produktionsdateien: `done`
- Kritische Testluecken (API-Clients, RestController-Routen, Shortcode-Randfaelle): `in progress`
- Reproduzierbarer Release- und Deploy-Prozess: `in progress`
- Multisite-Strategie: `planned`

## Leitplanken

- Privacy first: keine externen Pflichtdienste fuer Build/Test/Deploy.
- Stabilitaet vor Features fuer die naechsten v2.0.x-Releases.
- Dokumentation wird bei jeder Aenderung mitgezogen (README/ROADMAP/CHANGELOG).

## Prioritaeten

### P0 - Stabilisieren und release-faehig machen (naechste 1-2 Wochen)

1. Testabdeckung fuer risikohohe Bereiche erhoehen.
- Fokus: `Api/*Client`, `RestController` Route-Verhalten, Shortcode-Fehlerpfade.
- Ziel: Ausfaelle reproduzierbar abfangen statt nur manuell pruefen.

2. Release-Readiness fuer v2.0.1 absichern.
- Version-Bump, Changelog, Smoke-Test in sauberer WP-Testinstanz.
- Klare Go/No-Go-Checks vor Tag/Deploy.

3. Caching/Diagnostics absichern.
- Verhalten bei 204/304/429/5xx explizit pruefen.
- Debug-Logging auf sinnvolle Begrenzung und Verstaendlichkeit trimmen.

### P1 - Lieferfaehigkeit und Wartbarkeit verbessern (2-6 Wochen)

1. Optionaler Cloud-CI-Spiegel (GitHub Actions) fuer PR-Feedback.
- Lokal bleibt Source of Truth; Cloud-CI nur als schneller Spiegel.

2. Deploy-Prozess vereinheitlichen.
- Ein dokumentierter Deploy-Entry-Point (Script/Make-Target) statt manueller Einzelschritte.

3. Admin UX haerten.
- Deutlichere Hinweise bei fehlenden Credentials.
- Praezise Fehlertexte fuer API-Limits und Upstream-Ausfaelle.

### P2 - Produktausbau (6+ Wochen)

1. Multisite-Konzept und Umsetzung.
2. Erweiterbarkeit fuer weitere Dienste (z. B. Spotify) mit stabilem Extension-Muster.
3. Observability-Ausbau (strukturierte Diagnose, Export/Reset).

## Konkreter 2-Wochen-Sprint (Vorschlag)

### Sprintziel

v2.0.1 als stabiles Maintenance-Release vorbereiten und auslieferbar machen.

### Backlog (priorisiert)

1. `P0` RestController-Tests erweitern.
- Attrs-Sanitization edge cases, Invalid shortcode route, response shape.
- DoD: neue Tests laufen in `make ci-check` gruen.

2. `P0` API-Client-Fehlerpfade testen.
- 204/304/429/5xx + invalid JSON in Last.fm/Trakt.
- DoD: erwartetes Fallback-/Error-Verhalten in Tests fest verankert.

3. `P0` Release-Checkliste als Doku anlegen.
- Datei: `docs/RELEASE.md` oder `RELEASE.md`.
- DoD: eindeutige Schritte fuer Version, Test, Tag, Push, Deploy, Rollback.

4. `P0` Deploy-Entry-Point definieren.
- Ein Befehl fuer den wiederholbaren Deploy.
- DoD: dokumentiert, lokal pruefbar, ohne manuelle Glue-Arbeit.

5. `P1` Diagnostics-UX Quick-Wins.
- Klarere Statusmeldungen bei nicht konfigurierten APIs.
- DoD: nachvollziehbare Nutzerhinweise im Admin-Tab.

## Brainstorming-Backlog (Ideen)

### Produkt/UX

- Preset-Shortcodes im Admin mit Copy-Button (schneller Einstieg).
- Optionales Widget/Block fuer Gutenberg statt nur Shortcode.
- "Health Badge" im Admin (API ok / degraded / down) mit Handlungshinweis.

### Reliability

- Vertragstests fuer API-Response-Mapping (Last.fm/Trakt).
- Guardrails fuer zu grosse Option- oder Log-Payloads.
- Standardisierte Error-Codes intern (`NS_API_RATE_LIMIT`, `NS_API_BAD_JSON`, ...).

### Developer Experience

- `make ci-release` als Sammelziel fuer Test + Lint + Release-Pruefung.
- Kleinere Test-fixtures fuer typische API-Szenarien.
- CONTRIBUTING um "Definition of Done" je Change-Type erweitern.

## Nicht-Ziele (aktuell)

- Komplettes Frontend-Re-Design.
- Einfuehrung eines JS-Frameworks.
- Externer API-Proxy-Service.

## Erfolgsmessung

- `make ci-check` bleibt auf allen Aenderungen gruen.
- v2.0.1 ohne Regressionen bei den 11 Shortcodes.
- Dokumentierte und wiederholbare Release-/Deploy-Schritte.
