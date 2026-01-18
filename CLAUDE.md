# CLAUDE.md - NowScrobbling v1.4

## Project Overview

NowScrobbling is a WordPress plugin that displays Last.fm scrobbles and Trakt.tv watch history with beautiful, customizable layouts. Built with modern PHP 8.0+ patterns, PSR-4 autoloading, and a focus on performance, privacy, and perfect WordPress integration.

**Version**: 1.4.0
**PHP**: 8.0+
**WordPress**: 5.0+

## Quick Start

```bash
# No build step required - pure PHP/CSS/JS

# Install
1. Copy nowscrobbling/ to wp-content/plugins/
2. Activate in WordPress Admin
3. Go to Settings > NowScrobbling
4. Enter API credentials
5. Use shortcodes in your content
```

## Architecture

```
nowscrobbling/
├── nowscrobbling.php              # Bootstrap (67 lines)
├── src/
│   ├── Core/
│   │   ├── Plugin.php             # Singleton, initialization
│   │   ├── Autoloader.php         # PSR-4 autoloader
│   │   └── Assets.php             # Script/style enqueue
│   ├── Providers/
│   │   ├── ProviderInterface.php  # Provider contract
│   │   ├── AbstractProvider.php   # Shared provider logic
│   │   ├── ProviderRegistry.php   # Provider management
│   │   ├── LastFM/LastFMProvider.php
│   │   └── Trakt/TraktProvider.php
│   ├── Shortcodes/
│   │   ├── AbstractShortcode.php  # Shared shortcode logic
│   │   ├── ShortcodeRegistry.php  # Registration
│   │   ├── LastFM/                # 6 shortcodes
│   │   └── Trakt/                 # 5 shortcodes
│   ├── Cache/
│   │   ├── CacheManager.php       # Multi-layer caching
│   │   ├── CacheResult.php        # Cache response DTO
│   │   └── DataType.php           # TTL enum
│   ├── Admin/
│   │   └── AdminPage.php          # 7-tab dashboard
│   ├── Ajax/
│   │   └── AjaxHandler.php        # AJAX endpoints
│   └── Templates/
│       └── TemplateEngine.php     # Template rendering
├── templates/                     # PHP templates
├── assets/
│   ├── css/public/               # Frontend styles
│   ├── css/admin/                # Admin styles
│   └── js/                       # JavaScript
└── languages/                    # i18n
```

## Key Classes

### Core
| Class | Purpose |
|-------|---------|
| `Plugin` | Singleton bootstrap, hook registration |
| `Autoloader` | PSR-4 class loading |
| `Assets` | Conditional CSS/JS enqueue |

### Providers
| Class | Purpose |
|-------|---------|
| `ProviderInterface` | Contract for all providers |
| `AbstractProvider` | HTTP requests, caching, common logic |
| `LastFMProvider` | Last.fm API integration |
| `TraktProvider` | Trakt.tv API integration |

### Shortcodes
| Class | Purpose |
|-------|---------|
| `AbstractShortcode` | Shared attributes, rendering, wrapping |
| `ShortcodeRegistry` | Registration and retrieval |
| `*Shortcode` | Individual shortcode implementations |

### Cache
| Class | Purpose |
|-------|---------|
| `CacheManager` | Multi-layer cache (memory, transient, fallback) |
| `CacheResult` | Response with source info |
| `DataType` | Enum with TTL values |

## Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Namespace | `NowScrobbling\*` | `NowScrobbling\Providers\LastFM\LastFMProvider` |
| Shortcode Tag | `nowscr_*` | `nowscr_lastfm_indicator` |
| Option | `ns_*` | `ns_lastfm_api_key` |
| CSS Class | `nowscrobbling--*` | `nowscrobbling--card` |
| CSS Variable | `--ns-*` | `--ns-primary` |
| AJAX Action | `nowscrobbling_*` | `nowscrobbling_render` |
| Hook | `nowscrobbling_*` | `nowscrobbling_initialized` |

## Core Principles

### 1. "Old data is better than error messages"
The plugin uses 3-layer caching:
1. **Memory** - Same-request cache
2. **Primary** - Short-lived transient (1-15 min)
3. **Fallback** - Long-lived backup (7 days)

When APIs fail, the fallback shows stale data instead of errors.

### 2. Provider Pattern
New services can be added by:
1. Creating a class implementing `ProviderInterface`
2. Registering it in `Plugin::registerBuiltinProviders()`
3. Creating shortcodes and templates

No core code changes needed.

### 3. Template Engine
Templates can be overridden in themes:
```
your-theme/nowscrobbling/layouts/card.php
```
The engine checks theme first, then falls back to plugin templates.

### 4. Abstract Shortcodes
All shortcodes extend `AbstractShortcode` which provides:
- Common attributes (layout, show_image, limit, max_length, class)
- Data fetching with caching
- Template rendering
- Output wrapping with hash for AJAX updates

## Shortcodes

### Last.fm
| Shortcode | Description |
|-----------|-------------|
| `[nowscr_lastfm_indicator]` | Current scrobbling status |
| `[nowscr_lastfm_history]` | Recent scrobbles |
| `[nowscr_lastfm_top_artists]` | Top artists by period |
| `[nowscr_lastfm_top_albums]` | Top albums by period |
| `[nowscr_lastfm_top_tracks]` | Top tracks by period |
| `[nowscr_lastfm_lovedtracks]` | Loved tracks |

### Trakt.tv
| Shortcode | Description |
|-----------|-------------|
| `[nowscr_trakt_indicator]` | Current watching status |
| `[nowscr_trakt_history]` | Watch history |
| `[nowscr_trakt_last_movie]` | Last watched movie |
| `[nowscr_trakt_last_show]` | Last watched show |
| `[nowscr_trakt_last_episode]` | Last watched episode |

### Common Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `layout` | string | `inline` | inline, card, grid, list |
| `show_image` | bool | `false` | Show artwork |
| `limit` | int | `5` | Number of items |
| `max_length` | int | `0` | Text truncation |
| `class` | string | - | Custom CSS class |
| `period` | string | `7day` | Last.fm time period |

## Security Checklist

- [x] Nonce verification on all AJAX endpoints
- [x] Capability checks for admin actions
- [x] JSON sanitization with `json_last_error()` check
- [x] Template path validation with `realpath()`
- [x] Input sanitization with `sanitize_text_field()`
- [x] Output escaping with `esc_html()`, `esc_attr()`, `esc_url()`

## Adding a New Provider

1. Create provider class:
```php
namespace NowScrobbling\Providers\Spotify;

class SpotifyProvider extends AbstractProvider {
    public function getId(): string { return 'spotify'; }
    public function getName(): string { return 'Spotify'; }
    // ... implement interface methods
}
```

2. Register in Plugin.php:
```php
$this->providers->register(new SpotifyProvider());
```

3. Create shortcodes in `src/Shortcodes/Spotify/`
4. Create templates in `templates/shortcodes/spotify/`

## Debug Mode

Enable in Settings > NowScrobbling > Debug tab:
- Logs API requests/responses
- Shows cache hits/misses
- Displays error details

Logs appear in the Debug tab with timestamps.

## Testing Checklist

### Admin
- [ ] Settings page loads without errors
- [ ] All 7 tabs function correctly
- [ ] Settings save and persist
- [ ] Test Connection buttons work
- [ ] Clear Cache works

### Shortcodes
- [ ] All 11 shortcodes render
- [ ] All 4 layouts work
- [ ] Artwork displays correctly
- [ ] AJAX refresh updates content
- [ ] No layout shift on hydration

### Caching
- [ ] Fresh data fetched on first load
- [ ] Cache hit on subsequent loads
- [ ] Fallback used when API fails
- [ ] Cache clear works

## Release Process

1. Update version in:
   - `nowscrobbling.php` (header + constant)
   - `src/Core/Plugin.php` (VERSION constant)
   - `readme.txt` (Stable tag)
2. Update `CHANGELOG.md`
3. Test all functionality
4. Commit: `release: v1.4.0`
5. Push to remote

## Do NOT

- Bypass the CacheManager
- Use `extract()` in templates (use explicit variables)
- Add external dependencies without consent
- Echo output directly in bootstrap
- Commit API keys or credentials
