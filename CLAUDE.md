# CLAUDE.md - NowScrobbling

## Project Overview

NowScrobbling is a WordPress plugin that displays Last.fm and Trakt.tv activity via shortcodes. It uses server-side rendering (SSR) with progressive AJAX hydration and aggressive but polite caching.

**Current Version**: 1.3.1.3
**Target Version**: 1.4.0

## Quick Reference

### Essential Commands

```bash
# No build step required - pure PHP/JS/CSS
# Just edit files and refresh browser

# Testing in WordPress
1. Symlink or copy to wp-content/plugins/NowScrobbling
2. Activate plugin in WordPress admin
3. Add API credentials in Settings > NowScrobbling
4. Use shortcodes on any page/post
```

### File Structure

```
nowscrobbling/
├── nowscrobbling.php          # Bootstrap ONLY (constants, hooks, includes)
├── includes/
│   ├── api-functions.php      # API calls, caching, metrics, logging
│   ├── shortcodes.php         # All 11 shortcode definitions
│   ├── admin-settings.php     # Settings page, diagnostics, cache tools
│   └── ajax.php               # AJAX endpoints (public + admin)
├── public/
│   ├── css/                   # Public styles
│   ├── js/ajax-load.js        # Client polling + refresh
│   └── images/nowplaying.gif  # Animation for now-playing
└── languages/                 # i18n (text domain: nowscrobbling)
```

### Key Functions

| Function | Purpose | File |
|----------|---------|------|
| `nowscrobbling_get_or_set_transient()` | Central caching with fallback | api-functions.php |
| `nowscrobbling_fetch_api_data()` | HTTP calls with ETag, metrics | api-functions.php |
| `nowscrobbling_wrap_output()` | SSR wrapper with hash for AJAX | shortcodes.php |
| `nowscrobbling_should_cooldown()` | Rate-limit protection | api-functions.php |

## Code Conventions

### Naming

- **PHP functions**: `nowscrobbling_` prefix (e.g., `nowscrobbling_fetch_lastfm_data`)
- **Shortcodes only**: `nowscr_` prefix (e.g., `nowscr_lastfm_indicator`)
- **Options**: `ns_` prefix for new options (e.g., `ns_debug_enabled`)
- **Text domain**: `nowscrobbling` for all i18n strings

### Shortcode Pattern (Mandatory)

Every shortcode MUST:
1. Return HTML wrapped by `nowscrobbling_wrap_output($slug, $html, $hash, $nowplaying)`
2. Compute hash via `nowscrobbling_make_hash()` for client-side diffing
3. Be registered in `nowscrobbling_list_shortcodes()` (bootstrap)
4. Be whitelisted in `ajax.php` `$allowed_shortcodes` array

### Caching Rules

**ALWAYS use the helpers. Never call `wp_remote_get` or raw transient functions directly.**

```php
// Correct: Use the caching wrapper
$data = nowscrobbling_get_or_set_transient($key, $callback, $ttl);

// Correct: Use the fetch helper (handles ETag, metrics, cooldowns)
$response = nowscrobbling_fetch_api_data($url, $headers, $args, $cache_key);

// Wrong: Direct HTTP call
$response = wp_remote_get($url); // DON'T DO THIS
```

### Security Checklist

- [ ] Sanitize input: `sanitize_text_field(wp_unslash($_POST['key']))`
- [ ] Validate booleans: `wp_validate_boolean()`
- [ ] Verify nonces: `wp_verify_nonce()` for all AJAX/forms
- [ ] Check capabilities: `current_user_can('manage_options')` for admin actions
- [ ] Escape output: `esc_html()`, `esc_attr()`, `esc_url()` at render time

## v1.4 Development Goals

### Core Philosophy

> **"Old data is better than error messages"**

A visitor seeing week-old data is better than seeing an error. The plugin should gracefully degrade through multiple cache layers before ever showing an error.

### Cache Strategy

```
Request Flow:
1. In-Memory Cache (per-request)
2. Primary Transient (1-15 min, configurable)
3. Fallback Transient (7 days, never overwritten with errors)
4. HTML Persistence (12 hours, last successful render)
5. ETag Cache (24 hours, bandwidth savings)
```

### Priority Areas

1. **Security**: Granular nonces, proper sanitization order
2. **Code Quality**: Eliminate ~40% shortcode duplication
3. **Caching**: Stale-while-revalidate, longer fallbacks
4. **Admin UX**: Tab-based settings, paginated logs
5. **Migration**: Automatic option migration from v1.3

## Current Issues / Tech Debt

### Known Problems

- [ ] All AJAX endpoints share same nonce (should be granular)
- [ ] `wp_unslash()` sometimes called after `sanitize_*()` (wrong order)
- [ ] Fallback data not validated for age (could be months old)
- [ ] ~40% code duplication in Top Artists/Albums/Tracks shortcodes
- [ ] `nowscrobbling_get_or_set_transient()` is ~800 lines (too complex)
- [ ] Admin page has 20+ settings without tabs
- [ ] Log viewer loads all 200 entries at once (no pagination)

### Do NOT

- Add new external dependencies (keep it lean)
- Bypass the caching helpers
- Echo output directly in bootstrap file
- Add heavy frontend frameworks
- Commit credentials or `.env` files

## Testing

### Manual Test Protocol

```markdown
1. Admin Settings
   - [ ] Page loads without PHP errors
   - [ ] Settings save correctly
   - [ ] API Test button works
   - [ ] Cache clear works

2. Shortcodes
   - [ ] SSR renders immediately (no JS required)
   - [ ] AJAX refresh updates content
   - [ ] No layout shift on hydration
   - [ ] Fallback shown when API fails

3. Caching
   - [ ] Cache hit logged in debug
   - [ ] ETag 304 responses handled
   - [ ] Cooldown activates after errors
   - [ ] Fallback data survives cache clear
```

### Debug Mode

Enable debug logging in Settings > NowScrobbling > Debug section.
Logs appear in the admin panel and track:
- API requests/responses
- Cache hits/misses
- ETag matches
- Cooldown activations
- Fallback usage

## Release Process

1. Update version in `nowscrobbling.php`:
   - Plugin header `Version:`
   - Constant `NOWSCROBBLING_VERSION`
2. Update `README.md` version
3. Update `CHANGELOG.md` with changes
4. Test all shortcodes manually
5. Commit with message: `chore: bump version to X.Y.Z`
6. Push to Forgejo remote

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
// Get configured cache duration
$minutes = nowscrobbling_cache_minutes('lastfm'); // or 'trakt'

// Log with debug check
nowscrobbling_log('Message here');

// Update metrics
nowscrobbling_metrics_update('lastfm', 'cache_hits');

// Check if in cooldown
if (nowscrobbling_should_cooldown('trakt')) { /* skip API call */ }
```
