<?php
/**
 * Plugin Name:         NowScrobbling
 * Plugin URI:          https://github.com/willrobin/NowScrobbling
 * Description:         NowScrobbling ist ein einfaches WordPress-Plugin, um API-Einstellungen für last.fm und trakt.tv zu verwalten und deren letzte Aktivitäten anzuzeigen.
 * Version:             1.0.5
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

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action('admin_menu', 'nowscrobbling_admin_menu');
add_action('admin_init', 'nowscrobbling_register_settings');

function nowscrobbling_admin_menu() {
    add_options_page('NowScrobbling Einstellungen', 'NowScrobbling', 'manage_options', 'nowscrobbling-settings', 'nowscrobbling_settings_page');
}

function nowscrobbling_register_settings() {
    $settings = [
        'lastfm_api_key', 'lastfm_user', 'trakt_client_id', 'trakt_user',
        'top_tracks_count', 'top_artists_count', 'top_albums_count', 'lovedtracks_count',
        'time_period', 'cache_duration', 'lastfm_cache_duration', 'trakt_cache_duration',
        'lastfm_activity_limit', 'trakt_activity_limit'
    ];

    foreach ($settings as $setting) {
        register_setting('nowscrobbling-settings-group', $setting);
    }
}

function nowscrobbling_add_settings_fields() {
    // Add settings fields for top tracks, artists, albums, lovedtracks
    add_settings_field('top_tracks_count', 'Anzahl der Top-Titel', 'nowscrobbling_top_tracks_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_artists_count', 'Anzahl der Top-Künstler', 'nowscrobbling_top_artists_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_albums_count', 'Anzahl der Top-Alben', 'nowscrobbling_top_albums_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('lovedtracks_count', 'Anzahl der Lieblingslieder', 'nowscrobbling_lovedtracks_count_callback', 'nowscrobbling', 'nowscrobbling_section');

    // Add settings field for time period
    add_settings_field('time_period', 'Zeitraum', 'nowscrobbling_time_period_callback', 'nowscrobbling', 'nowscrobbling_section');

    // Add settings field for transient cache duration
    add_settings_field('cache_duration', 'Dauer des Transient-Cache', 'nowscrobbling_cache_duration_callback', 'nowscrobbling', 'nowscrobbling_section');
}

    // Callback functions for settings fields
    function nowscrobbling_top_tracks_count_callback() {
        $setting = esc_attr(get_option('top_tracks_count', 5));
        echo "<input type='number' name='top_tracks_count' value='$setting' min='1' />";
    }

    // Callback-Funktion für 'top_artists_count'
    function nowscrobbling_top_artists_count_callback() {
        $setting = esc_attr(get_option('top_artists_count', 5));
        echo "<input type='number' name='top_artists_count' value='$setting' min='1' />";
    }

    // Callback-Funktion für 'top_albums_count'
    function nowscrobbling_top_albums_count_callback() {
        $setting = esc_attr(get_option('top_albums_count', 5));
        echo "<input type='number' name='top_albums_count' value='$setting' min='1' />";
    }

    // Callback-Funktion für 'lovedtracks_count'
    function nowscrobbling_lovedtracks_count_callback() {
        $setting = esc_attr(get_option('lovedtracks_count', 5));
        echo "<input type='number' name='lovedtracks_count' value='$setting' min='1' />";
    }

    // Callback-Funktion für 'time_period'
    function nowscrobbling_time_period_callback() {
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
    function nowscrobbling_cache_duration_callback() {
        $setting = esc_attr(get_option('cache_duration', 15));
        echo "<input type='number' name='cache_duration' value='$setting' min='1' />";
    }

// The settings page content
function nowscrobbling_settings_page() {
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
                <th scope="row">Zeitraum</th>
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
    <h3>Last.fm</h2>
    <ul>
        <li><code>[nowscr_lastfm_indicator]</code>          - Zeigt den Status der Last.fm Aktivität an.</li>
        <li><code>[nowscr_lastfm_history]</code>            - Zeigt die letzten Scrobbles von Last.fm an.</li>
        <li><code>[nowscr_lastfm_top_artists]</code>        - Zeigt die Top-Künstler von Last.fm im gewählten Zeitraum an.</li>
        <li><code>[nowscr_lastfm_top_albums]</code>         - Zeigt die Top-Alben von Last.fm im gewählten Zeitraum an.</li>
        <li><code>[nowscr_lastfm_top_tracks]</code>         - Zeigt die Top-Titel von Last.fm im gewählten Zeitraum an.</li>
        <li><code>[nowscr_lastfm_lovedtracks]</code>        - Zeigt die Lieblingslieder von Last.fm an.</li>
    </ul>
    <h3>Trakt</h2>
    <ul>
        <li><code>[nowscr_trakt_indicator]</code>           - Zeigt den Status der Trakt Aktivität an.</li>
        <li><code>[nowscr_trakt_history]</code>             - Zeigt die letzten Scrobbles von Trakt an.</li>
        <li><code>[nowscr_trakt_last_movie]</code>          - Zeigt den letzten Film von Trakt an.</li>
        <li><code>[nowscr_trakt_last_show]</code>           - Zeigt die letzte Serie von Trakt an.</li>
        <li><code>[nowscr_trakt_last_episode]</code>        - Zeigt die letzte Episode von Trakt an.</li>
        
</div>
    <!-- Beginn der Vorschau-Bereich -->
    <div class="wrap">
        <h1>Vorschau der Daten</h1>
        <div id="nowscrobbling-preview">
            <h2>Last.fm</h2>
            <h3>Status (Indicator)</h3>
            <?php echo nowscr_lastfm_indicator_shortcode(); // Zeigt den Status der Last.fm Aktivität an. ?>
            <h3>Letzte Scrobbles (History)</h3>
            <?php echo nowscr_lastfm_history_shortcode(); // Zeigt die letzten Scrobbles von Last.fm an. ?>
            <h3>Top-Künstler</h3>
            <?php echo nowscr_lastfm_top_artists_shortcode(); // Zeigt die Top-Künstler von Last.fm im gewählten Zeitraum an. ?>
            <h3>Top-Alben</h3>
            <?php echo nowscr_lastfm_top_albums_shortcode(); // Zeigt die Top-Alben von Last.fm im gewählten Zeitraum an. ?>
            <h3>Top-Titel</h3>
            <?php echo nowscr_lastfm_top_tracks_shortcode(); // Zeigt die Top-Titel von Last.fm im gewählten Zeitraum an. ?>
            <h3>Lieblingslieder</h3>
            <?php echo nowscr_lastfm_lovedtracks_shortcode(); // Zeigt die Lieblingslieder von Last.fm an. ?>

            <h2>Trakt</h2>
            <h3>Status (Indicator)</h3>
            <?php echo nowscr_trakt_indicator_shortcode(); // Zeigt den Status der Trakt Aktivität an. ?>
            <h3>Letzte Scrobbles (History)</h3>
            <?php echo nowscr_trakt_history_shortcode(); // Zeigt die letzten Scrobbles von Trakt an. ?>
            <h3>Letzter Film</h3>
            <?php echo nowscr_trakt_last_movie_shortcode(); // Zeigt den letzten Film von Trakt an. ?>
            <h3>Letzter Serie</h3>
            <?php echo nowscr_trakt_last_show_shortcode(); // Zeigt die letzte Serie von Trakt an. ?>
            <h3>Letzte Episode</h3>
            <?php echo nowscr_trakt_last_episode_shortcode(); // Zeigt die letzte Episode von Trakt an. ?>
        </div>
    </div>
    <!-- Ende des Vorschau-Bereichs -->
    <?php
}

// CSS — Add styles for the plugin
function nowscrobbling_styles() {
    ?>
    <style type="text/css">
        .lastfm-scrobbles ol, .lastfm-scrobbles ul, .lastfm-scrobbles li, 
        .trakt-tv-activities ol, .trakt-tv-activities ul, .trakt-tv-activities li,
        .top-tracks ol, .top-tracks ul, .top-tracks li,
        .top-artists ol, .top-artists ul, .top-artists li,
        .top-albums ol, .top-albums ul, .top-albums li,
        .lovedtracks ol, .lovedtracks ul, .lovedtracks li {
            list-style-type: none !important;
            padding-left: 0 !important;
            margin: 0 !important;
            margin-left: 0 !important;
        }
        .lastfm-scrobbles li a, .trakt-tv-activities li a,
        .top-tracks li a, .top-artists li a, .top-albums li a,
        .lovedtracks li a,{
            text-decoration: none;
        }
        .nowscrobbling {
            background-color: rgba(38, 144, 255, 0.1); /* Hintergrundfarbe für den aktuell laufenden Track */
            border-radius: 5px; /* Abgerundete Ecken */
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
        ol, ul, li {
            margin: 0 !important; /* Entfernt den linken Außenabstand */
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
function nowscrobbling_fetch_lastfm_scrobbles() {
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

function fetch_lastfm_data($type, $count = null, $period = null) {
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

// SHORTCODES

// last.fm

function nowscr_lastfm_indicator_shortcode() {
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


/* // Define shortcodes for displaying activities and last activity times
function nowscr_lastfm_history_shortcode() {
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;

    // Versuchen Sie, die Scrobbles aus dem Transient Cache abzurufen
    $scrobbles = get_transient('nowscrobbling_lastfm_scrobbles');

    // Wenn die Scrobbles nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($scrobbles === false) {
        $scrobbles = nowscrobbling_fetch_lastfm_scrobbles();
        // Speichern Sie die Scrobbles für 1 Stunde im Cache
        set_transient('nowscrobbling_lastfm_scrobbles', $scrobbles, $cache_duration);
    }

    $output = '<ul class="lastfm-scrobbles">';
    // Überprüfen Sie, ob $scrobbles ein Array ist
    if (is_array($scrobbles)) {
        foreach ($scrobbles as $track) {
            $artist = esc_html($track->artist->{'#text'});
            $song = esc_html($track->name);
            $url = esc_url($track->url);
            $nowPlaying = '';
            if (isset($track->{'@attr'}) && $track->{'@attr'}->nowplaying == 'true') {
                $nowPlaying = '<img src="' . plugins_url('public/images/nowplaying.gif', __FILE__) . '" alt="NOW PLAYING" /> ';
                $output .= "<li class='now-playing'>{$nowPlaying} <a href='{$url}' target='_blank'>{$artist} - {$song}</a></li>";
            } else {
                $output .= "<li><a href='{$url}' target='_blank'>{$artist} - {$song}</a></li>";
            }
        }
    }
    $output .= '</ul>';
    return $output;
}
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode'); */

function nowscr_lastfm_history_shortcode() {
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
                $output = "<strong>Jetzt</strong> läuft <span class='bubble'>{$nowPlaying}<a href='{$url}' target='_blank'>{$artist} - {$song}</a></span>";
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
        $output = "Zuletzt lief <a class='bubble' href='{$url}' target='_blank'>{$artist} - {$song}</a>";
    }

    return $output;
}
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode');

/* function nowscr_lastfm_top_artists_shortcode() {
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
        // Speichern Sie die Top-Künstler für 1 Stunde im Cache
        set_transient('lastfm_top_artists', $data, $cache_duration);
    }

    $output = '<ul class="top-artists">'; // Add the CSS class here
    foreach ($data['topartists']['artist'] as $artist) {
        $name = esc_html($artist['name']);
        $url = esc_url($artist['url']);

        $output .= "<li><a href='{$url}' target='_blank'>{$name}</a></li>";
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode('nowscr_lastfm_top_artists', 'nowscr_lastfm_top_artists_shortcode'); */

function nowscr_lastfm_top_artists_shortcode() {
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

function nowscr_lastfm_top_albums_shortcode() {
    $count = get_option('top_albums_count', 5); // Default 5 albums
    $period = get_option('time_period', '7day'); // Default last 7 days
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

    $output = '<ul class="top-albums">'; // Add the CSS class here
    foreach ($data['topalbums']['album'] as $album) {
        $artist = esc_html($album['artist']['name']);
        $title = esc_html($album['name']);
        $url = esc_url($album['url']);

        $output .= "<li><a href='{$url}' target='_blank'>{$artist} - {$title}</a></li>";
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode('nowscr_lastfm_top_albums', 'nowscr_lastfm_top_albums_shortcode');

/* function nowscr_lastfm_top_tracks_shortcode() {
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

    $output = '<ul class="top-tracks">'; // Add the CSS class here
    foreach ($data['toptracks']['track'] as $track) {
        $artist = esc_html($track['artist']['name']);
        $title = esc_html($track['name']);
        $url = esc_url($track['url']);

        $output .= "<li><a href='{$url}' target='_blank'>{$artist} - {$title}</a></li>";
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode('nowscr_lastfm_top_tracks', 'nowscr_lastfm_top_tracks_shortcode'); */

function nowscr_lastfm_top_tracks_shortcode() {
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

function nowscr_lastfm_lovedtracks_shortcode() {
    $count = get_option('lovedtracks_count', 5); // Default 5 lovedtracks
    $period = get_option('time_period', '7day'); // Default last 7 days
    $cache_duration = get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS;

    // Versuchen Sie, die Lieblingslieder aus dem Transient Cache abzurufen
    $lovedtracks = get_transient('lastfm_lovedtracks');

    // Wenn die Lieblingslieder nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($lovedtracks === false) {
        $lovedtracks = fetch_lastfm_data('lovedtracks', $count, $period);
        // Wenn ein Fehler auftritt, geben Sie die Fehlermeldung zurück
        if ($lovedtracks === null) {
            return '<em>Komisch, es wurden keine Lieblingslieder gefunden</em>';
        }
        // Speichern Sie die Lieblingslieder für 1 Stunde im Cache
        set_transient('lastfm_lovedtracks', $lovedtracks, $cache_duration);
    }

    $output = '<ul class="lovedtracks">'; // Add the CSS class here
    foreach ($lovedtracks['lovedtracks']['track'] as $track) {
        $artist = esc_html($track['artist']['name']);
        $title = esc_html($track['name']);
        $url = esc_url($track['url']);

        $output .= "<li><a href='{$url}' target='_blank'>{$artist} - {$title}</a></li>";
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode('nowscr_lastfm_lovedtracks', 'nowscr_lastfm_lovedtracks_shortcode');


// TRAKT.TV

// Fetch and display Trakt.tv activities
function nowscrobbling_fetch_trakt_activities() {
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

function nowscr_trakt_indicator_shortcode() {
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    $cache_duration = get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS;

    // Versuchen Sie, die Aktivitäten aus dem Transient Cache abzurufen
    $activities = get_transient('nowscrobbling_trakt_activities');

    // Wenn die Aktivitäten nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($activities === false) {
        $activities = nowscrobbling_fetch_trakt_activities();
        // Speichern Sie die Aktivitäten für 1 Stunde im Cache
        set_transient('nowscrobbling_trakt_activities', $activities, $cache_duration);
    }

    if (empty($activities)) {
        return "<em>Komisch, es wurden keine kürzlichen Scrobbles gefunden</em>";
    }

    // Angenommen, die erste Aktivität im Array ist die letzte Aktivität
    $lastActivity = reset($activities);
    $lastActivityDate = $lastActivity['watched_at'];
    $date = new DateTime($lastActivityDate);
    $date->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));

    return 'Zuletzt geschaut: ' . $date->format(get_option('date_format') . ' ' . get_option('time_format'));
}
add_shortcode('nowscr_trakt_indicator', 'nowscr_trakt_indicator_shortcode');

function nowscr_trakt_history_shortcode() {
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    $cache_duration = get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS;

    // Versuchen Sie, die Aktivitäten aus dem Transient Cache abzurufen
    $activities = get_transient('nowscrobbling_trakt_activities');

    // Wenn die Aktivitäten nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($activities === false) {
        $activities = nowscrobbling_fetch_trakt_activities();
        // Speichern Sie die Aktivitäten für 1 Stunde im Cache
        set_transient('nowscrobbling_trakt_activities', $activities, $cache_duration);
    }

    $output = '<ul class="trakt-tv-activities">';
    foreach ($activities as $activity) {
        $type = $activity['type'];
        $title = $type == 'movie' ? "{$activity['movie']['title']} ({$activity['movie']['year']})" : "{$activity['show']['title']} - S{$activity['episode']['season']}E{$activity['episode']['number']} {$activity['episode']['title']}";
        $link = $type == 'movie' ? "https://trakt.tv/movies/{$activity['movie']['ids']['slug']}" : "https://trakt.tv/shows/{$activity['show']['ids']['slug']}/seasons/{$activity['episode']['season']}/episodes/{$activity['episode']['number']}";
        $output .= "<li><a href='{$link}' target='_blank'>{$title}</a></li>";
    }
    $output .= '</ul>';
    return $output;
}
add_shortcode('nowscr_trakt_history', 'nowscr_trakt_history_shortcode');

function nowscr_trakt_last_movie_shortcode() {
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    $cache_duration = get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS;

    // Versuchen Sie, die Aktivitäten aus dem Transient Cache abzurufen
    $activities = get_transient('nowscrobbling_trakt_activities');

    // Wenn die Aktivitäten nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($activities === false) {
        $activities = nowscrobbling_fetch_trakt_activities();
        // Speichern Sie die Aktivitäten für die festgelegte Cache-Dauer im Cache
        set_transient('nowscrobbling_trakt_activities', $activities, $cache_duration);
    }

    // Durchlaufen Sie die Aktivitäten rückwärts, um den letzten Film zu finden
    for ($i = count($activities) - 1; $i >= 0; $i--) {
        if ($activities[$i]['type'] == 'movie') {
            $title = "{$activities[$i]['movie']['title']} ({$activities[$i]['movie']['year']})";
            $link = "https://trakt.tv/movies/{$activities[$i]['movie']['ids']['slug']}";
            return "<a class='bubble' href='{$link}' target='_blank'>{$title}</a>";
        }
    }
    return '<em>Kein letzter Film gefunden</em>';
}
add_shortcode('nowscr_trakt_last_movie', 'nowscr_trakt_last_movie_shortcode');

function nowscr_trakt_last_show_shortcode() {
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    $cache_duration = get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS;

    // Versuchen Sie, die Aktivitäten aus dem Transient Cache abzurufen
    $activities = get_transient('nowscrobbling_trakt_activities');

    // Wenn die Aktivitäten nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($activities === false) {
        $activities = nowscrobbling_fetch_trakt_activities();
        // Speichern Sie die Aktivitäten für die festgelegte Cache-Dauer im Cache
        set_transient('nowscrobbling_trakt_activities', $activities, $cache_duration);
    }

    foreach ($activities as $activity) {
        if ($activity['type'] == 'episode' && isset($activity['show'])) {
            $showTitle = "{$activity['show']['title']}";
            $link = "https://trakt.tv/shows/{$activity['show']['ids']['slug']}";
            return "<a class='bubble' href='{$link}' target='_blank'>{$showTitle}</a>";
        }
    }
    return '<em>Keine letzte Serie gefunden</em>';
}
add_shortcode('nowscr_trakt_last_show', 'nowscr_trakt_last_show_shortcode');

function nowscr_trakt_last_episode_shortcode() {
    // Abrufen der Cache-Dauer aus den WordPress-Optionen
    $cache_duration = get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS;

    // Versuchen Sie, die Aktivitäten aus dem Transient Cache abzurufen
    $activities = get_transient('nowscrobbling_trakt_activities');

    // Wenn die Aktivitäten nicht im Cache sind, rufen Sie sie ab und speichern Sie sie im Cache
    if ($activities === false) {
        $activities = nowscrobbling_fetch_trakt_activities();
        // Speichern Sie die Aktivitäten für die festgelegte Cache-Dauer im Cache
        set_transient('nowscrobbling_trakt_activities', $activities, $cache_duration);
    }

    foreach ($activities as $activity) {
        if ($activity['type'] == 'episode') {
            $episodeTitle = "S{$activity['episode']['season']}E{$activity['episode']['number']} {$activity['episode']['title']}";
            $link = "https://trakt.tv/shows/{$activity['show']['ids']['slug']}/seasons/{$activity['episode']['season']}/episodes/{$activity['episode']['number']}";
            return "<a class='bubble' href='{$link}' target='_blank'>{$episodeTitle}</a>";
        }
    }
    return '<em>Keine letzte Folge gefunden</em>';
}
add_shortcode('nowscr_trakt_last_episode', 'nowscr_trakt_last_episode_shortcode');
