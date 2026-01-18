=== NowScrobbling ===
Contributors: robinwill
Tags: last.fm, trakt, scrobbling, music, movies, tv shows, now playing
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your Last.fm scrobbles and Trakt.tv watch history beautifully on your WordPress site.

== Description ==

NowScrobbling brings your music and media activity to your WordPress site. Show what you're currently listening to, your top artists, recent movies, and more - all with beautiful, customizable layouts.

= Features =

**Performance First**

* Smart multi-layer caching (memory, transient, fallback)
* "Old data is better than errors" - graceful API failure handling
* Conditional asset loading - CSS/JS only where needed
* Efficient polling with exponential backoff

**Privacy Focused**

* No external tracking or analytics
* Minimal API calls through smart caching
* No cookies for visitors
* GDPR friendly - you control displayed data

**Beautiful Design**

* 4 layout options: Inline, Card, Grid, List
* Automatic dark/light mode
* CSS variables for easy theming
* Fully responsive

**Developer Friendly**

* Modern PHP 8.0+ codebase
* PSR-4 autoloading
* Extensible provider pattern
* Template overrides via theme

= Supported Services =

* **Last.fm** - Scrobbles, top artists, albums, tracks, loved tracks
* **Trakt.tv** - Watch history, movies, shows, episodes
* **TMDb** - Movie and TV show posters (optional)

= Shortcodes =

**Last.fm:**
* `[nowscr_lastfm_indicator]` - Current scrobbling status
* `[nowscr_lastfm_history]` - Recent scrobbles
* `[nowscr_lastfm_top_artists]` - Top artists
* `[nowscr_lastfm_top_albums]` - Top albums
* `[nowscr_lastfm_top_tracks]` - Top tracks
* `[nowscr_lastfm_lovedtracks]` - Loved tracks

**Trakt.tv:**
* `[nowscr_trakt_indicator]` - Current watching status
* `[nowscr_trakt_history]` - Watch history
* `[nowscr_trakt_last_movie]` - Last watched movie
* `[nowscr_trakt_last_show]` - Last watched show
* `[nowscr_trakt_last_episode]` - Last watched episode

= Common Parameters =

* `layout` - Display layout (inline, card, grid, list)
* `show_image` - Show artwork/posters (true/false)
* `limit` - Number of items (1-50)
* `max_length` - Text truncation (0 = none)
* `period` - Time period for Last.fm (7day, 1month, 3month, 6month, 12month, overall)

== Installation ==

1. Upload the `nowscrobbling` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings > NowScrobbling
4. Enter your Last.fm API Key and/or Trakt.tv Client ID
5. Use shortcodes in your posts, pages, or widgets

= Getting API Keys =

**Last.fm:**
1. Visit https://www.last.fm/api/account/create
2. Create an application
3. Copy the API Key

**Trakt.tv:**
1. Visit https://trakt.tv/oauth/applications/new
2. Create an application
3. Copy the Client ID

**TMDb (optional, for posters):**
1. Visit https://www.themoviedb.org/settings/api
2. Request an API key
3. Enter it in the Trakt settings tab

== Frequently Asked Questions ==

= What PHP version is required? =

PHP 8.0 or higher is required. The plugin uses modern PHP features like typed properties, enums, and match expressions.

= Do I need both Last.fm and Trakt? =

No, you can use either one or both. Configure only what you need.

= How do I customize the appearance? =

Use CSS variables in your theme. The plugin provides variables like `--ns-primary`, `--ns-text`, `--ns-background` etc. that you can override.

= Can I override the templates? =

Yes! Copy templates from `nowscrobbling/templates/` to your theme's `nowscrobbling/` folder and modify them.

= Why isn't my data updating? =

Check the Cache tab in settings. You can clear all caches there. Also verify your API credentials are correct using the Test Connection buttons.

= Is this GDPR compliant? =

The plugin only fetches data from APIs you configure (Last.fm, Trakt, TMDb). No visitor data is collected or sent anywhere. You control what information is displayed.

== Screenshots ==

1. Admin dashboard overview
2. Inline layout example
3. Card layout with artwork
4. Grid layout for top artists
5. List layout with details
6. Settings page - Last.fm configuration
7. Settings page - Trakt configuration

== Changelog ==

= 1.4.0 =
* Complete architectural rewrite
* Now requires PHP 8.0+
* New layout system: inline, card, grid, list
* Artwork and poster support
* Multi-layer caching with 7-day fallback
* Modern admin dashboard with 7 tabs
* Template override system
* PSR-4 autoloading
* Provider pattern for extensibility
* Removed all legacy code

= 1.3.2 =
* Added CLAUDE.md for AI collaboration
* Optimized HTTP headers and timeouts
* Improved cache key generation
* Fixed mobile CSS margins

= 1.3.1 =
* Server-side rendering for faster initial load
* Hash-based DOM updates (no flicker)
* Improved caching with fallback
* Debug logging improvements

= 1.3.0 =
* Major performance improvements
* Conditional asset loading
* Admin status dashboard
* ETag support for Trakt API

== Upgrade Notice ==

= 1.4.0 =
Major update! Requires PHP 8.0+. Complete rewrite with new architecture, layouts, and features. Backup recommended before upgrading.
