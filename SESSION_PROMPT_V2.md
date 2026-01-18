# NowScrobbling v2.0 - Implementation Session Prompt

Kopiere diesen Prompt in eine neue Claude-Session, um mit der Implementierung zu beginnen.

---

## Prompt

```
# NowScrobbling v2.0 - Complete Rewrite Implementation

## Projekt-Kontext

NowScrobbling ist ein WordPress-Plugin, das Last.fm und Trakt.tv Aktivitäten via Shortcodes anzeigt. Wir führen einen kompletten Rewrite auf Version 2.0 durch.

**Repository**: `/home/user/NowScrobbling`
**Plugin-Verzeichnis**: `/home/user/NowScrobbling/nowscrobbling`

## Wichtige Dokumentation

Lies diese Dateien ZUERST, bevor du mit der Implementierung beginnst:

1. **ARCHITECTURE.md** - Komplette v2.0 Architektur (1800+ Zeilen)
   - Verzeichnisstruktur
   - Alle Klassen-Designs mit Code-Beispielen
   - Cache-Architektur
   - Security-Model
   - REST API Design

2. **CONTRIBUTING.md** - Code-Style Guidelines
   - PHP 8.2+ Patterns
   - Naming Conventions
   - Security Rules

3. **CLAUDE.md** - Projekt-spezifische Regeln

## Design-Entscheidungen (bereits getroffen)

- **PHP**: 8.2+ (readonly, enums, match, named arguments)
- **Autoloading**: PSR-4 via Composer
- **JavaScript**: Vanilla JS (kein jQuery)
- **API**: WordPress REST API (ersetzt admin-ajax.php)
- **Caching**: Multi-Layer (Memory → Transient → Fallback → ETag)
- **Cloudflare**: Header-basiert (kein API-Key erforderlich)
- **Erweiterbarkeit**: Ja (abstrakte Klassen für neue Services)
- **Multisite**: Nein (Single-Site Fokus)

## Implementierungs-Phasen

### Phase 1: Foundation ⬅️ START HERE
- [ ] `composer.json` mit PSR-4 Autoloading erstellen
- [ ] `src/Plugin.php` - Haupt-Orchestrator
- [ ] `src/Container.php` - Lightweight DI Container
- [ ] `src/Core/Bootstrap.php` - Hook Registration
- [ ] `nowscrobbling.php` - Minimaler Bootstrap (~50 Zeilen)

### Phase 2: Cache System
- [ ] `CacheLayerInterface.php`
- [ ] `InMemoryLayer.php`
- [ ] `TransientLayer.php`
- [ ] `FallbackLayer.php`
- [ ] `ETagLayer.php`
- [ ] `CacheManager.php`
- [ ] `ThunderingHerdLock.php`

### Phase 3: Environment Detection
- [ ] `EnvironmentDetector.php`
- [ ] `CloudflareAdapter.php`
- [ ] `LiteSpeedAdapter.php`
- [ ] `ObjectCacheAdapter.php`

### Phase 4: API Clients
- [ ] `ApiClientInterface.php`
- [ ] `AbstractApiClient.php`
- [ ] `LastFmClient.php`
- [ ] `TraktClient.php`
- [ ] `RateLimiter.php`
- [ ] `ApiResponse.php`

### Phase 5: Shortcodes
- [ ] `AbstractShortcode.php`
- [ ] `OutputRenderer.php`
- [ ] `HashGenerator.php`
- [ ] Last.fm Shortcodes (4 Klassen)
- [ ] Trakt Shortcodes (3 Klassen)
- [ ] `ShortcodeManager.php`

### Phase 6: Security
- [ ] `NonceManager.php`
- [ ] `Sanitizer.php`
- [ ] `Capability.php`

### Phase 7: REST API
- [ ] `RestController.php`
- [ ] `ShortcodeEndpoint.php`
- [ ] `CacheEndpoint.php`

### Phase 8: Admin Interface
- [ ] `AdminController.php`
- [ ] `SettingsManager.php`
- [ ] Tab-Klassen (4 Stück)
- [ ] Settings Page View

### Phase 9: JavaScript
- [ ] `NowScrobbling` Klasse (Vanilla JS)
- [ ] IntersectionObserver
- [ ] Hash-based DOM Diffing
- [ ] Exponential Backoff Polling

### Phase 10: Migration
- [ ] `Migrator.php` (v1.3 → v2.0)
- [ ] `uninstall.php`
- [ ] Activation/Deactivation Hooks

### Phase 11: CSS & Accessibility
- [ ] Neues Design System
- [ ] Focus States
- [ ] Reduced Motion
- [ ] ARIA Attributes

## Wichtige Architektur-Prinzipien

1. **"Old data is better than error messages"** - Graceful Degradation
2. **SSR-First** - Content rendert auf dem Server
3. **Privacy-First** - Alle API-Calls server-seitig
4. **DRY** - AbstractShortcode eliminiert ~40% Duplikation
5. **Hooks außerhalb von Konstruktoren** - Für Testbarkeit

## Vorhandener v1.3 Code (Referenz)

Diese Dateien enthalten die bestehende Logik, die migriert werden muss:
- `includes/api-functions.php` - Caching-Patterns (extrahieren zu CacheManager)
- `includes/shortcodes.php` - Shortcode-Logik (zu AbstractShortcode)
- `includes/ajax.php` - Security (zu REST API + NonceManager)
- `includes/admin-settings.php` - Admin UI (zu Tabbed Interface)
- `public/js/ajax-load.js` - Client-Logik (zu Vanilla JS rewrite)

## Anweisungen

1. Lies ARCHITECTURE.md komplett durch
2. Beginne mit Phase 1: Foundation
3. Erstelle jede Datei vollständig (keine Platzhalter)
4. Teste nach jeder Phase, dass das Plugin aktivierbar bleibt
5. Committe nach jeder abgeschlossenen Phase
6. Dokumentiere Änderungen in CHANGELOG.md

## Qualitäts-Anforderungen

- Korrekte Sanitization-Reihenfolge: `wp_unslash()` → `sanitize_*()`
- Alle Outputs escapen: `esc_html()`, `esc_attr()`, `esc_url()`
- Type Declarations für alle Parameter und Return-Typen
- PHPDoc für alle public Methods
- WCAG AA konforme Farben (4.5:1 Kontrast)

Beginne mit Phase 1 und erstelle die Foundation-Klassen.
```

---

## Quick Reference

### Namespace-Struktur
```
NowScrobbling\
├── Plugin
├── Container
├── Core\
│   ├── Bootstrap
│   ├── Activator
│   └── Migrator
├── Cache\
│   ├── CacheManager
│   ├── Layers\
│   └── Environment\
├── Api\
│   ├── AbstractApiClient
│   ├── LastFmClient
│   └── TraktClient
├── Shortcodes\
│   ├── AbstractShortcode
│   ├── LastFm\
│   └── Trakt\
├── Admin\
├── Rest\
├── Security\
└── Support\
```

### Wichtige Dateipfade
```
/home/user/NowScrobbling/
├── ARCHITECTURE.md      ← Komplette Architektur-Doku
├── CONTRIBUTING.md      ← Code-Style Guidelines
├── CLAUDE.md            ← Projekt-Regeln
└── nowscrobbling/
    ├── nowscrobbling.php  ← Bootstrap (wird minimal)
    ├── composer.json      ← NEU: PSR-4 Autoloading
    └── src/               ← NEU: Alle Klassen hier
```
