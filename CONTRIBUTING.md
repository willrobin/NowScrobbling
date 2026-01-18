# Contributing to NowScrobbling

Thank you for your interest in contributing! This document provides guidelines for contributing to NowScrobbling v2.0.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Development Setup](#development-setup)
3. [Code Style](#code-style)
4. [Architecture Guidelines](#architecture-guidelines)
5. [Testing](#testing)
6. [Commits & Pull Requests](#commits--pull-requests)
7. [Release Process](#release-process)

---

## Getting Started

### Prerequisites

- **PHP 8.2+** (required)
- **Composer** (for autoloading)
- **WordPress 6.0+** (recommended)
- **Node.js 18+** (for linting tools)

### Quick Start

```bash
# Clone the repository
git clone https://github.com/willrobin/NowScrobbling.git

# Navigate to plugin directory
cd NowScrobbling/nowscrobbling

# Install Composer dependencies (PSR-4 autoloading)
composer install

# Symlink to WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/nowscrobbling
```

---

## Development Setup

### Local Development

1. **Clone** the repo into `wp-content/plugins/NowScrobbling` (or symlink)
2. **Run** `composer install` in the `nowscrobbling/` directory
3. **Activate** the plugin in WordPress admin
4. **Configure** API credentials in Settings → NowScrobbling
5. **Edit** files - no build step required (pure PHP/JS/CSS)

### Directory Structure (v2.0)

```
nowscrobbling/
├── src/                    # PSR-4 autoloaded classes
│   ├── Plugin.php          # Main orchestrator
│   ├── Container.php       # DI container
│   ├── Core/               # Bootstrap, activation, migration
│   ├── Cache/              # Multi-layer caching system
│   ├── Api/                # API clients (Last.fm, Trakt)
│   ├── Shortcodes/         # Shortcode implementations
│   ├── Admin/              # Settings pages
│   ├── Rest/               # REST API endpoints
│   └── Security/           # Nonces, sanitization
├── assets/
│   ├── css/                # Stylesheets
│   ├── js/                 # Vanilla JavaScript
│   └── images/             # Static assets
├── languages/              # Translation files
├── templates/              # Theme-overridable templates
└── tests/                  # PHPUnit & Jest tests
```

---

## Code Style

### PHP Guidelines

Follow WordPress Coding Standards with PSR-4 autoloading:

```php
<?php
declare(strict_types=1);

namespace NowScrobbling\Shortcodes;

/**
 * Example class demonstrating code style
 */
final class ExampleShortcode extends AbstractShortcode
{
    /**
     * Constructor with dependency injection
     */
    public function __construct(
        private readonly OutputRenderer $renderer,
        private readonly LastFmClient $client,
    ) {
        parent::__construct($renderer);
    }

    /**
     * Render the shortcode
     *
     * @param array<string, mixed> $atts Shortcode attributes
     */
    public function render(array $atts = []): string
    {
        // Early return for errors
        if (!$this->isConfigured()) {
            return $this->renderEmpty($atts);
        }

        // Main logic
        $data = $this->getData($atts);

        return $this->formatOutput($data, $atts);
    }
}
```

### Key Principles

1. **Early returns** - Handle errors first, keep happy path unindented
2. **Readonly properties** - Use `readonly` for immutable dependencies
3. **Type declarations** - Always declare parameter and return types
4. **Named arguments** - Use for clarity with many parameters
5. **Match expressions** - Prefer over switch statements

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Namespaces | PascalCase | `NowScrobbling\Cache\Layers` |
| Classes | PascalCase | `CacheManager` |
| Methods | camelCase | `getOrSet()` |
| Properties | camelCase | `$primaryTtl` |
| Constants | SCREAMING_SNAKE | `LOCK_TTL` |
| Options | snake_case with `ns_` prefix | `ns_lastfm_api_key` |
| Shortcodes | snake_case with `nowscr_` prefix | `nowscr_lastfm_indicator` |

### JavaScript Guidelines

Use modern ES2022+ syntax (vanilla JS, no jQuery):

```javascript
/**
 * NowScrobbling client component
 */
class NowScrobbling {
    #apiBase;      // Private fields
    #observer;

    constructor(config) {
        this.#apiBase = config.restUrl;
    }

    async #fetch(shortcode) {
        const response = await fetch(`${this.#apiBase}/render/${shortcode}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    }
}
```

### CSS Guidelines

Use CSS custom properties for theming:

```css
/* Use CSS variables */
.bubble {
    background: var(--ns-surface);
    border-color: var(--ns-border);
    color: var(--ns-text);
}

/* Support dark mode */
@media (prefers-color-scheme: dark) {
    :root {
        --ns-surface: #202124;
    }
}

/* Support reduced motion */
@media (prefers-reduced-motion: reduce) {
    * { animation-duration: 0.01ms !important; }
}
```

---

## Architecture Guidelines

### Adding New Features

**Before coding**, read `ARCHITECTURE.md` for the full design.

### Adding a New API Service

1. Create client in `src/Api/`:
   ```php
   class SpotifyClient extends AbstractApiClient {
       protected readonly string $service = 'spotify';
   }
   ```

2. Create shortcodes in `src/Shortcodes/Spotify/`

3. Register in `Plugin.php`:
   ```php
   $this->container->singleton(SpotifyClient::class, fn($c) =>
       new SpotifyClient($c->make(CacheManager::class))
   );
   ```

4. Add settings in `CredentialsTab.php`

### Adding a New Shortcode

1. Extend `AbstractShortcode`:
   ```php
   class MyShortcode extends AbstractShortcode {
       public function getTag(): string {
           return 'nowscr_my_shortcode';
       }
   }
   ```

2. Register in `ShortcodeManager.php`

3. Add to REST API whitelist in `RestController.php`

### Security Rules

**Input (receive data):**
```php
// CORRECT: unslash THEN sanitize
$value = sanitize_text_field(wp_unslash($_POST['field']));

// Use centralized Sanitizer
$value = Sanitizer::input('field', 'text', 'POST');
```

**Output (render data):**
```php
// ALWAYS escape at render time
echo esc_html($text);
echo esc_attr($attribute);
echo esc_url($url);
```

**Actions:**
```php
// ALWAYS verify nonces AND capabilities
if (!NonceManager::verify($nonce, 'action')) {
    wp_die('Invalid nonce');
}
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

---

## Testing

### Manual Testing Checklist

Before submitting a PR, verify:

- [ ] **Admin Settings**: Page loads without PHP errors
- [ ] **API Test**: "Test API Connection" button works
- [ ] **Cache**: "Clear All Caches" works
- [ ] **SSR**: Shortcodes render without JavaScript
- [ ] **AJAX**: Content updates on refresh
- [ ] **Dark Mode**: Styles work in dark mode
- [ ] **Mobile**: Responsive on small screens
- [ ] **Accessibility**: Keyboard navigation works

### Running Tests

```bash
# PHPUnit tests
composer test

# PHP CodeSniffer
composer phpcs

# JavaScript lint
npm run lint

# All checks
composer check
```

### Debug Mode

Enable in Settings → Diagnostics → Debug Logging:

- View cache hits/misses
- Track API request timing
- Monitor rate limiting
- See fallback usage

---

## Commits & Pull Requests

### Commit Messages

Follow conventional commits format:

```
type(scope): short description

Longer description if needed.

Fixes #123
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `refactor`: Code restructuring
- `docs`: Documentation only
- `style`: Formatting, no code change
- `test`: Adding tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(shortcodes): add Trakt seasons progress shortcode
fix(cache): prevent thundering herd on concurrent requests
refactor(api): extract rate limiting to separate class
docs: update ARCHITECTURE.md with caching diagrams
```

### Pull Request Guidelines

1. **Small PRs** - One feature/fix per PR
2. **Description** - Explain what and why
3. **Screenshots** - For UI changes
4. **Tests** - Add tests for new features
5. **Docs** - Update relevant documentation

### PR Template

```markdown
## Summary
Brief description of changes.

## Changes
- Added X
- Fixed Y
- Refactored Z

## Testing
How to test these changes.

## Screenshots
(If applicable)
```

---

## Release Process

### Version Bump

1. Update `nowscrobbling.php`:
   ```php
   define('NOWSCROBBLING_VERSION', '2.1.0');
   ```

2. Update plugin header:
   ```php
   * Version: 2.1.0
   ```

3. Update `CHANGELOG.md`

4. Create git tag:
   ```bash
   git tag -a v2.1.0 -m "Release v2.1.0"
   git push origin v2.1.0
   ```

### Changelog Format

```markdown
## [2.1.0] - 2026-02-01

### Added
- New Spotify service integration
- Background cache refresh

### Changed
- Improved rate limiting algorithm

### Fixed
- Dark mode gradient issue
- Focus state on mobile
```

---

## Questions?

- Check `ARCHITECTURE.md` for technical details
- Open an issue for questions
- Join discussions for feature requests

Thank you for contributing!
