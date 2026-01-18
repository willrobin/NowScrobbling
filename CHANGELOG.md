# Changelog

## [2.0.0] - 2026-01-18

### Complete Rewrite - Modern Architecture

Version 2.0 is a complete rewrite of NowScrobbling with a modern PHP 8.2+ architecture, maintaining full backward compatibility with v1.3 settings.

### Added

**Architecture**
- PSR-4 autoloading with Composer
- Lightweight dependency injection container
- Plugin singleton orchestrator with proper hook registration
- Strict types throughout the codebase

**Multi-Layer Caching System**
- `InMemoryLayer`: Per-request static cache
- `TransientLayer`: WordPress transients as primary cache
- `FallbackLayer`: 7-day emergency cache for API failures
- `ETagLayer`: HTTP conditional requests for bandwidth savings
- `ThunderingHerdLock`: Prevents simultaneous API requests
- Stale-while-revalidate pattern for seamless updates

**Environment Detection**
- `CloudflareAdapter`: Automatic header optimization
- `LiteSpeedAdapter`: Cache purge integration
- `NginxAdapter`: FastCGI cache support
- `ObjectCacheAdapter`: Redis/Memcached/APCu detection
- `DefaultAdapter`: Fallback for standard environments

**API Clients**
- `ApiClientInterface`: Contract for all API clients
- `AbstractApiClient`: Base class with ETag, rate limiting, retry logic
- `LastFmClient`: Full Last.fm API implementation
- `TraktClient`: Full Trakt.tv API implementation
- `RateLimiter`: Prevents API abuse
- `ApiResponse`: DTO for successful responses

**Shortcodes (Unified Architecture)**
- `AbstractShortcode`: Base class eliminating 40% code duplication
- `OutputRenderer`: SSR wrapper with hash-based DOM diffing
- `HashGenerator`: Content hashing for efficient updates
- `TopListShortcode`: Unified artists/albums/tracks handling

**Admin Interface**
- Tab-based settings page (Credentials, Caching, Display, Diagnostics)
- `SettingsManager`: Orchestrates all settings
- `TabInterface`: Contract for admin tabs
- API connection testing
- Cache statistics and metrics
- Debug log viewer

**REST API (Replaces admin-ajax.php)**
- `GET /wp-json/nowscrobbling/v1/render/{shortcode}` - Public shortcode rendering
- `GET /wp-json/nowscrobbling/v1/status` - Admin plugin status
- `POST /wp-json/nowscrobbling/v1/cache/clear` - Admin cache management

**Security**
- `NonceManager`: Granular nonces per endpoint
- `Sanitizer`: Centralized sanitization with correct order (unslash → sanitize)
- Capability checks for all admin actions
- HTTPS enforcement for API calls
- Input validation (text, int, bool, email, url, json, html)
- Output escaping at render time

**JavaScript (Vanilla ES6+)**
- `NowScrobbling` class with IntersectionObserver
- Hash-based DOM diffing (no flicker)
- Exponential backoff polling
- Page Visibility API integration
- Smooth height transitions
- No jQuery dependency

**Migration System**
- Automatic v1.3 → v2.0 option migration (24 mappings)
- Legacy transient cleanup
- Legacy cron hook removal
- Migration rollback capability
- Migration status tracking

**Testing**
- PHPUnit test framework
- Unit tests for cache layers
- Unit tests for security components
- WordPress stub functions
- Test bootstrap configuration

**Documentation**
- ARCHITECTURE.md: Comprehensive architecture guide
- CONTRIBUTING.md: Contribution guidelines
- Updated CLAUDE.md for v2.0

### Changed
- Minimum PHP version: 7.0 → 8.2
- Minimum WordPress version: 5.0 → 6.0
- Plugin structure: Monolithic → Modular (`/src/` directory)
- Hook registration: Constructor → `plugins_loaded` action
- HTTP API: admin-ajax.php → REST API
- JavaScript: jQuery → Vanilla ES6+
- Caching: Single layer → Multi-layer with fallback

### Removed
- Legacy includes/ directory (v1.3 code, kept for reference during migration)
- jQuery dependency
- Direct `wp_remote_get` calls (replaced by API clients)
- Single nonce for all AJAX endpoints

### Security
- Fixed: Nonces now granular per endpoint (was: single nonce for all)
- Fixed: Sanitization order corrected (was: sometimes sanitize before unslash)
- Added: Rate limiting for API requests
- Added: Capability checks for all admin actions
- Added: HTTPS enforcement for external API calls

### Migration Notes
- All v1.3 settings are automatically migrated on activation
- No manual configuration required
- Rollback available in Diagnostics tab if needed
- Legacy cron hooks are automatically removed

---

## [1.3.2] - 2025-01-14

### Added
- **docs**: CLAUDE.md for Claude Code collaboration guidelines
- **api**: `nowscrobbling_log_throttled()` function to reduce log spam
- **api**: Deep key sorting for deterministic cache key generation

### Changed
- **api**: Optimized HTTP request headers (gzip, connection close, reduced timeout)
- **api**: Reduced default timeout from 10s to 5s, disabled redirects
- **docs**: Modernized cursorrules, now references CLAUDE.md
- **config**: Extended .gitignore with IDE files, logs, and local configs

### Fixed
- **css**: Added margin-top for mobile responsive views

---

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
