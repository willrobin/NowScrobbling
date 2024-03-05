<?php
/*
Plugin Name: Now Playing
Plugin URI: https://robinwill.de
Description: Ein einfaches Plugin, um API-Einstellungen für last.fm und trakt.tv zu verwalten und deren letzte Aktivitäten anzuzeigen.
Version: 1.0
Author: Robin Will
Author URI: https://robinwill.de
*/

// Hook into WordPress admin menu to add the plugin settings page
add_action('admin_menu', 'now_playing_create_menu');

// Creates the settings menu item
function now_playing_create_menu() {
    add_options_page('Now Playing Einstellungen', 'Now Playing', 'manage_options', 'now-playing-settings', 'now_playing_settings_page');
    add_action('admin_init', 'register_now_playing_settings');
}

// Register settings for the plugin
function register_now_playing_settings() {
    // General settings
    register_setting('now-playing-settings-group', 'lastfm_api_key');
    register_setting('now-playing-settings-group', 'lastfm_user');
    register_setting('now-playing-settings-group', 'trakt_client_id');
    register_setting('now-playing-settings-group', 'trakt_user');

    // Cache duration settings
    register_setting('now-playing-settings-group', 'lastfm_cache_duration');
    register_setting('now-playing-settings-group', 'trakt_cache_duration');

    // Activity limit settings
    register_setting('now-playing-settings-group', 'lastfm_activity_limit');
    register_setting('now-playing-settings-group', 'trakt_activity_limit');
}

// The settings page content
function now_playing_settings_page() {
    // Clear cache if requested
    if (isset($_POST['clear_cache'])) {
        delete_transient('my_lastfm_scrobbles');
        delete_transient('my_trakt_tv_activities');
        echo '<div class="updated"><p>Cache geleert.</p></div>';
    }
?>
<div class="wrap">
    <h1>Now Playing Einstellungen</h1>

    <form method="post" action="options.php">
        <?php settings_fields('now-playing-settings-group'); ?>
        <?php do_settings_sections('now-playing-settings-group'); ?>
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
                <th scope="row">Trakt.tv Client ID</th>
                <td><input type="text" name="trakt_client_id" value="<?php echo esc_attr(get_option('trakt_client_id')); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Trakt.tv Benutzername</th>
                <td><input type="text" name="trakt_user" value="<?php echo esc_attr(get_option('trakt_user')); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Last.fm Cache-Dauer (Minuten)</th>
                <td><input type="number" name="lastfm_cache_duration" value="<?php echo esc_attr(get_option('lastfm_cache_duration', 15)); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Trakt.tv Cache-Dauer (Minuten)</th>
                <td><input type="number" name="trakt_cache_duration" value="<?php echo esc_attr(get_option('trakt_cache_duration', 15)); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Anzahl der last.fm Aktivitäten</th>
                <td><input type="number" name="lastfm_activity_limit" value="<?php echo esc_attr(get_option('lastfm_activity_limit', 3)); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Anzahl der trakt.tv Aktivitäten</th>
                <td><input type="number" name="trakt_activity_limit" value="<?php echo esc_attr(get_option('trakt_activity_limit', 3)); ?>" /></td>
            </tr>
        </table>
        
        <?php submit_button('Speichern'); ?>
    </form>
    <form method="post">
        <input type="submit" name="clear_cache" value="Cache leeren" class="button">
    </form>
    
    <!-- Display available shortcodes -->
    <h2>Verfügbare Shortcodes</h2>
    <p>Du kannst diese Shortcodes verwenden, um Inhalte in Beiträgen, Seiten oder Widgets anzuzeigen:</p>
    <ul>
        <li><code>[lastfm_scrobbles]</code> - Zeigt die letzten Scrobbles von Last.fm an.</li>
        <li><code>[trakt_activities]</code> - Zeigt die letzten Aktivitäten von Trakt.tv an.</li>
        <li><code>[lastfm_last_activity]</code> - Zeigt die Zeit der letzten Last.fm Aktivität an oder "NOW PLAYING".</li>
        <li><code>[trakt_last_activity]</code> - Zeigt die Zeit der letzten Trakt.tv Aktivität an.</li>
    </ul>
</div>
<?php
}

// Add styles for the plugin
function now_playing_styles() {
    ?>
    <style type="text/css">
        .lastfm-scrobbles ol, .lastfm-scrobbles ul, .lastfm-scrobbles li, 
        .trakt-tv-activities ol, .trakt-tv-activities ul, .trakt-tv-activities li {
            list-style-type: none !important;
            padding-left: 0 !important;
            margin: 0 !important;
			margin-left: 0 !important;
        }
        .lastfm-scrobbles li a, .trakt-tv-activities li a {
            text-decoration: none;
        }
        .now-playing {
            background-color: rgba(38, 144, 255, 0.1); /* Hintergrundfarbe für den aktuell laufenden Track */
			border-radius: 5px; /* Abgerundete Ecken */
            padding: 0;
            margin-bottom: 0px;
            display: block;
        }
        .now-playing img {
            vertical-align: middle;
            margin-left: 10px;
			margin-right: 5px;
			margin-bottom: 0 !important;
        }
		ol, ul, li {
            margin: 0 !important; /* Entfernt den linken Außenabstand */
        }

        /* Weitere individuelle Stile hier hinzufügen */
    </style>
    <?php
}
add_action('wp_head', 'now_playing_styles');

// Fetch and display Last.fm scrobbles
function now_playing_fetch_lastfm_scrobbles() {
    $transient_key = 'my_lastfm_scrobbles';
    $cache_duration = get_option('lastfm_cache_duration', 15); // Default 15 minutes
    $activity_limit = get_option('lastfm_activity_limit', 3); // Default 3 activities

    if (false === ($scrobbles = get_transient($transient_key))) {
        $api_key = get_option('lastfm_api_key');
        $user = get_option('lastfm_user');
        $url = "http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user={$user}&api_key={$api_key}&limit={$activity_limit}&format=json";

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return 'Fehler beim Abrufen der Scrobbles.';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        $scrobbles = isset($data->recenttracks->track) ? $data->recenttracks->track : [];

        set_transient($transient_key, $scrobbles, $cache_duration * MINUTE_IN_SECONDS);
    }

    return $scrobbles;
}

// Fetch and display Trakt.tv activities
function now_playing_fetch_trakt_activities() {
    $transient_key = 'my_trakt_tv_activities';
    $cache_duration = get_option('trakt_cache_duration', 15); // Default 15 minutes
    $activity_limit = get_option('trakt_activity_limit', 3); // Default 3 activities

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

        if (is_wp_error($response)) {
            return 'Fehler beim Abrufen der Aktivitäten von trakt.tv.';
        }

        $activities = json_decode(wp_remote_retrieve_body($response), true);

        set_transient($transient_key, $activities, $cache_duration * MINUTE_IN_SECONDS);
    }

    return $activities;
}

// Define shortcodes for displaying activities and last activity times
function now_playing_lastfm_shortcode() {
    $scrobbles = now_playing_fetch_lastfm_scrobbles();
    $output = '<ul class="lastfm-scrobbles">';
    foreach ($scrobbles as $track) {
        $artist = esc_html($track->artist->{'#text'});
        $song = esc_html($track->name);
        $url = esc_url($track->url);
        $nowPlaying = '';
        if (isset($track->{'@attr'}) && $track->{'@attr'}->nowplaying == 'true') {
            $nowPlaying = '<img src="' . plugins_url('nowplaying.gif', __FILE__) . '" alt="NOW PLAYING" /> ';
            $output .= "<li class='now-playing'>{$nowPlaying}<a href='{$url}' target='_blank'>{$artist} - {$song}</a></li>";
        } else {
            $output .= "<li><a href='{$url}' target='_blank'>{$artist} - {$song}</a></li>";
        }
    }
    $output .= '</ul>';
    return $output;
}
add_shortcode('lastfm_scrobbles', 'now_playing_lastfm_shortcode');

function now_playing_trakt_shortcode() {
    $activities = now_playing_fetch_trakt_activities();
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
add_shortcode('trakt_activities', 'now_playing_trakt_shortcode');

function now_playing_lastfm_last_activity_shortcode() {
    $scrobbles = now_playing_fetch_lastfm_scrobbles();
    if (empty($scrobbles)) {
        return "Keine Scrobbles gefunden.";
    }

    foreach ($scrobbles as $track) {
        if (isset($track->{'@attr'}) && $track->{'@attr'}->nowplaying == 'true') {
            return "Scrobbelt gerade";
        }
    }

    $lastScrobble = reset($scrobbles); // Nimmt den ersten Scrobble aus der Liste
    if (isset($lastScrobble->date)) {
        $lastScrobbleTimestamp = $lastScrobble->date->uts;
        $dateTime = new DateTime("@$lastScrobbleTimestamp");
        $dateTime->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));
        return $dateTime->format(get_option('date_format') . ' ' . get_option('time_format'));
    } else {
        return "Keine kürzlichen Scrobbles gefunden.";
    }
}
add_shortcode('lastfm_last_activity', 'now_playing_lastfm_last_activity_shortcode');

function now_playing_trakt_last_activity_shortcode() {
    $activities = now_playing_fetch_trakt_activities();
    if (empty($activities)) {
        return "Keine Aktivitäten gefunden.";
    }

    // Angenommen, die erste Aktivität im Array ist die letzte Aktivität
    $lastActivity = reset($activities);
    $lastActivityDate = $lastActivity['watched_at'];
    $date = new DateTime($lastActivityDate);
    $date->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));

    return $date->format(get_option('date_format') . ' ' . get_option('time_format'));
}
add_shortcode('trakt_last_activity', 'now_playing_trakt_last_activity_shortcode');
