# CLAUDE.md - NowScrobbling v2.0

## Project Overview

NowScrobbling is a WordPress plugin that displays Last.fm and Trakt.tv activity via shortcodes. Version 2.0 uses a modern PHP 8.2+ architecture with PSR-4 autoloading, dependency injection, and multi-layer caching.

**Current Version**: 2.0.0
**PHP Requirement**: 8.2+
**WordPress Requirement**: 6.0+

## Quick Reference

### Essential Commands

```bash
# Install dependencies
cd nowscrobbling
composer install

# Run tests
composer test
# or
./vendor/bin/phpunit

# Check code style
composer phpcs
# or
./vendor/bin/phpcs

# Testing in WordPress
1. Symlink or copy nowscrobbling/ to wp-content/plugins/
2. Activate plugin in WordPress admin
3. Add API credentials in Settings > NowScrobbling > Credentials
4. Use shortcodes on any page/post
```

### File Structure (v2.0)

```
nowscrobbling/
├── nowscrobbling.php          # Bootstrap ONLY (constants, autoloader, hooks)
├── composer.json              # PSR-4 autoloading configuration
├── src/
│   ├── Plugin.php             # Main orchestrator (singleton)
│   ├── Container.php          # Lightweight DI container
│   ├── Core/
│   │   ├── Bootstrap.php      # Hook registration
│   │   ├── Activator.php      # Installation, defaults, migration
│   │   ├── Deactivator.php    # Cleanup on deactivation
│   │   ├── Migrator.php       # v1.3 → v2.0 option migration
│   │   └── Assets.php         # Conditional asset loading
│   ├── Cache/
│   │   ├── CacheManager.php   # Cache orchestrator
│   │   ├── Layers/            # InMemory, Transient, Fallback, ETag
│   │   ├── Environment/       # Cloudflare, LiteSpeed, Nginx adapters
│   │   └── Lock/              # ThunderingHerdLock
│   ├── Api/
│   │   ├── AbstractApiClient.php  # Base with ETag, rate limiting
│   │   ├── LastFmClient.php   # Last.fm API
│   │   ├── TraktClient.php    # Trakt.tv API
│   │   └── RateLimiter.php    # API rate limiting
│   ├── Shortcodes/
│   │   ├── ShortcodeManager.php   # Registration & routing
│   │   ├── AbstractShortcode.php  # Base class (DRY)
│   │   ├── LastFm/            # Last.fm shortcodes
│   │   └── Trakt/             # Trakt shortcodes
│   ├── Admin/
│   │   ├── AdminController.php    # Menu & registration
│   │   ├── SettingsManager.php    # Settings orchestration
│   │   ├── Tabs/              # Credentials, Caching, Display, Diagnostics
│   │   └── Views/             # Admin page templates
│   ├── Rest/
│   │   └── RestController.php # REST API endpoints
│   ├── Security/
│   │   ├── NonceManager.php   # Granular nonces per endpoint
│   │   └── Sanitizer.php      # Centralized sanitization
│   └── Support/
│       ├── Logger.php         # Debug logging
│       ├── Metrics.php        # Performance metrics
│       └── TextTruncator.php  # Multibyte truncation
├── assets/
│   ├── css/                   # Public and admin styles
│   ├── js/                    # Vanilla ES6+ JavaScript
│   └── images/                # Plugin images
├── tests/
│   ├── Unit/                  # PHPUnit tests
│   └── bootstrap.php          # Test configuration
└── uninstall.php              # Complete cleanup on uninstall
```

### Key Classes

| Class | Purpose | Location |
|-------|---------|----------|
| `Plugin` | Main orchestrator, singleton | src/Plugin.php |
| `Container` | Dependency injection | src/Container.php |
| `CacheManager` | Multi-layer cache orchestration | src/Cache/CacheManager.php |
| `AbstractApiClient` | API base with ETag, rate limiting | src/Api/AbstractApiClient.php |
| `AbstractShortcode` | Shortcode base class | src/Shortcodes/AbstractShortcode.php |
| `NonceManager` | Granular security tokens | src/Security/NonceManager.php |
| `Sanitizer` | Centralized input sanitization | src/Security/Sanitizer.php |
| `Migrator` | v1.3 → v2.0 migration | src/Core/Migrator.php |

## Code Conventions

### Naming

- **Namespace**: `NowScrobbling\` (e.g., `NowScrobbling\Cache\CacheManager`)
- **Shortcodes**: `nowscr_` prefix (e.g., `nowscr_lastfm_indicator`)
- **Options**: `ns_` prefix (e.g., `ns_debug_enabled`, `ns_lastfm_api_key`)
- **Text domain**: `nowscrobbling` for all i18n strings
- **REST namespace**: `nowscrobbling/v1`

### Shortcode Pattern (Mandatory)

Every shortcode MUST:
1. Extend `AbstractShortcode`
2. Implement `getTag()`, `getSlug()`, `renderContent()`
3. Use `OutputRenderer` for HTML wrapping with hash
4. Be registered via `ShortcodeManager`

```php
class MyShortcode extends AbstractShortcode
{
    public function getTag(): string
    {
        return 'nowscr_my_shortcode';
    }

    public function getSlug(): string
    {
        return 'my-shortcode';
    }

    protected function renderContent(array $atts): string
    {
        // Fetch data, render HTML
        return '<div>...</div>';
    }
}
```

### Caching Rules

**ALWAYS use the cache manager. Never call `wp_remote_get` or raw transient functions directly.**

```php
// Correct: Use API client (handles caching, ETag, rate limiting)
$client = $container->make(LastFmClient::class);
$response = $client->getRecentTracks($user);

// Correct: Use cache manager for custom data
$cacheManager = $container->make(CacheManager::class);
$data = $cacheManager->get($key, $callback, $ttl);

// Wrong: Direct HTTP call
$response = wp_remote_get($url); // DON'T DO THIS
```

### Security Checklist

- [ ] Use `Sanitizer` class for all input sanitization
- [ ] Sanitize with correct order: `unslash → sanitize → validate`
- [ ] Use `NonceManager` for endpoint-specific nonces
- [ ] Check capabilities: `current_user_can('manage_options')` for admin
- [ ] Escape output: `esc_html()`, `esc_attr()`, `esc_url()` at render time

### Hook Registration

**NEVER register hooks in constructors.** Use the Bootstrap pattern:

```php
// Correct: Bootstrap registers hooks
add_action('init', [$shortcodeManager, 'register']);

// Wrong: Constructor hook registration
public function __construct()
{
    add_action('init', [$this, 'register']); // DON'T
}
```

## v2.0 Architecture

### Core Philosophy

> **"Old data is better than error messages"**

A visitor seeing week-old data is better than seeing an error. The plugin gracefully degrades through multiple cache layers before ever showing an error.

### Cache Strategy (4 Layers)

```
Request Flow:
1. In-Memory Cache (per-request, static)
2. Primary Transient (1-60 min, configurable)
3. Fallback Transient (7 days, never overwritten with errors)
4. ETag Cache (conditional requests, bandwidth savings)

+ Stale-While-Revalidate (serve old, fetch new in background)
+ Thundering Herd Lock (prevent simultaneous API calls)
```

### Dependency Injection

```php
// Get service from container
$client = NowScrobbling\Plugin::getInstance()->make(LastFmClient::class);

// Or via injected container
public function __construct(private readonly Container $container) {}
$client = $this->container->make(LastFmClient::class);
```

### REST API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/render/{shortcode}` | GET | Public | Render shortcode HTML |
| `/status` | GET | Admin | Plugin status & metrics |
| `/cache/clear` | POST | Admin | Clear all caches |

## Testing

### Manual Test Protocol

```markdown
1. Admin Settings
   - [ ] Page loads without PHP errors
   - [ ] Tabs switch correctly
   - [ ] Settings save correctly
   - [ ] API Test button works
   - [ ] Cache clear works

2. Shortcodes
   - [ ] SSR renders immediately (no JS required)
   - [ ] REST API refresh updates content
   - [ ] No layout shift on hydration
   - [ ] Fallback shown when API fails

3. Caching
   - [ ] Cache hit logged in debug
   - [ ] ETag 304 responses handled
   - [ ] Fallback data survives cache clear
   - [ ] Thundering herd lock active

4. Migration (from v1.3)
   - [ ] Options migrated correctly
   - [ ] Legacy crons removed
   - [ ] Legacy transients cleaned
```

### Automated Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/phpunit tests/Unit/Cache/InMemoryLayerTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## Release Process

1. Update version in `nowscrobbling.php`:
   - Plugin header `Version:`
   - Constant `NOWSCROBBLING_VERSION`
2. Update `src/Plugin.php` constant `VERSION`
3. Update `README.md` version
4. Update `CHANGELOG.md` with changes
5. Run all tests: `composer test`
6. Check code style: `composer phpcs`
7. Test all shortcodes manually
8. Commit with message: `chore: bump version to X.Y.Z`
9. Create git tag: `git tag vX.Y.Z`
10. Push to remote: `git push && git push --tags`

## API Reference

### Last.fm Endpoints Used

- `user.getRecentTracks` - Scrobble history
- `user.getTopArtists` - Top artists by period
- `user.getTopAlbums` - Top albums by period
- `user.getTopTracks` - Top tracks by period
- `user.getLovedTracks` - Loved tracks

### Trakt.tv Endpoints Used

- `users/{user}/watching` - Currently watching
- `users/{user}/history` - Watch history
- `users/{user}/ratings` - User ratings
- `sync/activities` - Activity timestamps

## Useful Shortcuts

```php
// Get plugin instance
$plugin = NowScrobbling\Plugin::getInstance();

// Check if debug enabled
if ($plugin->isDebug()) { /* ... */ }

// Get service
$cache = $plugin->make(CacheManager::class);

// Log with debug check
NowScrobbling\Support\Logger::log('Message here');

// Sanitize input
$clean = NowScrobbling\Security\Sanitizer::text($input);
$email = NowScrobbling\Security\Sanitizer::email($input);
$json = NowScrobbling\Security\Sanitizer::json($input);
```

## Do NOT

- Add new external dependencies (keep it lean)
- Bypass the caching system
- Register hooks in constructors
- Use jQuery (vanilla JS only)
- Commit `vendor/` directory
- Skip nonce verification
- Echo output in shortcodes (always return)
