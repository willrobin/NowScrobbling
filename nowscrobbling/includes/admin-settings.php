<?php
/**
 * File:                nowscrobbling/includes/admin-settings.php
 * Description:         Admin settings and configuration for NowScrobbling plugin
 */

// Ensure the script is not accessed directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Admin Menu and Settings Registration
add_action('admin_menu', 'nowscrobbling_admin_menu');
add_action('admin_init', 'nowscrobbling_register_settings');

function nowscrobbling_admin_menu()
{
    add_options_page('NowScrobbling Einstellungen', 'NowScrobbling', 'manage_options', 'nowscrobbling-settings', 'nowscrobbling_settings_page');
}

function nowscrobbling_register_settings()
{
    // Text options
    register_setting('nowscrobbling-settings-group', 'lastfm_api_key', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ]);
    register_setting('nowscrobbling-settings-group', 'lastfm_user', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ]);
    register_setting('nowscrobbling-settings-group', 'trakt_client_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ]);
    register_setting('nowscrobbling-settings-group', 'trakt_user', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ]);

    // Integer helpers
    $int_settings = [
        'top_tracks_count', 'top_artists_count', 'top_albums_count', 'lovedtracks_count',
        'last_movies_count', 'last_shows_count', 'last_episodes_count',
        'cache_duration', 'lastfm_cache_duration', 'trakt_cache_duration',
        'lastfm_activity_limit', 'trakt_activity_limit',
    ];
    foreach ( $int_settings as $setting ) {
        register_setting( 'nowscrobbling-settings-group', $setting, [
            'type' => 'integer',
            'sanitize_callback' => function( $value ) {
                $v = absint( $value );
                return $v < 1 ? 1 : $v;
            }
        ] );
    }

    // Booleans
    register_setting('nowscrobbling-settings-group', 'nowscrobbling_debug_log', [
        'type' => 'boolean',
        'sanitize_callback' => function( $value ) {
            $bool = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            return $bool ? 1 : 0;
        },
        'default' => 0,
    ]);
    register_setting('nowscrobbling-settings-group', 'ns_enable_rewatch', [
        'type' => 'boolean',
        'sanitize_callback' => function( $value ) {
            $bool = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            return $bool ? 1 : 0;
        },
        'default' => 0,
    ]);

    // Debug log content is an array of lines; keep as array or reset to empty array
    register_setting('nowscrobbling-settings-group', 'nowscrobbling_log', [
        'type' => 'array',
        'sanitize_callback' => function( $value ) {
            if ( is_array( $value ) ) {
                return array_map( 'sanitize_text_field', $value );
            }
            return [];
        }
    ]);

    // Polling/backoff tuning
    register_setting('nowscrobbling-settings-group', 'ns_nowplaying_interval', [ 'type' => 'integer', 'sanitize_callback' => function( $value ) { $v = absint($value); return $v < 5 ? 5 : $v; } ]);
    register_setting('nowscrobbling-settings-group', 'ns_max_interval', [ 'type' => 'integer', 'sanitize_callback' => function( $value ) { $v = absint($value); return $v < 30 ? 30 : $v; } ]);
    register_setting('nowscrobbling-settings-group', 'ns_backoff_multiplier', [ 'type' => 'number', 'sanitize_callback' => function( $value ) { $v = floatval($value); return $v < 1 ? 1 : $v; } ]);
}

// Callback functions for settings fields
function nowscrobbling_setting_callback($setting, $type = 'text', $options = [])
{
    $value = get_option($setting, $options['default'] ?? '');
    if ($type === 'select') {
        echo '<select name="' . esc_attr($setting) . '" id="' . esc_attr($setting) . '">';
        foreach (($options['choices'] ?? []) as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'checkbox') {
        // Hidden field ensures unchecked state is saved as 0
        echo '<input type="hidden" name="' . esc_attr($setting) . '" value="0" />';
        $checked = checked(1, (int) $value, false);
        echo '<input type="checkbox" name="' . esc_attr($setting) . '" id="' . esc_attr($setting) . '" value="1" ' . $checked . ' />';
    } else {
        $min = isset($options['min']) ? ' min="' . esc_attr((string)$options['min']) . '"' : '';
        $step = isset($options['step']) ? ' step="' . esc_attr((string)$options['step']) . '"' : '';
        echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($setting) . '" id="' . esc_attr($setting) . '" value="' . esc_attr((string)$value) . '"' . $min . $step . ' />';
    }
}

// Add settings fields
add_action('admin_init', function () {
    add_settings_section('nowscrobbling_section', 'NowScrobbling Einstellungen', function () {
        echo '<p>API-Schlüssel und Benutzernamen für Last.fm und Trakt sowie weitere Einstellungen konfigurieren.</p>';
    }, 'nowscrobbling');

    $fields = [
        ['lastfm_api_key', 'Last.fm API Schlüssel', 'password'],
        ['lastfm_user', 'Last.fm Benutzername', 'text'],
        ['trakt_client_id', 'Trakt Client ID', 'password'],
        ['trakt_user', 'Trakt Benutzername', 'text'],
        ['top_tracks_count', 'Anzahl der Top-Titel', 'number', ['min' => 1, 'step' => 1, 'default' => 5]],
        ['top_artists_count', 'Anzahl der Top-Künstler', 'number', ['min' => 1, 'step' => 1, 'default' => 5]],
        ['top_albums_count', 'Anzahl der Top-Alben', 'number', ['min' => 1, 'step' => 1, 'default' => 5]],
        ['lovedtracks_count', 'Anzahl der Lieblingslieder', 'number', ['min' => 1, 'step' => 1, 'default' => 5]],
        ['last_movies_count', 'Anzahl der letzten Filme', 'number', ['min' => 1, 'step' => 1, 'default' => 3]],
        ['last_shows_count', 'Anzahl der letzten Serien', 'number', ['min' => 1, 'step' => 1, 'default' => 3]],
        ['last_episodes_count', 'Anzahl der letzten Episoden', 'number', ['min' => 1, 'step' => 1, 'default' => 3]],
        ['cache_duration', 'Dauer des Transient-Cache (Minuten)', 'number', ['min' => 1, 'step' => 1, 'default' => 60]],
        ['lastfm_cache_duration', 'Last.fm Cache-Dauer (Minuten)', 'number', ['min' => 1, 'step' => 1, 'default' => 1]],
        ['trakt_cache_duration', 'Trakt Cache-Dauer (Minuten)', 'number', ['min' => 1, 'step' => 1, 'default' => 5]],
        ['lastfm_activity_limit', 'Anzahl der last.fm Aktivitäten', 'number', ['min' => 1, 'step' => 1, 'default' => 5]],
        ['trakt_activity_limit', 'Anzahl der Trakt Aktivitäten', 'number', ['min' => 1, 'step' => 1, 'default' => 5]],
        ['nowscrobbling_debug_log', 'Debug-Log aktivieren', 'checkbox', ['default' => 0]],
        // AJAX is always enabled; option removed
        ['ns_enable_rewatch', 'Rewatch-Zählung aktivieren (sparsam, paginiert)', 'checkbox', ['default' => 0]],
        ['ns_nowplaying_interval', 'Polling: Intervall für Now-Playing (Sekunden)', 'number', ['min' => 5, 'step' => 1, 'default' => 20]],
        ['ns_max_interval', 'Polling: Maximalintervall (Sekunden)', 'number', ['min' => 30, 'step' => 5, 'default' => 300]],
        ['ns_backoff_multiplier', 'Polling: Backoff Multiplikator', 'number', ['min' => 1, 'step' => 0.1, 'default' => 2]],
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

// Settings Page Content
function nowscrobbling_settings_page()
{
    if ( ! current_user_can('manage_options') ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nowscrobbling' ) );
    }

    // Handle form submissions
    if (isset($_POST['clear_log']) && check_admin_referer('nowscrobbling_clear_log', 'nowscrobbling_log_nonce')) {
        delete_option('nowscrobbling_log');
        echo '<div class="updated"><p>Debug-Log wurde geleert.</p></div>';
    }
    
    if (isset($_POST['clear_cache']) && check_admin_referer('nowscrobbling_clear_cache', 'nowscrobbling_nonce')) {
        nowscrobbling_clear_all_caches();
        echo '<div class="updated"><p>Alle Caches wurden erfolgreich geleert.</p></div>';
    }
    
    if (isset($_POST['test_apis']) && check_admin_referer('nowscrobbling_test_apis', 'nowscrobbling_test_nonce')) {
        $results = nowscrobbling_test_api_connections();
        update_option('ns_last_api_test', [ 'ts' => current_time('mysql'), 'results' => $results ], false);
    }
    if (isset($_POST['reset_metrics']) && check_admin_referer('nowscrobbling_reset_metrics', 'nowscrobbling_metrics_nonce')) {
        delete_option('ns_metrics');
        echo '<div class="updated"><p>Metriken wurden zurückgesetzt.</p></div>';
    }
    
    // Precompute config flags used for UI state
    $lastfm_configured = !empty(get_option('lastfm_api_key')) && !empty(get_option('lastfm_user'));
    $trakt_configured  = !empty(get_option('trakt_client_id')) && !empty(get_option('trakt_user'));

    ?>
    <div class="wrap">
        <h1>NowScrobbling Einstellungen</h1>
        <?php if ( function_exists('settings_errors') ) { settings_errors(); } ?>
        <style>
            .ns-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;letter-spacing:.2px}
            .ns-badge.ok{background:#e7f7ee;color:#0a7f3f;border:1px solid #bfe9d0}
            .ns-badge.warn{background:#fff6e5;color:#9a6a00;border:1px solid #ffe2a8}
            .ns-badge.err{background:#fdecec;color:#a10b0b;border:1px solid #f7b4b4}
            .ns-badge.cache{background:#e8f0ff;color:#1a56db;border:1px solid #c4d6ff}
            .ns-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
            .ns-col{padding:6px 0}
            details.ns-block{margin:12px 0}
            details.ns-block > summary{cursor:pointer;font-weight:600}
            .form-table td input[type="text"], .form-table td input[type="number"], .form-table td input[type="password"], .form-table td select {min-width:260px}
            .form-table th, .form-table td { padding:6px 8px }
            .ns-toolbar form{display:inline;margin-right:8px}
            .ns-copy{cursor:pointer; user-select: none}
            .ns-copy:hover{background:#f0f0f1}
            .ns-alt{font-size:12px;color:#555;margin-left: 2px}
            .ns-alt code{cursor:pointer}
            table.ns-preview{width:100%;border-collapse:collapse}
            table.ns-preview th, table.ns-preview td{border-bottom:1px solid #e3e5e8;padding:8px;vertical-align:top}
            table.ns-preview th{text-align:left;color:#333}
            .ns-col-shortcode{width:30%}
            .ns-col-output{width:55%}
            .ns-col-source{width:15%}
            /* kompakter Status-Block */
            .ns-status-table th, .ns-status-table td{padding:4px 8px}
            .ns-status-table tr{line-height:1.2}
        </style>
        
        <!-- Status Overview -->
        <div class="card" style="max-width: 100%; margin-bottom: 20px;">
            <h2 class="title">Status & Verfügbarkeit</h2>
            <table class="form-table ns-status-table">
                <tr>
                    <th>Plugin Version:</th>
                    <td><?php echo esc_html(NOWSCROBBLING_VERSION); ?></td>
                </tr>
                <tr>
                    <th>Cron Job Status:</th>
                    <td>
                        <?php $cron_scheduled = wp_next_scheduled('nowscrobbling_cache_refresh'); ?>
                        <span class="ns-badge <?php echo $cron_scheduled ? 'ok' : 'warn'; ?>">
                            <?php echo $cron_scheduled ? ('Geplant · nächster Lauf in ' . esc_html(human_time_diff(time(), $cron_scheduled))) : 'Nicht geplant'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Cache Status:</th>
                    <td>
                        <?php 
                        $cache_keys = get_option('nowscrobbling_transient_keys', []);
                        $active_caches = 0;
                        foreach ($cache_keys as $key) {
                            if (get_transient($key) !== false) {
                                $active_caches++;
                            }
                        }
                        echo '<span class="ns-badge ' . ($active_caches > 0 ? 'ok' : 'warn') . '">' . intval($active_caches) . ' aktiv</span> von ' . count($cache_keys) . ' Schlüsseln';
                        ?>
                    </td>
                </tr>
            </table>
            <div class="ns-toolbar" style="margin-top:8px;">
                <form method="post">
                    <?php wp_nonce_field('nowscrobbling_clear_cache', 'nowscrobbling_nonce'); ?>
                    <input type="submit" name="clear_cache" value="Alle Caches leeren" class="button">
                </form>
                <form method="post">
                    <?php wp_nonce_field('nowscrobbling_reset_metrics', 'nowscrobbling_metrics_nonce'); ?>
                    <input type="submit" name="reset_metrics" value="Metriken zurücksetzen" class="button">
                </form>
            </div>
        </div>

        <!-- Settings grouped by topic -->
        <div class="card" style="max-width: 100%; margin-bottom: 20px;">
            <h2 class="title">API-Zugangsdaten</h2>
            <?php
                // Nach dem Speichern automatisch API-Verbindungen testen und anzeigen
                if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' ) {
                    $test = nowscrobbling_test_api_connections();
                    echo '<div class="updated" style="margin:10px 0;">';
                    echo '<p style="margin:6px 0;">API-Verbindungen geprüft:</p>';
                    echo '<ul style="margin:0 0 6px 18px;list-style:disc;">';
                    foreach (['lastfm' => 'Last.fm', 'trakt' => 'Trakt'] as $k => $label) {
                        $res = isset($test[$k]) ? $test[$k] : ['status' => 'warning', 'message' => 'Nicht konfiguriert'];
                        $color = ($res['status'] === 'success') ? '#0a7f3f' : (($res['status'] === 'warning') ? '#9a6a00' : '#a10b0b');
                        echo '<li><strong>' . esc_html($label) . ':</strong> <span style="color:' . esc_attr($color) . '">' . esc_html($res['message']) . '</span></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                    update_option('ns_last_api_test', [ 'ts' => current_time('mysql'), 'results' => $test ], false);
                }
            ?>
            <form method="post" action="options.php">
                <?php settings_fields('nowscrobbling-settings-group'); ?>

                <div class="ns-block" style="margin:12px 0;">
                    <div class="ns-grid">
                        <div class="ns-col" style="border:1px solid #e3e5e8;border-radius:6px;padding:8px;">
                            <h3 style="margin:0 0 6px 0;">Last.fm</h3>
                            <table class="form-table" style="margin-top:0;">
                                <tr><th>Benutzername</th><td><?php nowscrobbling_setting_callback('lastfm_user'); ?></td></tr>
                                <tr><th>API Key</th><td><?php nowscrobbling_setting_callback('lastfm_api_key','password'); ?></td></tr>
                                <tr><th>Status</th><td>
                                    <?php 
                                        $cred_hash = function_exists('nowscrobbling_get_service_cred_hash') ? nowscrobbling_get_service_cred_hash('lastfm') : '';
                                        $last_success_map = get_option('ns_last_success', []);
                                        $last_success = isset($last_success_map['lastfm'][$cred_hash]) ? $last_success_map['lastfm'][$cred_hash] : '';
                                        $last_api = get_option('ns_last_api_test');
                                        $api_ok = is_array($last_api) && isset($last_api['results']['lastfm']) && ($last_api['results']['lastfm']['status'] === 'success');
                                        $color = 'err';
                                        if ($last_success && $api_ok) { $color = 'ok'; }
                                        elseif ($last_success && ! $api_ok) { $color = 'warn'; }
                                        else { $color = 'err'; }
                                        $label = $last_success ? ('Zuletzt erfolgreich: ' . $last_success) : 'Noch keine erfolgreiche Antwort';
                                        echo '<span class="ns-badge ' . esc_attr($color) . '" title="Letzte erfolgreiche Antwort">' . esc_html($label) . '</span>';
                                    ?>
                                </td></tr>
                            </table>
                        </div>
                        <div class="ns-col" style="border:1px solid #e3e5e8;border-radius:6px;padding:8px;">
                            <h3 style="margin:0 0 6px 0;">Trakt</h3>
                            <table class="form-table" style="margin-top:0;">
                                <tr><th>Benutzername</th><td><?php nowscrobbling_setting_callback('trakt_user'); ?></td></tr>
                                <tr><th>Client ID</th><td><?php nowscrobbling_setting_callback('trakt_client_id','password'); ?></td></tr>
                                <tr><th>Status</th><td>
                                    <?php 
                                        $cred_hash = function_exists('nowscrobbling_get_service_cred_hash') ? nowscrobbling_get_service_cred_hash('trakt') : '';
                                        $last_success_map = get_option('ns_last_success', []);
                                        $last_success = isset($last_success_map['trakt'][$cred_hash]) ? $last_success_map['trakt'][$cred_hash] : '';
                                        $last_api = get_option('ns_last_api_test');
                                        $api_ok = is_array($last_api) && isset($last_api['results']['trakt']) && ($last_api['results']['trakt']['status'] === 'success');
                                        $color = 'err';
                                        if ($last_success && $api_ok) { $color = 'ok'; }
                                        elseif ($last_success && ! $api_ok) { $color = 'warn'; }
                                        else { $color = 'err'; }
                                        $label = $last_success ? ('Zuletzt erfolgreich: ' . $last_success) : 'Noch keine erfolgreiche Antwort';
                                        echo '<span class="ns-badge ' . esc_attr($color) . '" title="Letzte erfolgreiche Antwort">' . esc_html($label) . '</span>';
                                    ?>
                                </td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                

                

                <?php submit_button('Zugangsdaten speichern'); ?>
            </form>
        </div>

        <!-- Nutzung (Graph) -->
        <div class="card" style="max-width: 100%; margin-bottom: 20px;">
            <h2 class="title">Nutzung</h2>
            <p style="margin:6px 0 12px; color:#555;">Anfragen pro Stunde (letzte 48 Stunden) für Last.fm und Trakt. Zusätzlich Badges für die letzten 24 Stunden.</p>
            <?php
                $ts = get_option('ns_metrics_ts', []);
                $now_ts = (int) current_time('timestamp');
                $labels = [];
                $series = [
                    'lastfm' => [ 'req' => [], 'cache' => [] ],
                    'trakt'  => [ 'req' => [], 'cache' => [] ],
                ];
                for ($i = 47; $i >= 0; $i--) {
                    $t = $now_ts - $i * HOUR_IN_SECONDS;
                    $bucket = date_i18n('YmdH', $t);
                    $labels[] = esc_js( date_i18n('H\u\h', $t) );
                    foreach (['lastfm','trakt'] as $svc) {
                        $row = isset($ts[$bucket][$svc]) ? $ts[$bucket][$svc] : [];
                        $series[$svc]['req'][]      = isset($row['total_requests']) ? (int)$row['total_requests'] : 0;
                        $series[$svc]['cache'][]    = isset($row['cache_hits']) ? (int)$row['cache_hits'] : 0;
                    }
                }
                // Totals since activation (cumulative) and 24h sums
                $metrics = get_option('ns_metrics', []);
                $tot_lastfm = isset($metrics['lastfm']['total_requests']) ? (int)$metrics['lastfm']['total_requests'] : 0;
                $tot_trakt  = isset($metrics['trakt']['total_requests']) ? (int)$metrics['trakt']['total_requests'] : 0;
                $sum_lf_24_req = 0; $sum_lf_24_cache = 0; $sum_tr_24_req = 0; $sum_tr_24_cache = 0;
                $sum_lf_24_etag = 0; $sum_tr_24_etag = 0;
                $sum_lf_24_err  = 0; $sum_tr_24_err  = 0;
                $sum_lf_24_fb   = 0; $sum_tr_24_fb   = 0;
                for ($j = 23; $j >= 0; $j--) {
                    $tt = $now_ts - $j * HOUR_IN_SECONDS;
                    $bk = date_i18n('YmdH', $tt);
                    $rl = isset($ts[$bk]['lastfm']) ? $ts[$bk]['lastfm'] : [];
                    $rt = isset($ts[$bk]['trakt']) ? $ts[$bk]['trakt'] : [];
                    $sum_lf_24_req   += isset($rl['total_requests']) ? (int)$rl['total_requests'] : 0;
                    $sum_lf_24_cache += isset($rl['cache_hits']) ? (int)$rl['cache_hits'] : 0;
                    $sum_lf_24_etag  += isset($rl['etag_hits']) ? (int)$rl['etag_hits'] : 0;
                    $sum_lf_24_err   += isset($rl['total_errors']) ? (int)$rl['total_errors'] : 0;
                    $sum_lf_24_fb    += isset($rl['fallback_hits']) ? (int)$rl['fallback_hits'] : 0;
                    $sum_tr_24_req   += isset($rt['total_requests']) ? (int)$rt['total_requests'] : 0;
                    $sum_tr_24_cache += isset($rt['cache_hits']) ? (int)$rt['cache_hits'] : 0;
                    $sum_tr_24_etag  += isset($rt['etag_hits']) ? (int)$rt['etag_hits'] : 0;
                    $sum_tr_24_err   += isset($rt['total_errors']) ? (int)$rt['total_errors'] : 0;
                    $sum_tr_24_fb    += isset($rt['fallback_hits']) ? (int)$rt['fallback_hits'] : 0;
                }
            ?>
            <div class="ns-grid">
                <div class="ns-col">
                    <h3 style="margin:0 0 6px 0;">Last.fm</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px;align-items:center;">
                        <span class="ns-badge" title="API-Requests (24h)" style="background:#1d4ed8;color:#fff;border:1px solid #1d4ed8;">API 24h: <?php echo (int)$sum_lf_24_req; ?></span>
                        <span class="ns-badge" title="Cache-Hits (24h)" style="background:#10b981;color:#fff;border:1px solid #10b981;">Cache 24h: <?php echo (int)$sum_lf_24_cache; ?></span>
                        <?php if ($sum_lf_24_etag > 0): ?><span class="ns-badge" title="ETag-Hits (24h)" style="background:#6366f1;color:#fff;border:1px solid #6366f1;">ETag 24h: <?php echo (int)$sum_lf_24_etag; ?></span><?php endif; ?>
                        <?php if ($sum_lf_24_err > 0): ?><span class="ns-badge" title="Fehler (24h)" style="background:#ef4444;color:#fff;border:1px solid #ef4444;">Fehler 24h: <?php echo (int)$sum_lf_24_err; ?></span><?php endif; ?>
                        <?php if ($sum_lf_24_fb > 0): ?><span class="ns-badge" title="Fallback-Hits (24h)" style="background:#f59e0b;color:#fff;border:1px solid #f59e0b;">Fallback 24h: <?php echo (int)$sum_lf_24_fb; ?></span><?php endif; ?>
                    </div>
                    <canvas id="nsChartLastfm" width="640" height="220" style="max-width:100%;border:1px solid #e3e5e8;border-radius:6px;background:#fff"></canvas>
                </div>
                <div class="ns-col">
                    <h3 style="margin:0 0 6px 0;">Trakt</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px;align-items:center;">
                        <span class="ns-badge" title="API-Requests (24h)" style="background:#1d4ed8;color:#fff;border:1px solid #1d4ed8;">API 24h: <?php echo (int)$sum_tr_24_req; ?></span>
                        <span class="ns-badge" title="Cache-Hits (24h)" style="background:#10b981;color:#fff;border:1px solid #10b981;">Cache 24h: <?php echo (int)$sum_tr_24_cache; ?></span>
                        <?php if ($sum_tr_24_etag > 0): ?><span class="ns-badge" title="ETag-Hits (24h)" style="background:#6366f1;color:#fff;border:1px solid #6366f1;">ETag 24h: <?php echo (int)$sum_tr_24_etag; ?></span><?php endif; ?>
                        <?php if ($sum_tr_24_err > 0): ?><span class="ns-badge" title="Fehler (24h)" style="background:#ef4444;color:#fff;border:1px solid #ef4444;">Fehler 24h: <?php echo (int)$sum_tr_24_err; ?></span><?php endif; ?>
                        <?php if ($sum_tr_24_fb > 0): ?><span class="ns-badge" title="Fallback-Hits (24h)" style="background:#f59e0b;color:#fff;border:1px solid #f59e0b;">Fallback 24h: <?php echo (int)$sum_tr_24_fb; ?></span><?php endif; ?>
                    </div>
                    <canvas id="nsChartTrakt" width="640" height="220" style="max-width:100%;border:1px solid #e3e5e8;border-radius:6px;background:#fff"></canvas>
                </div>
            </div>
            <script>
            (function(){
                // Minimal Canvas line-drawing helper (no external deps)
                function drawChart(canvasId, labels, series){
                    var c = document.getElementById(canvasId);
                    if(!c) return;
                    var ctx = c.getContext('2d');
                    var W = c.width, H = c.height;
                    ctx.clearRect(0,0,W,H);
                    // padding
                    var P = { l: 32, r: 10, t: 10, b: 22 };
                    var plotW = W - P.l - P.r, plotH = H - P.t - P.b;
                    function maxOf(arr){ return arr.reduce(function(m,v){ return v>m? v:m; }, 0); }
                    var maxY = Math.max(1, maxOf(series.req.concat(series.cache)));
                    // grid
                    ctx.strokeStyle = '#eef1f4';
                    ctx.lineWidth = 1;
                    for(var i=0;i<=4;i++){
                        var y = P.t + (plotH * i / 4);
                        ctx.beginPath(); ctx.moveTo(P.l,y); ctx.lineTo(W-P.r,y); ctx.stroke();
                    }
                    // y labels
                    ctx.fillStyle = '#6b7280'; ctx.font = '11px sans-serif';
                    for(var i=0;i<=4;i++){
                        var val = Math.round(maxY * (1 - i/4));
                        var y = P.t + (plotH * i / 4);
                        ctx.fillText(String(val), 4, y+3);
                    }
                    // x labels (every 6th)
                    var n = labels.length;
                    for(var i=0;i<n;i+=6){
                        var x = P.l + (plotW * i / (n-1));
                        ctx.fillText(labels[i], x-12, H-6);
                    }
                    function pathOf(values){
                        ctx.beginPath();
                        for(var i=0;i<values.length;i++){
                            var x = P.l + (plotW * i / (values.length-1));
                            var y = P.t + plotH * (1 - (values[i]/maxY));
                            if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
                        }
                    }
                    function drawLine(values, color){
                        ctx.strokeStyle = color; ctx.lineWidth = 2; pathOf(values); ctx.stroke();
                    }
                    // draw lines: requests, cache
                    drawLine(series.req, '#1d4ed8'); // blue
                    drawLine(series.cache, '#10b981'); // green
                    // Legend
                    var legend = [ ['Anfragen','#1d4ed8'], ['Cache','#10b981'] ];
                    var lx = W - P.r - 160, ly = P.t + 6;
                    legend.forEach(function(item, idx){
                        ctx.fillStyle = item[1]; ctx.fillRect(lx, ly + idx*16 - 8, 10, 10);
                        ctx.fillStyle = '#374151'; ctx.fillText(item[0], lx + 14, ly + idx*16);
                    });
                }
                var labels = <?php echo wp_json_encode($labels); ?>;
                var data = <?php echo wp_json_encode($series); ?>;
                drawChart('nsChartLastfm', labels, data.lastfm);
                drawChart('nsChartTrakt', labels, data.trakt);
            })();
            </script>
        </div>

        

        <!-- Metriken -->
        <div class="card" style="max-width: 100%; margin-bottom: 20px;">
            <details class="ns-block">
                <summary>Metriken (Erklärung & Werte)</summary>
                <p style="margin:6px 0 12px; color:#555;">
                    Anfragen: Anzahl API-Requests · Fehler: HTTP 4xx/5xx/Netzwerk · ETag: 304/Unverändert · Cache: WordPress-Transient · Fallback: Langzeit-Cache nach Fehler · Letzte Antwort: Dauer/Status letzter Request
                </p>
                <table class="form-table">
                    <?php $metrics = get_option('ns_metrics', []); 
                    $services = ['lastfm' => 'Last.fm', 'trakt' => 'Trakt', 'generic' => 'Sonstige'];
                    foreach ($services as $key => $label):
                        $m = isset($metrics[$key]) && is_array($metrics[$key]) ? $metrics[$key] : [];
                        $tr = (int) ($m['total_requests'] ?? 0);
                        $te = (int) ($m['total_errors'] ?? 0);
                        $eh = (int) ($m['etag_hits'] ?? 0);
                        $ch = (int) ($m['cache_hits'] ?? 0);
                        $fh = (int) ($m['fallback_hits'] ?? 0);
                        $ms = (int) ($m['last_ms'] ?? 0);
                        $st = (int) ($m['last_status'] ?? 0);
                    ?>
                    <tr>
                        <th><?php echo esc_html($label); ?></th>
                        <td>
                            <span class="ns-badge ok" style="margin-right:6px;">Anfragen: <?php echo $tr; ?></span>
                            <span class="ns-badge <?php echo $te ? 'err' : 'ok'; ?>" style="margin-right:6px;">Fehler: <?php echo $te; ?></span>
                            <span class="ns-badge ok" style="margin-right:6px;" title="304/Unverändert dank ETag">ETag: <?php echo $eh; ?></span>
                            <span class="ns-badge ok" style="margin-right:6px;" title="Transient-Cache-Treffer">Cache: <?php echo $ch; ?></span>
                            <span class="ns-badge warn" style="margin-right:6px;" title="Langzeit-Cache nach Fehler">Fallback: <?php echo $fh; ?></span>
                            <span>Letzte Antwort: <strong><?php echo $ms; ?>ms</strong> · HTTP: <strong><?php echo $st; ?></strong></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </details>
        </div>

        <!-- Debug Log -->
        <div class="card" style="max-width: 100%; margin-bottom: 20px;">
            <details class="ns-block" open>
                <summary>Debug-Log</summary>
                <div style="max-height:300px;overflow:auto;font-size:12px;">
                <?php
                    $log = get_option('nowscrobbling_log', []);
                    if (!is_array($log)) { $log = []; }
                    $lines = array_reverse(array_slice($log, -100)); // latest first
                    foreach ($lines as $entry) {
                        // Expect format: [YYYY-MM-DD HH:MM:SS] message
                        $ts = '';
                        $msg = $entry;
                        if (preg_match('/^\[(.*?)\]\s*(.*)$/', $entry, $m)) {
                            $ts = $m[1];
                            $msg = $m[2];
                        }
                        $status = 'Okay';
                        $color = '#0a7f3f';
                        if (stripos($msg, 'error') !== false || stripos($msg, 'fehl') !== false || stripos($msg, 'failed') !== false) { $status = 'Fehler'; $color = '#a10b0b'; }
                        elseif (stripos($msg, 'warn') !== false || stripos($msg, 'cooldown') !== false) { $status = 'Warnung'; $color = '#9a6a00'; }
                        $line = trim(($ts ? '[' . $ts . '] ' : '') . $status . ': ' . $msg);
                        echo '<div style="margin:2px 0;">';
                        echo '<span style="font-family:monospace;white-space:pre-wrap;color:' . esc_attr($color) . ';">' . esc_html($line) . '</span>';
                        echo '</div>';
                    }
                ?>
                </div>
                <form method="post" style="margin: 10px 0;">
                    <?php wp_nonce_field('nowscrobbling_clear_log', 'nowscrobbling_log_nonce'); ?>
                    <input type="submit" name="clear_log" value="Log leeren" class="button">
                </form>
            </details>
        </div>
        

        

        <!-- Preview Section -->
        <div class="card" style="max-width: 100%;">
            <h2 class="title">Vorschau der Daten</h2>
            <div id="nowscrobbling-preview" style="margin-top:1em;">
                    <?php
                        $shortcodes = [
                            'Last.fm' => [
                                ['[nowscr_lastfm_indicator]', 'Indikator (Now Playing/Zuletzt)', 'lastfm', ['[nowscr_lastfm_indicator]']],
                                ['[nowscr_lastfm_history]', 'Letzte Scrobbles', 'lastfm', ['[nowscr_lastfm_history max_length="30"]']],
                                ['[nowscr_lastfm_top_artists]', 'Top-Künstler', 'lastfm', [
                                    '[nowscr_lastfm_top_artists]',
                                    '[nowscr_lastfm_top_artists period="overall"]',
                                    '[nowscr_lastfm_top_artists period="7day" max_length="15"]'
                                ]],
                                ['[nowscr_lastfm_top_albums]', 'Top-Alben', 'lastfm', [
                                    '[nowscr_lastfm_top_albums]',
                                    '[nowscr_lastfm_top_albums period="overall"]',
                                    '[nowscr_lastfm_top_albums period="7day" max_length="45"]'
                                ]],
                                ['[nowscr_lastfm_top_tracks]', 'Top-Titel', 'lastfm', [
                                    '[nowscr_lastfm_top_tracks]',
                                    '[nowscr_lastfm_top_tracks period="overall"]',
                                    '[nowscr_lastfm_top_tracks period="7day" max_length="45"]'
                                ]],
                                ['[nowscr_lastfm_lovedtracks]', 'Lieblingslieder', 'lastfm', ['[nowscr_lastfm_lovedtracks max_length="30"]']],
                            ],
                            'Trakt' => [
                                ['[nowscr_trakt_indicator]', 'Indikator (Watching/Zuletzt)', 'trakt', ['[nowscr_trakt_indicator]']],
                                ['[nowscr_trakt_history]', 'History', 'trakt', [
                                    '[nowscr_trakt_history]',
                                    '[nowscr_trakt_history show_year="true"]',
                                    '[nowscr_trakt_history show_year="true" show_rating="true"]',
                                    '[nowscr_trakt_history show_year="true" show_rating="true" show_rewatch="true"]'
                                ]],
                                ['[nowscr_trakt_last_movie]', 'Letzte Filme', 'trakt', [
                                    '[nowscr_trakt_last_movie]',
                                    '[nowscr_trakt_last_movie show_year="true"]',
                                    '[nowscr_trakt_last_movie show_year="true" show_rating="true"]',
                                    '[nowscr_trakt_last_movie show_year="true" show_rating="true" show_rewatch="true"]'
                                ]],
                                ['[nowscr_trakt_last_show]', 'Letzte Serien', 'trakt', [
                                    '[nowscr_trakt_last_show]',
                                    '[nowscr_trakt_last_show show_year="true"]',
                                    '[nowscr_trakt_last_show show_year="true" show_rating="true"]',
                                    '[nowscr_trakt_last_show show_year="true" show_rating="true" show_rewatch="true"]'
                                ]],
                                ['[nowscr_trakt_last_episode]', 'Letzte Episoden', 'trakt', [
                                    '[nowscr_trakt_last_episode]',
                                    '[nowscr_trakt_last_episode show_year="true"]',
                                    '[nowscr_trakt_last_episode show_year="true" show_rating="true"]',
                                    '[nowscr_trakt_last_episode show_year="true" show_rating="true" show_rewatch="true"]'
                                ]],
                            ],
                        ];
                    function ns_render_shortcode_row($code, $label, $service, $alts = []){
                            $html = do_shortcode($code);
                            $src = isset($GLOBALS['nowscrobbling_last_source']) ? $GLOBALS['nowscrobbling_last_source'] : 'unbekannt';
                            $key = isset($GLOBALS['nowscrobbling_last_source_key']) ? $GLOBALS['nowscrobbling_last_source_key'] : '';
                            $meta = isset($GLOBALS['nowscrobbling_last_meta']) && is_array($GLOBALS['nowscrobbling_last_meta']) ? $GLOBALS['nowscrobbling_last_meta'] : [];
                            $badge_class = 'warn';
                            if ($src === 'cache') $badge_class = 'cache';
                            if ($src === 'fresh') $badge_class = 'ok';
                            if ($src === 'fallback') $badge_class = 'warn';
                            if ($src === 'miss') $badge_class = 'err';
                        $src_display = ucfirst($src);
                        $src_badge = ($src === 'fresh') ? 'ok' : (($src === 'cache') ? 'cache' : (($src === 'miss') ? 'err' : 'warn'));
                        $minimal = preg_replace('/\s+period="[^"]*"/','',$code);
                        $minimal = preg_replace('/\s+max_length="[^"]*"/','',$minimal);

                        // Build diagnostic explanation
                        $service_key = isset($meta['service']) ? $meta['service'] : ( $service ?: 'generic' );
                        $cooldown_active = function_exists('nowscrobbling_should_cooldown') ? (bool) nowscrobbling_should_cooldown($service_key) : false;
                        $expires_at = isset($meta['expires_at']) ? intval($meta['expires_at']) : 0;
                        $saved_at   = isset($meta['saved_at']) ? intval($meta['saved_at']) : 0;
                        $ttl        = isset($meta['ttl']) ? intval($meta['ttl']) : 0;
                        $fallback_key = isset($meta['fallback_key']) ? (string) $meta['fallback_key'] : '';
                        $fallback_exists = !empty($meta['fallback_exists']);
                        $fallback_saved_at = isset($meta['fallback_saved_at']) ? intval($meta['fallback_saved_at']) : 0;
                        $fallback_expires_at = isset($meta['fallback_expires_at']) ? intval($meta['fallback_expires_at']) : 0;

                        $reason = '—';
                        if ($src === 'cache') {
                            if ($expires_at) {
                                $reason = 'Cache-Hit (noch gültig bis ' . esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $expires_at ) ) . ')';
                            } else {
                                $reason = 'Cache-Hit (noch gültig)';
                            }
                        } elseif ($src === 'fresh') {
                            $reason = 'Neu geladen (Cache leer/abgelaufen oder erzwungen)';
                        } elseif ($src === 'fallback') {
                            $reason = 'Fallback-Daten genutzt (Primärabruf fehlgeschlagen)';
                        } elseif ($src === 'miss') {
                            $reason = 'Keine Daten verfügbar';
                        }

                        // Optional metrics per service
                        $metrics = get_option('ns_metrics', []);
                        $m = isset($metrics[$service_key]) && is_array($metrics[$service_key]) ? $metrics[$service_key] : [];
                        $last_ms = isset($m['last_ms']) ? intval($m['last_ms']) : 0;
                        $last_status = isset($m['last_status']) ? intval($m['last_status']) : 0;

                        echo '<tr>';
                        echo '<td class="ns-col-shortcode"><div style="font-weight:600;margin-bottom:4px;">' . esc_html($label) . '</div><code class="ns-copy" onclick="navigator.clipboard.writeText(\'' . esc_js($minimal) . '\'); this.innerText=\'Kopiert!\'; setTimeout(()=>this.innerText=\'' . esc_js($minimal) . '\', 1200);" title="Klicken zum Kopieren" style="display:inline-block;background:#f6f7f7;border:1px solid #ccd0d4;padding:4px 6px;border-radius:4px;">' . esc_html($minimal) . '</code></td>';
                        echo '<td class="ns-col-output">' . $html . '</td>';
                        echo '<td class="ns-col-source">';
                        echo '<div style="display:flex;flex-direction:column;gap:4px;">';
                        echo '<div><span class="ns-badge ' . esc_attr($src_badge) . '" title="Quelle der Daten">' . esc_html($src_display) . '</span> <span style="font-size:11px;color:#555;">' . esc_html($reason) . '</span></div>';
                        echo '<details class="ns-block" style="margin:0"><summary class="ns-alt">Details</summary><div class="ns-alt">';
                        if ($key) {
                            echo '<div><strong>Cache-Key</strong>: <code>' . esc_html($key) . '</code></div>';
                        }
                        if ($saved_at) {
                            echo '<div><strong>Gespeichert</strong>: ' . esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $saved_at ) ) . '</div>';
                        }
                        if ($expires_at) {
                            echo '<div><strong>Läuft ab</strong>: ' . esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $expires_at ) ) . '</div>';
                        }
                        if ($ttl) {
                            echo '<div><strong>TTL</strong>: ' . esc_html( round($ttl/60) ) . ' Min.</div>';
                        }
                        if ($fallback_key) {
                            echo '<div><strong>Fallback-Key</strong>: <code>' . esc_html($fallback_key) . '</code>' . ($fallback_exists ? ' <span class="ns-badge warn">vorhanden</span>' : '') . '</div>';
                        }
                        if ($fallback_saved_at) {
                            echo '<div><strong>Fallback gespeichert</strong>: ' . esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $fallback_saved_at ) ) . '</div>';
                        }
                        if ($fallback_expires_at) {
                            echo '<div><strong>Fallback läuft ab</strong>: ' . esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $fallback_expires_at ) ) . '</div>';
                        }
                        echo '<div><strong>Service</strong>: ' . esc_html( ucfirst($service_key) ) . '</div>';
                        echo '<div><strong>Cooldown</strong>: ' . ( $cooldown_active ? '<span class="ns-badge warn">aktiv</span>' : '<span class="ns-badge ok">nein</span>' ) . '</div>';
                        if ($last_ms || $last_status) {
                            echo '<div><strong>Letzte Antwort</strong>: ' . esc_html($last_ms) . 'ms · HTTP ' . esc_html($last_status) . '</div>';
                        }
                        echo '</div></details>';
                        echo '</div>';
                        echo '</td>';
                        echo '</tr>';
                        if (!empty($alts) && count($alts) > 1) {
                            echo '<tr><td colspan="3" style="padding-top:0;">';
                            echo '<details class="ns-block"><summary class="ns-alt">Alternative Optionen</summary><div class="ns-alt">';
                            $first = true;
                            foreach ($alts as $alt) {
                                if (!$first) echo ' · ';
                                echo '<code class="ns-copy" onclick="navigator.clipboard.writeText(\'' . esc_js($alt) . '\'); this.innerText=\'Kopiert!\'; setTimeout(()=>this.innerText=\'' . esc_js($alt) . '\', 1200);" title="Klicken zum Kopieren">' . esc_html($alt) . '</code>';
                                $first = false;
                            }
                            echo '</div></details>';
                            echo '</td></tr>';
                        }
                        }
                    ?>
                <h3>Last.fm</h3>
                <table class="ns-preview">
                    <thead><tr><th class="ns-col-shortcode">Shortcode</th><th class="ns-col-output">Ausgabe</th><th class="ns-col-source">Quelle</th></tr></thead>
                    <tbody>
                        <?php foreach ($shortcodes['Last.fm'] as $row) { ns_render_shortcode_row($row[0], $row[1], $row[2], $row[3] ?? []); } ?>
                    </tbody>
                </table>

                <h3>Trakt</h3>
                <table class="ns-preview">
                    <thead><tr><th class="ns-col-shortcode">Shortcode</th><th class="ns-col-output">Ausgabe</th><th class="ns-col-source">Quelle</th></tr></thead>
                    <tbody>
                        <?php foreach ($shortcodes['Trakt'] as $row) { ns_render_shortcode_row($row[0], $row[1], $row[2], $row[3] ?? []); } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Test API connections
 */
function nowscrobbling_test_api_connections() {
    $results = [];
    
    // Test Last.fm
    if (get_option('lastfm_api_key') && get_option('lastfm_user')) {
        try {
            // Test mit der korrekten Method-Syntax
            $test_data = nowscrobbling_fetch_lastfm_data('getrecenttracks', ['limit' => 1]);
            if ($test_data && !isset($test_data['error'])) {
                $results['lastfm'] = ['status' => 'success', 'message' => 'Last.fm API funktioniert korrekt'];
            } else {
                $error_msg = isset($test_data['message']) ? $test_data['message'] : 'Unbekannter Fehler';
                $results['lastfm'] = ['status' => 'error', 'message' => 'Last.fm API Fehler: ' . $error_msg];
            }
        } catch (Exception $e) {
            $results['lastfm'] = ['status' => 'error', 'message' => 'Last.fm API Exception: ' . $e->getMessage()];
        }
    } else {
        $results['lastfm'] = ['status' => 'warning', 'message' => 'Last.fm nicht konfiguriert'];
    }
    
    // Test Trakt
    if (get_option('trakt_client_id') && get_option('trakt_user')) {
        try {
            $user = get_option('trakt_user');
            // Test mit einem einfacheren Endpoint
            // Use a simple endpoint with a low error surface; trakt profile can require auth.
            $test_data = nowscrobbling_fetch_trakt_data("users/$user/stats");
            if (is_array($test_data)) {
                $results['trakt'] = ['status' => 'success', 'message' => 'Trakt API funktioniert korrekt'];
            } else {
                $error_msg = is_array($test_data) && isset($test_data['error']) ? $test_data['error'] : 'Keine Antwort erhalten';
                $results['trakt'] = ['status' => 'error', 'message' => 'Trakt API Fehler: ' . $error_msg];
            }
        } catch (Exception $e) {
            $results['trakt'] = ['status' => 'error', 'message' => 'Trakt API Exception: ' . $e->getMessage()];
        }
    } else {
        $results['trakt'] = ['status' => 'warning', 'message' => 'Trakt nicht konfiguriert'];
    }
    
    return $results;
}
?>