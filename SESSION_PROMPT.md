# NowScrobbling v2.0 - Session Continuation Prompt

> **Kopiere diesen Prompt in eine neue Claude Code Session, um die Arbeit fortzusetzen.**

---

## Projekt-Kontext

NowScrobbling ist ein WordPress-Plugin, das Last.fm und Trakt.tv Aktivitäten via Shortcodes anzeigt. Wir führen einen kompletten Rewrite auf Version 2.0 durch.

**Repository**: `/home/user/NowScrobbling`
**Plugin-Verzeichnis**: `/home/user/NowScrobbling/nowscrobbling`
**Branch**: `claude/nowscrobbling-v2-rewrite-g4REW`

## Wichtige Dokumentation

Lies diese Dateien ZUERST:
1. **ARCHITECTURE.md** - Komplette v2.0 Architektur (1800+ Zeilen)
2. **CONTRIBUTING.md** - Code-Style Guidelines
3. **CLAUDE.md** - Projekt-spezifische Regeln

---

## Aktueller Stand (18.01.2026)

### ✅ Abgeschlossene Phasen

#### Phase 1: Foundation
- `src/Container.php` - Lightweight DI Container
- `src/Plugin.php` - Main Orchestrator (Singleton)
- `src/Core/Bootstrap.php` - Hook Registration
- `src/Core/Activator.php` - Activation mit v1.x Migration
- `src/Core/Deactivator.php` - Deactivation mit Cleanup
- `src/Core/Assets.php` - Conditional Asset Loading
- `nowscrobbling.php` - Minimal Bootstrap (~70 Zeilen)
- `composer.json` - PSR-4 Autoloading

#### Phase 2: Cache System
- `src/Cache/CacheLayerInterface.php` - Interface
- `src/Cache/CacheManager.php` - Multi-Layer Orchestrator
- `src/Cache/Layers/InMemoryLayer.php` - Per-Request Cache
- `src/Cache/Layers/TransientLayer.php` - WordPress Transients
- `src/Cache/Layers/FallbackLayer.php` - 7-Day Emergency Cache
- `src/Cache/Layers/ETagLayer.php` - HTTP Conditional Requests
- `src/Cache/Lock/ThunderingHerdLock.php` - Concurrent Request Lock

#### Phase 3: Environment Detection
- `src/Cache/Environment/EnvironmentAdapterInterface.php`
- `src/Cache/Environment/EnvironmentDetector.php`
- `src/Cache/Environment/CloudflareAdapter.php`
- `src/Cache/Environment/LiteSpeedAdapter.php`
- `src/Cache/Environment/NginxAdapter.php`
- `src/Cache/Environment/ObjectCacheAdapter.php`
- `src/Cache/Environment/DefaultAdapter.php`

#### Phase 4: API Clients
- `src/Api/ApiClientInterface.php`
- `src/Api/AbstractApiClient.php` - ETag, Rate Limiting, Retry
- `src/Api/LastFmClient.php` - Last.fm API
- `src/Api/TraktClient.php` - Trakt.tv API
- `src/Api/RateLimiter.php` - Exponential Backoff
- `src/Api/Response/ApiResponse.php` - Immutable DTO

#### Phase 5: Shortcodes
- `src/Shortcodes/AbstractShortcode.php` - Base Class
- `src/Shortcodes/ShortcodeManager.php` - Registration
- `src/Shortcodes/Renderer/OutputRenderer.php`
- `src/Shortcodes/Renderer/HashGenerator.php`
- **Last.fm Shortcodes:**
  - `src/Shortcodes/LastFm/IndicatorShortcode.php`
  - `src/Shortcodes/LastFm/HistoryShortcode.php`
  - `src/Shortcodes/LastFm/TopListShortcode.php` (unified: artists/albums/tracks)
  - `src/Shortcodes/LastFm/LovedTracksShortcode.php`
- **Trakt Shortcodes:**
  - `src/Shortcodes/Trakt/IndicatorShortcode.php`
  - `src/Shortcodes/Trakt/HistoryShortcode.php`
  - `src/Shortcodes/Trakt/LastMediaShortcode.php` (unified: movie/show/episode)

#### Phase 6: Security
- `src/Security/NonceManager.php` - Granular Nonces
- `src/Security/Sanitizer.php` - wp_unslash() → sanitize_*()

#### Phase 7: REST API
- `src/Rest/RestController.php` - Ersetzt admin-ajax.php
  - `GET /nowscrobbling/v1/render/{shortcode}`
  - `GET /nowscrobbling/v1/status`
  - `POST /nowscrobbling/v1/cache/clear`
  - `POST /nowscrobbling/v1/test/{service}`

---

### ❌ Noch Ausstehende Phasen

#### Phase 8: Admin Interface
```
src/Admin/
├── AdminController.php
├── SettingsManager.php
├── Tabs/
│   ├── TabInterface.php
│   ├── CredentialsTab.php
│   ├── CachingTab.php
│   ├── DisplayTab.php
│   └── DiagnosticsTab.php
└── Views/
    ├── settings-page.php
    └── partials/
        ├── tab-credentials.php
        ├── tab-caching.php
        ├── tab-display.php
        └── tab-diagnostics.php
```

#### Phase 9: JavaScript
```
assets/js/nowscrobbling.js   # Vanilla JS Client (~10KB)
assets/js/admin.js           # Admin JS
```
- IntersectionObserver für Lazy Loading
- Hash-basiertes DOM Diffing
- Exponential Backoff Polling
- Kein jQuery

#### Phase 10: Migration & Cleanup
- `src/Core/Migrator.php` - v1.3 → v2.0 Migration (erweitern)
- `uninstall.php` - Clean Removal

#### Phase 11: CSS & Design
```
assets/css/public.css   # WCAG AA compliant
assets/css/admin.css    # Admin styles
```
- CSS Custom Properties
- Dark Mode Support
- Reduced Motion

#### Phase 12: Testing & Docs
- PHPUnit Tests in `tests/`
- `.phpcs.xml` - PHP CodeSniffer Config
- `.editorconfig`

---

## Verzeichnisstruktur (aktuell)

```
nowscrobbling/
├── nowscrobbling.php              # Bootstrap
├── composer.json                  # PSR-4 Autoloading
├── composer.lock
├── vendor/                        # Composer dependencies
├── assets/
│   ├── css/                       # (noch leer)
│   ├── js/                        # (noch leer)
│   └── images/
│       └── nowplaying.gif
├── includes/                      # Legacy v1.3 (wird ersetzt)
├── languages/
├── public/                        # Legacy v1.3
└── src/                           # v2.0 Klassen
    ├── Plugin.php
    ├── Container.php
    ├── Core/
    ├── Cache/
    ├── Api/
    ├── Shortcodes/
    ├── Security/
    └── Rest/
```

---

## Anweisungen für nächste Session

### Option A: Phase 8 (Admin Interface) fortsetzen

```
Lies ARCHITECTURE.md Abschnitt "Admin Interface".
Erstelle die Admin-Klassen mit:
- Tabbed Settings UI (Credentials, Caching, Display, Diagnostics)
- WordPress Settings API Integration
- AJAX-basierte API-Tests
- Cache-Management Tools
```

### Option B: Phase 9 (JavaScript) fortsetzen

```
Lies ARCHITECTURE.md Abschnitt "JavaScript Architecture".
Erstelle den Vanilla JS Client mit:
- IntersectionObserver für Lazy Loading
- Hash-basiertes Content Diffing
- Exponential Backoff Polling
- Visibility API Integration
```

### Option C: Integration & Wiring

```
Die Klassen existieren, aber Plugin.php muss noch alle
Services im Container registrieren und Bootstrap muss
die ShortcodeManager und RestController initialisieren.
Aktualisiere:
- src/Plugin.php registerBindings()
- src/Core/Bootstrap.php
```

---

## Qualitäts-Anforderungen

- PHP 8.2+ Features (readonly, match, named arguments)
- Korrekte Sanitization: `wp_unslash()` → `sanitize_*()`
- Alle Outputs escapen
- Type Declarations überall
- PHPDoc für public Methods
- WCAG AA Farben

---

## Git Workflow

```bash
# Aktueller Branch
git checkout claude/nowscrobbling-v2-rewrite-g4REW

# Commits anzeigen
git log --oneline -10

# Nach Änderungen committen
git add -A
git commit -m "feat: implement Phase X - description"
git push origin claude/nowscrobbling-v2-rewrite-g4REW
```

---

## PR Status

Ein PR wurde vorbereitet aber noch nicht erstellt. Erstelle nach Abschluss weiterer Phasen:

**Base**: `main`
**Head**: `claude/nowscrobbling-v2-rewrite-g4REW`
**Titel**: `feat: NowScrobbling v2.0 Complete Rewrite`
