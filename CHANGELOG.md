# Changelog

All notable changes to NowScrobbling will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2025-01-15

### Added
- **Complete architectural rewrite** with modern PHP 8.0+ patterns
- **PSR-4 autoloading** with custom Autoloader class
- **Provider pattern** for extensible API integrations (LastFM, Trakt)
- **4 layout options**: inline, card, grid, list
- **Artwork support** for album covers and movie/TV posters
- **TMDb integration** for high-quality posters (optional)
- **Template engine** with theme override support
- **Multi-layer caching**: memory, transient, and 7-day fallback
- **7-tab admin dashboard**: Overview, Last.fm, Trakt, Display, Cache, Shortcodes, Debug
- **Abstract shortcode base class** reducing code duplication
- **Type-safe code** with PHP 8 typed properties and enums
- **CSS variables** for easy theming
- **Dark/Light mode** with automatic system preference detection
- **AJAX handlers** with proper JSON sanitization
- **Template path validation** with realpath() for security

### Changed
- **Minimum PHP version**: 8.0 (was 7.0)
- **File structure**: Complete reorganization into `src/` directory
- **Namespace**: All classes now under `NowScrobbling\` namespace
- **Admin UI**: Modern tabbed interface replacing old settings page
- **Caching strategy**: Three-layer system with graceful degradation
- **Asset loading**: Conditional enqueue only on pages with shortcodes

### Removed
- Legacy `includes/` directory (admin-settings.php, ajax.php, api-functions.php, shortcodes.php)
- Legacy `public/` and `admin/` directories
- Unused cron job scaffolding
- PHP 7.x compatibility code

### Security
- JSON sanitization in all AJAX handlers
- Template path validation prevents directory traversal
- Nonce verification on all AJAX endpoints
- Capability checks for admin actions

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

## [1.3.1.3] - 2025-01-10

### Changed
- Hardened Trakt cache fallback policy
- Normalized activities handling
- Reduced debug marker spam
- Improved history fallback rendering

---

## [1.3.1.2] - 2025-08-10

### Changed
- Version bump to 1.3.1.2
- Updated README documentation

### Fixed
- Minor robustness adjustments in Admin/AJAX handling

---

## [1.3.1.1] - 2025-08-09

### Changed
- Logging now tied to Debug option to reduce database bloat
- Default values for `nowscrobbling_debug_log` and `ns_enable_rewatch` set to 0
- Version bump to 1.3.1.1

### Fixed
- Removed double request counting on HTTP 204
- Boolean handling without `wp_validate_boolean` for WP 5.0 compatibility

---

## [1.3.0] - 2025-08-09

### Added
- **Server-Side Rendering (SSR)** for initial output, then lightweight AJAX refresh
- **Conditional asset loading** only on pages with shortcodes
- **Fallback caching** with ETag support for Trakt API
- **Background refresh** via WP-Cron (every 5 minutes)
- **Admin status dashboard** with metrics, cache tools, and API tests

### Changed
- Improved transient strategy with fallback caches
- Robust sanitization of settings and AJAX parameters
- Hash-based DOM updates without flicker
- Adaptive polling intervals with backoff on errors

---

## [1.2.5] - 2024-10-20

### Added
- Sanitization for all admin settings fields
- Functions to fetch specific ratings for movies, shows, and episodes from Trakt
- Rewatch count tracking for movies and episodes

### Fixed
- Updated Trakt API URL to use HTTPS
- Missing ABSPATH check to prevent direct file access

---

## [1.2.4] - 2024-05-19

### Added
- Optional attributes (`show_year`, `show_rating`, `show_rewatch`) for Trakt shortcodes

### Fixed
- White space issues in `nowscrobbling_format_output` function

---

## [1.2.3] - 2024-05-19

### Fixed
- Accurate rewatch count tracking for `nowscr_trakt_last_movie` shortcode

---

## [1.2.2] - 2024-05-17

### Added
- Detailed `title` attributes for Last.fm and Trakt shortcodes
- `nowscrobbling_format_output` for better Trakt output formatting
- `nowscrobbling_fetch_trakt_watched_shows` function

### Changed
- Refactored Trakt shortcodes to include ratings and rewatch counts
- Improved transient caching for API data
- Enhanced `nowscr_lastfm_history_shortcode` with title attributes

### Fixed
- Image path for Last.fm "Now Playing" GIF

---

## [1.2.1] - 2024-05-16

### Fixed
- Currently playing item not displaying in `nowscr_trakt_indicator`

---

## [1.2.0] - 2024-05-14

### Added
- Plugin restructure for better maintainability

### Changed
- Version requirements: WordPress 5.0, PHP 7.0
- Enhanced attribute handling for Last.fm shortcodes
- Improved caching for Last.fm and Trakt APIs
- Better admin settings page layout

### Fixed
- Sanitization of input values in admin settings
- Multiple issues with Last.fm and Trakt API fetching

### Removed
- Inline styles in favor of external CSS file

---

## [1.0.5] - 2024-03-10

### Added
- Comprehensive metadata in plugin header
- Explicit exit condition to prevent direct file access
- Dynamic input fields for user configuration
- Cache management for Last.fm and Trakt.tv

### Changed
- Complete plugin structure refactor

### Fixed
- Minor bugs and code syntax improvements
