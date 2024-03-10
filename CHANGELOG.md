# Changelog

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
