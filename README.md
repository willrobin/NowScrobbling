# NowScrobbling

WordPress-Plugin zur Anzeige von Last.fm- und Trakt.tv-Aktivitaet per Shortcode.

## Projektstatus

Stand: 5. Maerz 2026

- Technischer Stand: v2.0.0 (Rewrite auf PHP 8.2+, PSR-4, DI, REST API)
- Repo-Struktur: Monorepo-artig mit Plugin-Code in `nowscrobbling/`
- Fokus aktuell: Stabilisierung, Release-Qualitaet und klare Produkt-Roadmap

Die priorisierten naechsten Schritte stehen in [ROADMAP.md](ROADMAP.md).

## Kernfunktionen

- Last.fm- und Trakt.tv-Integration mit serverseitigen API-Requests
- 11 Shortcodes (Last.fm + Trakt)
- Multi-Layer-Caching (In-Memory, Transients, Fallback, ETag)
- Admin-Bereich mit Tabs fuer Credentials, Caching, Anzeige und Diagnostics
- REST-Endpunkte fuer Rendering, Status, Cache-Clear und API-Tests
- Vanilla-JS-Frontend (IntersectionObserver, Hash-basiertes Update)

## Anforderungen

- WordPress 6.0+
- PHP 8.2+
- Composer (fuer lokale Entwicklung)

## Installation

### 1) Plugin lokal entwickeln

```bash
git clone https://github.com/willrobin/NowScrobbling.git
cd NowScrobbling/nowscrobbling
composer install
```

Dann den Ordner `nowscrobbling/` in eine WordPress-Instanz unter `wp-content/plugins/` verlinken oder kopieren und aktivieren.

### 2) Plugin konfigurieren

WordPress Admin: `Einstellungen -> NowScrobbling`

- Last.fm: API Key + Username
- Trakt.tv: Client ID + Username

## Verfuegbare Shortcodes

### Last.fm

- `[nowscr_lastfm_indicator]`
- `[nowscr_lastfm_history]`
- `[nowscr_lastfm_top_artists]`
- `[nowscr_lastfm_top_albums]`
- `[nowscr_lastfm_top_tracks]`
- `[nowscr_lastfm_lovedtracks]`

### Trakt.tv

- `[nowscr_trakt_indicator]`
- `[nowscr_trakt_history]`
- `[nowscr_trakt_last_movie]`
- `[nowscr_trakt_last_show]`
- `[nowscr_trakt_last_episode]`

### Wichtige Attribute

- `max_length` (alle, 20-200)
- `style` (`inline` oder `bubble`)
- `limit` (Listen-Shortcodes, 1-50)
- `period` (Last.fm Top-Listen: `7day|1month|3month|6month|12month|overall`)
- `type` (nur `nowscr_trakt_history`: `all|movies|shows|episodes`)

## REST API

Namespace: `/wp-json/nowscrobbling/v1`

- `GET /render/{shortcode}`
- `GET /status`
- `POST /cache/clear` (Admin)
- `POST /test/{service}` mit `service=lastfm|trakt` (Admin)

## Entwicklung

```bash
cd nowscrobbling
composer install
composer test
composer phpcs
```

## Lokale CI Mit OrbStack (Privat + Kostenlos)

Alle Checks laufen lokal per Docker/OrbStack, ohne externen Cloud-Runner:

```bash
# aus dem Repo-Root
make ci-test
make ci-phpcs
make ci-phpcs-changed
make ci-check
```

`ci-check` nutzt absichtlich inkrementelles PHPCS auf geaenderte Produktionsdateien (`src/*.php`, `nowscrobbling.php`, `uninstall.php`), damit Legacy-Verstoesse im Altbestand den Alltag nicht blockieren.
Optional kann die Vergleichsbasis gesetzt werden, z. B. `BASE_REF=main make ci-check`.

Technische Basis:

- `docker-compose.local-ci.yml`
- `nowscrobbling/Dockerfile.ci`
- `Makefile` Targets `ci-*`

## Release Und Deploy

- Release-Check: `make release-check`
- Deploy-Entry-Point: `NS_DEPLOY_CMD='your-command' make deploy`
- Vollstaendige Checkliste: [RELEASE.md](RELEASE.md)

## Dokumentation

- [ARCHITECTURE.md](ARCHITECTURE.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CHANGELOG.md](CHANGELOG.md)
- [ROADMAP.md](ROADMAP.md)

## Lizenz

GPL-2.0-or-later (siehe Plugin-Header in `nowscrobbling/nowscrobbling.php`).
