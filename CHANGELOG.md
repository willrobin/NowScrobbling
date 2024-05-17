# Changelog

## [1.2.2] - 2024-05-17

### Added
- Added detailed title attributes for better user experience in Last.fm and Trakt shortcodes:
  - `title` attributes now provide more context about the displayed items.
- Implemented a `nowscrobbling_format_output` function for better and standardized formatting of Trakt outputs.
- Included rewatch count and rating information for Trakt history and last movie/show/episode shortcodes.
- Added `nowscrobbling_fetch_trakt_watched_shows` function to support fetching completed shows from Trakt.

### Changed
- Enhanced `nowscr_lastfm_history_shortcode` to include `title` attributes in the output links.
- Refactored Trakt shortcodes to include ratings and rewatch counts:
  - `nowscr_trakt_history_shortcode`
  - `nowscr_trakt_last_movie_shortcode`
  - `nowscr_trakt_last_show_shortcode`
  - `nowscr_trakt_last_episode_shortcode`
- Improved data fetching mechanism by using a more structured approach to transient caching.
- Optimized API data fetching and error handling across all shortcodes.

### Fixed
- Corrected the image path for the now playing GIF in Last.fm history shortcode.

## [1.2.1] - 2024-05-16

### Fixed
- Fixed an issue where the currently playing item was not being displayed in the `nowscr_trakt_indicator` and `nowscr_trakt_history` shortcodes.

## [1.2.0] - 2024-05-14
### Added
- The plugin has been restructured to improve maintainability and readability. The code is now split across multiple files:
  - `nowplaying.php` for the main plugin file.
  - `includes/admin-settings.php` for administrative settings and registration.
  - `includes/shortcodes.php` for handling all shortcode functionalities.
  - `includes/api-functions.php` for API fetching and data handling functions.

### Changed
- **Version Requirement**: The plugin now requires at least WordPress 5.0 and PHP 7.0.
- **Enqueue Public CSS**: The public stylesheet `nowscrobbling-public.css` is now enqueued correctly within `nowplaying.php`.
- **Shortcodes**:
  - Enhanced shortcode attributes handling for `period` in `nowscr_lastfm_top_artists`, `nowscr_lastfm_top_albums`, `nowscr_lastfm_top_tracks`, and `nowscr_lastfm_top_tags` to allow specifying the period (`7day`, `1month`, `3month`, `6month`, `12month`, `overall`).
  - Refined the output generation for all shortcodes to ensure consistency and better user experience.
- **Caching**: Improved caching mechanism for fetching data from Last.fm and Trakt.tv APIs.
- **Admin Settings**: Enhanced admin settings page layout and functionality for better user experience.

### Fixed
- **Sanitization**: Improved sanitization of input values in the settings to prevent potential security issues.
- **API Fetching**: Fixed various issues with fetching data from Last.fm and Trakt.tv APIs, including better error handling and displaying error messages to the user.

### Removed
- **CSS Inline Styles**: Removed inline styles in favor of a separate CSS file for better maintainability and customization.

### Notes
- Users must update their PHP and WordPress versions to meet the new requirements.
- Ensure all settings are configured correctly after the update, as some defaults and handling may have changed.

## [1.0.5] - 2024-03-10

### Added
- Plugin header comment with comprehensive metadata including plugin URI, description, version, author details, license, text domain, domain path, and GitHub repository links.
- Explicit exit condition to prevent direct file access, enhancing security.
- `nowscrobbling_admin_menu` and `nowscrobbling_register_settings` functions for better admin menu and settings management.
- `nowscrobbling_add_settings_fields` function to structure the settings page with dynamic input fields for user configurations.
- Callback functions for settings fields to render dynamic input options in the admin dashboard.
- Cache management features for Last.fm and Trakt.tv with user control over cache duration and a clear cache option in the settings.
- Comprehensive shortcode functionality to display Last.fm and Trakt.tv data within posts, pages, or widgets, including activity indicators, history, top artists/tracks/albums, and loved tracks.
- Custom CSS styling for plugin content, introducing styles for lists, links, and the 'bubble' class for an enhanced appearance.
- Improved code organization and modularization for better readability, maintainability, and scalability.

### Changed
- Refactored the entire plugin structure for better functionality and user experience.
- Updated version number to 1.0.5 to reflect the significant enhancements and additions.

### Fixed
- Various minor bugs and issues for improved plugin stability and performance.
- Standardized code syntax and incorporated WordPress core functions for data sanitization and validation to adhere to WordPress coding standards and best practices.
