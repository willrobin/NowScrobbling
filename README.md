# NowScrobbling

**Your music and media activity, beautifully presented.**

A modern WordPress plugin that displays your Last.fm scrobbles and Trakt.tv watch history with style. Built for performance, privacy, and perfect WordPress integration.

![Version](https://img.shields.io/badge/version-1.4.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![License](https://img.shields.io/badge/license-GPLv2-green)

## Features

### Performance First
- **Smart Multi-Layer Caching** - Memory, transient, and fallback caches ensure fast responses
- **"Old data is better than errors"** - Graceful degradation when APIs are unavailable
- **Conditional Asset Loading** - CSS/JS only load on pages that use shortcodes
- **Efficient Polling** - Exponential backoff prevents unnecessary API calls

### Privacy Focused
- **No external tracking** - Your data stays between you and the APIs you choose
- **Minimal API calls** - Smart caching reduces external requests
- **No cookies** - Zero cookie footprint for visitors
- **GDPR friendly** - You control what data is displayed

### Beautiful Design
- **4 Layout Options** - Inline, Card, Grid, and List views
- **Dark/Light Mode** - Automatic system preference detection
- **CSS Variables** - Easy theming with custom properties
- **Responsive** - Looks great on all devices

### Developer Friendly
- **Modern PHP 8.0+** - Typed properties, enums, match expressions
- **PSR-4 Autoloading** - Clean namespace structure
- **Extensible** - Provider pattern for adding new services
- **Template Overrides** - Customize output via your theme

## Requirements

- WordPress 5.0+
- PHP 8.0+
- Last.fm API Key (free) and/or Trakt.tv Client ID (free)

## Installation

1. Upload the `nowscrobbling` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress Admin
3. Navigate to **Settings > NowScrobbling**
4. Enter your API credentials
5. Use shortcodes in your content

## Configuration

### Last.fm Setup
1. Visit [Last.fm API](https://www.last.fm/api/account/create)
2. Create an application to get your API Key
3. Enter your API Key and Last.fm username in the plugin settings

### Trakt.tv Setup
1. Visit [Trakt.tv Apps](https://trakt.tv/oauth/applications/new)
2. Create an application to get your Client ID
3. Enter your Client ID and Trakt username in the plugin settings

### Optional: TMDb for Posters
1. Visit [TMDb API](https://www.themoviedb.org/settings/api)
2. Get an API Key for movie/TV show posters
3. Enter it in the Trakt settings tab

## Shortcodes

### Last.fm

| Shortcode | Description |
|-----------|-------------|
| `[nowscr_lastfm_indicator]` | Current scrobbling status |
| `[nowscr_lastfm_history]` | Recent scrobbles |
| `[nowscr_lastfm_top_artists]` | Top artists |
| `[nowscr_lastfm_top_albums]` | Top albums |
| `[nowscr_lastfm_top_tracks]` | Top tracks |
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

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| `layout` | `inline`, `card`, `grid`, `list` | `inline` | Display layout |
| `show_image` | `true`, `false` | `false` | Show artwork/posters |
| `limit` | `1-50` | `5` | Number of items |
| `max_length` | `0-200` | `0` | Text truncation (0 = none) |
| `class` | string | - | Custom CSS class |
| `period` | `7day`, `1month`, `3month`, `6month`, `12month`, `overall` | `7day` | Time period (Last.fm) |

### Examples

```
[nowscr_lastfm_indicator]

[nowscr_lastfm_top_artists layout="grid" show_image="true" limit="6" period="12month"]

[nowscr_trakt_history layout="list" show_image="true" limit="10"]

[nowscr_trakt_last_movie layout="card" show_image="true"]
```

## Customization

### CSS Variables

Override these in your theme's CSS:

```css
:root {
    /* Colors */
    --ns-primary: #d51007;
    --ns-primary-hover: #b50d06;
    --ns-text: #1a1a1a;
    --ns-text-muted: #666666;
    --ns-background: #ffffff;
    --ns-border: #e0e0e0;

    /* Spacing */
    --ns-gap: 1rem;
    --ns-radius: 8px;

    /* Typography */
    --ns-font-size: 0.9375rem;
    --ns-line-height: 1.5;
}

/* Dark mode automatically applies */
@media (prefers-color-scheme: dark) {
    :root {
        --ns-text: #e0e0e0;
        --ns-text-muted: #999999;
        --ns-background: #1a1a1a;
        --ns-border: #333333;
    }
}
```

### Template Overrides

Copy templates from `nowscrobbling/templates/` to your theme's `nowscrobbling/` folder:

```
your-theme/
  nowscrobbling/
    layouts/
      card.php      # Custom card layout
    shortcodes/
      lastfm/
        indicator.php  # Custom indicator
```

## Architecture

NowScrobbling v1.4 is a complete rewrite with modern PHP patterns:

```
nowscrobbling/
├── src/
│   ├── Core/           # Plugin bootstrap, autoloader, assets
│   ├── Providers/      # API integrations (Last.fm, Trakt)
│   ├── Shortcodes/     # All 11 shortcode classes
│   ├── Cache/          # Multi-layer caching system
│   ├── Admin/          # Settings page with 7 tabs
│   ├── Ajax/           # AJAX request handlers
│   └── Templates/      # Template engine
├── templates/          # Default templates
├── assets/
│   ├── css/           # Styles (variables, layouts, components)
│   └── js/            # Frontend polling, admin scripts
└── languages/         # Translations
```

### Key Design Decisions

- **Provider Pattern**: Easy to add new services (Spotify, Plex, etc.)
- **Abstract Shortcodes**: Shared logic, minimal duplication
- **Template Engine**: Theme overrides with path validation
- **Fallback Caching**: 7-day backup cache for API failures

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full history.

### 1.4.0 (2025-01-15)
- Complete architectural rewrite
- PHP 8.0+ with typed properties and enums
- PSR-4 autoloading
- New layout system (inline, card, grid, list)
- Artwork/poster support
- Multi-layer caching with fallback
- 7-tab admin dashboard
- Template override system
- Removed legacy code

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.

## Author

**Robin Will** - [robinwill.de](https://robinwill.de/)

## Credits

- [Last.fm](https://www.last.fm/) for the Scrobbling API
- [Trakt.tv](https://trakt.tv/) for the watch history API
- [TMDb](https://www.themoviedb.org/) for movie/TV posters
