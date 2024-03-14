<?php

/**
 * Plugin Name:         NowScrobbling
 * Plugin URI:          https://github.com/willrobin/NowScrobbling
 * Description:         NowScrobbling is a WordPress plugin designed to manage API settings and display recent activities for last.fm and trakt.tv on your site. It enables users to show their latest scrobbles through shortcodes.
 * Version:             1.1.2
 * Requires at least:   
 * Requires PHP:        
 * Author:              Robin Will
 * Author URI:          https://robinwill.de/
 * License:             GPL v2 or later
 * Text Domain:         nowscrobbling
 * Domain Path:         /languages
 * GitHub Plugin URI:   https://github.com/willrobin/NowScrobbling
 * GitHub Branch:       master
 */

// Sicherheitsprüfung — Sicherstellen, dass der Code nicht direkt aufgerufen wird
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Funktion zum Hinzufügen von Einstellungen
function nowscrobbling_add_settings()
{
    // Einstellungen hinzufügen
    add_settings_section('nowscrobbling_section', 'NowScrobbling Einstellungen', 'nowscrobbling_section_callback', 'nowscrobbling');
    // API-Schlüssel und Benutzernamen
    add_settings_field('lastfm_api_key', 'Last.fm API Schlüssel', 'nowscrobbling_lastfm_api_key_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('lastfm_user', 'Last.fm Benutzername', 'nowscrobbling_lastfm_user_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('trakt_client_id', 'Trakt Client ID', 'nowscrobbling_trakt_client_id_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('trakt_user', 'Trakt Benutzername', 'nowscrobbling_trakt_user_callback', 'nowscrobbling', 'nowscrobbling_section');
    // Anzahl der anzuzeigenden Top-Titel, Top-Künstler, Top-Alben, Lieblingslieder, Top-Tags, letzten Filme, Serien und Episoden
    add_settings_field('top_tracks_count', 'Anzahl der Top-Titel', 'nowscrobbling_top_tracks_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_artists_count', 'Anzahl der Top-Künstler', 'nowscrobbling_top_artists_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_albums_count', 'Anzahl der Top-Alben', 'nowscrobbling_top_albums_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('lovedtracks_count', 'Anzahl der Lieblingslieder', 'nowscrobbling_lovedtracks_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('top_tags_count', 'Anzahl der Top-Tags', 'nowscrobbling_top_tags_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('last_movies_count', 'Anzahl der letzten Filme', 'nowscrobbling_last_movies_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('last_shows_count', 'Anzahl der letzten Serien', 'nowscrobbling_last_shows_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    add_settings_field('last_episodes_count', 'Anzahl der letzten Episoden', 'nowscrobbling_last_episodes_count_callback', 'nowscrobbling', 'nowscrobbling_section');
    // Zeitraum (außer Alben)
    add_settings_field('time_period', 'Zeitraum', 'nowscrobbling_time_period_callback', 'nowscrobbling', 'nowscrobbling_section');
    
    // Cache-Dauer
    add_settings_field('cache_duration', 'Dauer des Transient-Cache', 'nowscrobbling_cache_duration_callback', 'nowscrobbling', 'nowscrobbling_section');

    // Registrierung der Einstellungen
    register_setting('nowscrobbling-settings-group', 'lastfm_api_key', 'nowscrobbling_sanitize_settings');
    register_setting('nowscrobbling-settings-group', 'lastfm_user', 'nowscrobbling_sanitize_settings');
    register_setting('nowscrobbling-settings-group', 'trakt_client_id');
    register_setting('nowscrobbling-settings-group', 'trakt_user');
    register_setting('nowscrobbling-settings-group', 'top_tracks_count');
    register_setting('nowscrobbling-settings-group', 'top_artists_count');
    register_setting('nowscrobbling-settings-group', 'top_albums_count');
    register_setting('nowscrobbling-settings-group', 'lovedtracks_count');
    register_setting('nowscrobbling-settings-group', 'top_tags_count');
    register_setting('nowscrobbling-settings-group', 'last_movies_count');
    register_setting('nowscrobbling-settings-group', 'last_shows_count');
    register_setting('nowscrobbling-settings-group', 'last_episodes_count');
    register_setting('nowscrobbling-settings-group', 'time_period');
    register_setting('nowscrobbling-settings-group', 'cache_duration');
}
add_action('admin_init', 'nowscrobbling_add_settings');

// Funktion zum Säubern der Einstellungen
function nowscrobbling_sanitize_settings($input) {
    $output = array();

    foreach( $input as $key => $value ) {
        if( isset( $input[$key] ) ) {
            if ($key == 'email') {
                $output[$key] = is_email($input[$key]) ? $input[$key] : '';
            } elseif ($key == 'url') {
                $output[$key] = esc_url($input[$key]);
            } elseif ($key == 'number') {
                $output[$key] = intval($input[$key]);
            } else {
                $output[$key] = sanitize_text_field($input[$key]);
            }
        }
    }

    return apply_filters( 'nowscrobbling_sanitize_settings', $output, $input );
}

// Callback-Funktion für die Einstellungen 
function nowscrobbling_section_callback()
{
    echo '<p>Bitte gib deine API-Schlüssel und Benutzernamen für Last.fm und Trakt.tv ein. Du kannst auch die Anzahl der anzuzeigenden Top-Titel, Top-Künstler, Top-Alben, Lieblingslieder, Top-Tags, letzten Filme, Serien und Episoden festlegen. Die Dauer des Transient-Cache kann ebenfalls angepasst werden.</p>';
}

// Callback-Funktion für 'lastfm_api_key' 
function nowscrobbling_lastfm_api_key_callback()
{
    $setting = esc_attr(get_option('lastfm_api_key'));
    echo "<input type='text' name='lastfm_api_key' value='$setting' />";
}

// Callback-Funktion für 'lastfm_user' 
function nowscrobbling_lastfm_user_callback()
{
    $setting = esc_attr(get_option('lastfm_user'));
    echo "<input type='text' name='lastfm_user' value='$setting' />";
}

// Callback-Funktion für 'trakt_client_id' 
function nowscrobbling_trakt_client_id_callback()
{
    $setting = esc_attr(get_option('trakt_client_id'));
    echo "<input type='text' name='trakt_client_id' value='$setting' />";
}

// Callback-Funktion für 'trakt_user' 
function nowscrobbling_trakt_user_callback()
{
    $setting = esc_attr(get_option('trakt_user'));
    echo "<input type='text' name='trakt_user' value='$setting' />";
}

// Callback-Funktion für 'top_tracks_count' 
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
    $setting = esc_attr(get_option('cache_duration', 1));
    echo "<input type='number' name='cache_duration' value='$setting' min='1' />";
}

// Funktion zum Hinzufügen des Admin-Menüs
function nowscrobbling_clear_cache() {
    if (isset($_POST['clear_cache']) && check_admin_referer('nowscrobbling_clear_cache', 'nowscrobbling_nonce')) {
        delete_transient('my_lastfm_scrobbles');
        delete_transient('lastfm_top_tags');
        delete_transient('my_trakt_tv_activities');
        delete_transient('my_trakt_tv_movies');
        delete_transient('my_trakt_tv_shows');
        delete_transient('my_trakt_tv_episodes');
        echo '<div class="notice notice-success is-dismissible"><p>Der Cache wurde erfolgreich geleert.</p></div>';
    }
}
add_action('admin_notices', 'nowscrobbling_clear_cache');

// Funktion zum Hinzufügen des Admin-Menüs 
function nowscrobbling_add_admin_menu() {
    add_options_page('NowScrobbling Einstellungen', 'NowScrobbling', 'manage_options', 'nowscrobbling', 'nowscrobbling_settings_page');
}
add_action('admin_menu', 'nowscrobbling_add_admin_menu');

// Funktion zum Hinzufügen von Shortcodes 
function nowscrobbling_add_shortcodes()
{
// Registrierung der Shortcodes

// Last.fm
add_shortcode('nowscr_lastfm_indicator', 'nowscr_lastfm_indicator_shortcode');
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode');
add_shortcode('nowscr_lastfm_top_artists', 'nowscr_lastfm_top_artists_shortcode');
add_shortcode('nowscr_lastfm_top_albums', 'nowscr_lastfm_top_albums_shortcode');
add_shortcode('nowscr_lastfm_top_tracks', 'nowscr_lastfm_top_tracks_shortcode');
add_shortcode('nowscr_lastfm_lovedtracks', 'nowscr_lastfm_lovedtracks_shortcode');
add_shortcode('nowscr_lastfm_top_tags', 'nowscr_lastfm_top_tags_shortcode');

// Trakt.tv
add_shortcode('nowscr_trakt_indicator', 'nowscr_trakt_indicator_shortcode');
add_shortcode('nowscr_trakt_history', 'nowscr_trakt_history_shortcode');
add_shortcode('nowscr_trakt_last_movie', 'nowscr_trakt_last_movie_shortcode');
add_shortcode('nowscr_trakt_last_movie_with_rating', 'nowscr_trakt_last_movie_with_rating_shortcode');
add_shortcode('nowscr_trakt_last_show', 'nowscr_trakt_last_show_shortcode');
add_shortcode('nowscr_trakt_last_episode', 'nowscr_trakt_last_episode_shortcode');
}
add_action('init', 'nowscrobbling_add_shortcodes');

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
            <h2>Transient-Cache (Minutes)</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Last.fm</th>
                    <td><input type="number" name="lastfm_cache_duration" value="<?php echo esc_attr(get_option('lastfm_cache_duration', 1)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Trakt</th>
                    <td><input type="number" name="trakt_cache_duration" value="<?php echo esc_attr(get_option('trakt_cache_duration', 1)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Letzte Wiedergabe / Scrobbelt gerade</th>
                    <td><input type="number" name="cache_duration" value="<?php echo esc_attr(get_option('cache_duration', 1)); ?>" /></td>
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
        
        <h3>Last.fm</h3>

        <ul>
            <li><code>[nowscr_lastfm_indicator]</code> - Zeigt den aktuellen Status der Last.fm Aktivität an.</li>
            <li><code>[nowscr_lastfm_history]</code> - Zeigt die letzten Scrobbles von Last.fm an.</li>
            <li><code>[nowscr_lastfm_top_artists]</code> - Zeigt die letzten Top-Künstler von Last.fm im gewählten Zeitraum an.</li>
            <li><code>[nowscr_lastfm_top_albums]</code> - Zeigt die letzten Top-Alben von Last.fm <del>im gewählten Zeitraum</del> an.</li>
            <li><code>[nowscr_lastfm_top_tracks]</code> - Zeigt die letzten Top-Titel von Last.fm im gewählten Zeitraum an.</li>
            <li><code>[nowscr_lastfm_lovedtracks]</code> - Zeigt die letzten Lieblingslieder von Last.fm an.</li>
            <li><code>[nowscr_lastfm_top_tags]</code> - Zeigt die letzten Top-Tags von Last.fm an.</li>
        </ul>
        <h3>Trakt</h3>

        <ul>
            <li><code>[nowscr_trakt_indicator]</code> - Zeigt den aktuellen Status der Trakt Aktivität an.</li>
            <li><code>[nowscr_trakt_history]</code> - Zeigt die letzten Scrobbles von Trakt an.</li>
            <li><code>[nowscr_trakt_last_movie]</code> - Zeigt die letzten Filme von Trakt an.</li>
            <li><code>[nowscr_trakt_last_movie_with_rating]</code> - Zeigt die letzten Filme mit Bewertung von Trakt an.</li>
            <li><code>[nowscr_trakt_last_show]</code> - Zeigt die letzten Serien von Trakt an.</li>
            <li><code>[nowscr_trakt_last_episode]</code> - Zeigt die letzten Episoden von Trakt an.</li>
        </ul>
    </div>
<?php
}

// Funktion zum Hinzufügen von Vorschau-Bereich
function nowscrobbling_preview()
{
?>

    <!-- Vorschau-Bereich -->
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

function nowscrobbling_fetch_api_data($url, $headers = []) {
    if (empty($url)) {
        return ['error' => 'Keine URL angegeben.'];
    }
    $response = wp_remote_get($url, ['headers' => $headers]);
    if (is_wp_error($response)) {
        return ['error' => 'WP_Error: ' . $response->get_error_message()];
    }
    if (wp_remote_retrieve_response_code($response) !== 200) {
        return ['error' => 'Ungültiger HTTP-Statuscode: ' . wp_remote_retrieve_response_code($response)];
    }
    return json_decode(wp_remote_retrieve_body($response), true);
}

// Wiederverwendbare Funktion für das Abrufen von Transient-Daten (Cache)
function nowscrobbling_get_or_set_transient($transient_key, $callback, $expiration = 3600) {
    if (!$transient_key || !is_callable($callback)) {
        return null;
    }
    if (false === ($data = get_transient($transient_key))) {
        $data = call_user_func($callback);
        set_transient($transient_key, $data, $expiration);
    }
    return $data;
}

/**
 * Generiert Shortcode-Ausgaben für verschiedene Datenquellen.
 *
 * @param string $type Der Typ der Datenquelle ('lastfm' oder 'trakt').
 * @param string $dataType Der spezifische Datentyp (z.B. 'top_artists', 'top_tracks').
 * @param array $params Zusätzliche Parameter für die Datenabfrage.
 * @return string Die generierte HTML-Ausgabe für den Shortcode.
 */
function nowscrobbling_generate_shortcode($type, $dataType, $params = []) {
    $output = '';
    $data = []; // Hier werden die Daten gespeichert

    // Beispiel für eine bedingte Logik basierend auf dem Typ und den Daten
    if ($type === 'lastfm') {
        $method = "user.get{$dataType}";
        $data = nowscrobbling_fetch_lastfm_data($method, $params);
    } elseif ($type === 'trakt') {
        $path = "users/{get_option('trakt_user')}/{$dataType}";
        $data = nowscrobbling_fetch_trakt_data($path, $params);
    }

    // Datenverarbeitung und -ausgabe
    if (!empty($data)) {
        // Angenommen, $data ist ein Array von Items
        foreach ($data as $item) {
            $name = esc_html($item['name']);
            $url = esc_url($item['url']);
            $output .= "<a href='{$url}' class='bubble'>{$name}</a> ";
        }
    } else {
        $output = 'Keine Daten gefunden.';
    }

    return $output;
}

// Wiederverwendbare Funktion für das Generieren von Shortcode-Ausgaben (mit Formatierung)
function nowscrobbling_generate_shortcode_output($items, $format_callback) {
    if (!$items || !is_array($items)) {
        return 'Keine Daten gefunden.';
    }
    $formatted_items = array_map($format_callback, $items);
    if (count($formatted_items) > 1) {
        $last_item = array_pop($formatted_items);
        return implode(' ', $formatted_items) . ' und ' . $last_item;
    } elseif (!empty($formatted_items)) {
        return $formatted_items[0];
    } else {
        return 'Keine Daten gefunden.';
    }
}

// Wiederverwendbare Funktion für das Abrufen von Last.fm-Daten (mit API-Schlüssel und Benutzername)

// Konstante für die Basis-URL der Last.fm-API
define('LASTFM_API_BASE_URL', 'http://ws.audioscrobbler.com/2.0/');

/**
 * Funktion zum Abrufen von Last.fm-Daten.
 * 
 * @param string $method Der API-Endpunkt, der aufgerufen werden soll.
 * @param array $params Ein assoziatives Array von Parametern, die an den API-Endpunkt gesendet werden sollen.
 * @return array Die Antwort von der API als assoziatives Array.
 */
function nowscrobbling_fetch_lastfm_data($method, $params = []) {
    $api_key = get_option('lastfm_api_key');
    $user = get_option('lastfm_user');
    $params = array_merge(['api_key' => $api_key, 'user' => $user, 'format' => 'json'], $params);
    $url = LASTFM_API_BASE_URL . "?method=user.$method&" . http_build_query($params);
    return nowscrobbling_fetch_api_data($url);
}

// Generische Funktion für das Abrufen von Trakt.tv-Daten (mit API-Schlüssel und Benutzername)

// Konstante für die Basis-URL der Trakt.tv-API
define('TRAKT_API_BASE_URL', 'https://api.trakt.tv');

/**
 * Funktion zum Abrufen von Trakt.tv-Daten.
 * 
 * @param string $path Der Pfad des API-Endpunkts, der aufgerufen werden soll.
 * @param array $params Ein assoziatives Array von Parametern, die an den API-Endpunkt gesendet werden sollen.
 * @return array Die Antwort von der API als assoziatives Array, oder null, wenn kein Pfad angegeben wurde.
 */
function nowscrobbling_fetch_trakt_data($path, $params = []) {
    if (!isset($path)) {
        return ['error' => 'Kein Pfad für die Trakt.tv-API angegeben.'];
    }
    $client_id = get_option('trakt_client_id');
    $headers = [
        'Content-Type' => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key' => $client_id,
    ];
    $query_string = http_build_query($params);
    $url = TRAKT_API_BASE_URL . "/$path?$query_string";
    $data = nowscrobbling_fetch_api_data($url, $headers);
    if (!$data || isset($data['error'])) {
        return ['error' => 'Es gab ein Problem beim Abrufen der Trakt.tv-Daten. Bitte versuchen Sie es später erneut.'];
    }
    return $data;
}

// Funktion zum Abrufen der letzten Scrobbles von Last.fm (mit Transient-Cache)
function nowscrobbling_fetch_lastfm_scrobbles() {
    return nowscrobbling_get_or_set_transient('my_lastfm_scrobbles', function() {
        $data = nowscrobbling_fetch_lastfm_data('user.getrecenttracks', [
            'limit' => get_option('lastfm_activity_limit', 3)
        ]);
        if (!$data || isset($data['error']) || empty($data['recenttracks']['track'])) {
            return ['error' => 'Es gab ein Problem beim Abrufen der Last.fm-Daten. Bitte versuchen Sie es später erneut.'];
        }
        return array_map(function($track) {
            // Prüfen, ob der Track als Array vorliegt (mehrere Tracks) oder direkt zugänglich ist (einzelner Track)
            if (isset($track['url'])) {
                $trackUrl = esc_url($track['url']);
            } else {
                // Wenn kein URL vorhanden, als Fallback
                $trackUrl = '#';
            }
            $trackName = isset($track['name']) ? esc_html($track['name']) : 'Unbekannter Track';
            return ['url' => $trackUrl, 'name' => $trackName];
        }, $data['recenttracks']['track']);
    }, get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS);
}

// Funktion zum Generieren von Shortcode-Ausgaben für Last.fm-Status (Scrobbelt gerade oder zuletzt gehört) 
function nowscr_lastfm_indicator_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'cache' => 10, // Standardwert
        ), 
        $atts, 
        'nowscr_lastfm_indicator'
    );

    // Cache-Dauer in Sekunden 
    $cache = intval($atts['cache']); 

    // Abrufen der Last.fm-Daten (mit Transient-Cache)  
    $scrobbles = nowscrobbling_get_or_set_transient('lastfm_scrobbles', 'nowscrobbling_fetch_lastfm_scrobbles', $cache);
    if (isset($scrobbles['error'])) { 
        return "<em>" . esc_html($scrobbles['error']) . "</em>"; 
    }

    // Überprüfen, ob der aktuelle Track scrobbelt
    if (!empty($scrobbles['recenttracks']['track'][0]['@attr']['nowplaying'])) {
        return 'Scrobbelt gerade';
    }

    // Wenn kein Track gerade scrobbelt, den zuletzt gescrobbelten Track anzeigen
    if (!empty($scrobbles['recenttracks']['track'][0])) {
        $lastTrack = $scrobbles['recenttracks']['track'][0];
        $date = new DateTime($lastTrack['date']['#text']);
        $date->setTimezone(new DateTimeZone(get_option('timezone_string')));
        $formattedDate = $date->format(get_option('date_format') . ' ' . get_option('time_format'));

        return "Zuletzt gehört: " . $formattedDate;
    }

    // Wenn keine Scrobbles gefunden wurden oder ein Fehler auftritt
    return "<em>Keine kürzlichen Scrobbles gefunden.</em>";
}
add_shortcode('nowscr_lastfm_indicator', 'nowscr_lastfm_indicator_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Last.fm-Scrobbles (mit Bildern und Bubble-Links)
function nowscr_lastfm_history_shortcode() {
    $scrobbles = nowscrobbling_fetch_lastfm_scrobbles();
    if (isset($scrobbles['error'])) {
        return "<em>" . esc_html($scrobbles['error']) . "</em>";
    }
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
    // Wenn $output leer ist und $lastPlayedTrack nicht null ist, wird der zuletzt gehörte Track angezeigt
    if (empty($output) && $lastPlayedTrack) {
        $artist = esc_html($lastPlayedTrack->artist->{'#text'});
        $song = esc_html($lastPlayedTrack->name);
        $url = esc_url($lastPlayedTrack->url);
        $output = "<a class='bubble' href='{$url}' target='_blank'>{$artist} - {$song}</a>";
    }
    return nowscrobbling_generate_shortcode_output($scrobbles, function($track) use ($output) {
        return $output;
    });
}
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Last.fm-Top-Künstler (mit Bubble-Links)
function nowscr_lastfm_top_artists_shortcode() {
    $artists = nowscrobbling_fetch_lastfm_data('topartists', ['limit' => get_option('top_artists_count', 5)]);
    if (!$artists || empty($artists['topartists']['artist'])) {
        return "Es konnten keine Top-Künstler gefunden werden.";
    }
    return nowscrobbling_generate_shortcode_output($artists['topartists']['artist'], function($artist) {
        $artistUrl = isset($artist['url']) ? esc_url($artist['url']) : '#';
        $artistName = isset($artist['name']) ? esc_html($artist['name']) : 'Unbekannter Künstler';
        return '<a href="' . $artistUrl . '" class="bubble">' . $artistName . '</a>';
    });
}
add_shortcode('nowscr_lastfm_top_artists', 'nowscr_lastfm_top_artists_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Last.fm-Top-Tags (mit Bubble-Links)
function nowscr_lastfm_top_tags_shortcode() {
    $tags = nowscrobbling_fetch_lastfm_data('toptags', ['limit' => get_option('top_tags_count', 3)]);
    if (!$tags || empty($tags['toptags']['tag'])) {
        return "Es konnten keine Top-Tags gefunden werden.";
    }
    return nowscrobbling_generate_shortcode_output($tags['toptags']['tag'], function($tag) {
        $tagUrl = isset($tag['url']) ? esc_url($tag['url']) : '#';
        $tagName = isset($tag['name']) ? esc_html($tag['name']) : 'Unbekanntes Tag';
        return '<a href="' . $tagUrl . '" class="bubble">' . $tagName . '</a>';
    });
}
add_shortcode('nowscr_lastfm_top_tags', 'nowscr_lastfm_top_tags_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Last.fm-Top-Alben (mit Bubble-Links)
function nowscr_lastfm_top_albums_shortcode() {
    $albums = nowscrobbling_fetch_lastfm_data('topalbums', ['limit' => get_option('top_albums_count', 5)]);
    if (!$albums || empty($albums['topalbums']['album'])) {
        return "Es konnten keine Top-Alben gefunden werden.";
    }
    return nowscrobbling_generate_shortcode_output($albums['topalbums']['album'], function($album) {
        $albumUrl = isset($album['url']) ? esc_url($album['url']) : '#';
        $albumName = isset($album['name']) ? esc_html($album['name']) : 'Unbekanntes Album';
        return '<a href="' . $albumUrl . '" class="bubble">' . $albumName . '</a>';
    });
}
add_shortcode('nowscr_lastfm_top_albums', 'nowscr_lastfm_top_albums_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Last.fm-Top-Titel (mit Bubble-Links)
function nowscr_lastfm_top_tracks_shortcode() {
    $tracks = nowscrobbling_fetch_lastfm_data('toptracks', ['limit' => get_option('top_tracks_count', 5)]);
    if (!$tracks || empty($tracks['toptracks']['track'])) {
        return "Es konnten keine Top-Titel gefunden werden.";
    }
    return nowscrobbling_generate_shortcode_output($tracks['toptracks']['track'], function($track) {
        $trackUrl = isset($track['url']) ? esc_url($track['url']) : '#';
        $trackName = isset($track['name']) ? esc_html($track['name']) : 'Unbekannter Titel';
        return '<a href="' . $trackUrl . '" class="bubble">' . $trackName . '</a>';
    });
}
add_shortcode('nowscr_lastfm_top_tracks', 'nowscr_lastfm_top_tracks_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Last.fm-Lieblingslieder (mit Bubble-Links)
function nowscr_lastfm_lovedtracks_shortcode() {
    $tracks = nowscrobbling_fetch_lastfm_data('lovedtracks', ['limit' => get_option('lovedtracks_count', 5)]);
    if (!$tracks || empty($tracks['lovedtracks']['track'])) {
        return "Es konnten keine Lieblingslieder gefunden werden.";
    }
    return nowscrobbling_generate_shortcode_output($tracks['lovedtracks']['track'], function($track) {
        $trackUrl = isset($track['url']) ? esc_url($track['url']) : '#';
        $trackName = isset($track['name']) ? esc_html($track['name']) : 'Unbekannter Titel';
        return '<a href="' . $trackUrl . '" class="bubble">' . $trackName . '</a>';
    });
}
add_shortcode('nowscr_lastfm_lovedtracks', 'nowscr_lastfm_lovedtracks_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Trakt.tv-Scrobbles (mit Bildern und Bubble-Links)
function nowscr_trakt_history_shortcode() {
    $activities = nowscrobbling_get_or_set_transient('my_trakt_tv_activities', function() {
        return nowscrobbling_fetch_trakt_data('sync/history', ['limit' => get_option('trakt_activity_limit', 3)]);
    }, get_option('trakt_cache_duration', 1) * MINUTE_IN_SECONDS);
    if (!$activities) {
        return "<em>Fehler beim Abrufen der Trakt.tv-Aktivitäten</em>";
    }
    $output = '';
    foreach ($activities as $activity) {
        $showUrl = isset($activity['show']['url']) ? esc_url($activity['show']['url']) : '#';
        $showTitle = isset($activity['show']['title']) ? esc_html($activity['show']['title']) : 'Unbekannter Titel';
        $episodeUrl = isset($activity['episode']['url']) ? esc_url($activity['episode']['url']) : '#';
        $episodeTitle = isset($activity['episode']['title']) ? esc_html($activity['episode']['title']) : 'Unbekannter Titel';
        $watchedAt = isset($activity['watched_at']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity['watched_at'])) : 'Unbekanntes Datum';
        $output .= '<div class="nowscrobbling">';
        $output .= '<a href="' . $showUrl . '" class="bubble">' . $showTitle . '</a>';
        if (isset($activity['episode'])) {
            $output .= ' - <a href="' . $episodeUrl . '" class="bubble">' . $episodeTitle . '</a>';
        }
        $output .= ' am ' . $watchedAt;
        $output .= '</div>';
    }
    return $output;
}
add_shortcode('nowscr_trakt_history', 'nowscr_trakt_history_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Trakt.tv-Indikator (letzte Aktivität) 
function nowscr_trakt_indicator_shortcode() {
    $activities = nowscrobbling_get_or_set_transient('my_trakt_tv_activities', function() {
        return nowscrobbling_fetch_trakt_data('sync/history', ['limit' => 1]);
    }, get_option('trakt_cache_duration', 1) * MINUTE_IN_SECONDS);
    if (!$activities) {
        return "<em>Fehler beim Abrufen der Trakt.tv-Aktivitäten</em>";
    }
    $lastActivity = reset($activities);
    $watchedAt = isset($lastActivity['watched_at']) ? strtotime($lastActivity['watched_at']) : null;
    if ($watchedAt === null) {
        return 'Zuletzt gesehen: Unbekanntes Datum';
    }
    $dateTime = new DateTime("@$watchedAt");
    $dateTime->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));
    return 'Zuletzt gesehen: ' . $dateTime->format(get_option('date_format') . ' ' . get_option('time_format'));
}
add_shortcode('nowscr_trakt_indicator', 'nowscr_trakt_indicator_shortcode');

// Funktion zum Generieren von Shortcode-Ausgaben für Trakt.tv-letzten Film 
function nowscr_trakt_last_movie_shortcode() {
    $movies = nowscrobbling_get_or_set_transient('my_trakt_tv_movies', function() {
        return nowscrobbling_fetch_trakt_data('users/' . get_option('trakt_user') . '/history/movies', ['limit' => get_option('last_movies_count', 3)]);
    }, get_option('trakt_cache_duration', 1) * MINUTE_IN_SECONDS);
    if (!$movies) {
        return "<em>Fehler beim Abrufen der Trakt.tv-Filme</em>";
    }
    $output = '';
    foreach ($movies as $movie) {
        $url = isset($movie['movie']['url']) ? esc_url($movie['movie']['url']) : '#'; // Provide a default URL if not available
        $title = isset($movie['movie']['title']) ? esc_html($movie['movie']['title']) : 'Unbekannter Titel'; // Provide a default title if not available
        $watchedAt = isset($movie['watched_at']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($movie['watched_at'])) : 'Unbekanntes Datum'; // Provide a default date if not available
        $output .= '<a href="' . $url . '" class="bubble">' . $title . '</a>';
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

function nowscr_trakt_last_show_shortcode() {
    $shows = nowscrobbling_get_or_set_transient('my_trakt_tv_shows', function() {
        return nowscrobbling_fetch_trakt_data('users/' . get_option('trakt_user') . '/history/shows', ['limit' => get_option('last_shows_count', 3)]);
    }, get_option('trakt_cache_duration', 1) * MINUTE_IN_SECONDS);
    if (!$shows) {
        return "<em>Fehler beim Abrufen der Trakt.tv-Serien</em>";
    }
    $output = '';
    foreach ($shows as $show) {
        $url = isset($show['show']['url']) ? esc_url($show['show']['url']) : '#';
        $title = isset($show['show']['title']) ? esc_html($show['show']['title']) : 'Unbekannter Titel';
        $year = isset($show['show']['year']) ? esc_html($show['show']['year']) : ''; // Hinzufügen der Jahreszahl
        $output .= '<a href="' . $url . '" class="bubble" target="_blank">' . $title . ' (' . $year . ')</a>'; // Hinzufügen der Jahreszahl zur Ausgabe
    }
    return $output;
}
add_shortcode('nowscr_trakt_last_show', 'nowscr_trakt_last_show_shortcode');

function nowscr_trakt_last_episode_shortcode() {
    $episodes = nowscrobbling_get_or_set_transient('my_trakt_tv_episodes', function() {
        return nowscrobbling_fetch_trakt_data('users/' . get_option('trakt_user') . '/history/episodes', ['limit' => get_option('last_episodes_count', 3)]);
    }, get_option('trakt_cache_duration', 1) * MINUTE_IN_SECONDS);
    if (!$episodes) {
        return "<em>Fehler beim Abrufen der Trakt.tv-Episoden</em>";
    }
    $output = '';
    foreach ($episodes as $episode) {
        $showUrl = isset($episode['show']['url']) ? esc_url($episode['show']['url']) : '#';
        $showTitle = isset($episode['show']['title']) ? esc_html($episode['show']['title']) : 'Unbekannter Titel';
        $season = isset($episode['episode']['season']) ? esc_html($episode['episode']['season']) : ''; // Hinzufügen der Staffelnummer
        $episodeNumber = isset($episode['episode']['number']) ? esc_html($episode['episode']['number']) : ''; // Hinzufügen der Episodennummer
        $episodeTitle = isset($episode['episode']['title']) ? esc_html($episode['episode']['title']) : 'Unbekannter Titel';
        // Verwendung von $showUrl statt $episodeUrl
        $output .= ' <a href="' . $showUrl . '" class="bubble" target="_blank">S' . $season . 'E' . $episodeNumber . ': ' . $episodeTitle . '</a>'; // Hinzufügen der Staffel- und Episodennummer zur Ausgabe
    }
    return $output;
}
add_shortcode('nowscr_trakt_last_episode', 'nowscr_trakt_last_episode_shortcode');
