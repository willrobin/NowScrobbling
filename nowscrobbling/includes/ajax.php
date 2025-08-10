<?php
/**
 * File:                nowscrobbling/includes/ajax.php
 * Description:         AJAX handlers for NowScrobbling plugin
 */

// Sicherheitshalber direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// AJAX-Endpunkt für eingeloggte und nicht eingeloggte Nutzer
add_action('wp_ajax_nowscrobbling_render_shortcode', 'nowscrobbling_render_shortcode_callback');
add_action('wp_ajax_nopriv_nowscrobbling_render_shortcode', 'nowscrobbling_render_shortcode_callback');

/**
 * AJAX Callback: Rendert den angegebenen Shortcode
 */
function nowscrobbling_render_shortcode_callback() {
    // Nonce check for security
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'nowscrobbling_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Ungültige Anfrage (nonce).' ], 403 );
    }
    
    if ( ! isset( $_POST['shortcode'] ) ) {
        wp_send_json_error( [ 'message' => 'Shortcode fehlt.' ], 400 );
    }

    // Strict whitelist for allowed shortcodes
    $allowed_shortcodes = [
        'nowscr_lastfm_indicator',
        'nowscr_lastfm_history',
        'nowscr_lastfm_top_artists',
        'nowscr_lastfm_top_albums',
        'nowscr_lastfm_top_tracks',
        'nowscr_lastfm_lovedtracks',
        'nowscr_trakt_indicator',
        'nowscr_trakt_history',
        'nowscr_trakt_last_movie',
        'nowscr_trakt_last_show',
        'nowscr_trakt_last_episode',
    ];

    $shortcode = sanitize_text_field( wp_unslash( $_POST['shortcode'] ) );

    if ( !in_array( $shortcode, $allowed_shortcodes, true ) ) {
        wp_send_json_error( [ 'message' => 'Shortcode nicht erlaubt.' ], 400 );
    }

    // Check if we should force refresh (first load after SSR, debugging or manual refresh)
    $force_refresh = false;
    if ( isset( $_POST['force_refresh'] ) ) {
        $raw = wp_unslash( sanitize_text_field( (string) $_POST['force_refresh'] ) );
        $force_refresh = filter_var( $raw, FILTER_VALIDATE_BOOLEAN );
    }
    
    // Get current hash if provided
    $current_hash = isset( $_POST['current_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['current_hash'] ) ) : '';
    
    // Optional attribute passthrough (from wrapper data-ns-attrs)
    $attrs = [];
    if ( isset( $_POST['attrs'] ) ) {
        $decoded = json_decode( wp_unslash( sanitize_text_field( (string) $_POST['attrs'] ) ), true );
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $k => $v ) {
                $key = sanitize_key( (string) $k );
                if ( $key === '' ) { continue; }
                if ( is_bool( $v ) ) {
                    $attrs[$key] = $v ? 'true' : 'false';
                } else {
                    $attrs[$key] = sanitize_text_field( (string) $v );
                }
            }
        }
    }

    // Build shortcode string with attributes
    $attr_str = '';
    foreach ($attrs as $k => $v) {
        $attr_str .= ' ' . $k . '="' . esc_attr( $v ) . '"';
    }

    // Allow forcing cache refresh for the duration of this render only
    if ($force_refresh) {
        if (!defined('NOWSCROBBLING_FORCE_REFRESH')) {
            define('NOWSCROBBLING_FORCE_REFRESH', true);
        }
    }

    // Render the shortcode (server-side; will use transients or refresh depending on flag)
    $output = do_shortcode("[$shortcode$attr_str]");
    
    if (empty($output)) {
        wp_send_json_error( [ 'message' => 'Shortcode konnte nicht gerendert werden.' ], 500 );
    }

    // Extract hash from the output
    $hash = '';
    if (preg_match('/data-ns-hash="([^"]+)"/', $output, $matches)) {
        $hash = $matches[1];
    } else {
        // Fallback: generate hash from content
        $hash = substr(md5(strip_tags($output)), 0, 12);
    }

    // Check if content has changed
    $content_changed = empty($current_hash) || $current_hash !== $hash;
    
    // If content hasn't changed and we're not forcing refresh, return early
    if (!$content_changed && !$force_refresh) {
        wp_send_json_success([
            'html' => $output,
            'hash' => $hash,
            'ts' => time(),
            'changed' => false,
            'message' => 'Content unchanged'
        ]);
    }

    // Include source/meta (from last shortcode render via globals) for admin diagnostics
    $source = isset($GLOBALS['nowscrobbling_last_source']) ? (string) $GLOBALS['nowscrobbling_last_source'] : '';
    $meta   = isset($GLOBALS['nowscrobbling_last_meta']) && is_array($GLOBALS['nowscrobbling_last_meta']) ? $GLOBALS['nowscrobbling_last_meta'] : [];

    wp_send_json_success([
        'html' => $output,
        'hash' => $hash,
        'ts' => time(),
        'changed' => $content_changed,
        'source' => $source,
        'meta'   => $meta,
    ]);
}

/**
 * AJAX Callback: Clear all caches (admin only)
 */
function nowscrobbling_clear_cache_callback() {
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ], 403 );
    }

    // Nonce check
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'nowscrobbling_admin_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Ungültige Anfrage (nonce).' ], 403 );
    }

    try {
        nowscrobbling_clear_all_caches();
        wp_send_json_success(['message' => 'Alle Caches erfolgreich gelöscht.']);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Fehler beim Löschen der Caches: ' . $e->getMessage()], 500);
    }
}
add_action('wp_ajax_nowscrobbling_clear_cache', 'nowscrobbling_clear_cache_callback');

/**
 * AJAX Callback: Get debug information (admin only)
 */
function nowscrobbling_debug_info_callback() {
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ], 403 );
    }

    // Nonce check
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'nowscrobbling_admin_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Ungültige Anfrage (nonce).' ], 403 );
    }

    $debug_info = [
        'version' => NOWSCROBBLING_VERSION,
        'php_version' => PHP_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'cache_keys' => get_option('nowscrobbling_transient_keys', []),
        'lastfm_configured' => !empty(get_option('lastfm_api_key')) && !empty(get_option('lastfm_user')),
        'trakt_configured' => !empty(get_option('trakt_client_id')) && !empty(get_option('trakt_user')),
        'debug_log_enabled' => (bool) get_option('nowscrobbling_debug_log', false),
        'cron_scheduled' => wp_next_scheduled('nowscrobbling_cache_refresh') !== false,
        // Zusatzinfos für Admin-Liveaktualisierung
        'next_cache' => (int) ( wp_next_scheduled('nowscrobbling_cache_refresh') ?: 0 ),
        'next_tick'  => (int) ( wp_next_scheduled('nowscrobbling_nowplaying_tick') ?: 0 ),
        'flags' => [
            'lastfm_nowplaying' => (int) get_option('ns_flag_lastfm_nowplaying', 0),
            'trakt_watching'    => (int) get_option('ns_flag_trakt_watching', 0),
        ],
    ];

    wp_send_json_success($debug_info);
}
add_action('wp_ajax_nowscrobbling_debug_info', 'nowscrobbling_debug_info_callback');

/**
 * AJAX Callback: Erstellt einen Test-Log-Eintrag (admin only)
 */
function nowscrobbling_test_log_callback() {
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ], 403 );
    }

    // Nonce check
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'nowscrobbling_admin_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Ungültige Anfrage (nonce).' ], 403 );
    }

    // Prüfe Debug-Status und aktiviere es bei Bedarf
    $debug_enabled = (bool) get_option('nowscrobbling_debug_log', 0);
    if (!$debug_enabled) {
        update_option('nowscrobbling_debug_log', 1);
        nowscrobbling_log('Debug-Log wurde automatisch aktiviert');
    }
    
    // 1. Direkter Log-Eintrag (umgeht nowscrobbling_log)
    $log = get_option('nowscrobbling_log', []);
    if (!is_array($log)) { $log = []; }
    $log[] = '[' . current_time('mysql') . '] Test-Log-Eintrag via AJAX (direkt geschrieben)';
    $result1 = update_option('nowscrobbling_log', $log);

    // 2. Standard-Log über Funktion
    nowscrobbling_log('Test-Log-Eintrag via AJAX (über nowscrobbling_log Funktion)');

    // 3. Erweitere die Debug-Infos für eine ausführliche Antwort
    $debug_info = [
        'success' => $result1,
        'debug_enabled' => $debug_enabled,
        'log_count' => count($log),
        'message' => 'Test-Log-Einträge wurden erstellt' . ($debug_enabled ? '' : ' (trotz deaktiviertem Debug-Log)'),
    ];

    wp_send_json_success($debug_info);
}
add_action('wp_ajax_nowscrobbling_test_log', 'nowscrobbling_test_log_callback');