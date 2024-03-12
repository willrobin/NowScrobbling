<?php

/**
 * Plugin Name:         NowScrobbling
 * Plugin URI:          https://github.com/willrobin/NowScrobbling
 * Description:         NowScrobbling ist ein einfaches WordPress-Plugin, um API-Einstellungen für last.fm und trakt.tv zu verwalten und deren letzte Aktivitäten anzuzeigen.
 * Version:             1.0.8
 * Requires at least:   
 * Requires PHP:        
 * Author:              Robin Will
 * Author URI:          https://robinwill.de/
 * License:             GPLv2 or later
 * Text Domain:         nowscrobbling
 * Domain Path:         /languages
 * GitHub Plugin URI:   https://github.com/willrobin/NowScrobbling
 * GitHub Branch:       master
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('admin_menu', 'nowscrobbling_admin_menu');
add_action('admin_init', 'nowscrobbling_register_settings');

function nowscrobbling_admin_menu()
{
    add_options_page('NowScrobbling Einstellungen', 'NowScrobbling', 'manage_options', 'nowscrobbling-settings', 'nowscrobbling_settings_page');
}

function nowscrobbling_register_settings()
{
    $settings = [
        'lastfm_api_key', 'lastfm_user', 'trakt_client_id', 'trakt_user',
        'top_tracks_count', 'top_artists_count', 'top_albums_count', 'lovedtracks_count',  'top_tags_count', 'last_movies_count', 'last_shows_count', 'last_episodes_count',
        'time_period', 'cache_duration', 'lastfm_cache_duration', 'trakt_cache_duration',
        'lastfm_activity_limit', 'trakt_activity_limit'
    ];

    foreach ($settings as $setting) {
        register_setting('nowscrobbling-settings-group', $setting);
    }
}

function nowscrobbling_add_settings_fields()
{
    // Add settings fields for top tracks, artists, albums, lovedtracks
    add_settings_field('top_tracks_count', 'Anzahl der Top-Titel', 'nowscrobbling_top_tracks_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_artists_count', 'Anzahl der Top-Künstler', 'nowscrobbling_top_artists_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_albums_count', 'Anzahl der Top-Alben', 'nowscrobbling_top_albums_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('lovedtracks_count', 'Anzahl der Lieblingslieder', 'nowscrobbling_lovedtracks_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_tags_count', 'Anzahl der Top-Tags', 'nowscrobbling_top_tags_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('last_movies_count', 'Anzahl der Lieblingslieder', 'nowscrobbling_last_movies_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('last_shows_count', 'Anzahl der Lieblingslieder', 'nowscrobbling_last_shows_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('last_episodes_count', 'Anzahl der Lieblingslieder', 'nowscrobbling_last_episodes_count_callback', 'nowscrobbling', 'nowscrobbling_section');

    // Add settings field for time period
    add_settings_field('time_period', 'Zeitraum', 'nowscrobbling_time_period_callback', 'nowscrobbling', 'nowscrobbling_section');

    // Add settings field for transient cache duration
    add_settings_field('cache_duration', 'Dauer des Transient-Cache', 'nowscrobbling_cache_duration_callback', 'nowscrobbling', 'nowscrobbling_section');
}

// Callback functions for settings fields
function nowscrobbling_top_tracks_count_callback()
{
    $setting = esc_attr(get_option('top_tracks_count', 3));
    echo "<input type='number' name='top_tracks_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'top_artists_count'
function nowscrobbling_top_artists_count_callback()
{
    $setting = esc_attr(get_option('top_artists_count', 3));
    echo "<input type='number' name='top_artists_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'top_albums_count'
function nowscrobbling_top_albums_count_callback()
{
    $setting = esc_attr(get_option('top_albums_count', 3));
    echo "<input type='number' name='top_albums_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'lovedtracks_count'
function nowscrobbling_lovedtracks_count_callback()
{
    $setting = esc_attr(get_option('lovedtracks_count', 3));
    echo "<input type='number' name='lovedtracks_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'top_tags_count'
function nowscrobbling_top_tags_count_callback()
{
    $setting = esc_attr(get_option('top_tags_count', 3));
    echo "<input type='number' name='top_tags_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'last_movies_count'
function nowscrobbling_last_movies_count_callback()
{
    $setting = esc_attr(get_option('last_movies_count', 3));
    echo "<input type='number' name='last_movies_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'last_shows_count'
function nowscrobbling_last_shows_count_callback()
{
    $setting = esc_attr(get_option('last_shows_count', 3));
    echo "<input type='number' name='last_shows_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'last_episodes_count'
function nowscrobbling_last_episodes_count_callback()
{
    $setting = esc_attr(get_option('last_episodes_count', 3));
    echo "<input type='number' name='last_episodes_count' value='$setting' min='1' />";
}

// Callback-Funktion für 'time_period'
function nowscrobbling_time_period_callback()
{
    $setting = esc_attr(get_option('time_period', '7day'));
    echo "<select name='time_period'>
               <option value='7day' " . selected($setting, '7day', false) . ">Letzte 7 Tage</option>
               <option value='1month' " . selected($setting, '1month', false) . ">Letzte 30 Tage</option>
               <option value='3month' " . selected($setting, '3month', false) . ">Letzte 90 Tage</option>
               <option value='6month' " . selected($setting, '6month', false) . ">Letzte 180 Tage</option>
               <option value='12month' " . selected($setting, '12month', false) . ">Letzte 365 Tage</option>
               <option value='overall' " . selected($setting, 'overall', false) . ">Insgesamt</option>
           </select>";
}

// Callback-Funktion für 'cache_duration'
function nowscrobbling_cache_duration_callback()
{
    $setting = esc_attr(get_option('cache_duration', 5));
    echo "<input type='number' name='cache_duration' value='$setting' min='1' />";
}

// The settings page content
function nowscrobbling_settings_page()
{
    // Clear cache if requested
    if (isset($_POST['clear_cache'])) {
        delete_transient('my_lastfm_scrobbles');
        delete_transient('my_trakt_tv_activities');
        echo '<div class="updated"><p>Cache geleert.</p></div>';
    }
?>
    <div class="wrap">
        <h1>NowScrobbling Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('nowscrobbling-settings-group'); ?>
            <?php do_settings_sections('nowscrobbling-settings-group'); ?>
            <h2>Last.fm</h2>
            <table class="form-table">
                <!-- Input fields for API keys and user names -->
                <tr valign="top">
                    <th scope="row">Last.fm API Schlüssel</th>
                    <td><input type="text" name="lastfm_api_key" value="<?php echo esc_attr(get_option('lastfm_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Last.fm Benutzername</th>
                    <td><input type="text" name="lastfm_user" value="<?php echo esc_attr(get_option('lastfm_user')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der last.fm Aktivitäten</th>
                    <td><input type="number" name="lastfm_activity_limit" value="<?php echo esc_attr(get_option('lastfm_activity_limit', 1)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der Top-Künstler</th>
                    <td><input type="number" name="top_artists_count" value="<?php echo esc_attr(get_option('top_artists_count', 5)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der Top-Alben</th>
                    <td><input type="number" name="top_albums_count" value="<?php echo esc_attr(get_option('top_albums_count', 5)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der Top-Titel</th>
                    <td><input type="number" name="top_tracks_count" value="<?php echo esc_attr(get_option('top_tracks_count', 5)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der Lieblingslieder</th>
                    <td><input type="number" name="lovedtracks_count" value="<?php echo esc_attr(get_option('lovedtracks_count', 5)); ?>" /></td>
                </tr>
                <tr valign="top">
                <th scope="row">Anzahl der Top-Tags</th>
                <td><input type="number" name="top_tags_count" value="<?php echo esc_attr(get_option('top_tags_count', 3)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Zeitraum (außer Alben)</th>
                    <td>
                        <select name="time_period">
                            <option value="7day" <?php echo get_option('time_period') == '7day' ? 'selected' : ''; ?>>Letzte 7 Tage</option>
                            <option value="1month" <?php echo get_option('time_period') == '1month' ? 'selected' : ''; ?>>Letzte 30 Tage</option>
                            <option value="3month" <?php echo get_option('time_period') == '3month' ? 'selected' : ''; ?>>Letzte 90 Tage</option>
                            <option value="6month" <?php echo get_option('time_period') == '6month' ? 'selected' : ''; ?>>Letzte 180 Tage</option>
                            <option value="12month" <?php echo get_option('time_period') == '12month' ? 'selected' : ''; ?>>Letzte 365 Tage</option>
                            <option value="overall" <?php echo get_option('time_period') == 'overall' ? 'selected' : ''; ?>>Insgesamt</option>
                        </select>
                    </td>
                </tr>

            </table>
            <h2>Trakt</h2>
            <table class="form-table">
                <!-- Input fields for API keys and user names -->
                <tr valign="top">
                    <th scope="row">Trakt Client ID</th>
                    <td><input type="text" name="trakt_client_id" value="<?php echo esc_attr(get_option('trakt_client_id')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Trakt Benutzername</th>
                    <td><input type="text" name="trakt_user" value="<?php echo esc_attr(get_option('trakt_user')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der trakt.tv Aktivitäten</th>
                    <td><input type="number" name="trakt_activity_limit" value="<?php echo esc_attr(get_option('trakt_activity_limit', 3)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der letzten Filme</th>
                    <td><input type="number" name="last_movies_count" value="<?php echo esc_attr(get_option('last_movies_count', 3)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der letzten Serien</th>
                    <td><input type="number" name="last_shows_count" value="<?php echo esc_attr(get_option('last_shows_count', 3)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Anzahl der letzten Episoden</th>
                    <td><input type="number" name="last_episodes_count" value="<?php echo esc_attr(get_option('last_episodes_count', 3)); ?>" /></td>
                </tr>
            </table>
            <h2>Cache</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Last.fm Cache-Dauer (Minuten)</th>
                    <td><input type="number" name="lastfm_cache_duration" value="<?php echo esc_attr(get_option('lastfm_cache_duration', 1)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Trakt Cache-Dauer (Minuten)</th>
                    <td><input type="number" name="trakt_cache_duration" value="<?php echo esc_attr(get_option('trakt_cache_duration', 5)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Dauer des Transient-Cache</th>
                    <td><input type="number" name="cache_duration" value="<?php echo esc_attr(get_option('cache_duration', 15)); ?>" /></td>
                </tr>
            </table>
            <?php submit_button('Speichern'); ?>
        </form>
        <form method="post">
            <?php wp_nonce_field('nowscrobbling_clear_cache', 'nowscrobbling_nonce'); ?>
            <input type="submit" name="clear_cache" value="Cache leeren" class="button">
        </form>
        <!-- Display available shortcodes -->
        <h2>Verfügbare Shortcodes</h2>
        <p>Du kannst diese Shortcodes verwenden, um Inhalte in Beiträgen, Seiten oder Widgets anzuzeigen:</p>
        <h3>
            Last.fm</h2>
            <ul>
                <li><code>[nowscr_lastfm_indicator]</code> - Zeigt den aktuellen Status der Last.fm Aktivität an.</li>
                <li><code>[nowscr_lastfm_history]</code> - Zeigt die letzten Scrobbles von Last.fm an.</li>
                <li><code>[nowscr_lastfm_top_artists]</code> - Zeigt die letzten Top-Künstler von Last.fm im gewählten Zeitraum an.</li>
                <li><code>[nowscr_lastfm_top_albums]</code> - Zeigt die letzten Top-Alben von Last.fm <del>im gewählten Zeitraum</del> an.</li>
                <li><code>[nowscr_lastfm_top_tracks]</code> - Zeigt die letzten Top-Titel von Last.fm im gewählten Zeitraum an.</li>
                <li><code>[nowscr_lastfm_lovedtracks]</code> - Zeigt die letzten Lieblingslieder von Last.fm an.</li>
                <li><code>[nowscr_lastfm_top_tags]</code> - Zeigt die letzten Top-Tags von Last.fm an.</li>
            </ul>
            <h3>
                Trakt</h2>
                <ul>
                    <li><code>[nowscr_trakt_indicator]</code> - Zeigt den aktuellen Status der Trakt Aktivität an.</li>
                    <li><code>[nowscr_trakt_history]</code> - Zeigt die letzten Scrobbles von Trakt an.</li>
                    <li><code>[nowscr_trakt_last_movie]</code> - Zeigt die letzten Filme von Trakt an.</li>
                    <li><code>[nowscr_trakt_last_movie_with_rating]</code> - Zeigt die letzten Filme mit Bewertung von Trakt an.</li>
                    <li><code>[nowscr_trakt_last_show]</code> - Zeigt die letzten Serien von Trakt an.</li>
                    <li><code>[nowscr_trakt_last_episode]</code> - Zeigt die letzten Episoden von Trakt an.</li>
    </div>
    <!-- Beginn der Vorschau-Bereich -->
    <div class="wrap">
        <h1>Vorschau der Daten</h1>
        <div id="nowscrobbling-preview">
            <h2>Last.fm</h2>
            <h3>Status (Indicator)</h3>
            <?php echo nowscr_lastfm_indicator_shortcode(); // Zeigt den Status der Last.fm Aktivität an. 
            ?>
            <h3>Letzte Scrobbles (History)</h3>
            <?php echo nowscr_lastfm_history_shortcode(); // Zeigt die letzten Scrobbles von Last.fm an. 
            ?>
            <h3>Letzte Top-Künstler</h3>
            <?php echo nowscr_lastfm_top_artists_shortcode(); // Zeigt die Top-Künstler von Last.fm im gewählten Zeitraum an. 
            ?>
            <h3>Letzte Top-Alben</h3>
            <?php echo nowscr_lastfm_top_albums_shortcode(); // Zeigt die Top-Alben von Last.fm im gewählten Zeitraum an. 
            ?>
            <h3>Letzte Top-Titel</h3>
            <?php echo nowscr_lastfm_top_tracks_shortcode(); // Zeigt die Top-Titel von Last.fm im gewählten Zeitraum an. 
            ?>
            <h3>Letzte Lieblingslieder</h3>
            <?php echo nowscr_lastfm_lovedtracks_shortcode(); // Zeigt die Lieblingslieder von Last.fm an. 
            ?>
            <h3>Letzte Top-Tags</h3>
            <?php echo nowscr_lastfm_top_tags_shortcode(); // Zeigt die Top-Tags von Last.fm an.
            ?>
            
            <h2>Trakt</h2>
            <h3>Status (Indicator)</h3>
            <?php echo nowscr_trakt_indicator_shortcode(); // Zeigt den Status der Trakt Aktivität an. 
            ?>
            <h3>Letzte Scrobbles (History)</h3>
            <?php echo nowscr_trakt_history_shortcode(); // Zeigt die letzten Scrobbles von Trakt an. 
            ?>
            <h3>Letzte Filme</h3>
            <?php echo nowscr_trakt_last_movie_shortcode(); // Zeigt den letzten Film von Trakt an. 
            ?>
            <h3>Letzte Filme (mit Bewertung)</h3>
            <?php echo nowscr_trakt_last_movie_with_rating_shortcode(); // Zeigt den letzten Film von Trakt an. 
            ?>
            <h3>Letzte Serien</h3>
            <?php echo nowscr_trakt_last_show_shortcode(); // Zeigt die letzte Serie von Trakt an. 
            ?>
            <h3>Letzte Episoden</h3>
            <?php echo nowscr_trakt_last_episode_shortcode(); // Zeigt die letzte Episode von Trakt an. 
            ?>
        </div>
    </div>
    <!-- Ende des Vorschau-Bereichs -->
<?php
}

// CSS — Add styles for the plugin
function nowscrobbling_styles()
{
?>
    <style type="text/css">
        .nowscrobbling {
            background-color: rgba(38, 144, 255, 0.1);
            /* Hintergrundfarbe für den aktuell laufenden Track */
            border-radius: 5px;
            /* Abgerundete Ecken */
            padding: 0;
            margin-bottom: 0px;
            display: block;
        }

        .nowscrobbling img {
            vertical-align: middle;
            margin-left: 10px;
            margin-right: 5px;
            margin-bottom: 0 !important;
        }

        .bubble {
            background-color: rgba(38, 144, 255, 0.033);
            border: 1px solid rgba(38, 144, 255, 0.066);
            text-decoration: none;
            display: inline-block;
            max-width: fit-content;
            padding: 0px 4px 0px 4px;
            line-height: 1.75;
            border-radius: 5px;
            font-size: 0.7rem;
            white-space: nowrap;
        }

        .bubble img[src*="public/images/nowplaying.gif"] {
            transform: scale(0.8);
            vertical-align: middle;
            margin-left: 0px;
            margin-right: 0px;
            margin-bottom: 0 !important;
        }
    </style>
<?php
}
add_action('wp_head', 'nowscrobbling_styles');
// Fetch and display Last.fm scrobbles
function nowscrobbling_fetch_lastfm_scrobbles()
{
    $transient_key = 'my_lastfm_scrobbles';
    $cache_duration = get_option('lastfm_cache_duration', 15); // Default 15 Minuten
    $activity_limit = get_option('lastfm_activity_limit', 3); // Default 3 Aktivitäten
    if (false === ($scrobbles = get_transient($transient_key))) {
        $api_key = get_option('lastfm_api_key');
        $user = get_option('lastfm_user');
        $url = "http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user={$user}&api_key={$api_key}&limit={$activity_limit}&format=json";
        $response = wp_remote_get($url);
        if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response)) {
            // Im Fehlerfall nicht cachen, ggf. Fehlerbehandlung durchführen
            return '<em>Fehler beim Abrufen der Scrobbles von last.fm</em>';
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        if (isset($data->error)) {
            // Spezifischer Fehler von Last.fm, nicht cachen
            return '<em>Fehler beim Abrufen der Scrobbles: ' . esc_html($data->message) . '</em>';
        }
        $scrobbles = isset($data->recenttracks->track) ? $data->recenttracks->track : [];
        // Nur im Erfolgsfall cachen
        set_transient($transient_key, $scrobbles, $cache_duration * MINUTE_IN_SECONDS);
    }
    return $scrobbles;
}
function fetch_lastfm_data($type, $count = null, $period = null)
{
    $api_key = get_option('lastfm_api_key');
    $user = get_option('lastfm_user');
    $url = "http://ws.audioscrobbler.com/2.0/?method=user.get{$type}&user={$user}&api_key={$api_key}&format=json";
    // Fügen Sie das Limit nur hinzu, wenn es angegeben wurde
    if ($count !== null) {
        $url .= "&limit={$count}";
    }
    // Fügen Sie den Zeitraum nur hinzu, wenn er angegeben wurde
    if ($period !== null) {
        $url .= "&period={$period}";
    }
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data;
}
function fetch_lastfm_top_tags($user, $api_key, $limit = 5) {
    $url = "http://ws.audioscrobbler.com/2.0/?method=user.gettoptags&user={$user}&api_key={$api_key}&limit={$limit}&format=json";
    $response = wp_remote_get($url);
    if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response)) {
        return null; // Im Fehlerfall nicht cachen, ggf. Fehlerbehandlung durchführen
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!isset($data['toptags']['tag'])) {
        return null; // Fehlerbehandlung, falls die erwarteten Daten nicht vorhanden sind
    }
    return $data['toptags']['tag'];
}

function nowscr_lastfm_top_tags_shortcode() {
    $api_key = get_option('lastfm_api_key');
    $user = get_option('lastfm_user');
    $limit = get_option('top_tags_count', 5); // Verwenden Sie den im Admin-Panel festgelegten Wert
    $tags = fetch_lastfm_top_tags($user, $api_key, $limit);
    if (is_null($tags)) {
        return "Es konnten keine Top-Tags gefunden werden.";
    }
    $tagList = array_map(function ($tag) {
        return '<a href="' . esc_url($tag['url']) . '" class="bubble">' . esc_html($tag['name']) . '</a>';
    }, $tags);
    return implode(' ', $tagList);
}
add_shortcode('nowscr_lastfm_top_tags', 'nowscr_lastfm_top_tags_shortcode');

// SHORTCODES

// last.fm

// Zeigt den Status der Last.fm Aktivität an
function nowscr_lastfm_indicator_shortcode()
{
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;
    // Versuchen Sie, die Scrobbles aus dem Transient Cache abzurufen
    $scrobbles = get_transient('nowscrobbling_lastfm_scrobbles');
    // Wenn die Scrobbles nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($scrobbles === false) {
        $scrobbles = nowscrobbling_fetch_lastfm_scrobbles();
        // Speichern Sie die Scrobbles für die festgelegte Cache-Dauer
        set_transient('nowscrobbling_lastfm_scrobbles', $scrobbles, $cache_duration);
    }
    // Überprüfen Sie, ob $scrobbles ein Array ist
    if (!is_array($scrobbles) || empty($scrobbles)) {
        return "<em>Komisch, es wurden keine kürzlichen Scrobbles gefunden</em>"; // oder eine andere Fehlermeldung
    }
    foreach ($scrobbles as $track) {
        if (isset($track->{'@attr'}) && $track->{'@attr'}->nowplaying == 'true') {
            return "<strong>Scrobbelt gerade</strong>";
        }
    }
    $lastScrobble = reset($scrobbles); // Nimmt den ersten Scrobble aus der Liste
    if (isset($lastScrobble->date)) {
        $lastScrobbleTimestamp = $lastScrobble->date->uts;
        $dateTime = new DateTime("@$lastScrobbleTimestamp");
        $dateTime->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));
        return 'Zuletzt gehört: ' . $dateTime->format(get_option('date_format') . ' ' . get_option('time_format'));
    } else {
        return "<em>Komisch, es wurden keine kürzlichen Scrobbles gefunden</em>";
    }
}
add_shortcode('nowscr_lastfm_indicator', 'nowscr_lastfm_indicator_shortcode');

// Zeigt die letzten Scrobbles von Last.fm an
function nowscr_lastfm_history_shortcode()
{
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    // $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;
    // Versuchen Sie, die Scrobbles aus dem Transient Cache abzurufen
    // $scrobbles = get_transient('nowscrobbling_lastfm_scrobbles');
    // Wenn die Scrobbles nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    // if ($scrobbles === false) {
    $scrobbles = nowscrobbling_fetch_lastfm_scrobbles();
    // Speichern Sie die Scrobbles für 1 Stunde im Cache
    // set_transient('nowscrobbling_lastfm_scrobbles', $scrobbles, $cache_duration);
    // }
    $output = '';
    $lastPlayedTrack = null;
    // Überprüfen Sie, ob $scrobbles ein Array ist
    if (is_array($scrobbles)) {
        foreach ($scrobbles as $track) {
            if (isset($track->{'@attr'}) && $track->{'@attr'}->nowplaying == 'true') {
                $artist = esc_html($track->artist->{'#text'});
                $song = esc_html($track->name);
                $url = esc_url($track->url);
                $nowPlaying = '<img src="' . plugins_url('public/images/nowplaying.gif', __FILE__) . '" alt="NOW PLAYING" /> ';
                $output = "<span class='bubble'>{$nowPlaying}<a href='{$url}' target='_blank'>{$artist} - {$song}</a></span>";
                break;
            } else {
                $lastPlayedTrack = $track;
            }
        }
    }
    if (empty($output) && $lastPlayedTrack) {
        $artist = esc_html($lastPlayedTrack->artist->{'#text'});
        $song = esc_html($lastPlayedTrack->name);
        $url = esc_url($lastPlayedTrack->url);
        $output = "<a class='bubble' href='{$url}' target='_blank'>{$artist} - {$song}</a>";
    }
    return $output;
}
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode');

function nowscr_lastfm_top_artists_shortcode()
{
    $count = get_option('top_artists_count', 5); // Default 5 artists
    $period = get_option('time_period', '7day'); // Default last 7 days
    $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;
    // Versuchen Sie, die Top-Künstler aus dem Transient Cache abzurufen
    $data = get_transient('lastfm_top_artists');
    // Wenn die Top-Künstler nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($data === false) {
        $data = fetch_lastfm_data('topartists', $count, $period);
        // Wenn ein Fehler auftritt, geben Sie die Fehlermeldung zurück
        if ($data === null) {
            return '<em>Komisch, es wurden keine Künstler gefunden</em>';
        }
        // Speichern Sie die Top-Künstler für 1 Minute im Cache
        set_transient('lastfm_top_artists', $data, $cache_duration);
    }
    $artists = array();
    foreach ($data['topartists']['artist'] as $artist) {
        $name = esc_html($artist['name']);
        $url = esc_url($artist['url']);
        $artists[] = "<a class='bubble' href='{$url}' target='_blank'>{$name}</a>";
    }
    if (count($artists) > 1) {
        $last_artist = array_pop($artists);
        $output = implode(' ', $artists) . ' und ' . $last_artist;
    } else {
        $output = $artists[0];
    }
    return $output;
}
add_shortcode('nowscr_lastfm_top_artists', 'nowscr_lastfm_top_artists_shortcode');

function nowscr_lastfm_top_albums_shortcode()
{
    $count = get_option('top_albums_count', 1); // Default 1 albums
    $period = get_option('6month', '6month'); // Default last 6 months
    $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;
    // Versuchen Sie, die Top-Alben aus dem Transient Cache abzurufen
    $data = get_transient('lastfm_top_albums');
    // Wenn die Top-Alben nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($data === false) {
        $data = fetch_lastfm_data('topalbums', $count, $period);
        // Wenn ein Fehler auftritt, geben Sie die Fehlermeldung zurück
        if ($data === null) {
            return '<em>Komisch, es wurden keine Alben gefunden</em>';
        }
        // Speichern Sie die Top-Alben für 1 Stunde im Cache
        set_transient('lastfm_top_albums', $data, $cache_duration);
    }
    $albums = array();
    foreach ($data['topalbums']['album'] as $album) {
        $artist = esc_html($album['artist']['name']);
        $title = esc_html($album['name']);
        $url = esc_url($album['url']);
        $albums[] = "<a class='bubble' href='{$url}' target='_blank'>{$artist} - {$title}</a>";
    }
    if (count($albums) > 1) {
        $last_album = array_pop($albums);
        $output = implode(' ', $albums) . ' und ' . $last_album;
    } else {
        $output = $albums[0];
    }
    return $output;
}
add_shortcode('nowscr_lastfm_top_albums', 'nowscr_lastfm_top_albums_shortcode');

function nowscr_lastfm_top_tracks_shortcode()
{
    $count = get_option('top_tracks_count', 5); // Default 5 tracks
    $period = get_option('time_period', '7day'); // Default last 7 days
    $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;
    // Versuchen Sie, die Top-Tracks aus dem Transient Cache abzurufen
    $data = get_transient('lastfm_top_tracks');
    // Wenn die Top-Tracks nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($data === false) {
        $data = fetch_lastfm_data('toptracks', $count, $period);
        // Wenn ein Fehler auftritt, geben Sie die Fehlermeldung zurück
        if ($data === null) {
            return '<em>Komisch, es wurden keine Titel gefunden</em>';
        }
        // Speichern Sie die Top-Tracks für 1 Stunde im Cache
        set_transient('lastfm_top_tracks', $data, $cache_duration);
    }
    $tracks = array();
    foreach ($data['toptracks']['track'] as $track) {
        $artist = esc_html($track['artist']['name']);
        $title = esc_html($track['name']);
        $url = esc_url($track['url']);
        $tracks[] = "<a class='bubble' href='{$url}' target='_blank'>{$artist} - {$title}</a>";
    }
    if (count($tracks) > 1) {
        $last_track = array_pop($tracks);
        $output = implode(' ', $tracks) . ' und ' . $last_track;
    } else {
        $output = $tracks[0];
    }
    return $output;
}
add_shortcode('nowscr_lastfm_top_tracks', 'nowscr_lastfm_top_tracks_shortcode');

function nowscr_lastfm_lovedtracks_shortcode()
{
    $count = get_option('lovedtracks_count', 3); // Default 5 top tracks
    $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;
    // Versuchen Sie, die Top-Tracks aus dem Transient Cache abzurufen
    $data = get_transient('lastfm_loved_tracks');
    // Wenn die Top-Tracks nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($data === false) {
        $data = fetch_lastfm_data('lovedtracks', $count);
        // Wenn ein Fehler auftritt, geben Sie die Fehlermeldung zurück
        if ($data === null) {
            return '<em>Komisch, es wurden keine geliebten Titel gefunden</em>';
        }
        // Speichern Sie die Top-Tracks für 1 Stunde im Cache
        set_transient('lastfm_loved_tracks', $data, $cache_duration);
    }
    $tracks = array();
    foreach ($data['lovedtracks']['track'] as $track) {
        $artist = esc_html($track['artist']['name']);
        $title = esc_html($track['name']);
        $url = esc_url($track['url']);
        $tracks[] = "<a class='bubble' href='{$url}' target='_blank'>{$artist} - {$title}</a>";
    }
    if (count($tracks) > 1) {
        $last_track = array_pop($tracks);
        $output = implode(' ', $tracks) . ' und ' . $last_track;
    } else {
        $output = $tracks[0];
    }
    return $output;
}
add_shortcode('nowscr_lastfm_lovedtracks', 'nowscr_lastfm_lovedtracks_shortcode');

// TRAKT.TV

// Fetch and display Trakt.tv activities
function nowscrobbling_fetch_trakt_activities()
{
    $transient_key = 'my_trakt_tv_activities';
    $cache_duration = get_option('trakt_cache_duration', 5); // Default 5 Minuten
    $activity_limit = get_option('trakt_activity_limit', 25); // Default 25 Aktivitäten
    // Überprüfen, ob die Daten bereits im Cache vorhanden sind
    if (false === ($activities = get_transient($transient_key))) {
        $client_id = get_option('trakt_client_id');
        $user = get_option('trakt_user');
        $url = "https://api.trakt.tv/users/{$user}/history?limit={$activity_limit}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'trakt-api-version' => '2',
                'trakt-api-key' => $client_id
            ]
        ]);
        // Fehlerbehandlung für die API-Anfrage
        if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response)) {
            // Im Fehlerfall nicht cachen und eine Fehlermeldung zurückgeben
            return '<em>Fehler beim Abrufen der Aktivitäten von trakt.tv</em>';
        }
        // Extrahieren der Antwortdaten
        $activities = json_decode(wp_remote_retrieve_body($response), true);
        // Prüfen, ob die Antwort gültige Daten enthält
        if (empty($activities)) {
            return '<em>Keine Aktivitäten gefunden oder Fehler in der Antwort von trakt.tv</em>';
        }
        // Nur im Erfolgsfall cachen
        set_transient($transient_key, $activities, $cache_duration * MINUTE_IN_SECONDS);
    }
    return $activities;
}
function nowscr_trakt_indicator_shortcode()
{
    $client_id = get_option('trakt_client_id');
    $user = get_option('trakt_user');
    $watching_url = "https://api.trakt.tv/users/{$user}/watching";
    $response = wp_remote_get($watching_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => $client_id
        ]
    ]);
    if (is_wp_error($response)) {
        return '<em>Fehler beim Überprüfen der aktuellen Wiedergabe auf trakt.tv</em>';
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    // Überprüfen, ob Daten vorhanden sind, was bedeutet, dass eine Wiedergabe läuft
    if (!empty($data)) {
        return "<strong>Scrobbelt gerade</strong>";
    }
    // Keine laufende Wiedergabe, zeigen Sie das letzte Aktivitätsdatum an
    $cache_duration = get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS;
    $activities = get_transient('nowscrobbling_trakt_activities');
    if ($activities === false) {
        $activities = nowscrobbling_fetch_trakt_activities();
        set_transient('nowscrobbling_trakt_activities', $activities, $cache_duration);
    }
    if (empty($activities)) {
        return "<em>Komisch, es wurden keine kürzlichen Scrobbles gefunden</em>";
    }
    $lastActivity = reset($activities);
    $lastActivityDate = $lastActivity['watched_at'];
    $date = new DateTime($lastActivityDate);
    $date->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));
    return 'Zuletzt geschaut: ' . $date->format(get_option('date_format') . ' ' . get_option('time_format'));
}
add_shortcode('nowscr_trakt_indicator', 'nowscr_trakt_indicator_shortcode');

function nowscr_trakt_history_shortcode()
{
    $client_id = get_option('trakt_client_id');
    $user = get_option('trakt_user');
    $watching_url = "https://api.trakt.tv/users/{$user}/watching";
    $response = wp_remote_get($watching_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => $client_id
        ]
    ]);
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
        $watching = json_decode(wp_remote_retrieve_body($response), true);
        // Überprüfen, ob eine aktuelle Wiedergabe vorhanden ist
        if (!empty($watching)) {
            // Wiedergabe ist aktiv, Daten für die aktuelle Wiedergabe ausgeben
            $type = $watching['type'];
            $title = $type == 'movie' ? "{$watching['movie']['title']} ({$watching['movie']['year']})" : "{$watching['show']['title']} - S{$watching['episode']['season']}E{$watching['episode']['number']}: {$watching['episode']['title']}";
            $link = $type == 'movie' ? "https://trakt.tv/movies/{$watching['movie']['ids']['slug']}" : "https://trakt.tv/shows/{$watching['show']['ids']['slug']}/seasons/{$watching['episode']['season']}/episodes/{$watching['episode']['number']}";
            $nowPlaying = '<img src="' . plugins_url('public/images/nowplaying.gif', __FILE__) . '" alt="NOW PLAYING" /> ';
            return "<span class='bubble'>{$nowPlaying}<a href='{$link}' target='_blank'>{$title}</a></span>";
        }
    }
    // Keine laufende Wiedergabe, greife auf die letzte Aktivität zurück
    $activities = nowscrobbling_fetch_trakt_activities();
    if (empty($activities)) {
        return "<em>Keine kürzlichen Aktivitäten gefunden</em>";
    }
    // Letzte Aktivität ausgeben
    $lastActivity = reset($activities);
    $type = $lastActivity['type'];
    $title = $type == 'movie' ? "{$lastActivity['movie']['title']} ({$lastActivity['movie']['year']})" : "{$lastActivity['show']['title']} - S{$lastActivity['episode']['season']}E{$lastActivity['episode']['number']}: {$lastActivity['episode']['title']}";
    $link = $type == 'movie' ? "https://trakt.tv/movies/{$lastActivity['movie']['ids']['slug']}" : "https://trakt.tv/shows/{$lastActivity['show']['ids']['slug']}/seasons/{$lastActivity['episode']['season']}/episodes/{$lastActivity['episode']['number']}";
    return "<span class='bubble'><a href='{$link}' target='_blank'>{$title}</a></span>";
}
add_shortcode('nowscr_trakt_history', 'nowscr_trakt_history_shortcode');

function nowscr_trakt_last_movie_shortcode()
{
    $client_id = get_option('trakt_client_id');
    $user = get_option('trakt_user');
    // Hole die Einstellung für die Anzahl der letzten Filme
    $movies_count = get_option('last_movies_count', 3); // Standardwert ist 3, falls nicht gesetzt
    $watched_movies_url = "https://api.trakt.tv/users/{$user}/history/movies?limit={$movies_count}";
    $response = wp_remote_get($watched_movies_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => $client_id
        ]
    ]);
    if (is_wp_error($response)) {
        return '<em>Fehler beim Abrufen der letzten Filme von trakt.tv</em>';
    }
    $movies = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($movies)) {
        return '<em>Keine Filme gefunden</em>';
    }
    // Erstelle Ausgaben für alle abgerufenen Filme
    $output = '';
    $movie_items = [];
    foreach ($movies as $movie) {
        $title = $movie['movie']['title'] . ' (' . $movie['movie']['year'] . ')';
        $link = "https://trakt.tv/movies/" . $movie['movie']['ids']['slug'];
        $movie_items[] = "<a class='bubble' href='{$link}' target='_blank'>{$title}</a>";
    }

    if (count($movie_items) > 1) {
        // Wenn es mehr als einen Film gibt, füge sie mit Komma und " und " für das letzte Element zusammen
        $last_movie = array_pop($movie_items); // Entferne und speichere das letzte Element
        $output = implode(' ', $movie_items) . ' und ' . $last_movie;
    } else {
        // Wenn nur ein Film vorhanden ist, gib diesen direkt zurück
        $output = $movie_items[0];
    }

    return $output;
}
add_shortcode('nowscr_trakt_last_movie', 'nowscr_trakt_last_movie_shortcode');

function nowscr_trakt_last_movie_with_rating_shortcode()
{
    $client_id = get_option('trakt_client_id');
    $user = get_option('trakt_user');
    // Einstellung für die Anzahl der letzten Filme
    $movies_count = get_option('last_movies_count', 3);
    $watched_movies_url = "https://api.trakt.tv/users/{$user}/history/movies?limit={$movies_count}";
    $ratings_url = "https://api.trakt.tv/users/{$user}/ratings/movies";

    $response_watched = wp_remote_get($watched_movies_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => $client_id
        ]
    ]);
    
    $response_ratings = wp_remote_get($ratings_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => $client_id
        ]
    ]);

    if (is_wp_error($response_watched) || is_wp_error($response_ratings)) {
        return '<em>Fehler beim Abrufen der Daten von trakt.tv</em>';
    }

    $movies = json_decode(wp_remote_retrieve_body($response_watched), true);
    $ratings = json_decode(wp_remote_retrieve_body($response_ratings), true);
    
    if (empty($movies)) {
        return '<em>Keine Filme gefunden</em>';
    }

    // Erstelle Ausgaben für alle abgerufenen Filme
    $movie_items = [];
    foreach ($movies as $movie) {
        $movie_id = $movie['movie']['ids']['trakt'];
        $title = $movie['movie']['title'] . ' (' . $movie['movie']['year'] . ')';
        $link = "https://trakt.tv/movies/" . $movie['movie']['ids']['slug'];

        // Suche nach der Bewertung für jeden Film
        $rating_text = '';
        foreach ($ratings as $rating) {
            if ($rating['movie']['ids']['trakt'] === $movie_id) {
                $rating_text = "<span style='font-style: italic; font-weight: bold;'>" . $rating['rating'] . "/10</span>";
                break;
            }
        }
        $movie_items[] = "<span class='bubble'><a href='{$link}' target='_blank'>{$title}</a> {$rating_text}</span>";
    }

    $output = '';
    if (count($movie_items) > 1) {
        // Wenn es mehr als einen Film gibt, füge sie mit Komma und " und " für das letzte Element zusammen
        $last_movie_item = array_pop($movie_items); // Entferne und speichere das letzte Element
        $output = implode(' ', $movie_items) . ' und ' . $last_movie_item;
    } else {
        // Wenn nur ein Film vorhanden ist, gib diesen direkt zurück
        $output = $movie_items[0];
    }

    return $output;
}
add_shortcode('nowscr_trakt_last_movie_with_rating', 'nowscr_trakt_last_movie_with_rating_shortcode');

function nowscr_trakt_last_show_shortcode()
{
    $client_id = get_option('trakt_client_id');
    $user = get_option('trakt_user');
    // Einstellung für die Anzahl der letzten Aktivitäten bezüglich Serien
    $shows_count = get_option('last_shows_count', 3); // Standardwert ist 3, falls nicht gesetzt
    $history_shows_url = "https://api.trakt.tv/users/{$user}/history/shows?limit={$shows_count}";

    $response = wp_remote_get($history_shows_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => $client_id
        ]
    ]);
    
    if (is_wp_error($response)) {
        return '<em>Fehler beim Abrufen der letzten Serienaktivitäten von trakt.tv</em>';
    }
    
    $activities = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($activities)) {
        return '<em>Keine Serienaktivitäten gefunden</em>';
    }
    
    // Erstelle Ausgaben für die letzten Serienaktivitäten
    $show_items = [];
    foreach ($activities as $activity) {
        $title = $activity['show']['title'] . ' (' . $activity['show']['year'] . ')'; // Hinzufügen der Jahreszahl
        $season = $activity['episode']['season'];
        $episode = $activity['episode']['number'];
        $episodeTitle = $activity['episode']['title'];
        $link = "https://trakt.tv/shows/" . $activity['show']['ids']['slug'];
        $show_items[] = "<a class='bubble' href='{$link}' target='_blank'>{$title}</a>";
    }

    $output = '';
    if (count($show_items) > 1) {
        // Wenn es mehr als eine Serie gibt, füge sie mit Komma und " und " für das letzte Element zusammen
        $last_show_item = array_pop($show_items); // Entferne und speichere das letzte Element
        $output = implode(', ', $show_items) . ' und ' . $last_show_item;
    } else {
        // Wenn nur eine Serie vorhanden ist, gib diese direkt zurück
        $output = $show_items[0];
    }

    return $output;
}
add_shortcode('nowscr_trakt_last_show', 'nowscr_trakt_last_show_shortcode');

function nowscr_trakt_last_episode_shortcode()
{
    $client_id = get_option('trakt_client_id');
    $user = get_option('trakt_user');
    // Einstellung für die Anzahl der letzten Episoden
    $episodes_count = get_option('last_episodes_count', 3); // Standardwert ist 3, falls nicht gesetzt
    $history_episodes_url = "https://api.trakt.tv/users/{$user}/history/episodes?limit={$episodes_count}";

    $response = wp_remote_get($history_episodes_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => $client_id
        ]
    ]);
    
    if (is_wp_error($response)) {
        return '<em>Fehler beim Abrufen der letzten Episoden von trakt.tv</em>';
    }
    
    $episodes = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($episodes)) {
        return '<em>Keine Episoden gefunden</em>';
    }
    
    // Erstelle Ausgaben für die letzten Episoden
    $episode_items = [];
    foreach ($episodes as $episode) {
        $title = $episode['show']['title'];
        $season = $episode['episode']['season'];
        $episodeNumber = $episode['episode']['number'];
        $episodeTitle = $episode['episode']['title'];
        $link = "https://trakt.tv/shows/" . $episode['show']['ids']['slug'] . "/seasons/{$season}/episodes/{$episodeNumber}";
        $episode_items[] = "<a class='bubble' href='{$link}' target='_blank'>S{$season}E{$episodeNumber}: {$episodeTitle}</a>";
    }

    $output = '';
    if (count($episode_items) > 1) {
        // Wenn es mehr als eine Episode gibt, füge sie mit Komma und " und " für das letzte Element zusammen
        $last_episode_item = array_pop($episode_items); // Entferne und speichere das letzte Element
        $output = implode(', ', $episode_items) . ' und ' . $last_episode_item;
    } else {
        // Wenn nur eine Episode vorhanden ist, gib diese direkt zurück
        $output = $episode_items[0];
    }

    return $output;
}
add_shortcode('nowscr_trakt_last_episode', 'nowscr_trakt_last_episode_shortcode');

