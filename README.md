# NowScrobbling (WordPress Plugin)
NowScrobbling is a straightforward WordPress plugin that manages API settings for Last.fm and Trakt and displays their latest activities. It offers an easy solution to integrate these activities on your WordPress site.

## Features
- **Integration with Last.fm and Trakt**: Displays the most recent scrobbles and activities from Last.fm and Trakt.
- **Easy Management of API Keys and Usernames**: Configure your API keys and usernames directly in the WordPress admin area.
- **Customizable Settings**: Adjust the number of top tracks, top artists, top albums, lovedtracks, and last scrobbles to display.
- **Cache Duration Control**: Reduce API load by controlling the cache duration of fetched data.
- **Shortcodes Availability**: Incorporate data flexibly into posts, pages, or widgets using shortcodes.

## Installation
1. Upload the plugin folder to the `/wp-content/plugins/` directory of your WordPress installation.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Configuration
After activation, you'll find the settings page under `Settings > NowScrobbling`. Here, you can enter your Last.fm and Trakt API keys and usernames, among other settings.

## Usage
The plugin provides several shortcodes for use in your posts, pages, or widgets. Available shortcodes include:

### Last.fm Shortcodes
- `[nowscr_lastfm_indicator]` - Displays the current Last.fm activity status.
- `[nowscr_lastfm_history]` - Displays the recent scrobbles from Last.fm.
- `[nowscr_lastfm_top_artists period="7day"]` - Displays the top artists from Last.fm for the specified period.
- `[nowscr_lastfm_top_albums period="7day"]` - Displays the top albums from Last.fm for the specified period.
- `[nowscr_lastfm_top_tracks period="7day"]` - Displays the top tracks from Last.fm for the specified period.
- `[nowscr_lastfm_lovedtracks]` - Displays the loved tracks from Last.fm.

#### Period Options for Last.fm Shortcodes
The `period` attribute for Last.fm shortcodes can be set to the following values:
- `7day` - Last 7 days
- `1month` - Last 30 days
- `3month` - Last 90 days
- `6month` - Last 180 days
- `12month` - Last 365 days
- `overall` - Overall period

### Trakt.tv Shortcodes
- `[nowscr_trakt_indicator]` - Displays the current Trakt activity status.
- `[nowscr_trakt_history]` - Displays the recent activities from Trakt.
- `[nowscr_trakt_last_movie]` - Displays the last watched movies from Trakt.
- `[nowscr_trakt_last_movie_with_rating]` - Displays the last watched movies with ratings from Trakt.
- `[nowscr_trakt_last_show]` - Displays the last watched shows from Trakt.
- `[nowscr_trakt_last_episode]` - Displays the last watched episodes from Trakt.

## Customization
The appearance of the displayed data can be customized with CSS. Relevant classes are documented in the source code.

## Support
For support requests, please visit [https://robinwill.de](https://robinwill.de).

## Frequently Asked Questions (FAQ)
### How do I get my Last.fm API Key?
Visit [Last.fm API Documentation](https://www.last.fm/api) and follow the instructions to create an API key.

### How do I get my Trakt Client ID?
Visit [Trakt API Documentation](https://trakt.docs.apiary.io/) and follow the instructions to create a new application and obtain a Client ID.

## License
The plugin is licensed under the GPL v2 or later.