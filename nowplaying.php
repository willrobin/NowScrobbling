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

    register_setting('now-playing-settings-group', 'top_tracks_count');
    register_setting('now-playing-settings-group', 'top_artists_count');
    register_setting('now-playing-settings-group', 'top_albums_count');
    register_setting('now-playing-settings-group', 'obsessions_count');
    register_setting('now-playing-settings-group', 'scrobbles_count');
    register_setting('now-playing-settings-group', 'time_period');
    register_setting('now-playing-settings-group', 'cache_duration');

    // Cache duration settings
    register_setting('now-playing-settings-group', 'lastfm_cache_duration');
    register_setting('now-playing-settings-group', 'trakt_cache_duration');

    // Activity limit settings
    register_setting('now-playing-settings-group', 'lastfm_activity_limit');
    register_setting('now-playing-settings-group', 'trakt_activity_limit');
}

function now_playing_add_settings_fields() {
    // Add settings fields for top tracks, artists, albums, obsessions, and scrobbles
    add_settings_field('top_tracks_count', 'Anzahl der Top-Titel', 'now_playing_top_tracks_count_callback', 'now_playing', 'now_playing_section');
    add_settings_field('top_artists_count', 'Anzahl der Top-Künstler', 'now_playing_top_artists_count_callback', 'now_playing', 'now_playing_section');
    add_settings_field('top_albums_count', 'Anzahl der Top-Alben', 'now_playing_top_albums_count_callback', 'now_playing', 'now_playing_section');
    add_settings_field('obsessions_count', 'Anzahl der Obsessionen', 'now_playing_obsessions_count_callback', 'now_playing', 'now_playing_section');
    add_settings_field('scrobbles_count', 'Anzahl der Scrobbles', 'now_playing_scrobbles_count_callback', 'now_playing', 'now_playing_section');

    // Add settings field for time period
    add_settings_field('time_period', 'Zeitraum', 'now_playing_time_period_callback', 'now_playing', 'now_playing_section');

    // Add settings field for transient cache duration
    add_settings_field('cache_duration', 'Dauer des Transient-Cache', 'now_playing_cache_duration_callback', 'now_playing', 'now_playing_section');
}

// Callback functions for settings fields
function now_playing_top_tracks_count_callback() {
    $setting = esc_attr(get_option('top_tracks_count', 5));
    echo "<input type='number' name='top_tracks_count' value='$setting' min='1' />";
}

// Repeat similar callback functions for other settings fields

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
            
            <tr valign="top">
                <th scope="row">Anzahl der Top-Titel</th>
                <td><input type="number" name="top_tracks_count" value="<?php echo esc_attr(get_option('top_tracks_count', 5)); ?>" /></td>
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
                <th scope="row">Anzahl der Obsessionen</th>
                <td><input type="number" name="obsessions_count" value="<?php echo esc_attr(get_option('obsessions_count', 5)); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Anzahl der Scrobbles</th>
                <td><input type="number" name="scrobbles_count" value="<?php echo esc_attr(get_option('scrobbles_count', 5)); ?>" /></td>
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
            <tr valign="top">
                <th scope="row">Dauer des Transient-Cache</th>
                <td><input type="number" name="cache_duration" value="<?php echo esc_attr(get_option('cache_duration', 15)); ?>" /></td>
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
        <li><code>[top_tracks]</code> - Zeigt die Top-Titel von Last.fm an.</li>
        <li><code>[top_artists]</code> - Zeigt die Top-Künstler von Last.fm an.</li>
        <li><code>[top_albums]</code> - Zeigt die Top-Alben von Last.fm an.</li>
        <li><code>[obsessions]</code> - Zeigt die Obsessionen von Last.fm an.</li>
        <li><code>[scrobbles]</code> - Zeigt die Scrobbles von Last.fm an.</li>
    </ul>
</div>
<?php
}

// Add styles for the plugin
function now_playing_styles() {
    ?>
    <style type="text/css">
        .lastfm-scrobbles ol, .lastfm-scrobbles ul, .lastfm-scrobbles li, 
        .trakt-tv-activities ol, .trakt-tv-activities ul, .trakt-tv-activities li,
        .top-tracks ol, .top-tracks ul, .top-tracks li,
        .top-artists ol, .top-artists ul, .top-artists li,
        .top-albums ol, .top-albums ul, .top-albums li,
        .obsessions ol, .obsessions ul, .obsessions li,
        .scrobbles-count ol, .scrobbles-count ul, .scrobbles-count li {
            list-style-type: none !important;
            padding-left: 0 !important;
            margin: 0 !important;
            margin-left: 0 !important;
        }
        .lastfm-scrobbles li a, .trakt-tv-activities li a,
        .top-tracks li a, .top-artists li a, .top-albums li a,
        .obsessions li a, .scrobbles-count li a {
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

function shortcode_top_tracks() {
    $count = get_option('top_tracks_count', 5); // Default 5 tracks
    $period = get_option('time_period', '7day'); // Default last 7 days

    $data = fetch_lastfm_data('toptracks', $count, $period);
    if ($data === null) {
        return 'Fehler beim Abrufen der Top-Titel.';
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
add_shortcode('top_tracks', 'shortcode_top_tracks');

function shortcode_top_artists() {
    $count = get_option('top_artists_count', 5); // Default 5 artists
    $period = get_option('time_period', '7day'); // Default last 7 days

    $data = fetch_lastfm_data('topartists', $count, $period);
    if ($data === null) {
        return 'Fehler beim Abrufen der Top-Künstler.';
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
add_shortcode('top_artists', 'shortcode_top_artists');

function shortcode_top_albums() {
    $count = get_option('top_albums_count', 5); // Default 5 albums
    $period = get_option('time_period', '7day'); // Default last 7 days

    $data = fetch_lastfm_data('topalbums', $count, $period);
    if ($data === null) {
        return 'Fehler beim Abrufen der Top-Alben.';
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
add_shortcode('top_albums', 'shortcode_top_albums');

function shortcode_obsessions() {
    $count = get_option('obsessions_count', 5); // Default 5 obsessions
    $period = get_option('time_period', '7day'); // Default last 7 days

    $data = fetch_lastfm_data('lovedtracks', $count, $period);
    if ($data === null) {
        return 'Fehler beim Abrufen der Obsessions.';
    }

    $output = '<ul class="obsessions">'; // Add the CSS class here
    foreach ($data['lovedtracks']['track'] as $track) {
        $artist = esc_html($track['artist']['name']);
        $title = esc_html($track['name']);
        $url = esc_url($track['url']);

        $output .= "<li><a href='{$url}' target='_blank'>{$artist} - {$title}</a></li>";
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode('obsessions', 'shortcode_obsessions');

function shortcode_scrobbles() {
    $period = get_option('time_period', '7day'); // Default last 7 days

    $data = fetch_lastfm_data('recenttracks', null, $period);
    if ($data === null) {
        return 'Fehler beim Abrufen der Scrobbles.';
    }

    // Return the count of scrobbles
    return count($data['recenttracks']['track']);
}
add_shortcode('scrobbles', 'shortcode_scrobbles');

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
