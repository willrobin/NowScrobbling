<?php

/**
 * Version:             1.2.1
*/

// Admin Menu and Settings Registration
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
        'top_tracks_count', 'top_artists_count', 'top_albums_count', 'lovedtracks_count',
        'last_movies_count', 'last_shows_count', 'last_episodes_count',
        'cache_duration', 'lastfm_cache_duration', 'trakt_cache_duration',
        'lastfm_activity_limit', 'trakt_activity_limit'
    ];

    foreach ($settings as $setting) {
        register_setting('nowscrobbling-settings-group', $setting);
    }
}

// Callback functions for settings fields
function nowscrobbling_setting_callback($setting, $type = 'text', $options = [])
{
    $value = esc_attr(get_option($setting, $options['default'] ?? ''));
    if ($type == 'select') {
        echo "<select name='{$setting}'>";
        foreach ($options['choices'] as $key => $label) {
            echo "<option value='{$key}' " . selected($value, $key, false) . ">{$label}</option>";
        }
        echo "</select>";
    } else {
        $min = $options['min'] ?? '';
        echo "<input type='{$type}' name='{$setting}' value='{$value}' min='{$min}' />";
    }
}

// Add settings fields
add_action('admin_init', function () {
    add_settings_section('nowscrobbling_section', 'NowScrobbling Einstellungen', function () {
        echo '<p>API-Schlüssel und Benutzernamen für Last.fm und Trakt sowie weitere Einstellungen konfigurieren.</p>';
    }, 'nowscrobbling');

    $fields = [
        ['lastfm_api_key', 'Last.fm API Schlüssel'],
        ['lastfm_user', 'Last.fm Benutzername'],
        ['trakt_client_id', 'Trakt Client ID'],
        ['trakt_user', 'Trakt Benutzername'],
        ['top_tracks_count', 'Anzahl der Top-Titel', 'number', ['min' => 1, 'default' => 5]],
        ['top_artists_count', 'Anzahl der Top-Künstler', 'number', ['min' => 1, 'default' => 5]],
        ['top_albums_count', 'Anzahl der Top-Alben', 'number', ['min' => 1, 'default' => 5]],
        ['lovedtracks_count', 'Anzahl der Lieblingslieder', 'number', ['min' => 1, 'default' => 5]],
        ['last_movies_count', 'Anzahl der letzten Filme', 'number', ['min' => 1, 'default' => 3]],
        ['last_shows_count', 'Anzahl der letzten Serien', 'number', ['min' => 1, 'default' => 3]],
        ['last_episodes_count', 'Anzahl der letzten Episoden', 'number', ['min' => 1, 'default' => 3]],
        ['cache_duration', 'Dauer des Transient-Cache (Minuten)', 'number', ['min' => 1, 'default' => 60]],
        ['lastfm_cache_duration', 'Last.fm Cache-Dauer (Minuten)', 'number', ['min' => 1, 'default' => 60]],
        ['trakt_cache_duration', 'Trakt Cache-Dauer (Minuten)', 'number', ['min' => 1, 'default' => 60]],
        ['lastfm_activity_limit', 'Anzahl der last.fm Aktivitäten', 'number', ['min' => 1, 'default' => 5]],
        ['trakt_activity_limit', 'Anzahl der Trakt Aktivitäten', 'number', ['min' => 1, 'default' => 5]],
    ];

    foreach ($fields as $field) {
        $id = $field[0];
        $title = $field[1];
        $type = $field[2] ?? 'text';
        $options = $field[3] ?? [];
        add_settings_field($id, $title, function () use ($id, $type, $options) {
            nowscrobbling_setting_callback($id, $type, $options);
        }, 'nowscrobbling', 'nowscrobbling_section');
    }
});

// Function to clear all relevant transients
function nowscrobbling_clear_all_caches()
{
    $transients = [
        'my_lastfm_scrobbles',
        'lastfm_top_artists',
        'lastfm_top_albums',
        'lastfm_top_tracks',
        'lastfm_lovedtracks',
        'my_trakt_tv_activities',
        'my_trakt_tv_movies',
        'my_trakt_tv_shows',
        'my_trakt_tv_episodes'
    ];

    foreach ($transients as $transient) {
        delete_transient($transient);
    }
}

// Settings Page Content
function nowscrobbling_settings_page()
{
    if (isset($_POST['clear_cache']) && check_admin_referer('nowscrobbling_clear_cache', 'nowscrobbling_nonce')) {
        nowscrobbling_clear_all_caches();
        echo '<div class="updated"><p>Alle Caches wurden erfolgreich geleert.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>NowScrobbling Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('nowscrobbling-settings-group');
            do_settings_sections('nowscrobbling');
            submit_button('Speichern');
            ?>
        </form>
        <form method="post">
            <?php wp_nonce_field('nowscrobbling_clear_cache', 'nowscrobbling_nonce'); ?>
            <input type="submit" name="clear_cache" value="Cache leeren" class="button">
        </form>
        <h2>Verfügbare Shortcodes</h2>
        <p>Du kannst diese Shortcodes verwenden, um Inhalte in Beiträgen, Seiten oder Widgets anzuzeigen:</p>
        <h3>Last.fm</h3>
        <ul>
            <li><code>[nowscr_lastfm_indicator]</code> - Zeigt den aktuellen Status der Last.fm Aktivität an.</li>
            <li><code>[nowscr_lastfm_history]</code> - Zeigt die letzten Scrobbles von Last.fm an.</li>
            <li><code>[nowscr_lastfm_top_artists period="7day"]</code> - Zeigt die letzten Top-Künstler von Last.fm im gewählten Zeitraum an.</li>
            <li><code>[nowscr_lastfm_top_albums period="7day"]</code> - Zeigt die letzten Top-Alben von Last.fm im gewählten Zeitraum an.</li>
            <li><code>[nowscr_lastfm_top_tracks period="7day"]</code> - Zeigt die letzten Top-Titel von Last.fm im gewählten Zeitraum an.</li>
            <li><code>[nowscr_lastfm_lovedtracks]</code> - Zeigt die letzten Lieblingslieder von Last.fm an.</li>
            <li>Verfügbare Werte für <code>period</code>: <code>7day</code>, <code>1month</code>, <code>3month</code>, <code>6month</code>, <code>12month</code>, <code>overall</code>.</li>
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
        <h2>Vorschau der Daten</h2>
        <div id="nowscrobbling-preview">
            <h3>Last.fm</h3>
            <h4>Status (Indicator)</h4>
            <?php echo do_shortcode('[nowscr_lastfm_indicator]'); ?>
            <h4>Letzte Scrobbles (History)</h4>
            <?php echo do_shortcode('[nowscr_lastfm_history]'); ?>
            <h4>Letzte Top-Künstler</h4>
            <?php echo do_shortcode('[nowscr_lastfm_top_artists period="7day"]'); ?>
            <h4>Letzte Top-Alben</h4>
            <?php echo do_shortcode('[nowscr_lastfm_top_albums period="7day"]'); ?>
            <h4>Letzte Top-Titel</h4>
            <?php echo do_shortcode('[nowscr_lastfm_top_tracks period="7day"]'); ?>
            <h4>Letzte Lieblingslieder</h4>
            <?php echo do_shortcode('[nowscr_lastfm_lovedtracks]'); ?>
            <h3>Trakt</h3>
            <h4>Status (Indicator)</h4>
            <?php echo do_shortcode('[nowscr_trakt_indicator]'); ?>
            <h4>Letzte Scrobbles (History)</h4>
            <?php echo do_shortcode('[nowscr_trakt_history]'); ?>
            <h4>Letzte Filme</h4>
            <?php echo do_shortcode('[nowscr_trakt_last_movie]'); ?>
            <h4>Letzte Filme (mit Bewertung)</h4>
            <?php echo do_shortcode('[nowscr_trakt_last_movie_with_rating]'); ?>
            <h4>Letzte Serien</h4>
            <?php echo do_shortcode('[nowscr_trakt_last_show]'); ?>
            <h4>Letzte Episoden</h4>
            <?php echo do_shortcode('[nowscr_trakt_last_episode]'); ?>
        </div>
    </div>
    <?php
}

?>
