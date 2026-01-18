# NowScrobbling v1.4 Architecture

## Overview

A flexible, provider-agnostic media scrobbling plugin for WordPress.
Built for extensibility - new providers (Spotify, Letterboxd, Goodreads, Steam) can be added without touching core code.

---

## Core Principles

1. **Provider Agnostic** - Core knows nothing about specific APIs
2. **Smart Caching** - Cache TTL based on data volatility
3. **Graceful Degradation** - Old data > Error messages
4. **Minimal API Calls** - Aggressive caching, ETag support
5. **Separation of Concerns** - Each class does one thing

---

## Provider System

### Interface: `ProviderInterface`

Every media provider (Last.fm, Trakt, Spotify, etc.) implements this interface:

```php
namespace NowScrobbling\Providers;

interface ProviderInterface {
    // Identity
    public function getId(): string;           // 'lastfm', 'trakt', 'spotify'
    public function getName(): string;         // 'Last.fm', 'Trakt.tv'
    public function getIcon(): string;         // SVG or dashicon

    // Configuration
    public function getSettingsFields(): array;
    public function isConfigured(): bool;
    public function testConnection(): ConnectionResult;

    // Attribution (legal compliance)
    public function getAttribution(): Attribution;

    // Capabilities - what can this provider do?
    public function getCapabilities(): array;  // ['now_playing', 'history', 'top_items', 'ratings']

    // Data fetching
    public function fetchData(string $endpoint, array $params = []): ProviderResponse;
}
```

### Abstract Base: `AbstractProvider`

Common functionality for all providers:

```php
abstract class AbstractProvider implements ProviderInterface {
    protected CacheManager $cache;
    protected HttpClient $http;
    protected array $config;

    // Shared methods
    protected function getCached(string $key, callable $fetcher, int $ttl): mixed;
    protected function request(string $url, array $options = []): Response;
    protected function handleRateLimit(Response $response): void;
}
```

### Provider Registry

Central registry for all providers:

```php
class ProviderRegistry {
    private array $providers = [];

    public function register(ProviderInterface $provider): void;
    public function get(string $id): ?ProviderInterface;
    public function getAll(): array;
    public function getConfigured(): array;  // Only providers with valid credentials
    public function getByCapability(string $capability): array;
}
```

---

## Data Types & Cache Strategy

### Data Volatility Levels

| Level | TTL | Fallback TTL | Examples |
|-------|-----|--------------|----------|
| **Realtime** | 30s | 5 min | Now playing, currently watching |
| **Frequent** | 5 min | 1 hour | Recent scrobbles, watch history |
| **Daily** | 1 hour | 24 hours | Top items (7day), activity stats |
| **Stable** | 24 hours | 7 days | Top items (yearly), ratings, favorites |
| **Static** | 7 days | 30 days | Artwork URLs, metadata |

### DataType Enum

```php
enum DataType: string {
    case NOW_PLAYING = 'now_playing';      // Realtime
    case HISTORY = 'history';              // Frequent
    case TOP_ITEMS_WEEKLY = 'top_weekly';  // Daily
    case TOP_ITEMS_MONTHLY = 'top_monthly'; // Daily
    case TOP_ITEMS_YEARLY = 'top_yearly';  // Stable
    case TOP_ITEMS_ALLTIME = 'top_alltime'; // Stable
    case RATINGS = 'ratings';              // Stable
    case FAVORITES = 'favorites';          // Stable
    case ARTWORK = 'artwork';              // Static

    public function getCacheTTL(): int {
        return match($this) {
            self::NOW_PLAYING => 30,
            self::HISTORY => 300,           // 5 min
            self::TOP_ITEMS_WEEKLY => 3600, // 1 hour
            self::TOP_ITEMS_MONTHLY,
            self::TOP_ITEMS_YEARLY,
            self::TOP_ITEMS_ALLTIME,
            self::RATINGS,
            self::FAVORITES => 86400,       // 24 hours
            self::ARTWORK => 604800,        // 7 days
        };
    }

    public function getFallbackTTL(): int {
        return match($this) {
            self::NOW_PLAYING => 300,       // 5 min
            self::HISTORY => 3600,          // 1 hour
            self::TOP_ITEMS_WEEKLY => 86400, // 24 hours
            default => 604800,              // 7 days
        };
    }
}
```

---

## Cache Architecture

### Multi-Layer Cache

```
┌─────────────────────────────────────────────────────────────┐
│ Layer 1: In-Memory (per request)                            │
│ - Static array, survives multiple calls in same request     │
│ - TTL: Request lifetime                                     │
├─────────────────────────────────────────────────────────────┤
│ Layer 2: Primary Transient                                  │
│ - WordPress transients (or object cache if available)       │
│ - TTL: Based on DataType (30s - 7 days)                    │
├─────────────────────────────────────────────────────────────┤
│ Layer 3: Fallback Transient                                 │
│ - Long-lived backup, never overwritten with errors          │
│ - TTL: 3x Primary or DataType fallback                     │
├─────────────────────────────────────────────────────────────┤
│ Layer 4: HTML Persistence                                   │
│ - Last successful render output                             │
│ - TTL: 12 hours                                            │
│ - Used when all data sources fail                          │
└─────────────────────────────────────────────────────────────┘
```

### CacheManager

```php
class CacheManager {
    public function get(
        string $key,
        callable $fetcher,
        DataType $type,
        string $provider = 'generic'
    ): CacheResult {
        // 1. Check in-memory
        // 2. Check primary transient
        // 3. If expired but cooldown active, return fallback
        // 4. Fetch fresh data
        // 5. On success: update primary + fallback
        // 6. On failure: return fallback, set cooldown
        // 7. Return CacheResult with source info
    }
}

class CacheResult {
    public mixed $data;
    public CacheSource $source;  // MEMORY, PRIMARY, FALLBACK, FRESH
    public int $age;             // Seconds since cached
    public bool $isStale;
}
```

---

## Rate Limiting

### Per-Provider Limits

```php
class RateLimiter {
    private array $limits = [
        'lastfm' => ['requests' => 5, 'period' => 1],      // 5/sec
        'trakt'  => ['requests' => 1000, 'period' => 300], // 1000/5min
        'tmdb'   => ['requests' => 50, 'period' => 1],     // ~50/sec
    ];

    public function canRequest(string $provider): bool;
    public function recordRequest(string $provider): void;
    public function getWaitTime(string $provider): int;
}
```

### Cooldown System

```php
class CooldownManager {
    // Exponential backoff on errors
    // 429/503 → Immediate cooldown
    // 3 consecutive failures → Cooldown
    // Max cooldown: 30 minutes

    public function isInCooldown(string $provider): bool;
    public function setCooldown(string $provider, int $seconds): void;
    public function clearCooldown(string $provider): void;
}
```

---

## Shortcode System

### AbstractShortcode

```php
abstract class AbstractShortcode {
    protected string $tag;
    protected string $provider;
    protected DataType $dataType;

    // Common attributes for ALL shortcodes
    protected array $baseAttributes = [
        'layout'     => 'inline',  // inline, card, grid, list
        'show_image' => false,
        'image_size' => 'medium',  // small, medium, large
        'limit'      => 5,
        'max_length' => 0,         // 0 = no truncation
        'class'      => '',
        'id'         => '',
    ];

    abstract protected function getProviderAttributes(): array;
    abstract protected function fetchData(array $atts): mixed;
    abstract protected function getTemplateName(): string;

    final public function render(array $atts): string {
        $atts = $this->parseAttributes($atts);
        $data = $this->fetchData($atts);
        $html = $this->renderTemplate($data, $atts);
        return $this->wrapOutput($html, $data, $atts);
    }
}
```

### ShortcodeRegistry

```php
class ShortcodeRegistry {
    public function register(AbstractShortcode $shortcode): void;
    public function getAll(): array;
    public function getByProvider(string $provider): array;
    public function getForBuilder(): array;  // For admin UI
}
```

---

## Template System

### Template Hierarchy

```
1. Theme: {theme}/nowscrobbling/{template}.php
2. Plugin: {plugin}/templates/{template}.php
3. Fallback: Inline HTML
```

### TemplateEngine

```php
class TemplateEngine {
    public function render(string $template, array $data): string {
        $path = $this->locateTemplate($template);

        if (!$path) {
            return $this->renderFallback($template, $data);
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $path;
        return ob_get_clean();
    }

    private function locateTemplate(string $template): ?string {
        // Check theme first (allows customization)
        $theme_path = get_stylesheet_directory() . "/nowscrobbling/{$template}.php";
        if (file_exists($theme_path)) {
            return $theme_path;
        }

        // Then plugin
        $plugin_path = NOWSCROBBLING_PATH . "templates/{$template}.php";
        if (file_exists($plugin_path)) {
            return $plugin_path;
        }

        return null;
    }
}
```

---

## Attribution System

### Attribution Class

```php
class Attribution {
    public string $provider;
    public string $text;           // "Data from Last.fm"
    public string $url;            // Link to provider
    public ?string $logo;          // Logo path (if allowed)
    public bool $logoAllowed;      // Can we show logo?
    public ?string $disclaimer;    // Required legal text
}
```

### AttributionManager

```php
class AttributionManager {
    public function getForProviders(array $providers): array;
    public function renderFooter(): string;
    public function renderShortcode(): string;  // [nowscr_attribution]
}
```

---

## File Structure

```
nowscrobbling/
├── nowscrobbling.php                 # Bootstrap only (minimal)
│
├── src/
│   ├── Core/
│   │   ├── Plugin.php                # Main plugin class
│   │   ├── Autoloader.php            # PSR-4 autoloader
│   │   ├── Assets.php                # Script/style enqueue
│   │   ├── I18n.php                  # Translations
│   │   └── Migrator.php              # Version migrations
│   │
│   ├── Providers/
│   │   ├── ProviderInterface.php
│   │   ├── AbstractProvider.php
│   │   ├── ProviderRegistry.php
│   │   ├── LastFM/
│   │   │   ├── LastFMProvider.php
│   │   │   └── Endpoints.php
│   │   ├── Trakt/
│   │   │   ├── TraktProvider.php
│   │   │   └── Endpoints.php
│   │   └── TMDb/
│   │       ├── TMDbProvider.php
│   │       └── ImageResolver.php
│   │
│   ├── Cache/
│   │   ├── CacheManager.php
│   │   ├── CacheResult.php
│   │   ├── DataType.php
│   │   ├── Layers/
│   │   │   ├── MemoryLayer.php
│   │   │   ├── TransientLayer.php
│   │   │   └── FallbackLayer.php
│   │   ├── RateLimiter.php
│   │   └── CooldownManager.php
│   │
│   ├── Shortcodes/
│   │   ├── AbstractShortcode.php
│   │   ├── ShortcodeRegistry.php
│   │   ├── LastFM/
│   │   │   ├── IndicatorShortcode.php
│   │   │   ├── HistoryShortcode.php
│   │   │   ├── TopItemsShortcode.php
│   │   │   └── LovedTracksShortcode.php
│   │   └── Trakt/
│   │       ├── IndicatorShortcode.php
│   │       ├── HistoryShortcode.php
│   │       └── LastMediaShortcode.php
│   │
│   ├── Templates/
│   │   └── TemplateEngine.php
│   │
│   ├── Artwork/
│   │   ├── ArtworkManager.php
│   │   └── BackgroundManager.php
│   │
│   ├── Admin/
│   │   ├── AdminPage.php
│   │   ├── ShortcodeBuilder.php
│   │   └── Tabs/
│   │       ├── ProvidersTab.php
│   │       ├── DisplayTab.php
│   │       ├── CacheTab.php
│   │       └── DebugTab.php
│   │
│   ├── Ajax/
│   │   ├── AjaxRouter.php
│   │   └── Handlers/
│   │       ├── RenderHandler.php
│   │       └── AdminHandler.php
│   │
│   ├── Attribution/
│   │   ├── Attribution.php
│   │   └── AttributionManager.php
│   │
│   └── Utilities/
│       ├── HttpClient.php
│       ├── Logger.php
│       ├── Sanitizer.php
│       └── Helpers.php
│
├── templates/
│   ├── layouts/
│   │   ├── inline.php
│   │   ├── card.php
│   │   ├── grid.php
│   │   └── list.php
│   ├── shortcodes/
│   │   ├── lastfm/
│   │   │   ├── indicator.php
│   │   │   ├── history.php
│   │   │   ├── top-artists.php
│   │   │   ├── top-albums.php
│   │   │   ├── top-tracks.php
│   │   │   └── loved-tracks.php
│   │   └── trakt/
│   │       ├── indicator.php
│   │       ├── history.php
│   │       ├── last-movie.php
│   │       ├── last-show.php
│   │       └── last-episode.php
│   ├── components/
│   │   ├── artwork.php
│   │   ├── rating.php
│   │   ├── now-playing-badge.php
│   │   ├── rewatch-badge.php
│   │   └── skeleton.php
│   └── attribution.php
│
├── assets/
│   ├── css/
│   │   ├── public/
│   │   │   ├── variables.css
│   │   │   ├── core.css
│   │   │   ├── layouts/
│   │   │   │   ├── inline.css
│   │   │   │   ├── card.css
│   │   │   │   ├── grid.css
│   │   │   │   └── list.css
│   │   │   ├── components/
│   │   │   │   ├── artwork.css
│   │   │   │   ├── badges.css
│   │   │   │   └── rating.css
│   │   │   └── themes/
│   │   │       └── dark.css
│   │   └── admin/
│   │       ├── admin.css
│   │       └── builder.css
│   ├── js/
│   │   ├── public/
│   │   │   ├── nowscrobbling.js
│   │   │   └── modules/
│   │   │       ├── polling.js
│   │   │       └── hydration.js
│   │   └── admin/
│   │       ├── admin.js
│   │       └── builder.js
│   └── images/
│       ├── nowplaying.gif
│       ├── placeholder-album.svg
│       ├── placeholder-poster.svg
│       └── providers/
│           ├── trakt.svg
│           └── tmdb.svg
│
├── languages/
│   └── nowscrobbling.pot
│
└── docs/
    ├── ARCHITECTURE-v1.4.md
    └── PROVIDERS.md
```

---

## Adding a New Provider

Example: Adding Spotify

1. Create `src/Providers/Spotify/SpotifyProvider.php`:

```php
namespace NowScrobbling\Providers\Spotify;

use NowScrobbling\Providers\AbstractProvider;

class SpotifyProvider extends AbstractProvider {
    public function getId(): string {
        return 'spotify';
    }

    public function getName(): string {
        return 'Spotify';
    }

    public function getCapabilities(): array {
        return ['now_playing', 'top_items', 'recently_played'];
    }

    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'label' => __('Client ID', 'nowscrobbling'),
                'type' => 'text',
            ],
            'client_secret' => [
                'label' => __('Client Secret', 'nowscrobbling'),
                'type' => 'password',
            ],
        ];
    }

    // ... implement other methods
}
```

2. Register in Plugin bootstrap:

```php
$registry->register(new SpotifyProvider());
```

3. Create shortcodes in `src/Shortcodes/Spotify/`

4. Create templates in `templates/shortcodes/spotify/`

**No core code changes needed.**

---

## Hooks & Filters

### Actions

```php
// Provider lifecycle
do_action('nowscrobbling_provider_registered', $provider);
do_action('nowscrobbling_before_fetch', $provider_id, $endpoint);
do_action('nowscrobbling_after_fetch', $provider_id, $endpoint, $response);

// Cache events
do_action('nowscrobbling_cache_hit', $key, $source);
do_action('nowscrobbling_cache_miss', $key);
do_action('nowscrobbling_cache_fallback_used', $key, $age);

// Render events
do_action('nowscrobbling_before_render', $shortcode, $atts);
do_action('nowscrobbling_after_render', $shortcode, $html);
```

### Filters

```php
// Data modification
apply_filters('nowscrobbling_data', $data, $provider_id, $endpoint);
apply_filters('nowscrobbling_cache_ttl', $ttl, $data_type, $provider_id);

// Output modification
apply_filters('nowscrobbling_output', $html, $shortcode, $data, $atts);
apply_filters('nowscrobbling_template_path', $path, $template_name);
apply_filters('nowscrobbling_artwork_url', $url, $type, $item);

// Attributes
apply_filters('nowscrobbling_shortcode_atts', $atts, $shortcode, $defaults);
```

---

## WordPress.org Compliance

### Requirements Met

- [ ] GPL v2+ license header in all files
- [ ] No external resources loaded without user consent
- [ ] All strings translatable via `__()` / `_e()`
- [ ] Proper escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- [ ] Proper sanitization: `sanitize_text_field()`, etc.
- [ ] Nonce verification on all forms/AJAX
- [ ] Capability checks for admin functions
- [ ] No direct database queries (use WP APIs)
- [ ] Proper enqueueing of scripts/styles
- [ ] readme.txt in WordPress format
