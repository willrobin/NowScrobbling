# Changelog

## [1.3.1.2] - 2025-08-10

### Changes
- **core**: Version auf 1.3.1.2 angehoben (Header, Konstante, Doku)
- **docs**: README auf 1.3.1.2 aktualisiert, Abschnitt ergänzt

### Fixes
- Kleinere Robustheitsanpassungen in Admin-/AJAX-Darstellung dokumentiert

## [1.3.1.1] - 2025-08-09

### Fixes
- **api**: Logging an Debug-Option gekoppelt, um Optionsgröße zu reduzieren
- **api**: Doppeltes Request-Zählen bei HTTP 204 entfernt
- **ajax**: Boolean-Handling ohne `wp_validate_boolean` (Kompatibilität WP 5.0)

### Changes
- **core**: Standardwerte für `nowscrobbling_debug_log` und `ns_enable_rewatch` auf 0 gesetzt
- **core**: Version auf 1.3.1.1 angehoben

## [1.3.0] - 2025-08-09

### Highlights
- Server-Side Rendering (SSR) für initiale Ausgabe, danach schlankes AJAX-Nachladen
- Bedingtes Laden von Assets nur auf Seiten mit Shortcodes
- Verbesserte Transient-Strategie inkl. Fallback-Caches und ETag-Unterstützung
- Hintergrund-Aktualisierung via WP-Cron (alle 5 Minuten)
- Admin-Seite mit Status, Metriken, Cache-Tools und API-Tests

### Verbesserungen
- Robustere Sanitization der Einstellungen und AJAX-Parameter
- Optimierte Hash-basierte DOM-Updates ohne Flackern
- Adaptive Polling-Intervalle mit Backoff bei Fehlern

### Sonstiges
- Konsolidierung auf Branch `main`
- Dokumentation aktualisiert

## [1.2.5] - 2024-10-20

### Features
- **admin**: Add sanitization to all admin settings fields using `sanitize_text_field`
- **trakt**: Add functions to fetch specific ratings for movies, shows, and episodes from Trakt
- **trakt**: Add rewatch count tracking for movies and episodes

### Fixes
- **api**: Update Trakt API URL to use HTTPS for better security
- **core**: Fix missing ABSPATH check to prevent direct access to files

### Chores
- **css**: Improve public-facing elements styling in `nowscrobbling-public.css`

## [1.2.4] - 2024-05-19

### Features
- **trakt**: Add optional attributes (`show_year`, `show_rating`, `show_rewatch`) for Trakt shortcodes

### Fixes
- **formatting**: Adjust `nowscrobbling_format_output` function to eliminate white spaces

## [1.2.3] - 2024-05-19

### Fixes
- **trakt**: Implement accurate rewatch count tracking for `nowscr_trakt_last_movie` shortcode

## [1.2.2] - 2024-05-17

### Features
- **shortcodes**: Add detailed `title` attributes for Last.fm and Trakt shortcodes
- **formatting**: Implement `nowscrobbling_format_output` for better Trakt output formatting
- **trakt**: Add `nowscrobbling_fetch_trakt_watched_shows` function to fetch completed shows

### Changes
- **shortcodes**: Refactor Trakt shortcodes to include ratings and rewatch counts
- **core**: Improve transient caching for API data fetching
- **shortcodes**: Enhance `nowscr_lastfm_history_shortcode` with title attributes

### Fixes
- **ui**: Correct image path for Last.fm "Now Playing" GIF

## [1.2.1] - 2024-05-16

### Fixes
- **trakt**: Fix issue with currently playing item not displaying in `nowscr_trakt_indicator`

## [1.2.0] - 2024-05-14

### Features
- **core**: Restructure plugin to improve maintainability (split code into multiple files)

### Changes
- **requirements**: Update version requirements to WordPress 5.0 and PHP 7.0
- **shortcodes**: Enhance attribute handling for Last.fm shortcodes to specify period
- **caching**: Improve caching for Last.fm and Trakt.tv APIs
- **admin**: Improve admin settings page layout

### Fixes
- **security**: Improve sanitization of input values in admin settings
- **api**: Fix multiple issues with Last.fm and Trakt API fetching

### Removed
- **styles**: Remove inline styles in favor of external CSS file

## [1.0.5] - 2024-03-10

### Features
- **core**: Add comprehensive metadata in plugin header
- **security**: Add explicit exit condition to prevent direct file access
- **admin**: Add dynamic input fields for user configuration in admin settings
- **cache**: Add cache management for Last.fm and Trakt.tv

### Changes
- **core**: Refactor entire plugin structure for better functionality and user experience

### Fixes
- **core**: Fix minor bugs and improve code syntax to adhere to WordPress standards
