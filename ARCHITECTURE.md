# NowScrobbling v2.0 - Architecture Documentation

> **Internal Reference Document**
> Last Updated: 2026-01-18
> Target Version: 2.0.0

---

## Table of Contents

1. [Overview](#overview)
2. [Design Decisions](#design-decisions)
3. [Architecture Summary](#architecture-summary)
4. [Directory Structure](#directory-structure)
5. [Core Concepts](#core-concepts)
6. [Cache Architecture](#cache-architecture)
7. [Environment Detection](#environment-detection)
8. [API Client System](#api-client-system)
9. [Shortcode System](#shortcode-system)
10. [Security Model](#security-model)
11. [REST API](#rest-api)
12. [Admin Interface](#admin-interface)
13. [JavaScript Architecture](#javascript-architecture)
14. [Visual Design System](#visual-design-system)
15. [Accessibility](#accessibility)
16. [Internationalization](#internationalization)
17. [Testing Strategy](#testing-strategy)
18. [Performance Targets](#performance-targets)
19. [Adding New Services](#adding-new-services)
20. [Migration Guide](#migration-guide)
21. [Debugging](#debugging)
22. [Security Checklist](#security-checklist)

---

## Overview

NowScrobbling is a WordPress plugin that displays Last.fm and Trakt.tv activity via shortcodes. Version 2.0 is a complete rewrite with modern architecture, intelligent caching, and privacy-first design.

### Philosophy

> **"Old data is better than error messages"**

A visitor seeing week-old data is better than seeing an error. The plugin gracefully degrades through multiple cache layers before ever showing an error.

### Key Principles

1. **Privacy-First**: All API calls are server-side. Users never connect to third-party servers directly.
2. **SSR-First**: Content renders on the server. JavaScript enhances, not enables.
3. **Graceful Degradation**: Multiple fallback layers ensure content is always displayed.
4. **Environment-Aware**: Automatically detects and leverages Cloudflare, LiteSpeed, Redis, etc.
5. **Extensible**: Abstract architecture allows adding new services (Spotify, Letterboxd, etc.)

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| PHP Version | 8.2+ | Modern features: readonly, enums, named arguments, fibers |
| Cloudflare | Header-based only | No API key requirement, works with free tier |
| Extensibility | Yes | Abstract API classes for future services |
| Multisite | No | Single-site focus, simpler implementation |
| JavaScript | Vanilla JS | No jQuery dependency, smaller bundle |
| API Style | REST API | Replaces admin-ajax.php, better caching, modern |

---

## Architecture Summary

### Tech Stack

- **PHP 8.2+** with PSR-4 autoloading (Composer)
- **Namespaces**: `NowScrobbling\*`
- **Vanilla JavaScript** (no jQuery dependency)
- **WordPress REST API** (replacing admin-ajax.php)
- **Multi-layer caching** with stale-while-revalidate
- **Extensible API architecture** (abstract base for future services)

### Improvements Over v1.3

| Issue | v1.3 | v2.0 |
|-------|------|------|
| Code size | 30,505 lines in api-functions.php | Modular classes ~200-300 lines each |
| Duplication | ~40% in shortcodes | Abstract base class, unified TopListShortcode |
| Security | Single nonce for all AJAX | Granular nonces per endpoint |
| Sanitization | Sometimes wrong order | Centralized Sanitizer class |
| Caching | Complex 800-line function | CacheManager with layer classes |
| Admin UI | 20+ flat settings | Tabbed interface (4 tabs) |
| JavaScript | jQuery-dependent | Vanilla JS with IntersectionObserver |

---

## Directory Structure

```
nowscrobbling/
├── nowscrobbling.php              # Bootstrap only (~50 lines)
├── composer.json                  # PSR-4 autoloading
├── uninstall.php                  # Clean removal
├── ARCHITECTURE.md                # This file
├── CONTRIBUTING.md                # Development guidelines
├── CHANGELOG.md                   # Version history
├── .editorconfig                  # Code style consistency
├── .phpcs.xml                     # PHP CodeSniffer config
├── .eslintrc.json                 # ESLint config
│
├── assets/
│   ├── css/
│   │   └── public.css             # Main stylesheet (~5KB gzipped)
│   ├── js/
│   │   └── nowscrobbling.js       # Vanilla JS client (~10KB gzipped)
│   └── images/
│       └── nowplaying.gif         # Now-playing animation
│
├── languages/
│   ├── nowscrobbling.pot          # Translation template
│   └── nowscrobbling-de_DE.po     # German translation
│
├── src/
│   ├── Plugin.php                 # Main orchestrator (singleton)
│   ├── Container.php              # Lightweight DI container
│   │
│   ├── Core/
│   │   ├── Bootstrap.php          # Hook registration
│   │   ├── Activator.php          # Activation logic
│   │   ├── Deactivator.php        # Deactivation logic
│   │   ├── Migrator.php           # v1.3 → v2.0 migration
│   │   └── Assets.php             # Conditional asset loading
│   │
│   ├── Cache/
│   │   ├── CacheManager.php       # Multi-layer orchestration
│   │   ├── CacheLayerInterface.php
│   │   ├── Layers/
│   │   │   ├── InMemoryLayer.php  # Per-request static cache
│   │   │   ├── TransientLayer.php # WordPress transients
│   │   │   ├── FallbackLayer.php  # 7-day emergency cache
│   │   │   └── ETagLayer.php      # HTTP conditional requests
│   │   ├── Environment/
│   │   │   ├── EnvironmentDetector.php
│   │   │   ├── EnvironmentAdapterInterface.php
│   │   │   ├── CloudflareAdapter.php
│   │   │   ├── LiteSpeedAdapter.php
│   │   │   ├── NginxAdapter.php
│   │   │   ├── ObjectCacheAdapter.php
│   │   │   └── DefaultAdapter.php
│   │   └── Lock/
│   │       └── ThunderingHerdLock.php
│   │
│   ├── Api/
│   │   ├── ApiClientInterface.php
│   │   ├── AbstractApiClient.php  # ETag, rate limiting, retry
│   │   ├── LastFmClient.php
│   │   ├── TraktClient.php
│   │   ├── RateLimiter.php
│   │   └── Response/
│   │       ├── ApiResponse.php    # Success DTO
│   │       └── ErrorResponse.php  # Error DTO
│   │
│   ├── Shortcodes/
│   │   ├── ShortcodeManager.php   # Registration and routing
│   │   ├── AbstractShortcode.php  # Base class (DRY!)
│   │   ├── LastFm/
│   │   │   ├── IndicatorShortcode.php
│   │   │   ├── HistoryShortcode.php
│   │   │   ├── TopListShortcode.php    # Unified: artists/albums/tracks
│   │   │   └── LovedTracksShortcode.php
│   │   ├── Trakt/
│   │   │   ├── IndicatorShortcode.php
│   │   │   ├── HistoryShortcode.php
│   │   │   └── LastMediaShortcode.php  # Unified: movie/show/episode
│   │   └── Renderer/
│   │       ├── OutputRenderer.php
│   │       ├── HashGenerator.php
│   │       └── Templates/
│   │           ├── bubble.php
│   │           ├── indicator.php
│   │           └── list.php
│   │
│   ├── Admin/
│   │   ├── AdminController.php
│   │   ├── SettingsManager.php
│   │   ├── Tabs/
│   │   │   ├── TabInterface.php
│   │   │   ├── CredentialsTab.php
│   │   │   ├── CachingTab.php
│   │   │   ├── DisplayTab.php
│   │   │   └── DiagnosticsTab.php
│   │   └── Views/
│   │       ├── settings-page.php
│   │       └── partials/
│   │           ├── tab-credentials.php
│   │           ├── tab-caching.php
│   │           ├── tab-display.php
│   │           └── tab-diagnostics.php
│   │
│   ├── Rest/
│   │   ├── RestController.php
│   │   ├── ShortcodeEndpoint.php  # GET /wp-json/nowscrobbling/v1/render/{shortcode}
│   │   ├── StatusEndpoint.php     # GET /wp-json/nowscrobbling/v1/status
│   │   └── CacheEndpoint.php      # POST /wp-json/nowscrobbling/v1/cache/clear
│   │
│   ├── Cron/
│   │   ├── CronManager.php
│   │   └── BackgroundRefresh.php
│   │
│   ├── Security/
│   │   ├── NonceManager.php       # Granular nonces per endpoint
│   │   ├── Sanitizer.php          # Centralized: unslash → sanitize
│   │   └── Capability.php         # Permission checks
│   │
│   └── Support/
│       ├── Logger.php             # Debug logging with rotation
│       ├── Metrics.php            # Performance metrics
│       ├── TextTruncator.php      # Multibyte-safe truncation
│       └── DateFormatter.php      # Timezone-aware dates
│
├── templates/                     # Theme-overridable templates
│   ├── bubble.php
│   └── now-playing.php
│
└── tests/
    ├── Unit/
    │   ├── Cache/
    │   ├── Api/
    │   └── Security/
    └── Integration/
        └── Shortcodes/
```

---

## Core Concepts

### 1. Service Container

Lightweight dependency injection in `src/Container.php`:

```php
<?php
namespace NowScrobbling;

class Container {
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $resolved = [];

    public static function getInstance(): self {
        return self::$instance ??= new self();
    }

    public function singleton(string $abstract, callable $factory): void {
        $this->bindings[$abstract] = fn() =>
            $this->resolved[$abstract] ??= $factory($this);
    }

    public function make(string $abstract): mixed {
        if (!isset($this->bindings[$abstract])) {
            throw new \RuntimeException("No binding for: $abstract");
        }
        return $this->bindings[$abstract]($this);
    }
}
```

**Key Features:**
- No external dependencies
- Singleton registration
- Factory bindings
- Lazy instantiation

### 2. Plugin Bootstrap

Minimal `nowscrobbling.php` (~50 lines):

```php
<?php
/**
 * Plugin Name: NowScrobbling
 * Version: 2.0.0
 * Requires PHP: 8.2
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/vendor/autoload.php';

define('NOWSCROBBLING_VERSION', '2.0.0');
define('NOWSCROBBLING_FILE', __FILE__);
define('NOWSCROBBLING_PATH', plugin_dir_path(__FILE__));
define('NOWSCROBBLING_URL', plugin_dir_url(__FILE__));

// Boot plugin after WordPress loads
add_action('plugins_loaded', fn() =>
    NowScrobbling\Plugin::getInstance()->boot()
, 10);

register_activation_hook(__FILE__, [NowScrobbling\Core\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [NowScrobbling\Core\Deactivator::class, 'deactivate']);
```

### 3. Hook Registration Pattern

Hooks are registered **outside constructors** for testability:

```php
<?php
namespace NowScrobbling;

class Plugin {
    private bool $booted = false;

    public function boot(): void {
        if ($this->booted) return;

        $this->registerHooks();  // All hooks here
        $this->booted = true;
    }

    private function registerHooks(): void {
        add_action('init', [$this, 'onInit']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        // ... more hooks
    }
}
```

---

## Cache Architecture

### Multi-Layer Strategy

```
Request Flow:
┌─────────────────────────────────────────────────────────────────┐
│                         Page Request                             │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 1. In-Memory Cache (Static per-request)                         │
│    └─ HIT? → Return immediately (0ms)                           │
└────────────────────────────┬────────────────────────────────────┘
                             │ MISS
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Primary Transient (Object Cache/MySQL, 1-15 min)             │
│    └─ HIT? → Store in memory, return (~1-5ms)                   │
└────────────────────────────┬────────────────────────────────────┘
                             │ MISS
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Check Fallback Transient (7 days)                            │
│    └─ EXISTS? → Serve stale, continue to refresh in background  │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Thundering Herd Lock                                          │
│    └─ LOCKED? → Return fallback if exists, else wait            │
└────────────────────────────┬────────────────────────────────────┘
                             │ ACQUIRED
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Rate Limit Check                                              │
│    └─ THROTTLED? → Return fallback                              │
└────────────────────────────┬────────────────────────────────────┘
                             │ OK
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. API Request with ETag                                         │
│    └─ 304 Not Modified? → Renew cache TTL, return existing      │
│    └─ Success? → Store all layers, return fresh                 │
│    └─ Error? → Return fallback if exists                        │
└─────────────────────────────────────────────────────────────────┘
```

### Cache Layer Interface

```php
<?php
namespace NowScrobbling\Cache;

interface CacheLayerInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function getAge(string $key): ?int;  // For stale-while-revalidate
}
```

### Stale-While-Revalidate Pattern

```php
public function getOrSet(
    string $key,
    callable $callback,
    int $primaryTtl = 300,
    array $options = []
): mixed {
    // 1. Try fast caches first
    if ($data = $this->layers['memory']->get($key)) {
        return $data;
    }

    // 2. Check for stale data we can serve immediately
    $fallback = $this->layers['fallback']->get($key);
    $hasFallback = $fallback !== null;

    // 3. Acquire lock (prevent thundering herd)
    if (!$this->lock->acquire($key)) {
        return $hasFallback ? $fallback : $this->wait($key);
    }

    try {
        // 4. Fetch fresh data
        $data = $callback();

        // 5. On error, return fallback
        if ($this->isError($data) && $hasFallback) {
            return $fallback;
        }

        // 6. Store in all layers
        $this->storeAll($key, $data, $primaryTtl);
        return $data;

    } finally {
        $this->lock->release($key);
    }
}
```

### Thundering Herd Lock

Prevents multiple concurrent API calls for the same data:

```php
<?php
namespace NowScrobbling\Cache\Lock;

class ThunderingHerdLock {
    private const LOCK_TTL = 30;  // Max lock duration

    public function acquire(string $key): bool {
        $lockKey = 'ns_lock_' . md5($key);

        // Use atomic set-if-not-exists
        if (wp_using_ext_object_cache()) {
            return wp_cache_add($lockKey, time(), '', self::LOCK_TTL);
        }

        // Fallback: transient-based lock
        if (get_transient($lockKey)) {
            return false;
        }
        set_transient($lockKey, time(), self::LOCK_TTL);
        return true;
    }

    public function release(string $key): void {
        $lockKey = 'ns_lock_' . md5($key);

        if (wp_using_ext_object_cache()) {
            wp_cache_delete($lockKey);
        } else {
            delete_transient($lockKey);
        }
    }
}
```

---

## Environment Detection

### Auto-Detection Flow

```
Hosting Environment Detection:
┌────────────────────────────────────────────────────────────────┐
│                      Request Received                           │
└───────────────────────────┬────────────────────────────────────┘
                            │
                            ▼
                ┌───────────────────────┐
                │ CF-Ray header exists? │
                └───────────┬───────────┘
                    YES     │     NO
                    ▼       │     ▼
    ┌───────────────────┐   │   ┌────────────────────┐
    │ Cloudflare Mode   │   │   │ LiteSpeed detected?│
    │ - CDN-Cache-Ctrl  │   │   └──────────┬─────────┘
    │ - Cache-Tag       │   │        YES   │    NO
    └───────────────────┘   │        ▼     │    ▼
                            │   ┌──────────┴────────┐
                            │   │ LSCache Mode      │
                            │   │ - X-LiteSpeed-*   │
                            │   └───────────────────┘
                            │              │
                            │              ▼
                            │   ┌────────────────────┐
                            │   │ Object Cache?      │
                            │   │ Redis/Memcached?   │
                            │   └──────────┬─────────┘
                            │              │
                            │              ▼
                            │   ┌────────────────────┐
                            │   │ Default Mode       │
                            │   │ - Browser cache    │
                            │   │ - MySQL transients │
                            │   └────────────────────┘
```

### Environment Detector

```php
<?php
namespace NowScrobbling\Cache\Environment;

class EnvironmentDetector {
    private ?string $detectedEnv = null;

    public function detect(): string {
        return $this->detectedEnv ??= match(true) {
            $this->isCloudflare() => 'cloudflare',
            $this->isLiteSpeed() => 'litespeed',
            $this->isNginxFastCGI() => 'nginx',
            $this->hasObjectCache() => 'object_cache',
            default => 'default',
        };
    }

    public function isCloudflare(): bool {
        return isset($_SERVER['HTTP_CF_RAY'])
            || isset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function isLiteSpeed(): bool {
        return isset($_SERVER['SERVER_SOFTWARE'])
            && stripos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false;
    }

    public function hasObjectCache(): bool {
        return wp_using_ext_object_cache();
    }

    public function getObjectCacheType(): ?string {
        if (!$this->hasObjectCache()) return null;

        $class = get_class($GLOBALS['wp_object_cache'] ?? new \stdClass());

        return match(true) {
            str_contains(strtolower($class), 'redis') => 'redis',
            str_contains(strtolower($class), 'memcache') => 'memcached',
            str_contains(strtolower($class), 'apcu') => 'apcu',
            default => 'unknown',
        };
    }
}
```

### Cloudflare Adapter

```php
<?php
namespace NowScrobbling\Cache\Environment;

class CloudflareAdapter implements EnvironmentAdapterInterface {
    public function setHeaders(int $maxAge, bool $isPrivate = false): void {
        if (headers_sent()) return;

        $directive = $isPrivate ? 'private' : 'public';

        // Standard Cache-Control with stale-while-revalidate
        header(sprintf(
            'Cache-Control: %s, max-age=%d, stale-while-revalidate=%d',
            $directive,
            $maxAge,
            $maxAge * 2
        ));

        // Cloudflare-specific CDN cache
        if (!$isPrivate) {
            header('CDN-Cache-Control: max-age=' . ($maxAge * 2));
        }

        // Cache tags for targeted purging
        header('Cache-Tag: nowscrobbling');
        header('Vary: Accept-Encoding', false);
    }

    public function getName(): string {
        return 'Cloudflare';
    }
}
```

---

## API Client System

### Abstract Base Client

```php
<?php
namespace NowScrobbling\Api;

abstract class AbstractApiClient {
    protected readonly CacheManager $cache;
    protected readonly RateLimiter $rateLimiter;
    protected readonly string $service;

    abstract protected function getBaseUrl(): string;
    abstract protected function getDefaultHeaders(): array;
    abstract protected function getDefaultTtl(): int;

    public function __construct(CacheManager $cache) {
        $this->cache = $cache;
        $this->rateLimiter = new RateLimiter($this->service);
    }

    protected function fetch(
        string $endpoint,
        array $params = [],
        array $options = []
    ): ApiResponse {
        // Check rate limit
        if ($this->rateLimiter->shouldThrottle()) {
            return ApiResponse::rateLimited();
        }

        $url = $this->buildUrl($endpoint, $params);
        $cacheKey = $this->buildCacheKey($endpoint, $params);

        return $this->cache->getOrSet(
            $cacheKey,
            fn() => $this->executeRequest($url, $options),
            $options['ttl'] ?? $this->getDefaultTtl(),
            ['service' => $this->service]
        );
    }

    protected function executeRequest(string $url, array $options = []): array {
        $args = [
            'timeout' => $options['timeout'] ?? 10,
            'headers' => array_merge(
                $this->getDefaultHeaders(),
                $this->getETagHeader($url),
                $options['headers'] ?? []
            ),
        ];

        $response = wp_safe_remote_get($url, $args);

        if (is_wp_error($response)) {
            $this->rateLimiter->recordError();
            return ['error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        // Handle 304 Not Modified
        if ($code === 304) {
            return ['__ns_not_modified' => true];
        }

        // Handle errors
        if ($code >= 400) {
            $this->rateLimiter->recordError($code);
            return ['error' => "HTTP $code"];
        }

        // Store new ETag
        $this->storeETag($url, $response);
        $this->rateLimiter->recordSuccess();

        return json_decode(wp_remote_retrieve_body($response), true) ?? [];
    }
}
```

### Last.fm Client Example

```php
<?php
namespace NowScrobbling\Api;

class LastFmClient extends AbstractApiClient {
    protected readonly string $service = 'lastfm';

    protected function getBaseUrl(): string {
        return 'https://ws.audioscrobbler.com/2.0/';
    }

    protected function getDefaultHeaders(): array {
        return [
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
        ];
    }

    protected function getDefaultTtl(): int {
        return (int) get_option('ns_lastfm_cache_ttl', 60);
    }

    public function getRecentTracks(int $limit = 5): ApiResponse {
        return $this->fetch('getrecenttracks', [
            'method' => 'user.getrecenttracks',
            'user' => get_option('ns_lastfm_user'),
            'api_key' => get_option('ns_lastfm_api_key'),
            'format' => 'json',
            'limit' => $limit,
        ], ['ttl' => 30]);  // Shorter TTL for now-playing
    }

    public function getTopItems(
        string $type,  // 'artists', 'albums', 'tracks'
        string $period = '7day',
        int $limit = 5
    ): ApiResponse {
        return $this->fetch("top{$type}", [
            'method' => "user.gettop{$type}",
            'user' => get_option('ns_lastfm_user'),
            'api_key' => get_option('ns_lastfm_api_key'),
            'format' => 'json',
            'period' => $period,
            'limit' => $limit,
        ]);
    }
}
```

---

## Shortcode System

### Abstract Base Class

Eliminates ~40% code duplication:

```php
<?php
namespace NowScrobbling\Shortcodes;

abstract class AbstractShortcode {
    protected readonly OutputRenderer $renderer;
    protected readonly HashGenerator $hasher;

    abstract public function getTag(): string;
    abstract protected function getData(array $atts): mixed;
    abstract protected function formatOutput(mixed $data, array $atts): string;
    abstract protected function getEmptyMessage(): string;

    public function register(): void {
        add_shortcode($this->getTag(), $this->render(...));
    }

    public function render(array $atts = []): string {
        $atts = shortcode_atts($this->getDefaultAttributes(), $atts);

        try {
            $data = $this->getData($atts);

            if ($this->isEmpty($data)) {
                return $this->renderEmpty($atts);
            }

            $html = $this->formatOutput($data, $atts);
            $hash = $this->hasher->generate($data);
            $isNowPlaying = $this->isNowPlaying($data);

            return $this->renderer->wrap(
                shortcode: $this->getTag(),
                html: $html,
                hash: $hash,
                nowPlaying: $isNowPlaying,
                attrs: $atts
            );
        } catch (\Throwable $e) {
            do_action('nowscrobbling_shortcode_error', $e, $this->getTag());
            return $this->renderEmpty($atts);
        }
    }

    protected function getDefaultAttributes(): array {
        return ['max_length' => 45];
    }

    protected function isNowPlaying(mixed $data): bool {
        return false;
    }
}
```

### Unified TopList Shortcode

One class handles artists, albums, and tracks:

```php
<?php
namespace NowScrobbling\Shortcodes\LastFm;

class TopListShortcode extends AbstractShortcode {
    public function __construct(
        OutputRenderer $renderer,
        private readonly LastFmClient $client,
        private readonly string $type  // 'artists', 'albums', 'tracks'
    ) {
        parent::__construct($renderer);
    }

    public function getTag(): string {
        return "nowscr_lastfm_top_{$this->type}";
    }

    protected function getData(array $atts): mixed {
        return $this->client->getTopItems(
            $this->type,
            $atts['period'],
            $atts['limit']
        );
    }

    protected function formatOutput(mixed $data, array $atts): string {
        $items = $data["top{$this->type}"][$this->getSingular()] ?? [];

        return implode(', ', array_map(
            fn($item) => $this->formatItem($item, $atts),
            array_slice($items, 0, $atts['limit'])
        ));
    }

    private function getSingular(): string {
        return rtrim($this->type, 's');  // 'artists' → 'artist'
    }
}
```

### Output Wrapper

```php
<?php
namespace NowScrobbling\Shortcodes\Renderer;

class OutputRenderer {
    public function wrap(
        string $shortcode,
        string $html,
        string $hash,
        bool $nowPlaying,
        array $attrs,
        string $tag = 'span'
    ): string {
        $classes = ['nowscrobbling', "ns-{$shortcode}"];
        if ($nowPlaying) {
            $classes[] = 'ns-nowplaying';
        }

        return sprintf(
            '<%1$s class="%2$s" data-nowscrobbling-shortcode="%3$s" data-ns-hash="%4$s"%5$s data-ns-attrs="%6$s">%7$s</%1$s>',
            esc_attr($tag),
            esc_attr(implode(' ', $classes)),
            esc_attr($shortcode),
            esc_attr($hash),
            $nowPlaying ? ' data-ns-nowplaying="1"' : '',
            esc_attr(wp_json_encode($attrs)),
            $html
        );
    }
}
```

---

## Security Model

### Granular Nonce Manager

```php
<?php
namespace NowScrobbling\Security;

class NonceManager {
    private const PREFIX = 'ns_';

    public function create(string $action): string {
        return wp_create_nonce(self::PREFIX . $action);
    }

    public function verify(string $nonce, string $action): bool {
        return wp_verify_nonce($nonce, self::PREFIX . $action) !== false;
    }

    /**
     * Get all nonces for JavaScript
     */
    public function getForJs(): array {
        return [
            'render' => $this->create('render'),
            'refresh' => $this->create('refresh'),
            'cache_clear' => $this->create('cache_clear'),
        ];
    }
}
```

### Centralized Sanitizer

**Critical: Correct order is `wp_unslash()` → `sanitize_*()`**

```php
<?php
namespace NowScrobbling\Security;

class Sanitizer {
    /**
     * Sanitize input with correct order
     */
    public static function input(string $key, string $type = 'text', string $method = 'POST'): mixed {
        $source = match($method) {
            'GET' => $_GET,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST,
            default => $_POST,
        };

        if (!isset($source[$key])) {
            return null;
        }

        // 1. UNSLASH FIRST (WordPress adds magic quotes)
        $unslashed = wp_unslash($source[$key]);

        // 2. THEN SANITIZE based on type
        return match($type) {
            'text' => sanitize_text_field($unslashed),
            'textarea' => sanitize_textarea_field($unslashed),
            'int' => absint($unslashed),
            'bool' => filter_var($unslashed, FILTER_VALIDATE_BOOLEAN),
            'key' => sanitize_key($unslashed),
            'email' => sanitize_email($unslashed),
            'url' => esc_url_raw($unslashed),
            'html' => wp_kses_post($unslashed),
            'json' => self::sanitizeJson($unslashed),
            default => sanitize_text_field($unslashed),
        };
    }

    private static function sanitizeJson(string $json): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        array_walk_recursive($decoded, function(&$value) {
            if (is_string($value)) {
                $value = sanitize_text_field($value);
            }
        });

        return $decoded;
    }
}
```

---

## REST API

### Controller Registration

```php
<?php
namespace NowScrobbling\Rest;

class RestController {
    private const NAMESPACE = 'nowscrobbling/v1';

    public function register(): void {
        // Public: Render shortcode
        register_rest_route(self::NAMESPACE, '/render/(?P<shortcode>[a-z_]+)', [
            'methods' => 'GET',
            'callback' => $this->renderShortcode(...),
            'permission_callback' => '__return_true',
            'args' => [
                'shortcode' => [
                    'required' => true,
                    'validate_callback' => $this->validateShortcode(...),
                ],
            ],
        ]);

        // Admin: Clear cache
        register_rest_route(self::NAMESPACE, '/cache/clear', [
            'methods' => 'POST',
            'callback' => $this->clearCache(...),
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Admin: Status
        register_rest_route(self::NAMESPACE, '/status', [
            'methods' => 'GET',
            'callback' => $this->getStatus(...),
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function renderShortcode(\WP_REST_Request $request): \WP_REST_Response {
        $shortcode = $request->get_param('shortcode');
        $html = do_shortcode("[{$shortcode}]");

        // Extract hash from output
        preg_match('/data-ns-hash="([^"]+)"/', $html, $matches);

        return new \WP_REST_Response([
            'html' => $html,
            'hash' => $matches[1] ?? md5($html),
            'ts' => time(),
        ]);
    }

    private function validateShortcode(string $shortcode): bool {
        $allowed = [
            'nowscr_lastfm_indicator',
            'nowscr_lastfm_history',
            'nowscr_lastfm_top_artists',
            'nowscr_lastfm_top_albums',
            'nowscr_lastfm_top_tracks',
            'nowscr_lastfm_lovedtracks',
            'nowscr_trakt_indicator',
            'nowscr_trakt_history',
            'nowscr_trakt_last_movie',
            'nowscr_trakt_last_show',
            'nowscr_trakt_last_episode',
        ];

        return in_array($shortcode, $allowed, true);
    }
}
```

---

## Admin Interface

### Tabbed Settings

```php
<?php
namespace NowScrobbling\Admin;

class SettingsManager {
    private array $tabs;

    public function __construct() {
        $this->tabs = [
            new Tabs\CredentialsTab(),
            new Tabs\CachingTab(),
            new Tabs\DisplayTab(),
            new Tabs\DiagnosticsTab(),
        ];
    }

    public function registerSettings(): void {
        foreach ($this->tabs as $tab) {
            $tab->registerSettings();
        }
    }

    public function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'nowscrobbling'));
        }

        $activeTab = Sanitizer::input('tab', 'key', 'GET') ?? 'credentials';

        include NOWSCROBBLING_PATH . 'src/Admin/Views/settings-page.php';
    }
}
```

### Tab Interface

```php
<?php
namespace NowScrobbling\Admin\Tabs;

interface TabInterface {
    public function getId(): string;
    public function getTitle(): string;
    public function getIcon(): string;
    public function render(): void;
    public function registerSettings(): void;
}
```

---

## JavaScript Architecture

### Vanilla JS Client

```javascript
/**
 * NowScrobbling v2.0 - Vanilla JS Client
 */
class NowScrobbling {
    #apiBase;
    #nonces;
    #polling;
    #observer;
    #elements = new Map();

    constructor(config) {
        this.#apiBase = config.restUrl;
        this.#nonces = config.nonces;
        this.#polling = {
            baseInterval: 30000,
            maxInterval: 300000,
            backoffMultiplier: 2,
            ...config.polling
        };
    }

    init() {
        // Lazy load with IntersectionObserver
        this.#observer = new IntersectionObserver(
            entries => this.#onIntersect(entries),
            { rootMargin: '200px 0px' }
        );

        document.querySelectorAll('[data-nowscrobbling-shortcode]')
            .forEach(el => this.#observer.observe(el));

        // Visibility change handler
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.refreshAll();
            }
        });
    }

    async #fetch(shortcode, hash = null) {
        const url = new URL(
            `${this.#apiBase}/render/${shortcode}`,
            window.location.origin
        );

        if (hash) url.searchParams.set('hash', hash);

        const response = await fetch(url, {
            headers: { 'X-WP-Nonce': this.#nonces.render }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    #updateElement(el, data) {
        if (data.hash === el.dataset.nsHash) return;

        // Smooth height transition
        const oldHeight = el.offsetHeight;
        el.innerHTML = this.#extractContent(data.html);
        el.dataset.nsHash = data.hash;

        el.style.height = `${oldHeight}px`;
        el.style.transition = 'height 250ms ease';

        requestAnimationFrame(() => {
            el.style.height = `${el.scrollHeight}px`;
            setTimeout(() => {
                el.style.height = '';
                el.style.transition = '';
            }, 250);
        });
    }

    #startPolling(el) {
        const shortcode = el.dataset.nowscrobblingShortcode;
        let interval = this.#polling.baseInterval;
        let fails = 0;

        const poll = async () => {
            if (!document.body.contains(el)) return;
            if (document.visibilityState !== 'visible') return;

            try {
                const data = await this.#fetch(shortcode, el.dataset.nsHash);
                this.#updateElement(el, data);
                fails = 0;
                interval = this.#polling.baseInterval;
            } catch {
                fails++;
                interval = Math.min(
                    interval * this.#polling.backoffMultiplier,
                    this.#polling.maxInterval
                );
            }

            if (el.dataset.nsNowplaying === '1') {
                setTimeout(poll, interval);
            }
        };

        setTimeout(poll, interval);
    }

    refreshAll() {
        this.#elements.forEach((_, el) => {
            if (el.dataset.nowscrobblingShortcode) {
                this.#initElement(el);
            }
        });
    }
}

// Auto-init
document.addEventListener('DOMContentLoaded', () => {
    if (typeof nowscrobblingConfig !== 'undefined') {
        window.nowscrobbling = new NowScrobbling(nowscrobblingConfig);
        window.nowscrobbling.init();
    }
});
```

---

## Visual Design System

### CSS Custom Properties

```css
:root {
  /* Colors - WCAG AA compliant */
  --ns-primary: #1a73e8;
  --ns-primary-hover: #1557b0;
  --ns-text: #202124;
  --ns-text-muted: #5f6368;
  --ns-surface: #ffffff;
  --ns-border: #dadce0;
  --ns-error: #d93025;

  /* Service-specific */
  --ns-lastfm: #d51007;
  --ns-trakt: #ed1c24;

  /* Typography */
  --ns-font-size-sm: 0.8125rem;    /* 13px minimum */
  --ns-font-size-base: 0.875rem;   /* 14px */
  --ns-line-height: 1.5;

  /* Spacing */
  --ns-space-xs: 4px;
  --ns-space-sm: 8px;
  --ns-space-md: 16px;

  /* Animation */
  --ns-transition: 200ms ease-out;
  --ns-radius: 6px;
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
  :root {
    --ns-primary: #8ab4f8;
    --ns-text: #e8eaed;
    --ns-text-muted: #9aa0a6;
    --ns-surface: #202124;
    --ns-border: #3c4043;
  }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

### Component Styles

```css
/* Bubble */
.bubble {
  display: inline-flex;
  align-items: center;
  padding: var(--ns-space-xs) var(--ns-space-sm);
  background: var(--ns-surface);
  border: 1px solid var(--ns-border);
  border-radius: var(--ns-radius);
  font-size: var(--ns-font-size-sm);
  color: var(--ns-text);
  text-decoration: none;
  transition: all var(--ns-transition);
  min-height: 44px;  /* Touch target */
}

.bubble:hover {
  border-color: var(--ns-primary);
  color: var(--ns-primary);
}

.bubble:focus-visible {
  outline: 2px solid var(--ns-primary);
  outline-offset: 2px;
}

/* Now playing indicator */
.ns-nowplaying::before {
  content: '';
  display: inline-block;
  width: 12px;
  height: 12px;
  margin-right: var(--ns-space-xs);
  background: url('nowplaying.gif') center/contain no-repeat;
}

/* Loading skeleton */
.nowscrobbling-loading {
  position: relative;
  overflow: hidden;
}

.nowscrobbling-loading::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    90deg,
    transparent,
    var(--ns-surface),
    transparent
  );
  animation: ns-shimmer 1.5s infinite;
}

@keyframes ns-shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
```

---

## Accessibility

### Requirements

- **WCAG AA compliance** (4.5:1 color contrast)
- **Keyboard navigation** (all interactive elements focusable)
- **Screen reader support** (ARIA labels, live regions)
- **Reduced motion** (respects `prefers-reduced-motion`)
- **Focus management** (after AJAX updates)

### ARIA Implementation

```html
<!-- Now playing with live region -->
<span
  class="nowscrobbling ns-nowplaying"
  role="status"
  aria-live="polite"
  aria-label="Currently playing: Artist - Track"
>
  <span class="visually-hidden">Currently playing:</span>
  Artist - Track
</span>

<!-- Loading state -->
<span
  class="nowscrobbling nowscrobbling-loading"
  aria-busy="true"
  aria-label="Loading music activity..."
>
  Loading...
</span>
```

### Focus Management

```javascript
#updateElement(el, data) {
  const hadFocus = el.contains(document.activeElement);
  const focusedIndex = this.#getFocusedLinkIndex(el);

  // Update content
  el.innerHTML = this.#extractContent(data.html);

  // Restore focus
  if (hadFocus) {
    const links = el.querySelectorAll('a');
    const targetLink = links[focusedIndex] || links[0];
    targetLink?.focus();
  }
}
```

---

## Internationalization

### Translation Setup

```php
// Load text domain
add_action('init', function() {
    load_plugin_textdomain(
        'nowscrobbling',
        false,
        'nowscrobbling/languages'
    );
});

// Translatable strings
__('Currently playing', 'nowscrobbling');
__('Last played', 'nowscrobbling');
__('No recent tracks found.', 'nowscrobbling');
_n('%d track', '%d tracks', $count, 'nowscrobbling');
```

### Date/Time Localization

```php
<?php
namespace NowScrobbling\Support;

class DateFormatter {
    public function relative(int $timestamp): string {
        $diff = time() - $timestamp;

        return match(true) {
            $diff < 60 => __('just now', 'nowscrobbling'),
            $diff < 3600 => sprintf(
                _n('%d minute ago', '%d minutes ago', (int)($diff / 60), 'nowscrobbling'),
                (int)($diff / 60)
            ),
            $diff < 86400 => sprintf(
                _n('%d hour ago', '%d hours ago', (int)($diff / 3600), 'nowscrobbling'),
                (int)($diff / 3600)
            ),
            default => wp_date(get_option('date_format'), $timestamp),
        };
    }
}
```

---

## Testing Strategy

### PHPUnit (PHP)

```php
<?php
namespace NowScrobbling\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use NowScrobbling\Cache\Layers\InMemoryLayer;

class InMemoryLayerTest extends TestCase {
    private InMemoryLayer $layer;

    protected function setUp(): void {
        $this->layer = new InMemoryLayer();
    }

    public function testGetReturnsNullForMissingKey(): void {
        $this->assertNull($this->layer->get('nonexistent'));
    }

    public function testSetAndGet(): void {
        $this->layer->set('key', 'value', 300);
        $this->assertEquals('value', $this->layer->get('key'));
    }

    public function testGetAgeReturnsCorrectAge(): void {
        $this->layer->set('key', 'value', 300);
        sleep(1);
        $this->assertGreaterThanOrEqual(1, $this->layer->getAge('key'));
    }
}
```

### Jest (JavaScript)

```javascript
import { NowScrobbling } from './nowscrobbling';

describe('NowScrobbling', () => {
  let ns;

  beforeEach(() => {
    ns = new NowScrobbling({
      restUrl: '/wp-json/nowscrobbling/v1',
      nonces: { render: 'test-nonce' }
    });
  });

  test('initializes without errors', () => {
    expect(() => ns.init()).not.toThrow();
  });

  test('refreshAll calls fetch for each element', async () => {
    // Setup mock elements
    document.body.innerHTML = `
      <span data-nowscrobbling-shortcode="nowscr_lastfm_indicator"></span>
    `;

    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ html: 'test', hash: 'abc123' })
    });

    ns.init();
    await ns.refreshAll();

    expect(fetch).toHaveBeenCalled();
  });
});
```

---

## Performance Targets

| Metric | Target | Measurement |
|--------|--------|-------------|
| Cache hit latency | < 5ms | Time to return from in-memory/transient cache |
| Cache miss latency | < 2000ms | Time for API call + response |
| JS bundle size | < 10KB gzipped | `gzip -c nowscrobbling.js \| wc -c` |
| CSS bundle size | < 5KB gzipped | `gzip -c public.css \| wc -c` |
| First contentful paint | < 100ms | SSR renders immediately |
| Time to interactive | < 500ms | AJAX hydration complete |
| Memory usage | < 2MB | PHP memory_get_peak_usage() |

---

## Adding New Services

### Step 1: Create API Client

```php
<?php
// src/Api/SpotifyClient.php
namespace NowScrobbling\Api;

class SpotifyClient extends AbstractApiClient {
    protected readonly string $service = 'spotify';

    protected function getBaseUrl(): string {
        return 'https://api.spotify.com/v1/';
    }

    protected function getDefaultHeaders(): array {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Accept' => 'application/json',
        ];
    }

    public function getCurrentlyPlaying(): ApiResponse {
        return $this->fetch('me/player/currently-playing');
    }
}
```

### Step 2: Create Shortcodes

```php
<?php
// src/Shortcodes/Spotify/NowPlayingShortcode.php
namespace NowScrobbling\Shortcodes\Spotify;

class NowPlayingShortcode extends AbstractShortcode {
    public function __construct(
        OutputRenderer $renderer,
        private readonly SpotifyClient $client
    ) {
        parent::__construct($renderer);
    }

    public function getTag(): string {
        return 'nowscr_spotify_nowplaying';
    }

    protected function getData(array $atts): mixed {
        return $this->client->getCurrentlyPlaying();
    }
}
```

### Step 3: Register in Plugin

```php
// In Plugin.php registerBindings()
$this->container->singleton(SpotifyClient::class, fn($c) =>
    new SpotifyClient($c->make(CacheManager::class))
);

// In ShortcodeManager
new Spotify\NowPlayingShortcode($renderer, $container->make(SpotifyClient::class));
```

### Step 4: Add Admin Settings

```php
// In CredentialsTab
$this->addSection('spotify', [
    'client_id' => ['type' => 'text', 'label' => 'Client ID'],
    'client_secret' => ['type' => 'password', 'label' => 'Client Secret'],
]);
```

---

## Migration Guide

### v1.3 → v2.0

The `Migrator` class handles automatic migration:

```php
<?php
namespace NowScrobbling\Core;

class Migrator {
    public function migrate(): void {
        $version = get_option('ns_migration_version', '0.0.0');

        if (version_compare($version, '2.0.0', '<')) {
            $this->migrateFrom1x();
            update_option('ns_migration_version', '2.0.0');
        }
    }

    private function migrateFrom1x(): void {
        // Option mappings
        $mappings = [
            'lastfm_api_key' => 'ns_lastfm_api_key',
            'lastfm_user' => 'ns_lastfm_user',
            'trakt_client_id' => 'ns_trakt_client_id',
            // ... more mappings
        ];

        foreach ($mappings as $old => $new) {
            $value = get_option($old);
            if ($value !== false) {
                update_option($new, $value);
            }
        }

        // Clear old transients
        $this->clearLegacyTransients();

        // Clear old cron hooks
        wp_clear_scheduled_hook('nowscrobbling_cache_refresh');
    }
}
```

---

## Debugging

### Enable Debug Mode

Settings → NowScrobbling → Diagnostics → Enable Debug Logging

### Log Levels

```php
$logger = new Logger('lastfm');

$logger->debug('Cache hit', ['key' => $key]);           // Verbose
$logger->info('API request completed', ['ms' => 150]);  // Normal
$logger->warning('Rate limited', ['until' => $time]);   // Important
$logger->error('API error', ['code' => 500]);           // Critical
```

### Debug Output

```
[2026-01-18 14:30:00] [lastfm] DEBUG: Cache hit | {"key":"ns_lastfm_recent_abc123"}
[2026-01-18 14:30:01] [lastfm] INFO: API request | {"url":"...","ms":142,"status":200}
[2026-01-18 14:30:02] [trakt] WARNING: Rate limited | {"until":"2026-01-18 14:35:00"}
```

### Admin Dashboard

The Diagnostics tab shows:
- Environment detection (Cloudflare, LiteSpeed, etc.)
- Cache statistics (hits, misses, hit rate)
- API request metrics
- Error log (paginated)
- Cache management tools

---

## Security Checklist

Before each release, verify:

- [ ] All user inputs sanitized with correct order: `wp_unslash()` → `sanitize_*()`
- [ ] All outputs escaped: `esc_html()`, `esc_attr()`, `esc_url()` at render time
- [ ] Nonces verified for all AJAX/REST endpoints
- [ ] `current_user_can()` checks for admin-only actions
- [ ] No direct database queries (use WordPress APIs)
- [ ] No `eval()` or dynamic code execution
- [ ] No hardcoded credentials
- [ ] API keys stored in options (not code)
- [ ] HTTPS enforced for all external API calls
- [ ] Rate limiting prevents API abuse

---

## Implementation Phases

### Phase 1: Foundation
- [ ] Create `composer.json` with PSR-4 autoloading
- [ ] Create `src/Plugin.php` orchestrator
- [ ] Create `src/Container.php` lightweight DI
- [ ] Create `src/Core/Bootstrap.php` hook registration
- [ ] Update `nowscrobbling.php` bootstrap (minimal)

### Phase 2: Cache System
- [ ] Create `CacheLayerInterface.php`
- [ ] Implement `InMemoryLayer.php`
- [ ] Implement `TransientLayer.php`
- [ ] Implement `FallbackLayer.php`
- [ ] Implement `ETagLayer.php`
- [ ] Create `CacheManager.php` orchestrator
- [ ] Create `ThunderingHerdLock.php`

### Phase 3: Environment Detection
- [ ] Create `EnvironmentDetector.php`
- [ ] Implement `CloudflareAdapter.php`
- [ ] Implement `LiteSpeedAdapter.php`
- [ ] Implement `ObjectCacheAdapter.php`
- [ ] Add cache header logic

### Phase 4: API Clients
- [ ] Create `ApiClientInterface.php`
- [ ] Create `AbstractApiClient.php` (ETag, rate limiting)
- [ ] Implement `LastFmClient.php`
- [ ] Implement `TraktClient.php`
- [ ] Create `RateLimiter.php`
- [ ] Create `ApiResponse.php` DTO

### Phase 5: Shortcodes
- [ ] Create `AbstractShortcode.php` base class
- [ ] Create `OutputRenderer.php`
- [ ] Create `HashGenerator.php`
- [ ] Implement Last.fm shortcodes (4 classes)
- [ ] Implement Trakt shortcodes (3 classes)
- [ ] Create `ShortcodeManager.php`

### Phase 6: Security
- [ ] Create `NonceManager.php` (granular)
- [ ] Create `Sanitizer.php` (correct order)
- [ ] Create `Capability.php`

### Phase 7: REST API
- [ ] Create `RestController.php`
- [ ] Create `ShortcodeEndpoint.php` (public)
- [ ] Create `CacheEndpoint.php` (admin)

### Phase 8: Admin Interface
- [ ] Create `AdminController.php`
- [ ] Create `SettingsManager.php`
- [ ] Implement 4 tab classes
- [ ] Create settings page view

### Phase 9: JavaScript
- [ ] Create vanilla JS `NowScrobbling` class
- [ ] Implement IntersectionObserver lazy loading
- [ ] Implement hash-based DOM diffing
- [ ] Implement exponential backoff polling
- [ ] Remove jQuery dependency

### Phase 10: Migration & Cleanup
- [ ] Create `Migrator.php` (v1.3 → v2.0)
- [ ] Create `uninstall.php`
- [ ] Update activation/deactivation hooks
- [ ] Test migration path

### Phase 11: Documentation & Quality
- [ ] Create `ARCHITECTURE.md` (this file)
- [ ] Create `CONTRIBUTING.md`
- [ ] Add inline PHPDoc comments
- [ ] Add JSDoc comments
- [ ] Set up PHPCS configuration
- [ ] Set up ESLint configuration
- [ ] Create `.editorconfig`

### Phase 12: Accessibility & Design
- [ ] Implement new CSS design system
- [ ] Add focus states
- [ ] Add reduced-motion support
- [ ] Add ARIA attributes
- [ ] Add skeleton loading
- [ ] Test color contrast
- [ ] Test keyboard navigation

---

## Changelog

### v2.0.0 (Upcoming)

- Complete rewrite with modern PHP 8.2 architecture
- PSR-4 autoloading with Composer
- Multi-layer caching with stale-while-revalidate
- Environment-aware (Cloudflare, LiteSpeed, Redis)
- REST API replaces admin-ajax.php
- Granular security (nonces per endpoint)
- WCAG AA accessible design
- Vanilla JavaScript (no jQuery)
- Extensible service architecture

---

*This document is automatically maintained. Last generated: 2026-01-18*
