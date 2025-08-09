# Architekturüberblick · NowScrobbling

Kurzfassung der Struktur, Datenflüsse und Erweiterungspunkte des WordPress-Plugins.

## Ziele
- Zuletzt-aktiv/Now-Playing aus last.fm und trakt.tv anzeigen
- Serverseitiges Rendering (SSR) per Shortcodes, progressive Hydration via AJAX
- Schonender Umgang mit APIs durch Transient-Caching, Fallback-Caches und ETag

## Verzeichnisstruktur (wichtigste Dateien)
- `nowscrobbling/nowscrobbling.php`: Bootstrap (Konstanten, i18n, Asset-Loading, Cron, Includes, Hooks)
- `nowscrobbling/includes/api-functions.php`: API-Aufrufe, Caching-Primitive, ETag, Metriken, Logs, Hintergrundaktualisierung
- `nowscrobbling/includes/shortcodes.php`: Alle Shortcodes und SSR-Ausgabe-Helfer
- `nowscrobbling/includes/admin-settings.php`: Settings-Registrierung, Admin-Seite, Diagnostik, Metriken, Cache-Tools
- `nowscrobbling/includes/ajax.php`: Öffentliche AJAX-Endpunkte (Shortcode-Rendering) und Admin-AJAX (Caches/Debug)
- `nowscrobbling/public/js/ajax-load.js`: Clientseitiges Nachladen, Polling, Backoff
- `nowscrobbling/public/css/*`: Styles für die Bubble-Ausgabe

## Datenfluss
1) Seite lädt mit SSR-Shortcode-Ausgabe (kompakt, „bubble“-Links, Wrapper mit `data-nowscrobbling-shortcode` und `data-ns-hash`).
2) `ajax-load.js` fragt über `admin-ajax.php` den Endpunkt `nowscrobbling_render_shortcode` an.
3) Server rendert Shortcode erneut; Hash wird verglichen. Nur bei Änderung ersetzt der Client-Inhalt den Wrapper.
4) Für Indikatoren/Now-Playing wird Polling mit konfigurierbaren Intervallen und Backoff aktiv.

## Caching & Netzwerk
- Jeder Abruf nutzt `nowscrobbling_get_or_set_transient()`:
  - Speichert Hauptcache und langfristigen Fallback-Cache
  - Hält Metadaten (gespeichert/ablauf, Quelle, Fallback vorhanden) für Admin-Diagnostik
- HTTP-Aufrufe grundsätzlich über `nowscrobbling_fetch_api_data()`:
  - Setzt User-Agent, `Accept: application/json`, nutzt ETag (If-None-Match/304), zeichnet Metriken auf, respektiert Cooldowns

## Cron & Background Refresh
- WP-Cron Hook `nowscrobbling_cache_refresh` (alle 5 Minuten):
  - Frischt ausgewählte Daten im Hintergrund auf (Last.fm: recent+top, Trakt: watching/history)

## Erweiteren (Best Practice)
- Neuer Shortcode:
  - SSR in `includes/shortcodes.php` implementieren
  - Tag in `nowscrobbling_list_shortcodes()` (Bootstrap) aufnehmen
  - Whitelist in `includes/ajax.php` ergänzen
  - Immer Wrapper via `nowscrobbling_wrap_output()` + Hash verwenden
- Neuer API-Endpunkt:
  - Fetch-Funktion in `includes/api-functions.php` mit `nowscrobbling_fetch_api_data()`
  - Cache-Strategie per `nowscrobbling_get_or_set_transient()`

## Admin & Diagnostik
- Einstellungsseite zeigt Version, Cron-Status, Cache-Aktivität
- Buttons: Caches leeren, Metriken resetten, API-Verbindungen testen
- Debug-Log (`nowscrobbling_log`) zeigt Ereignisse, Fehler, ETag-Treffer

## Frontend UX
- Minimalistische, inlinefähige Ausgabe als Links mit Klasse `bubble`
- Optionales Now-Playing-GIF nur bei History/Watching-Ausgaben

## Internationalisierung
- Textdomain `nowscrobbling`
- Übersetzbare Strings in PHP via `__()/_e()` (s. Bootstrap `load_plugin_textdomain`)
