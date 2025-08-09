# Contributing · NowScrobbling

Vielen Dank für deinen Beitrag! Dieses Repo ist ein WordPress-Plugin. Bitte beachte die folgenden Leitlinien.

## Entwicklung lokal
1. Klone das Repo in `wp-content/plugins/NowScrobbling` (oder symlink das Verzeichnis).
2. Aktiviere das Plugin im WordPress-Backend.
3. Trage auf der Einstellungsseite last.fm- und/oder trakt.tv-Zugangsdaten ein.
4. Es gibt keinen Build-Schritt (reines PHP/JS/CSS). Browser-Refresh genügt.

## Stil & Architektur
- Halte dich an die Regeln in `cursorrules` und `ARCHITECTURE.md`.
- PHP-Helfer und API-Zugriffe: `nowscrobbling/includes/api-functions.php`
- Shortcodes und Ausgabe: `nowscrobbling/includes/shortcodes.php`
- Admin-Einstellungen/Seiten: `nowscrobbling/includes/admin-settings.php`
- AJAX-Handler: `nowscrobbling/includes/ajax.php`
- Bootstrap bleibt schlank: `nowscrobbling/nowscrobbling.php`

### Coding-Guidelines (Auszug)
- Präfixe: `nowscrobbling_` (Helpers), `nowscr_` (Shortcodes)
- Frühe Returns, Fehler zuerst behandeln, flache Verschachtelung
- Sanitize beim Eingang, escapen an der Ausgabestelle (`esc_html`, `esc_attr`, `esc_url`)
- Für HTTP und Caches ausschließlich die vorhandenen Helfer verwenden
- SSR-Ausgaben immer mit Wrapper + Hash (siehe `nowscrobbling_wrap_output`)

## Tests & QA
- Admin: „API-Verbindungen testen“ ausführen und Rückmeldungen prüfen
- Shortcodes manuell auf einer Testseite einsetzen; prüfen, dass SSR sichtbar ist und AJAX-Refresh funktioniert
- Admin: „Alle Caches leeren“, dann Seite neu laden → Cache füllt sich wieder
- Fehler- und Ereignisprotokoll in den Einstellungen prüfen (Debug-Log)

## Commits & PRs
- Schreibe aussagekräftige Commits (z. B. "shortcodes: add trakt seasons progress + caching")
- Kleine, thematisch fokussierte PRs bevorzugen
- Beschreibe kurz Motivation, Changeset, Screenshots/GIFs bei UI-relevanten Änderungen

## Release-Hinweise
- Version bump in `nowscrobbling.php` (Konstante `NOWSCROBBLING_VERSION`)
- `README.md`/`CHANGELOG.md` aktualisieren

Danke fürs Mitwirken! 🙌
