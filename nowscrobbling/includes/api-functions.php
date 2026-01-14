<?php
/**
 * File:                nowscrobbling/includes/api-functions.php
 */

// Ensure the script is not accessed directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants for API URLs (prefixed to avoid collisions)
if (!defined('NOWSCROBBLING_LASTFM_API_URL')) {
    define('NOWSCROBBLING_LASTFM_API_URL', 'https://ws.audioscrobbler.com/2.0/');
}
if (!defined('NOWSCROBBLING_TRAKT_API_URL')) {
    define('NOWSCROBBLING_TRAKT_API_URL', 'https://api.trakt.tv/');
}

// Option getters (lazy, always current)
function nowscrobbling_opt( $key, $default = '' ) {
    $val = get_option( $key );
    return ( $val === '' || $val === null ) ? $default : $val;
}

function nowscrobbling_cache_minutes( $service ) {
    if ( $service === 'lastfm' ) {
        return (int) nowscrobbling_opt( 'lastfm_cache_duration', 1 );
    }
    if ( $service === 'trakt' ) {
        return (int) nowscrobbling_opt( 'trakt_cache_duration', 5 );
    }
    return (int) nowscrobbling_opt( 'cache_duration', 5 );
}

function nowscrobbling_user_agent() {
    $ver = defined('NOWSCROBBLING_VERSION') ? NOWSCROBBLING_VERSION : 'dev';
    return 'NowScrobbling/' . $ver . '; ' . home_url( '/' );
}

/**
 * Lightweight metrics storage in options.
 * Keys per service: total_requests, total_errors, etag_hits, cache_hits, fallback_hits, last_ms, last_status
 */
function nowscrobbling_metrics_update( $service, $field, $value = null ) {
    $metrics = get_option( 'ns_metrics', [] );
    if ( ! isset( $metrics[ $service ] ) || ! is_array( $metrics[ $service ] ) ) {
        $metrics[ $service ] = [
            'total_requests' => 0,
            'total_errors'   => 0,
            'etag_hits'      => 0,
            'cache_hits'     => 0,
            'fallback_hits'  => 0,
            'cooldown_skips' => 0,
            'last_ms'        => 0,
            'last_status'    => 0,
        ];
    }
    if ( is_array( $field ) ) {
        foreach ( $field as $k => $v ) {
            $metrics[ $service ][ $k ] = $v;
        }
    } else {
        if ( $value === null ) {
            $metrics[ $service ][ $field ] = (int) ( $metrics[ $service ][ $field ] ?? 0 ) + 1;
        } else {
            $metrics[ $service ][ $field ] = $value;
        }
    }
    update_option( 'ns_metrics', $metrics, false );

    // Time-series bucket for admin graph (hourly)
    $ts = get_option('ns_metrics_ts', []);
    if ( ! is_array($ts) ) { $ts = []; }
    $bucket = date_i18n('YmdH', (int) current_time('timestamp'));
    if ( ! isset($ts[$bucket]) || ! is_array($ts[$bucket]) ) { $ts[$bucket] = []; }
    if ( ! isset($ts[$bucket][$service]) || ! is_array($ts[$bucket][$service]) ) {
        $ts[$bucket][$service] = [
            'total_requests' => 0,
            'total_errors'   => 0,
            'etag_hits'      => 0,
            'cache_hits'     => 0,
            'fallback_hits'  => 0,
            'cooldown_skips' => 0,
        ];
    }
    if ( is_array($field) ) {
        foreach ( $field as $k => $v ) {
            // Only store numeric values in time-series
            if ( isset($ts[$bucket][$service][$k]) ) {
                $ts[$bucket][$service][$k] = is_numeric($v) ? (int) $v : $ts[$bucket][$service][$k];
            }
        }
    } else {
        if ( isset($ts[$bucket][$service][$field]) ) {
            $ts[$bucket][$service][$field] = (int) ( $ts[$bucket][$service][$field] ?? 0 ) + 1;
        }
    }
    // Keep at most 96 buckets (~48h) to limit option size
    if ( count($ts) > 96 ) {
        ksort($ts); // oldest first by key string
        while ( count($ts) > 96 ) { array_shift($ts); }
    }
    update_option('ns_metrics_ts', $ts, false);
}

/**
 * Update or read cache metadata for a given transient key.
 * Stores: saved_at, expires_at, ttl, last_access, service, fallback_key, fallback_saved_at, fallback_expires_at.
 *
 * This metadata is used for admin preview diagnostics to explain source decisions.
 *
 * @param string $transient_key
 * @param array $data Optional data to merge into meta. If empty, function returns current meta.
 * @return array The current/updated meta for this key.
 */
function nowscrobbling_cache_meta( $transient_key, $data = [] ) {
    $meta = get_option( 'ns_cache_meta', [] );
    if ( ! is_array( $meta ) ) { $meta = []; }
    if ( ! isset( $meta[ $transient_key ] ) || ! is_array( $meta[ $transient_key ] ) ) {
        $meta[ $transient_key ] = [];
    }
    if ( ! empty( $data ) ) {
        foreach ( $data as $k => $v ) {
            $meta[ $transient_key ][ $k ] = $v;
        }
        update_option( 'ns_cache_meta', $meta, false );
    }
    return $meta[ $transient_key ];
}

function nowscrobbling_guess_service_from_url( $url ) {
    if ( strpos( $url, 'audioscrobbler.com' ) !== false ) return 'lastfm';
    if ( strpos( $url, 'trakt.tv' ) !== false ) return 'trakt';
    return 'generic';
}

function nowscrobbling_get_service_cred_hash( $service ) {
    if ( $service === 'lastfm' ) {
        $user = (string) get_option('lastfm_user');
        $key  = (string) get_option('lastfm_api_key');
        return md5($user . '|' . $key);
    }
    if ( $service === 'trakt' ) {
        $user = (string) get_option('trakt_user');
        $key  = (string) get_option('trakt_client_id');
        return md5($user . '|' . $key);
    }
    return md5('generic');
}

function nowscrobbling_record_last_success( $service ) {
    $map = get_option('ns_last_success', []);
    if ( ! is_array($map) ) $map = [];
    if ( ! isset($map[$service]) || ! is_array($map[$service]) ) $map[$service] = [];
    $hash = nowscrobbling_get_service_cred_hash( $service );
    $map[$service][$hash] = current_time('mysql');
    update_option('ns_last_success', $map, false);
}

function nowscrobbling_build_cache_key( $base, $parts = [] ) {
    // Ensure $parts is always an array
    if ( !is_array( $parts ) ) {
        $parts = [];
    }
    // Deep sort keys for deterministic hashing
    $deep_ksort = function (&$arr) use (&$deep_ksort) {
        if (!is_array($arr)) return;
        ksort($arr);
        foreach ($arr as &$v) { if (is_array($v)) { $deep_ksort($v); } }
    };
    $deep_ksort($parts);

    if ( $parts ) {
        $base .= ':' . substr( md5( wp_json_encode( $parts ) ), 0, 12 );
    }
    return 'nowscrobbling_' . sanitize_key( $base );
}

function nowscrobbling_should_cooldown( $service ) {
    $key = 'nowscrobbling_cooldown_' . $service;
    return (bool) get_transient( $key );
}

function nowscrobbling_set_cooldown( $service, $seconds = 60 ) {
    set_transient( 'nowscrobbling_cooldown_' . $service, 1, absint( $seconds ) );
}

/**
 * Log debug messages for NowScrobbling
 *
 * @param string $message The message to log.
 */
function nowscrobbling_log($message) {
    // Log only when enabled to avoid option bloat in production
    if ( ! get_option('nowscrobbling_debug_log', 0) ) {
        return;
    }
    $log = get_option('nowscrobbling_log', []);
    if (!is_array($log)) { $log = []; }
    $log[] = '[' . current_time('mysql') . '] ' . $message;
    // Begrenze auf die letzten 200 Einträge statt 100 für mehr Kontext
    if (count($log) > 200) array_shift($log);
    // Log Eintrag speichern
    $result = update_option('nowscrobbling_log', $log);
    
    // Debug: Prüfe ob das Speichern erfolgreich war
    if (!$result) {
        // Versuche einen Notfall-Log-Eintrag zu erstellen
        error_log('NowScrobbling: Fehler beim Speichern des Debug-Logs: ' . $message);
    }
}

function nowscrobbling_log_throttled($bucket, $message, $seconds = 60) {
    $guard_key = 'ns_log_guard_' . sanitize_key($bucket);
    if ( get_transient($guard_key) ) {
        return; // kürzlich geloggt
    }
    set_transient($guard_key, 1, max(5, (int)$seconds));
    nowscrobbling_log($message);
}

// Test-Log-Nachricht beim Laden der Datei (nur wenn Debug aktiviert ist)
add_action('init', function() {
    // Schreibe nur bei aktiviertem Debug-Log und im Admin-Kontext eine kurze Marker-Zeile
    $debug_enabled = (bool) get_option('nowscrobbling_debug_log', 0);
    if ($debug_enabled && is_admin()) {
        // Guard: höchstens 1× pro Minute loggen, um Spam zu vermeiden
        if (!get_transient('nowscrobbling_debug_marker_guard')) {
            nowscrobbling_log('Debug-Log aktiv – Plugin geladen');
            set_transient('nowscrobbling_debug_marker_guard', 1, 60);
        }
    }
});

/**
 * Emit a log line when the debug flag is toggled, to verify activation works.
 */
add_action('update_option_nowscrobbling_debug_log', function($old_value, $value){
    // Cast to int and write a marker entry before the value is saved
    $new = (int) $value;
    if ($new === 1) {
        // Bypass flag to ensure at least one entry after activation
        $log = get_option('nowscrobbling_log', []);
        $log[] = '[' . current_time('mysql') . '] Debug-Log aktiviert';
        if (count($log) > 100) array_shift($log);
        update_option('nowscrobbling_log', $log);
    } else {
        // Also log deactivation once
        $log = get_option('nowscrobbling_log', []);
        $log[] = '[' . current_time('mysql') . '] Debug-Log deaktiviert';
        if (count($log) > 100) array_shift($log);
        update_option('nowscrobbling_log', $log);
    }
}, 10, 2);

/**
 * Handle API request errors
 *
 * @param WP_Error $error The error object.
 * @param string $message The custom error message.
 * @param string $service Optional. Service identifier ('lastfm', 'trakt', or 'generic'). Default 'generic'.
 * @return null Always returns null.
 */
function nowscrobbling_handle_api_error($error, $message, $service = 'generic')
{
    $error_message = $error->get_error_message();
    $error_code = $error->get_error_code();
    
    nowscrobbling_log("$message: $error_code - $error_message");
    
    // Set a cooldown if we get specific errors that suggest rate-limiting or temporary API issues
    if (strpos($error_message, 'timed out') !== false || 
        strpos($error_message, 'rate limit') !== false || 
        $error_code === 429 || 
        $error_code === 503) {
        
        // Set a progressive cooldown period based on recent errors
        $metrics = get_option('ns_metrics', []);
        $errors = isset($metrics[$service]['total_errors']) ? (int)$metrics[$service]['total_errors'] : 0;
        $cooldown_period = min(1800, 60 * pow(2, min(4, floor($errors / 3)))); // Progressive cooldown: 1m, 2m, 4m, 8m, 16m, max 30m
        
        nowscrobbling_set_cooldown($service, $cooldown_period);
        nowscrobbling_log("Cooldown für $service aktiviert für " . round($cooldown_period/60, 1) . " Minuten nach wiederholten Fehlern.");
    }
    
    return null;
}

/**
 * Fetch API Data mit verbessertem ETag-Support und Fehlerbehandlung
 *
 * Diese Funktion kümmert sich um:
 * - HTTP-Requests mit konfigurierbaren Headern
 * - ETag-basierte 304-Erkennung für Bandbreitenersparnis
 * - Fehlerbehandlung und Logging
 * - Metriken-Sammlung für die Admin-UI
 * - Rate-Limit-Erkennung und -Tracking
 *
 * @param string $url Die API-Endpunkt-URL
 * @param array $headers Die zu sendenden HTTP-Header
 * @param array $args Zusätzliche Argumente für wp_safe_remote_get
 * @param string $cache_key Optionaler Cache-Schlüssel für ETag-Speicherung
 * @return array|null Die Antwortdaten oder null bei Fehlern
 */
function nowscrobbling_fetch_api_data($url, $headers = [], $args = [], $cache_key = '') {
    $args = wp_parse_args($args, [
        'timeout'     => 5,
        'redirection' => 0,
        'headers'     => [],
    ]);
    
    $args['headers'] = array_merge([
        'User-Agent'      => nowscrobbling_user_agent(),
        'Accept'          => 'application/json',
        'Accept-Encoding' => 'gzip',
        'Connection'      => 'close',
    ], (array) $headers, (array) $args['headers']);

    // Add ETag support if cache key is provided
    if ($cache_key) {
        $etag_key = 'nowscrobbling_etag_' . $cache_key;
        $etag = get_transient($etag_key);
        if ($etag) {
            $args['headers']['If-None-Match'] = $etag;
        }
    }

    $service = nowscrobbling_guess_service_from_url( $url );
    $start   = microtime(true);
    
    // Prüfen, ob für diesen Dienst ein Cooldown aktiv ist
    if (nowscrobbling_should_cooldown($service)) {
        nowscrobbling_log_throttled('cooldown_'.$service, "API-Request für $service übersprungen aufgrund aktivem Cooldown: $url", 60);
        nowscrobbling_metrics_update( $service, 'cooldown_skips' );
        return null;
    }
    
    // Versuche den Request mit Timeout-Schutz
    $response = wp_safe_remote_get($url, $args);
    if (is_wp_error($response)) {
        nowscrobbling_handle_api_error($response, "API request failed for $url", $service);
        nowscrobbling_metrics_update( $service, 'total_requests' );
        nowscrobbling_metrics_update( $service, [ 'last_ms' => (int) round( ( microtime(true) - $start ) * 1000 ), 'last_status' => 0 ] );
        nowscrobbling_metrics_update( $service, 'total_errors' );
        
        // Log Rate-Limit-Fehler explizit
        if ($response->get_error_code() === 429) {
            nowscrobbling_log("RATE LIMIT erreicht für $service API. Cooldown aktiviert.");
            // Forciere hier einen längeren Cooldown bei 429
            nowscrobbling_set_cooldown($service, 900); // 15 Minuten Cooldown
        }
        
        return null;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    // Store last seen rate-limit headers for known services
    try {
        $h_limit     = wp_remote_retrieve_header($response, 'x-ratelimit-limit');
        $h_remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');
        $h_reset     = wp_remote_retrieve_header($response, 'x-ratelimit-reset');
        if ($h_limit || $h_remaining || $h_reset) {
            $limits = get_option('ns_rate_limits', []);
            if (!is_array($limits)) { $limits = []; }
            if (!isset($limits[$service]) || !is_array($limits[$service])) { $limits[$service] = []; }
            $limits[$service]['limit']     = is_numeric($h_limit) ? (int) $h_limit : ($limits[$service]['limit'] ?? null);
            $limits[$service]['remaining'] = is_numeric($h_remaining) ? (int) $h_remaining : ($limits[$service]['remaining'] ?? null);
            $limits[$service]['reset']     = is_numeric($h_reset) ? (int) $h_reset : ($limits[$service]['reset'] ?? null);
            $limits[$service]['updated_at'] = (int) current_time('timestamp');
            update_option('ns_rate_limits', $limits, false);
        }
    } catch (Exception $e) {
        // ignore header parsing errors
    }
    nowscrobbling_metrics_update( $service, 'total_requests' );
    nowscrobbling_metrics_update( $service, [ 'last_ms' => (int) round( ( microtime(true) - $start ) * 1000 ), 'last_status' => (int) $response_code ] );

    // Handle 304 Not Modified: signal upper layers that data is unchanged
    if ($response_code === 304) {
        nowscrobbling_log("ETag cache hit for $url");
        nowscrobbling_metrics_update( $service, 'etag_hits' );
        // Return a sentinel structure so the cache-layer can extend primary cache from fallback
        return [ '__ns_not_modified' => true ];
    }

    // Treat 204 as empty success for all services
    if ($response_code === 204) {
        // Return empty but don't double-count requests; last_ms/status already set above
        return [];
    }

    // Handle successful responses
    if ($response_code >= 200 && $response_code < 300) {
        // Trakt often returns 204 No Content for "watching" when idle → treat as empty, not an error
        if ($response_code === 204) {
            return [];
        }

        // Store ETag if provided
        if ($cache_key) {
            $etag = wp_remote_retrieve_header($response, 'etag');
            if ($etag) {
                $etag_key = 'nowscrobbling_etag_' . $cache_key;
                set_transient($etag_key, $etag, DAY_IN_SECONDS);
            }
        }

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If body is empty on a 2xx, treat as empty payload instead of error
            if (trim((string) $response_body) === '') {
                return [];
            }
            nowscrobbling_log("JSON decode error for $url: " . json_last_error_msg());
            return null;
        }

        // record last success for known services
        if ( in_array( $service, ['lastfm','trakt'], true ) ) {
            nowscrobbling_record_last_success( $service );
        }

        return $data;
    }

    // Handle error responses
    nowscrobbling_log("API request failed for $url with status $response_code: $response_body");
    if ( $response_code >= 400 ) {
        nowscrobbling_metrics_update( $service, 'total_errors' );
        
        // Spezielle Behandlung für Rate-Limiting und Server-Fehler
        if ( $response_code === 429 ) {
            // Rate Limit - setze längeren Cooldown
            nowscrobbling_set_cooldown($service, 900); // 15 Minuten
            nowscrobbling_log("RATE LIMIT (429) für $service API. 15-Minuten-Cooldown aktiviert.");
        } else if ( $response_code >= 500 ) {
            // Server-Fehler - kurzer Cooldown
            nowscrobbling_set_cooldown($service, 300); // 5 Minuten
            nowscrobbling_log("Server-Fehler ($response_code) für $service API. 5-Minuten-Cooldown aktiviert.");
        } else if ( $response_code >= 400 ) {
            // Client-Fehler - kurzer Cooldown bei wiederholten Fehlern
            $metrics = get_option('ns_metrics', []);
            $errors = isset($metrics[$service]['total_errors']) ? (int)$metrics[$service]['total_errors'] : 0;
            
            if ( $errors > 3 ) {
                // Nach mehreren Fehlern kurzen Cooldown setzen
                nowscrobbling_set_cooldown($service, 180); // 3 Minuten
                nowscrobbling_log("Wiederholte Client-Fehler für $service API. 3-Minuten-Cooldown aktiviert.");
            }
        }
    }
    return null;
}

/**
 * Fetch Last.fm Data
 *
 * @param string $method The API method to call.
 * @param array $params The query parameters for the API request.
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_lastfm_data($method, $params = [])
{
    // Vermeide API-Calls während Cooldown-Phasen
    if (nowscrobbling_should_cooldown('lastfm')) {
        nowscrobbling_log('Last.fm im Cooldown — überspringe API-Call');
        nowscrobbling_metrics_update('lastfm', 'cooldown_skips');
        return null;
    }
    
    // Prüfe, ob API-Schlüssel und Benutzername konfiguriert sind
    $api_key = nowscrobbling_opt('lastfm_api_key');
    $user = nowscrobbling_opt('lastfm_user');
    
    if (empty($api_key) || empty($user)) {
        nowscrobbling_log('Last.fm API-Call übersprungen: API-Key oder Benutzername nicht konfiguriert');
        return null;
    }
    
    // Bereite Parameter vor und sichere die URL
    $params = array_merge([
        'api_key' => $api_key,
        'user'    => $user,
        'format'  => 'json',
    ], $params);
    
    // Methode mit Präfix 'user.' versehen, wenn nicht schon vorhanden
    if (strpos($method, 'user.') !== 0 && strpos($method, '.') === false) {
        $method = 'user.' . $method;
    }
    
    // Stabilisiere Param-Reihenfolge
ksort($params);
    $url = NOWSCROBBLING_LASTFM_API_URL . '?method=' . rawurlencode($method) . '&' . http_build_query($params);
    
    // Use ETag per URL to support 304 Not Modified
    $etag_key = 'lastfm:' . substr(md5($url), 0, 24);
    $data = nowscrobbling_fetch_api_data($url, [], [], $etag_key);
    
    // Prüfe auf Last.fm API-Fehler im Erfolgsfall (HTTP 200 aber API-Fehler im JSON)
    if (is_array($data) && isset($data['error'])) {
        $error_code = isset($data['error']) ? (int) $data['error'] : 0;
        $error_message = isset($data['message']) ? $data['message'] : 'Unknown Last.fm API error';
        nowscrobbling_log("Last.fm API-Fehler ($error_code): $error_message");
        nowscrobbling_metrics_update('lastfm', 'total_errors');
        
        // Bestimmte Fehler mit spezifischem Cooldown behandeln
        if ($error_code === 8 || $error_code === 11 || $error_code === 16) {
            // 8: Operation failed, 11: Service offline, 16: Service temporarily unavailable
            nowscrobbling_set_cooldown('lastfm', 600); // 10 Minuten
            nowscrobbling_log("Last.fm API temporär nicht verfügbar (Fehler $error_code). 10-Minuten-Cooldown aktiviert.");
        } elseif ($error_code === 6 || $error_code === 7) {
            // 6: Invalid parameters, 7: Invalid resource
            nowscrobbling_log("Last.fm API Parameterfehler. Kein Cooldown, da wahrscheinlich Konfigurationsproblem.");
        } elseif ($error_code === 4 || $error_code === 5) { 
            // 4: Authentication Failed, 5: Invalid session key
            nowscrobbling_log("Last.fm API Authentifizierungsfehler. Überprüfe API-Schlüssel.");
        } elseif ($error_code === 29) {
            // 29: Rate limit exceeded
            nowscrobbling_set_cooldown('lastfm', 900); // 15 Minuten
            nowscrobbling_log("Last.fm API Rate Limit überschritten. 15-Minuten-Cooldown aktiviert.");
        }
        
        return null;
    }
    
    return $data;
}

/**
 * Fetch and Display Last.fm Scrobbles
 *
 * @param string $context Optional context for caching (e.g., 'lastfm_indicator', 'lastfm_history').
 * @return array The scrobbles data or error message.
 */
function nowscrobbling_fetch_lastfm_scrobbles($context = 'default')
{
    $result = nowscrobbling_get_or_set_transient(
        // Unify cache key across contexts to avoid doppelte API-Calls
        nowscrobbling_build_cache_key('lastfm_scrobbles', ['limit' => (int) nowscrobbling_opt('lastfm_activity_limit', 3)]),
        function () {
            $start = microtime(true);
            $data = nowscrobbling_fetch_lastfm_data('getrecenttracks', [
                'limit' => nowscrobbling_opt('lastfm_activity_limit', 3)
            ]);
            
            if (!$data) {
                nowscrobbling_log("Last.fm API-Call lieferte keine Daten");
                // Flag: Now-Playing aus
                update_option('ns_flag_lastfm_nowplaying', 0, false);
                // Rückgabe von Fehlerdaten für Caching
                return ['error' => 'Keine Daten von last.fm API erhalten', 'timestamp' => time()];
            }
            
            if (isset($data['error'])) {
                nowscrobbling_log("Last.fm API-Call lieferte Fehler: " . $data['error']);
                // Flag: Now-Playing aus
                update_option('ns_flag_lastfm_nowplaying', 0, false);
                // API-Fehlermeldung zurückgeben (wird jetzt gecached)
                return $data;
            }
            
            if (empty($data['recenttracks']['track'])) {
                nowscrobbling_log("Last.fm API-Call erfolgreich, aber keine Tracks erhalten");
                // Flag: Now-Playing aus
                update_option('ns_flag_lastfm_nowplaying', 0, false);
                // Leere Daten zurückgeben (wird jetzt gecached)
                return ['value' => [], 'error' => 'Keine Tracks in last.fm Antwort gefunden', 'timestamp' => time()];
            }
            
            nowscrobbling_log("Last.fm API-Call ausgeführt und Daten gecacht (" . count($data['recenttracks']['track']) . " Einträge).");
            $duration = round((microtime(true) - $start) * 1000);
            nowscrobbling_log("Last.fm API Dauer: {$duration}ms");
            
            $mapped = array_map(function ($track) {
                $date_text = isset($track['date']['#text']) ? (string) $track['date']['#text'] : null;
                $date_uts  = isset($track['date']['uts']) ? (int) $track['date']['uts'] : null;
                
                // Prüfe, ob die erforderlichen Felder vorhanden und nicht leer sind
                $artist = isset($track['artist']['#text']) ? (string) $track['artist']['#text'] : '';
                $name = isset($track['name']) ? (string) $track['name'] : '';
                
                // Wenn entweder Künstler oder Track leer ist, füge Fallback-Werte ein
                if (empty($artist)) {
                    $artist = 'Unbekannter Künstler';
                }
                if (empty($name)) {
                    $name = 'Unbekannter Track';
                }
                
                return [
                    'url'        => esc_url($track['url'] ?? '#'),
                    'name'       => esc_html($name),
                    'artist'     => esc_html($artist),
                    'nowplaying' => isset($track['@attr']['nowplaying']) ? (bool) $track['@attr']['nowplaying'] : false,
                    'date'       => $date_text,
                    'uts'        => $date_uts,
                ];
            }, $data['recenttracks']['track']);
            
            // Prüfen auf leere Antwort nach der Verarbeitung
            if (empty($mapped)) {
                nowscrobbling_log("Last.fm API-Call: Nach der Verarbeitung keine gültigen Tracks übrig");
                update_option('ns_flag_lastfm_nowplaying', 0, false);
                // Leere aber gültige Daten zurückgeben (wird gecached)
                return ['value' => []];
            }
            
            // Now-Playing-Status aktualisieren
            $any_now = false;
            foreach ($mapped as $row) { if (!empty($row['nowplaying'])) { $any_now = true; break; } }
            update_option('ns_flag_lastfm_nowplaying', $any_now ? 1 : 0, false);
            
            // TTL-Override: Bei aktivem Now-Playing häufiger aktualisieren (30s)
            $payload = ['value' => $mapped];
            if ($any_now) { $payload['__ns_meta'] = ['ttl' => 30]; }
            
            return $payload;
        },
        nowscrobbling_cache_minutes('lastfm') * MINUTE_IN_SECONDS
    );
    nowscrobbling_log("Last.fm Cache verwendet.");
    return $result;
}

/**
 * Fetch Last.fm Top Data
 *
 * @param string $type The type of data to fetch (e.g., topartists, topalbums).
 * @param int $count The number of items to fetch.
 * @param string $period The period to fetch data for.
 * @param string $cache_key Optional cache key for different contexts.
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_lastfm_top_data($type, $count, $period, $cache_key = '')
{
    $cache_key = $cache_key ?: "lastfm_{$type}";
    
    $result = nowscrobbling_get_or_set_transient(
        nowscrobbling_build_cache_key($cache_key, ['count' => $count, 'period' => $period]),
        function () use ($type, $count, $period) {
            return nowscrobbling_fetch_lastfm_data("get{$type}", [
                'limit' => $count,
                'period' => $period
            ]);
        },
        nowscrobbling_cache_minutes('lastfm') * MINUTE_IN_SECONDS
    );
    
    return $result;
}

/**
 * Fetch Trakt Data
 *
 * @param string $path The API endpoint path.
 * @param array $params The query parameters for the API request.
 * @param string $cache_key Optional cache key for different contexts.
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_trakt_data($path, $params = [], $cache_key = '')
{
    // Vermeide API-Calls während Cooldown-Phasen
    if (nowscrobbling_should_cooldown('trakt')) {
        nowscrobbling_log('Trakt im Cooldown — überspringe API-Call');
        nowscrobbling_metrics_update('trakt', 'cooldown_skips');
        return null;
    }
    
    // Prüfe, ob Client-ID und Benutzername konfiguriert sind
    $client_id = nowscrobbling_opt('trakt_client_id');
    $user = nowscrobbling_opt('trakt_user');
    
    if (empty($client_id) || empty($user)) {
        nowscrobbling_log('Trakt API-Call übersprungen: Client-ID oder Benutzername nicht konfiguriert');
        return null;
    }
    
    // Ensure $params is always an array
    if (!is_array($params)) {
        $params = [];
    }
    
    // Bereite Request-Komponenten vor
    $headers = [
        'Content-Type'      => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key'     => $client_id,
    ];
    
    // Sicherstellen, dass der Benutzerpfad korrekt formatiert ist
    if (strpos($path, 'users/') === 0 && strpos($path, 'users/' . $user) !== 0) {
        // Wenn der Pfad mit users/ beginnt aber nicht mit dem korrekten Benutzernamen, korrigieren
        nowscrobbling_log("Trakt API-Pfadkorrektur: $path enthält möglicherweise falschen Benutzernamen, ersetze mit $user");
        $path = preg_replace('#^users/[^/]+#', 'users/' . $user, $path);
    }
    
    // Normalisiere den Pfad (entferne doppelte Schrägstriche, etc.)
    $path = ltrim($path, '/');
    
    // Generiere URL und ETag-Schlüssel
    $query = is_array($params) ? http_build_query($params) : '';
    $url = NOWSCROBBLING_TRAKT_API_URL . $path . ($query ? "?$query" : '');
    $etag_key = ($cache_key ? $cache_key : 'trakt') . ':' . substr(md5($url), 0, 24);
    
    // Verwende Cache oder rufe frische Daten ab
    if ($cache_key) {
        $result = nowscrobbling_get_or_set_transient(
            nowscrobbling_build_cache_key($cache_key, $params),
            function () use ($url, $headers, $etag_key) {
                $data = nowscrobbling_fetch_api_data($url, $headers, [], $etag_key);
                
                // Prüfe auf leere Antwort (204) bei Watching-Endpoint
                if ($data === [] && strpos($url, '/watching') !== false) {
                    nowscrobbling_log('Trakt: Leere Antwort vom Watching-Endpoint (normal, wenn nichts geschaut wird)');
                }
                
                return $data;
            },
            nowscrobbling_cache_minutes('trakt') * MINUTE_IN_SECONDS
        );
        return $result;
    }
    
    // Direkter API-Aufruf ohne Cache
    $data = nowscrobbling_fetch_api_data($url, $headers, [], $etag_key);
    
    // Zusätzliche Logging für debugging
    if ($data === null) {
        nowscrobbling_log("Trakt API-Anfrage fehlgeschlagen für: $path");
    }
    
    return $data;
}

/**
 * Fetch and Display Trakt Activities
 *
 * @param string $context Optional context for caching (e.g., 'trakt_indicator', 'trakt_history').
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_trakt_activities($context = 'default')
{
    // Direkten ungecachten Abruf vermeiden: Immer Cache-Layer nutzen; optional kurze TTL via TTL-Override unten

    // Normaler Cache-basierter Aufruf
    $result = nowscrobbling_get_or_set_transient(
        // Unify cache key across contexts to avoid doppelte API-Calls
        nowscrobbling_build_cache_key('trakt_activities', ['limit' => (int) nowscrobbling_opt('trakt_activity_limit', 25)]),
        function () {
            $start = microtime(true);
            $user = nowscrobbling_opt('trakt_user');
            $limit = (int) nowscrobbling_opt('trakt_activity_limit', 25);
            $limit = max(5, min(50, $limit)); // Sicherstellen, dass Limit im sinnvollen Bereich ist
            
            // Call without inner cache-key to avoid double caching; outer layer handles caching/TTL
            $data = nowscrobbling_fetch_trakt_data("users/" . $user . "/history", [
                'limit' => $limit
            ]);
            
            if ($data === null) {
                nowscrobbling_log("Trakt API-Call lieferte keine Daten");
                // Rückgabe von Fehlerdaten für Caching
                return ['error' => 'Keine Daten von Trakt API erhalten', 'timestamp' => time()];
            }
            
            if (is_array($data) && isset($data['error'])) {
                nowscrobbling_log("Fehler beim Abrufen der Trakt-Aktivitäten: " . $data['error']);
                // API-Fehlermeldung zurückgeben (wird jetzt gecached)
                return $data;
            }
            
            // Validiere Datenstruktur
            if (!is_array($data)) {
                nowscrobbling_log("Unerwarteter Datentyp in Trakt-Antwort: " . gettype($data));
                return ['error' => 'Ungültiges Datenformat in Trakt-Antwort', 'timestamp' => time()];
            }
            // Sanitize: entferne nicht-Array-Einträge (z. B. versehentliche Skalarwerte)
            $data = array_values(array_filter($data, 'is_array'));
            
            // Prüfen auf leere Antwort
            if (empty($data)) {
                nowscrobbling_log("Trakt API-Call erfolgreich, aber keine Aktivitäten erhalten");
                // Leere aber gültige Daten zurückgeben (wird gecached)
                return ['value' => []];
            }
            
            $entries_count = count($data);
            nowscrobbling_log("Trakt API-Call ausgeführt und Daten gecacht ($entries_count Einträge).");
            $duration = round((microtime(true) - $start) * 1000);
            nowscrobbling_log("Trakt API Dauer: {$duration}ms");
            
            // TTL-Override: Wenn Watching aktiv ist, Activities häufiger aktualisieren; sonst 5 Minuten
            $watching_active = (int) get_option('ns_flag_trakt_watching', 0) === 1;
            $ttl = $watching_active ? 60 : 300; // 1 min vs 5 min
            
            // Bei mehr als 5 Einträgen Array trimmen zur Performance-Verbesserung
            if ($entries_count > 5) {
                // Beim Caching nur die nötige Anzahl behalten
                $trimmed_data = array_slice($data, 0, max(5, (int) $limit));
                nowscrobbling_log("Trakt Activities auf " . count($trimmed_data) . " Einträge gekürzt für Cache-Effizienz");
                return ['__ns_meta' => ['ttl' => $ttl], 'value' => $trimmed_data];
            }
            
            return ['__ns_meta' => ['ttl' => $ttl], 'value' => $data];
        },
        nowscrobbling_cache_minutes('trakt') * MINUTE_IN_SECONDS
    );
    nowscrobbling_log("Trakt Cache verwendet.");
    return $result;
}

/**
 * Holt aktuelle Watching-Informationen von Trakt und setzt entsprechendes Flag
 *
 * @return array|null Die Watching-Daten oder null bei Fehler
 */
function nowscrobbling_fetch_trakt_watching()
{
    // Spezifisches Caching, da Watching hochfrequent abgefragt wird
    $result = nowscrobbling_get_or_set_transient(
        nowscrobbling_build_cache_key('trakt_watching'),
        function () {
            // Prüfe Verfügbarkeit von Credentials
            $client_id = nowscrobbling_opt('trakt_client_id');
            $user = nowscrobbling_opt('trakt_user');
            
            if (empty($client_id) || empty($user)) {
                nowscrobbling_log('Trakt Watching-Abfrage übersprungen: Credentials fehlen');
                // Flag auf "nicht aktiv" setzen
                update_option('ns_flag_trakt_watching', 0, false);
                return ['error' => 'Keine Credentials für Trakt', 'timestamp' => time()];
            }
            
            // Erstelle Request
            $path = "users/$user/watching";
            $data = nowscrobbling_fetch_trakt_data($path);
            
            // Prüfe auf API-Fehler
            if ($data === null) {
                nowscrobbling_log('Trakt Watching-Abfrage: Keine Antwort von der API');
                update_option('ns_flag_trakt_watching', 0, false);
                return ['error' => 'Keine Antwort von Trakt API', 'timestamp' => time()];
            }
            
            if (is_array($data) && isset($data['error'])) {
                nowscrobbling_log('Trakt Watching-Abfrage Fehler: ' . $data['error']);
                update_option('ns_flag_trakt_watching', 0, false);
                return $data; // Gib den Fehler zurück, wird jetzt gecached
            }
            
            // Spezieller Fall: leere Antwort ist kein Fehler, sondern "nicht aktiv"
            // Trakt: 204 => [] (in fetch_api_data); watching aktiv wenn nicht-leer und array
            $watching_active = is_array($data) && !empty($data);
            
            // Status-Flag setzen für andere Funktionen
            $old_state = (int) get_option('ns_flag_trakt_watching', 0);
            $new_state = $watching_active ? 1 : 0;
            update_option('ns_flag_trakt_watching', $new_state, false);
            
            // Log bei Statuswechsel
            if ($old_state !== $new_state) {
                nowscrobbling_log("Trakt Watching-Status geändert: " . ($watching_active ? "Aktiv" : "Inaktiv"));
            }
            
            // TTL-Override: 60s bei aktivem Watching, sonst 5 Minuten
            $ttl = $watching_active ? 60 : 300;
            
            // Wenn nicht aktiv, leeres aber gültiges Array zurückgeben
            if (!$watching_active) {
                return ['__ns_meta' => ['ttl' => $ttl], 'value' => []];
            }
            
            // Validiere Datenstruktur (falls aktiv)
            if (!isset($data['type'])) {
                nowscrobbling_log("Ungültiges Watching-Format: Typ fehlt");
                update_option('ns_flag_trakt_watching', 0, false);
                return [
                    '__ns_meta' => ['ttl' => 60],
                    'value' => [],
                    'error' => 'Ungültiges Watching-Format: Typ fehlt',
                    'timestamp' => time()
                ];
            }
            
            // Prüfe, ob passende Episode- oder Movie-Daten vorhanden sind
            $type = $data['type'];
            if (!isset($data[$type]) || !is_array($data[$type])) {
                nowscrobbling_log("Ungültiges Watching-Format: $type-Daten fehlen");
                update_option('ns_flag_trakt_watching', 0, false);
                return [
                    '__ns_meta' => ['ttl' => 60],
                    'value' => [],
                    'error' => "Ungültiges Watching-Format: $type-Daten fehlen",
                    'timestamp' => time()
                ];
            }
            
            // Daten sind gültig und watching ist aktiv
            nowscrobbling_log("Aktive Watching-Daten für Typ: $type");
            return ['__ns_meta' => ['ttl' => $ttl], 'value' => $data];
        },
        nowscrobbling_cache_minutes('trakt') * MINUTE_IN_SECONDS
    );
    return $result;
}

/**
 * Fetch Trakt Watched Shows
 *
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_trakt_watched_shows()
{
    $headers = [
        'Content-Type'     => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key'     => nowscrobbling_opt('trakt_client_id'),
    ];
    $url = NOWSCROBBLING_TRAKT_API_URL . "users/" . ltrim(nowscrobbling_opt('trakt_user'), '/') . "/watched/shows";
    return nowscrobbling_fetch_api_data($url, $headers);
}

/**
 * Hole die Bewertung eines bestimmten Films auf Trakt.
 * Diese Funktion kann verwendet werden, um die Bewertung innerhalb von AJAX-Antworten darzustellen.
 *
 * @param int $movie_id The Trakt ID of the movie.
 * @return int|null The movie rating or null if not rated.
 */
function nowscrobbling_fetch_trakt_movie_rating($movie_id) {
    $cache_key = nowscrobbling_build_cache_key('trakt_rating_movie', [ 'id' => (int) $movie_id ]);
    $data = nowscrobbling_get_or_set_transient($cache_key, function() use ($movie_id) {
        $user = nowscrobbling_opt('trakt_user');
        return nowscrobbling_fetch_trakt_data("users/$user/ratings/movies/$movie_id");
    }, DAY_IN_SECONDS);
    nowscrobbling_log("[shortcode: trakt_history] Direktabfrage Bewertung [movie/$movie_id]: " . json_encode($data));
    return is_array($data) && array_key_exists('rating', $data) ? $data['rating'] : null;
}

/**
 * Hole die Bewertung einer bestimmten Serie auf Trakt.
 * Diese Funktion kann verwendet werden, um zusätzliche Bewertungsinformationen für die Anzeige bereitzustellen.
 *
 * @param int $show_id The Trakt ID of the show.
 * @return int|null The show rating or null if not rated.
 */
function nowscrobbling_fetch_trakt_show_rating($show_id) {
    $cache_key = nowscrobbling_build_cache_key('trakt_rating_show', [ 'id' => (int) $show_id ]);
    $data = nowscrobbling_get_or_set_transient($cache_key, function() use ($show_id) {
        $user = nowscrobbling_opt('trakt_user');
        return nowscrobbling_fetch_trakt_data("users/$user/ratings/shows/$show_id");
    }, DAY_IN_SECONDS);
    nowscrobbling_log("[shortcode: trakt_history] Direktabfrage Bewertung [show/$show_id]: " . json_encode($data));
    return is_array($data) && array_key_exists('rating', $data) ? $data['rating'] : null;
}

/**
 * Hole die Bewertung einer bestimmten Episode auf Trakt.
 * Diese Funktion kann z. B. in einer Shortcode-AJAX-Ausgabe genutzt werden.
 *
 * @param int $episode_id The Trakt ID of the episode.
 * @return int|null The episode rating or null if not rated.
 */
function nowscrobbling_fetch_trakt_episode_rating($episode_id) {
    $cache_key = nowscrobbling_build_cache_key('trakt_rating_episode', [ 'id' => (int) $episode_id ]);
    $data = nowscrobbling_get_or_set_transient($cache_key, function() use ($episode_id) {
        $user = nowscrobbling_opt('trakt_user');
        return nowscrobbling_fetch_trakt_data("users/$user/ratings/episodes/$episode_id");
    }, DAY_IN_SECONDS);
    nowscrobbling_log("[shortcode: trakt_history] Direktabfrage Bewertung [episode/$episode_id]: " . json_encode($data));
    return is_array($data) && array_key_exists('rating', $data) ? $data['rating'] : null;
}

/**
 * Build and cache a map of trakt id => rating for a given type.
 * Types: 'movies', 'shows', 'episodes'
 */
function nowscrobbling_get_trakt_ratings_map($type) {
    $type = in_array($type, ['movies','shows','episodes'], true) ? $type : 'movies';
    $cache_key = nowscrobbling_build_cache_key('trakt_ratings_map', ['type' => $type]);
    $map = nowscrobbling_get_or_set_transient($cache_key, function() use ($type) {
        $user = nowscrobbling_opt('trakt_user');
        // Paginierte Abfrage: Trakt liefert i.d.R. in Seiten; wir holen bis zu 5 Seiten (1000 Items) und mergen
        $out = [];
        $page = 1;
        $per_page = 200; // Trakt max 200
        while ($page <= 5) {
            $list = nowscrobbling_fetch_trakt_data("users/$user/ratings/$type", [ 'page' => $page, 'limit' => $per_page ], "trakt_ratings_$type");
            if (!is_array($list) || empty($list)) { break; }
            foreach ($list as $entry) {
                $bucket = ($type === 'episodes') ? 'episode' : rtrim($type,'s');
                if (!isset($entry[$bucket]['ids']['trakt'])) { continue; }
                $tid = (int) $entry[$bucket]['ids']['trakt'];
                if (isset($entry['rating'])) { $out[$tid] = (int) $entry['rating']; }
            }
            if (count($list) < $per_page) { break; }
            $page++;
        }
        return $out;
    }, DAY_IN_SECONDS);
    return is_array($map) ? $map : [];
}

function nowscrobbling_get_trakt_rating_from_map($type, $id) {
    $map = nowscrobbling_get_trakt_ratings_map($type);
    $key = (int)$id;
    return array_key_exists($key, $map) ? (int)$map[$key] : null;
}

/**
 * Ermittelt, wie oft ein Film oder eine Episode erneut gesehen wurde (Rewatch Count).
 * Ideal zur Anzeige z. B. in einer AJAX-basierten Trakt-Shortcode-Ausgabe.
 *
 * @param int $id The ID of the movie or episode.
 * @param string $type The type (e.g., 'movies', 'episodes').
 * @return int The rewatch count.
 */
function nowscrobbling_get_rewatch_count($id, $type) {
    $id = (int) $id;
    $type = in_array($type, ['movies','episodes','shows'], true) ? $type : 'movies';
    $cache_key = nowscrobbling_build_cache_key('trakt_rewatch', [ 'type' => $type, 'id' => $id ]);
    $count = nowscrobbling_get_or_set_transient($cache_key, function() use ($id, $type) {
        // Schlank: Nur Kopf der History ermitteln und total items aus Header lesen
        $user = nowscrobbling_opt('trakt_user');
        $path = "users/$user/history/$type/$id";
        // Nutze fetch_trakt_data mit ETag und kleiner Limit/Page, um Header mitzunehmen
        // Hinweis: nowscrobbling_fetch_api_data schreibt Rate-Limits, aber nicht total items;
        // wir nutzen daher eine kleine Komplettliste, aber capped per_page.
        $page = 1; $per_page = 200; $total = 0; $acc = 0;
        while ($page <= 5) {
            $resp = nowscrobbling_fetch_trakt_data($path, [ 'page' => $page, 'limit' => $per_page ], 'trakt_rewatch');
            if (!is_array($resp) || empty($resp)) { break; }
            $acc += count($resp);
            if (count($resp) < $per_page) { break; }
            $page++;
        }
        return $acc;
    }, DAY_IN_SECONDS);
    return (int) $count;
}

/**
 * Enhanced transient management with fallback and error handling
 *
 * @param string $transient_key The transient key.
 * @param callable $callback The callback to generate data if transient is not set.
 * @param int $expiration The expiration time in seconds.
 * @param bool $force_refresh Whether to force refresh the cache.
 * @return mixed The transient data.
 */
/**
 * Verbesserte Transient-Verwaltung mit Fallback und Fehlerbehandlung.
 * 
 * Diese Funktion stellt sicher, dass Daten aus folgenden Quellen in Prioritätsreihenfolge kommen:
 * 1. In-Request Cache (für Duplikat-Anfragen in der gleichen Anfrage)
 * 2. WordPress Transient Cache (primärer Cache)
 * 3. Fallback-Cache (langlebiger, wird bei Fehlern verwendet)
 * 4. Live-Abruf über API (falls möglich und erlaubt)
 *
 * @param string $transient_key Eindeutiger Cache-Schlüssel
 * @param callable $callback Funktion, die bei Cache-Miss aufgerufen wird
 * @param int $expiration Ablaufzeit in Sekunden
 * @param bool $force_refresh Erzwingt einen frischen Datenabruf
 * @return mixed Die Daten aus dem Cache oder vom Callback
 */
function nowscrobbling_get_or_set_transient($transient_key, $callback, $expiration, $force_refresh = false) {
    // In-request caching um wiederholte Berechnungen innerhalb derselben Anfrage zu verhindern
    static $request_cache = [];

    // Erlaube globales/konstantes Überschreiben (z.B. via AJAX force_refresh)
    if ( defined('NOWSCROBBLING_FORCE_REFRESH') && NOWSCROBBLING_FORCE_REFRESH ) {
        $force_refresh = true;
    }
    
    // Service-Info aus Key extrahieren für Metriken und Logging
    $service = 'generic';
    if ( strpos( $transient_key, 'lastfm' ) !== false ) {
        $service = 'lastfm';
    } elseif ( strpos( $transient_key, 'trakt' ) !== false ) {
        $service = 'trakt';
    }
    
    // Fallback-Key konstant halten für Konsistenz
    $fallback_key = $transient_key . '_fallback';
    
    // 1. Aus In-Request-Cache bedienen, falls vorhanden und kein Force-Refresh
    if ( !$force_refresh && array_key_exists( $transient_key, $request_cache ) ) {
        // In-Memory ist immer schneller als Transient
        return $request_cache[$transient_key];
    }
    
    // 2. Aus Transient-Cache bedienen, falls vorhanden und kein Force-Refresh
    if ( !$force_refresh ) {
        $data = get_transient( $transient_key );
        if ( $data !== false ) {
            // Prüfen, ob die Daten komprimiert sind und dekomprimieren
            if (is_array($data) && isset($data['__ns_compressed']) && $data['__ns_compressed'] === true && isset($data['data'])) {
                try {
                    if (function_exists('gzuncompress')) {
                        $decompressed = @unserialize(gzuncompress(base64_decode($data['data'])));
                        if ($decompressed !== false) {
                            $data = $decompressed;
                            nowscrobbling_log("Komprimierte Daten für {$transient_key} erfolgreich dekomprimiert");
                        } else {
                            nowscrobbling_log("Fehler beim Dekomprimieren der Daten für {$transient_key}");
                        }
                    }
                } catch (Exception $e) {
                    nowscrobbling_log("Exception beim Dekomprimieren der Daten für {$transient_key}: " . $e->getMessage());
                }
            }
            
            // Cache-Hit: Statistiken aktualisieren
            $GLOBALS['nowscrobbling_last_source'] = 'cache';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            nowscrobbling_metrics_update( $service, 'cache_hits' );
            
            // Meta-Info für Diagnostik aktualisieren
            $fallback_exists = ( get_transient( $fallback_key ) !== false );
            $meta = nowscrobbling_cache_meta( $transient_key, [
                'last_access' => time(),
                'service' => $service,
                'fallback_key' => $fallback_key,
                'fallback_exists' => $fallback_exists ? 1 : 0,
            ] );
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            
            // In-Request-Cache für weitere Anfragen im selben Request aktualisieren
            $request_cache[$transient_key] = $data;
            
            return $data;
        }
    }

    // 3. Fallback-Daten holen für potentielle Nutzung
    $fallback_data = null;
    $has_fallback = false;
    
    // Nur Fallback abrufen, wenn wir ihn möglicherweise brauchen
    if ( !$force_refresh ) {
        $fallback_data = get_transient( $fallback_key );
        $has_fallback = ( $fallback_data !== false );
    }

    // 4. Prüfen, ob Live-Abruf erlaubt ist - nur in AJAX/Cron standardmäßig, kann gefiltert werden
    $allow_live_fetch = apply_filters(
        'nowscrobbling_allow_live_fetch', 
        ( defined('DOING_AJAX') && DOING_AJAX ) || ( function_exists('wp_doing_cron') && wp_doing_cron() ), 
        $transient_key
    );
    
    // Wenn kein Live-Fetch erlaubt ist und Fallback existiert, diesen verwenden
    if ( !$force_refresh && !$allow_live_fetch && $has_fallback ) {
        // Prüfen, ob die Fallback-Daten komprimiert sind und dekomprimieren
        if (is_array($fallback_data) && isset($fallback_data['__ns_compressed']) && $fallback_data['__ns_compressed'] === true && isset($fallback_data['data'])) {
            try {
                if (function_exists('gzuncompress')) {
                    $decompressed = @unserialize(gzuncompress(base64_decode($fallback_data['data'])));
                    if ($decompressed !== false) {
                        $fallback_data = $decompressed;
                        nowscrobbling_log("Komprimierte Fallback-Daten für {$transient_key} erfolgreich dekomprimiert");
                    } else {
                        nowscrobbling_log("Fehler beim Dekomprimieren der Fallback-Daten für {$transient_key}");
                        
                        // Bei Dekomprimierungsfehler: Leere Daten zurückgeben, damit der Code nicht abstürzt
                        if (strpos($transient_key, 'lastfm_') !== false) {
                            $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                        } else if (strpos($transient_key, 'trakt_') !== false) {
                            $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                        } else {
                            $fallback_data = [];
                        }
                    }
                }
            } catch (Exception $e) {
                nowscrobbling_log("Exception beim Dekomprimieren der Fallback-Daten für {$transient_key}: " . $e->getMessage());
                
                // Bei Exception: Leere Daten zurückgeben
                if (strpos($transient_key, 'lastfm_') !== false || strpos($transient_key, 'trakt_') !== false) {
                    $fallback_data = ['error' => 'Dekomprimierungsfehler: ' . $e->getMessage(), 'timestamp' => time()];
                } else {
                    $fallback_data = [];
                }
            }
        }
        
        nowscrobbling_log("Fallback-Daten für {$transient_key} verwendet (Live-Fetch deaktiviert)");
        $GLOBALS['nowscrobbling_last_source'] = 'fallback';
        $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
        nowscrobbling_metrics_update( $service, 'fallback_hits' );
        
        // Meta-Infos aktualisieren
        $meta = nowscrobbling_cache_meta( $transient_key, [
            'last_access' => (int) current_time('timestamp'),
            'fallback_key' => $fallback_key,
            'fallback_exists' => 1,
            'service' => $service,
        ] );
        $GLOBALS['nowscrobbling_last_meta'] = $meta;
        
        // Auch in-request cachen
        $request_cache[$transient_key] = $fallback_data;
        
        return $fallback_data;
    }
    
    // 5. Fallback bevorzugen, außer bei bestimmten hochdynamischen Daten oder Force-Refresh
    $prefer_fallback = true;
    
    // Bei Last.fm-Scrobbles immer frisch abrufen, wenn möglich
    if ( strpos( $transient_key, 'lastfm_scrobbles' ) !== false || 
         strpos( $transient_key, 'trakt_watching' ) !== false ) {
        $prefer_fallback = false;
    }
    
    // Bei vorhandenem Fallback und wenn bevorzugt, diesen verwenden (außer bei erzwungenem Refresh)
    if ( !$force_refresh && $has_fallback && $prefer_fallback ) {
        // Prüfen, ob die Fallback-Daten komprimiert sind und dekomprimieren
        if (is_array($fallback_data) && isset($fallback_data['__ns_compressed']) && $fallback_data['__ns_compressed'] === true && isset($fallback_data['data'])) {
            try {
                if (function_exists('gzuncompress')) {
                    $decompressed = @unserialize(gzuncompress(base64_decode($fallback_data['data'])));
                    if ($decompressed !== false) {
                        $fallback_data = $decompressed;
                        nowscrobbling_log("Komprimierte Fallback-Daten für {$transient_key} erfolgreich dekomprimiert");
                    } else {
                        nowscrobbling_log("Fehler beim Dekomprimieren der Fallback-Daten für {$transient_key}");
                        
                        // Bei Dekomprimierungsfehler: Leere Daten zurückgeben, damit der Code nicht abstürzt
                        if (strpos($transient_key, 'lastfm_') !== false) {
                            $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                        } else if (strpos($transient_key, 'trakt_') !== false) {
                            $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                        } else {
                            $fallback_data = [];
                        }
                    }
                }
            } catch (Exception $e) {
                nowscrobbling_log("Exception beim Dekomprimieren der Fallback-Daten für {$transient_key}: " . $e->getMessage());
                
                // Bei Exception: Leere Daten zurückgeben
                if (strpos($transient_key, 'lastfm_') !== false || strpos($transient_key, 'trakt_') !== false) {
                    $fallback_data = ['error' => 'Dekomprimierungsfehler: ' . $e->getMessage(), 'timestamp' => time()];
                } else {
                    $fallback_data = [];
                }
            }
        }
        
        nowscrobbling_log("Fallback-Daten für {$transient_key} verwendet (bevorzugt)");
        $GLOBALS['nowscrobbling_last_source'] = 'fallback';
        $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
        nowscrobbling_metrics_update( $service, 'fallback_hits' );
        
        // Meta-Infos aktualisieren
        $meta = nowscrobbling_cache_meta( $transient_key, [
            'last_access' => (int) current_time('timestamp'),
            'fallback_key' => $fallback_key,
            'fallback_exists' => 1,
            'service' => $service,
        ] );
        $GLOBALS['nowscrobbling_last_meta'] = $meta;
        
        // Auch in-request cachen
        $request_cache[$transient_key] = $fallback_data;
        
        return $fallback_data;
    }
    
    // 6. Live-Abruf mit Schutz vor "Thundering Herd"
    try {
        // Lock-Mechanismus, um gleichzeitige Abrufe zu vermeiden
        $lock_key = $transient_key . '_lock';
        $locked = get_transient( $lock_key );
        
        // Wenn Lock aktiv und Fallback vorhanden, diesen verwenden
        if ( $locked !== false && $has_fallback ) {
            // Prüfen, ob die Fallback-Daten komprimiert sind und dekomprimieren
            if (is_array($fallback_data) && isset($fallback_data['__ns_compressed']) && $fallback_data['__ns_compressed'] === true && isset($fallback_data['data'])) {
                try {
                    if (function_exists('gzuncompress')) {
                        $decompressed = @unserialize(gzuncompress(base64_decode($fallback_data['data'])));
                        if ($decompressed !== false) {
                            $fallback_data = $decompressed;
                            nowscrobbling_log("Komprimierte Fallback-Daten für {$transient_key} erfolgreich dekomprimiert");
                        } else {
                            nowscrobbling_log("Fehler beim Dekomprimieren der Fallback-Daten für {$transient_key}");
                            
                            // Bei Dekomprimierungsfehler: Leere Daten zurückgeben, damit der Code nicht abstürzt
                            if (strpos($transient_key, 'lastfm_') !== false) {
                                $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                            } else if (strpos($transient_key, 'trakt_') !== false) {
                                $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                            } else {
                                $fallback_data = [];
                            }
                        }
                    }
                } catch (Exception $e) {
                    nowscrobbling_log("Exception beim Dekomprimieren der Fallback-Daten für {$transient_key}: " . $e->getMessage());
                    
                    // Bei Exception: Leere Daten zurückgeben
                    if (strpos($transient_key, 'lastfm_') !== false || strpos($transient_key, 'trakt_') !== false) {
                        $fallback_data = ['error' => 'Dekomprimierungsfehler: ' . $e->getMessage(), 'timestamp' => time()];
                    } else {
                        $fallback_data = [];
                    }
                }
            }
            
            nowscrobbling_log("Lock aktiv für {$transient_key}, verwende Fallback");
            return $fallback_data;
        }
        
        // Lock setzen (kurze Dauer)
        set_transient( $lock_key, 1, 15 );
        
        // Daten abrufen über Callback
            $data = call_user_func( $callback );
            // If lower layer returned ETag Not Modified signal, try to refresh from fallback/primary
            if ( is_array($data) && isset($data['__ns_not_modified']) ) {
                // Prefer existing primary cache if still present, else fallback
                $existing = get_transient( $transient_key );
                if ( $existing === false && $has_fallback ) {
                    $existing = $fallback_data;
                }
                if ( $existing !== false && $existing !== null ) {
                    // Renew primary cache TTL using current $expiration
                    try {
                        set_transient( $transient_key, $existing, $expiration );
                    } catch ( Exception $e ) {}
                    $GLOBALS['nowscrobbling_last_source'] = 'cache';
                    $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
                    nowscrobbling_metrics_update( $service, 'cache_hits' );
                    // Meta aktualisieren
                    $now = (int) current_time('timestamp');
                    $meta = nowscrobbling_cache_meta($transient_key, [
                        'last_access' => $now,
                        'expires_at'  => $now + (int) $expiration,
                        'ttl'         => (int) $expiration,
                        'service'     => $service,
                        'fallback_key' => $fallback_key,
                        'fallback_exists' => $has_fallback ? 1 : 0,
                    ]);
                    $GLOBALS['nowscrobbling_last_meta'] = $meta;
                    $request_cache[$transient_key] = $existing;
                    return $existing;
                }
                // No existing data to renew; treat as miss
                $data = null;
            }
        
        // Behandle explizite Fehlerpayloads (z.B. { error: '...' })
        if ( is_array($data) && isset($data['error']) ) {
            // Fehler im Log vermerken, aber trotzdem cachen (wichtig für UI-Konsistenz)
            nowscrobbling_log("Fehler in Callback-Daten für {$transient_key}: " . $data['error']);
            // Fehler-Daten behalten, damit auch diese gecached werden
        }
        
        // TTL-Override prüfen (Callback kann TTL in Metadaten überschreiben)
        // und Wrapper konsistent entpacken (immer, wenn 'value' vorhanden ist)
        $override_ttl = null;
        if ( is_array($data) && array_key_exists('value', $data) ) {
            if ( isset($data['__ns_meta']) && is_array($data['__ns_meta']) && isset($data['__ns_meta']['ttl']) ) {
                $override_ttl = (int) $data['__ns_meta']['ttl'];
            }
            $data = $data['value'];
        }

        // Wenn Daten abgerufen wurden (auch Fehlerdaten sind wichtig zu speichern!)
        if ( $data !== null && $data !== false ) {
            // TTL festlegen (Standard oder überschrieben)
            $ttl_to_use = ($override_ttl !== null && $override_ttl > 0) ? $override_ttl : $expiration;
            
            // Prüfen ob es sich um Fehlerdaten handelt
            $has_error = is_array($data) && isset($data['error']);
            // Erkennen, ob es sich um eine leere Nutzlast handelt
            $is_empty_array = is_array($data) && count($data) === 0;
            // Dienst-/Key-spezifische Policy: Trakt-Listen (Activities/Last-*) nie mit leer/Fehler überschreiben
            $is_trakt_list_key = (
                strpos($transient_key, 'trakt_activities') !== false ||
                strpos($transient_key, 'trakt_last_movies') !== false ||
                strpos($transient_key, 'trakt_last_shows') !== false ||
                strpos($transient_key, 'trakt_last_episodes') !== false
            );
            // Für Watching/Nowplaying wollen wir leere Zustände speichern (zeigt korrekt "nicht aktiv")
            $is_trakt_watching_key = strpos($transient_key, 'trakt_watching') !== false;
            $is_lastfm_scrobbles_key = strpos($transient_key, 'lastfm_scrobbles') !== false;
            
            // Ob Primär/Fallback aktualisiert werden sollen
            $should_store_primary  = true;
            $should_store_fallback = true;
            
            // Überschreibe nie Fallback/Primary mit Fehlerdaten für Trakt-Listen
            if ($has_error && $is_trakt_list_key) {
                $should_store_primary  = false;
                $should_store_fallback = false;
            }
            // Überschreibe nie Fallback/Primary mit leeren Daten für Trakt-Listen
            if ($is_empty_array && $is_trakt_list_key) {
                $should_store_primary  = false;
                $should_store_fallback = false;
            }
            // Für Last.fm Scrobbles leere Daten nicht als Fallback speichern (alte Anzeige behalten),
            // Primär aber aktualisieren, damit TTL/Frische korrekt ist
            if ($is_empty_array && $is_lastfm_scrobbles_key) {
                $should_store_fallback = false;
            }
            // Für Watching explizit: leere Zustände sind valide und dürfen gespeichert werden
            if ($is_trakt_watching_key) {
                $should_store_primary  = true;
                // Fallback ist für Watching irrelevant → nicht notwendig zu überschreiben
                // (lassen wir $should_store_fallback unverändert)
            }
            if ($has_error) {
                // Bei Fehlerdaten kürzeres TTL verwenden, damit bald ein neuer Versuch folgt
                $ttl_to_use = min($ttl_to_use, 120); // max 2 Minuten für Fehlerdaten
                nowscrobbling_log("Setze Cache mit kürzerem TTL (2 Min.) für Fehlerdaten von {$transient_key}");
            }
            
            // In Haupt-Cache speichern
            $cache_set = false;
            try {
                // Daten vor dem Speichern komprimieren, wenn sie zu groß sind
                $data_to_store = $data;
                $data_size = strlen(maybe_serialize($data));
                
                // Wenn Daten zu groß sind (> 100KB), komprimieren
                if ($data_size > 100000) {
                    nowscrobbling_log("Große Datenmenge für {$transient_key} ({$data_size} Bytes) - komprimiere vor Speicherung");
                    if (function_exists('gzcompress')) {
                        $data_to_store = ['__ns_compressed' => true, 'data' => base64_encode(gzcompress(serialize($data), 9))];
                    }
                }
                
                // Wenn Daten immer noch zu groß sind (> 400KB nach Kompression), reduziere sie
                $compressed_size = strlen(maybe_serialize($data_to_store));
                if ($compressed_size > 400000) {
                    nowscrobbling_log("Daten immer noch zu groß nach Kompression: {$compressed_size} Bytes - reduziere Datenmenge");
                    
                    // Bei Arrays nur die wichtigsten Daten behalten
                    if (is_array($data)) {
                        // Für Last.fm Scrobbles nur die ersten 10 Einträge behalten
                        if (strpos($transient_key, 'lastfm_scrobbles') !== false) {
                            if (isset($data['recenttracks']['track']) && is_array($data['recenttracks']['track']) && count($data['recenttracks']['track']) > 10) {
                                $data['recenttracks']['track'] = array_slice($data['recenttracks']['track'], 0, 10);
                                nowscrobbling_log("Last.fm Scrobbles reduziert auf 10 Einträge");
                            }
                        }
                        
                        // Für Top-Listen nur die ersten 10 Einträge behalten
                        if (strpos($transient_key, 'top_') !== false) {
                            foreach ($data as $key => $value) {
                                if (is_array($value) && count($value) > 10) {
                                    $data[$key] = array_slice($value, 0, 10);
                                }
                            }
                            nowscrobbling_log("Top-Listen reduziert auf 10 Einträge pro Kategorie");
                        }
                        
                        // Erneut komprimieren mit reduzierter Datenmenge
                        $data_to_store = ['__ns_compressed' => true, 'data' => base64_encode(gzcompress(serialize($data), 9))];
                    }
                }

                // Nur speichern, wenn Richtlinie es erlaubt
                if ($should_store_primary) {
                    $cache_set = set_transient($transient_key, $data_to_store, $ttl_to_use);
                } else {
                    $cache_set = false;
                    nowscrobbling_log("Primär-Cache nicht überschrieben für {$transient_key} (Policy: bewahre frühere Daten)");
                }
            } catch (Exception $e) {
                nowscrobbling_log("Exception beim Speichern des Transient für {$transient_key}: " . $e->getMessage());
            }
            
            if (!$cache_set) {
                // Versuche mit noch kleineren Daten (nur 1 Eintrag)
                try {
                    // Bei Arrays drastisch reduzieren
                    if (is_array($data)) {
                        // Für Last.fm Scrobbles nur den ersten Eintrag behalten
                        if (strpos($transient_key, 'lastfm_scrobbles') !== false) {
                            if (isset($data['recenttracks']['track']) && is_array($data['recenttracks']['track']) && count($data['recenttracks']['track']) > 1) {
                                $data['recenttracks']['track'] = array_slice($data['recenttracks']['track'], 0, 1);
                                nowscrobbling_log("Last.fm Scrobbles auf 1 Eintrag reduziert nach fehlgeschlagenem Speichern");
                            }
                        }
                        
                        // Für Top-Listen nur den ersten Eintrag behalten
                        if (strpos($transient_key, 'top_') !== false) {
                            foreach ($data as $key => $value) {
                                if (is_array($value) && count($value) > 1) {
                                    $data[$key] = array_slice($value, 0, 1);
                                }
                            }
                            nowscrobbling_log("Top-Listen auf 1 Eintrag pro Kategorie reduziert nach fehlgeschlagenem Speichern");
                        }
                        
                        // Minimale Kompression mit höchster Stufe
                        $minimal_data = ['__ns_compressed' => true, 'data' => base64_encode(gzcompress(serialize($data), 9))];
                        $minimal_size = strlen(maybe_serialize($minimal_data));
                        nowscrobbling_log("Minimale Datengröße: {$minimal_size} Bytes - letzter Versuch zum Speichern");
                        
                        // Letzter Versuch mit stark reduzierten Daten (nur wenn erlaubt)
                        if ($should_store_primary) {
                            $cache_set = set_transient($transient_key, $minimal_data, $ttl_to_use);
                            if ($cache_set) {
                                nowscrobbling_log("Transient erfolgreich gespeichert nach Datenreduktion");
                            } else {
                                nowscrobbling_log("Fehler beim Speichern des Transient für {$transient_key} trotz Datenreduktion");
                            }
                        } else {
                            nowscrobbling_log("Primär-Cache weiterhin nicht überschrieben für {$transient_key} (Policy)");
                        }
                    } else {
                        nowscrobbling_log("Fehler beim Speichern des Transient für {$transient_key} - keine weiteren Optimierungen möglich");
                    }
                } catch (Exception $e) {
                    nowscrobbling_log("Exception bei letztem Speicherversuch für {$transient_key}: " . $e->getMessage());
                }
            }
            
            // In Fallback-Cache mit längerer Ablaufzeit speichern
            $fallback_expiration = min(WEEK_IN_SECONDS, $ttl_to_use * 3);
            $fallback_set = false;
            try {
                // Auch hier Kompression verwenden, wenn nötig
                $fallback_data_to_store = $data;
                $data_size = strlen(maybe_serialize($data));
                
                // Wenn Daten zu groß sind (> 100KB), komprimieren
                if ($data_size > 100000) {
                    if (function_exists('gzcompress')) {
                        $fallback_data_to_store = ['__ns_compressed' => true, 'data' => base64_encode(gzcompress(serialize($data), 9))];
                    }
                }
                
                // Wenn Daten immer noch zu groß sind (> 400KB nach Kompression), reduziere sie
                $compressed_size = strlen(maybe_serialize($fallback_data_to_store));
                if ($compressed_size > 400000) {
                    // Bei Arrays nur die wichtigsten Daten behalten
                    if (is_array($data)) {
                        // Für Last.fm Scrobbles nur die ersten 5 Einträge behalten (Fallback noch kleiner)
                        if (strpos($transient_key, 'lastfm_scrobbles') !== false) {
                            if (isset($data['recenttracks']['track']) && is_array($data['recenttracks']['track']) && count($data['recenttracks']['track']) > 5) {
                                $data['recenttracks']['track'] = array_slice($data['recenttracks']['track'], 0, 5);
                                nowscrobbling_log("Last.fm Scrobbles Fallback reduziert auf 5 Einträge");
                            }
                        }
                        
                        // Für Top-Listen nur die ersten 5 Einträge behalten
                        if (strpos($transient_key, 'top_') !== false) {
                            foreach ($data as $key => $value) {
                                if (is_array($value) && count($value) > 5) {
                                    $data[$key] = array_slice($value, 0, 5);
                                }
                            }
                            nowscrobbling_log("Top-Listen Fallback reduziert auf 5 Einträge pro Kategorie");
                        }
                        
                        // Erneut komprimieren mit reduzierter Datenmenge
                        $fallback_data_to_store = ['__ns_compressed' => true, 'data' => base64_encode(gzcompress(serialize($data), 9))];
                    }
                }

                // Fallback nur aktualisieren, wenn Policy es erlaubt
                if ($should_store_fallback) {
                    $fallback_set = set_transient($fallback_key, $fallback_data_to_store, $fallback_expiration);
                } else {
                    $fallback_set = false;
                    nowscrobbling_log("Fallback nicht überschrieben für {$transient_key} (Policy: bewahre frühere Daten)");
                }
            } catch (Exception $e) {
                nowscrobbling_log("Exception beim Speichern des Fallback-Cache für {$transient_key}: " . $e->getMessage());
            }
            
            if (!$fallback_set) {
                // Versuche mit noch kleineren Daten (nur 1 Eintrag)
                try {
                    // Bei Arrays drastisch reduzieren
                    if (is_array($data)) {
                        // Für Last.fm Scrobbles nur den ersten Eintrag behalten
                        if (strpos($transient_key, 'lastfm_scrobbles') !== false) {
                            if (isset($data['recenttracks']['track']) && is_array($data['recenttracks']['track']) && count($data['recenttracks']['track']) > 1) {
                                $data['recenttracks']['track'] = array_slice($data['recenttracks']['track'], 0, 1);
                                nowscrobbling_log("Last.fm Scrobbles Fallback auf 1 Eintrag reduziert nach fehlgeschlagenem Speichern");
                            }
                        }
                        
                        // Für Top-Listen nur den ersten Eintrag behalten
                        if (strpos($transient_key, 'top_') !== false) {
                            foreach ($data as $key => $value) {
                                if (is_array($value) && count($value) > 1) {
                                    $data[$key] = array_slice($value, 0, 1);
                                }
                            }
                            nowscrobbling_log("Top-Listen Fallback auf 1 Eintrag pro Kategorie reduziert nach fehlgeschlagenem Speichern");
                        }
                        
                        // Minimale Kompression mit höchster Stufe
                        $minimal_data = ['__ns_compressed' => true, 'data' => base64_encode(gzcompress(serialize($data), 9))];
                        $minimal_size = strlen(maybe_serialize($minimal_data));
                        nowscrobbling_log("Minimale Fallback-Datengröße: {$minimal_size} Bytes - letzter Versuch zum Speichern");
                        
                        // Letzter Versuch mit stark reduzierten Daten
                        if ($should_store_fallback) {
                            $fallback_set = set_transient($fallback_key, $minimal_data, $fallback_expiration);
                            if ($fallback_set) {
                                nowscrobbling_log("Fallback-Cache erfolgreich gespeichert nach Datenreduktion");
                            } else {
                                nowscrobbling_log("Fehler beim Speichern des Fallback-Cache für {$transient_key} trotz Datenreduktion");
                            }
                        } else {
                            nowscrobbling_log("Fallback weiterhin nicht überschrieben für {$transient_key} (Policy)");
                        }
                    } else {
                        nowscrobbling_log("Fehler beim Speichern des Fallback-Cache für {$transient_key} - keine weiteren Optimierungen möglich");
                    }
                } catch (Exception $e) {
                    nowscrobbling_log("Exception bei letztem Fallback-Speicherversuch für {$transient_key}: " . $e->getMessage());
                }
            }
            
            // Key für späteres Löschen tracken
            $keys = get_option('nowscrobbling_transient_keys', []);
            if (!is_array($keys)) { $keys = []; }
            
            if (!in_array($transient_key, $keys, true)) {
                $keys[] = $transient_key;
                update_option('nowscrobbling_transient_keys', $keys, false);
            }
            if (!in_array($fallback_key, $keys, true)) {
                $keys[] = $fallback_key;
                update_option('nowscrobbling_transient_keys', $keys, false);
            }
            
            // Globale Infos für diese Anfrage setzen
            $GLOBALS['nowscrobbling_last_source'] = 'fresh';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            
            // Timestamp für Konsistenz mit Log
            $now = (int) current_time('timestamp');
            $expires_local = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $now + (int) $ttl_to_use);
            nowscrobbling_log("Transient gesetzt: {$transient_key}, gültig bis " . $expires_local . ($has_error ? " (enthält Fehlerdaten)" : ""));
            
            // Meta für Diagnostik speichern
            $meta_payload = [
                'saved_at' => $now,
                'expires_at' => $now + (int) $ttl_to_use,
                'ttl' => (int) $ttl_to_use,
                'last_access' => $now,
                'service' => $service,
                'fallback_key' => $fallback_key,
                'fallback_exists' => 1,
                'has_error' => $has_error ? 1 : 0,
            ];
            if ($should_store_fallback) {
                $meta_payload['fallback_saved_at'] = $now;
                $meta_payload['fallback_expires_at'] = $now + (int) $fallback_expiration;
            }
            $meta = nowscrobbling_cache_meta($transient_key, $meta_payload);
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            
            // Entscheidung: Für Trakt-Listen bei leer/Fehler lieber Fallback sofort zurückgeben
            if ($is_trakt_list_key && ($is_empty_array || $has_error) && $has_fallback) {
                $request_cache[$transient_key] = $fallback_data;
                $GLOBALS['nowscrobbling_last_source'] = 'fallback';
                nowscrobbling_metrics_update( $service, 'fallback_hits' );
                return $fallback_data;
            }
            
            // In-Request-Cache aktualisieren
            $request_cache[$transient_key] = $data;
            
            // Letzte Erfolg-Zeit für Service aktualisieren (nur für erfolgreiche Daten ohne Fehler)
            if (!$has_error && in_array($service, ['lastfm','trakt'], true)) {
                nowscrobbling_record_last_success($service);
            }
            
            return $data;
        } 
        // Wenn Callback fehlschlägt oder null/false zurückgibt, Fallback verwenden
        else if ( $has_fallback ) {
            // Prüfen, ob die Fallback-Daten komprimiert sind und dekomprimieren
            if (is_array($fallback_data) && isset($fallback_data['__ns_compressed']) && $fallback_data['__ns_compressed'] === true && isset($fallback_data['data'])) {
                try {
                    if (function_exists('gzuncompress')) {
                        $decompressed = @unserialize(gzuncompress(base64_decode($fallback_data['data'])));
                        if ($decompressed !== false) {
                            $fallback_data = $decompressed;
                            nowscrobbling_log("Komprimierte Fallback-Daten für {$transient_key} erfolgreich dekomprimiert");
                        } else {
                            nowscrobbling_log("Fehler beim Dekomprimieren der Fallback-Daten für {$transient_key}");
                            
                            // Bei Dekomprimierungsfehler: Leere Daten zurückgeben, damit der Code nicht abstürzt
                            if (strpos($transient_key, 'lastfm_') !== false) {
                                $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                            } else if (strpos($transient_key, 'trakt_') !== false) {
                                $fallback_data = ['error' => 'Dekomprimierungsfehler', 'timestamp' => time()];
                            } else {
                                $fallback_data = [];
                            }
                        }
                    }
                } catch (Exception $e) {
                    nowscrobbling_log("Exception beim Dekomprimieren der Fallback-Daten für {$transient_key}: " . $e->getMessage());
                    
                    // Bei Exception: Leere Daten zurückgeben
                    if (strpos($transient_key, 'lastfm_') !== false || strpos($transient_key, 'trakt_') !== false) {
                        $fallback_data = ['error' => 'Dekomprimierungsfehler: ' . $e->getMessage(), 'timestamp' => time()];
                    } else {
                        $fallback_data = [];
                    }
                }
            }
            nowscrobbling_log("Callback fehlgeschlagen für {$transient_key}, verwende Fallback");
            $GLOBALS['nowscrobbling_last_source'] = 'fallback';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            nowscrobbling_metrics_update( $service, 'fallback_hits' );
            
            // Prüfen ob es sich im Fallback um Fehlerdaten handelt
            $fallback_has_error = is_array($fallback_data) && isset($fallback_data['error']);
            
            // Für Meta-Diagnostik mögliche TTL-Daten aus gespeicherten Meta-Daten extrahieren
            $existing_meta = nowscrobbling_cache_meta($transient_key, []);
            
            // Meta für Diagnostik aktualisieren
            $meta = nowscrobbling_cache_meta($transient_key, [
                'last_access' => time(),
                'fallback_key' => $fallback_key,
                'fallback_exists' => 1,
                'service' => $service,
                'has_error' => $fallback_has_error ? 1 : 0,
                'using_fallback' => 1,
                // Bestehende Meta-Werte beibehalten, falls vorhanden
                'saved_at' => isset($existing_meta['saved_at']) ? $existing_meta['saved_at'] : null,
                'expires_at' => isset($existing_meta['expires_at']) ? $existing_meta['expires_at'] : null,
                'ttl' => isset($existing_meta['ttl']) ? $existing_meta['ttl'] : null,
                'fallback_saved_at' => isset($existing_meta['fallback_saved_at']) ? $existing_meta['fallback_saved_at'] : null,
                'fallback_expires_at' => isset($existing_meta['fallback_expires_at']) ? $existing_meta['fallback_expires_at'] : null,
            ]);
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            
            // Auch in-request cachen
            $request_cache[$transient_key] = $fallback_data;
            
            // Bei häufiger Nutzung des Fallbacks den Transient erneuern (keep-alive)
            $metrics = get_option('ns_metrics', []);
            $fallback_hits = isset($metrics[$service]['fallback_hits']) ? (int)$metrics[$service]['fallback_hits'] : 0;
            if ($fallback_hits > 5) {
                // Nach mehreren Fallbacks den Cache auffrischen, damit er nicht ausläuft
                nowscrobbling_log("Verlängere Cache-Lebenszeit für {$transient_key} nach mehreren Fallback-Hits");
                set_transient($transient_key, $fallback_data, $expiration);
            }
            
            return $fallback_data;
        }
        // Keine Daten und kein Fallback verfügbar
        else {
            // Fehler-Daten erstellen und im Cache speichern für kurze Zeit (30s)
            $error_data = ['error' => 'Keine Daten verfügbar', 'timestamp' => time()];
            
            try {
                // Cache mit Fehlerdaten für kurze Zeit setzen
                set_transient($transient_key, $error_data, 30);
            } catch (Exception $e) {
                nowscrobbling_log("Exception beim Speichern der Fehlerdaten für {$transient_key}: " . $e->getMessage());
            }
            
            // Metadata und Status aktualisieren
            $now = (int) current_time('timestamp');
            $GLOBALS['nowscrobbling_last_source'] = 'miss';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            $meta = nowscrobbling_cache_meta($transient_key, [
                'last_access' => $now,
                'service' => $service,
                'saved_at' => $now,
                'expires_at' => $now + 30,
                'ttl' => 30,
                'has_error' => 1,
                'error_type' => 'no_data'
            ]);
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            
            // In-Request-Cache aktualisieren
            $request_cache[$transient_key] = $error_data;
            
            nowscrobbling_log("Leere Daten für {$transient_key} im Cache gespeichert (TTL: 30s)");
            return $error_data;
        }
    } 
    // Bei Exceptions Fallback verwenden oder Fehlerdaten cachen und zurückgeben
    catch ( Exception $e ) {
        nowscrobbling_log("Exception in Callback für {$transient_key}: " . $e->getMessage());
        
        // Fehler-Metriken aktualisieren
        nowscrobbling_metrics_update($service, 'total_errors');
        
        if ( $has_fallback ) {
            nowscrobbling_log("Nach Exception Fallback verwenden für {$transient_key}");
            $GLOBALS['nowscrobbling_last_source'] = 'fallback';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            nowscrobbling_metrics_update( $service, 'fallback_hits' );
            
            // Prüfen ob es sich im Fallback um Fehlerdaten handelt
            $fallback_has_error = is_array($fallback_data) && isset($fallback_data['error']);
            
            // Für Meta-Diagnostik mögliche TTL-Daten aus gespeicherten Meta-Daten extrahieren
            $existing_meta = nowscrobbling_cache_meta($transient_key, []);
            
            // Meta aktualisieren
            $meta = nowscrobbling_cache_meta( $transient_key, [
                'last_access' => time(),
                'fallback_key' => $fallback_key,
                'fallback_exists' => 1,
                'service' => $service,
                'last_error' => $e->getMessage(),
                'has_error' => $fallback_has_error ? 1 : 0,
                'using_fallback' => 1,
                // Bestehende Meta-Werte beibehalten
                'saved_at' => isset($existing_meta['saved_at']) ? $existing_meta['saved_at'] : null,
                'expires_at' => isset($existing_meta['expires_at']) ? $existing_meta['expires_at'] : null,
                'ttl' => isset($existing_meta['ttl']) ? $existing_meta['ttl'] : null,
                'fallback_saved_at' => isset($existing_meta['fallback_saved_at']) ? $existing_meta['fallback_saved_at'] : null,
                'fallback_expires_at' => isset($existing_meta['fallback_expires_at']) ? $existing_meta['fallback_expires_at'] : null,
            ] );
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            
            // In-Request-Cache aktualisieren
            $request_cache[$transient_key] = $fallback_data;
            
            return $fallback_data;
        }
        
        // Wenn kein Fallback verfügbar ist, Fehlerdaten erstellen und im Cache speichern
        $error_data = ['error' => 'Exception: ' . $e->getMessage(), 'timestamp' => time()];
        
        // Cache mit Fehlerdaten für kurze Zeit (60s) setzen, damit nicht ständig neu versucht wird
        set_transient($transient_key, $error_data, 60);
        
        // Metadata und Status aktualisieren
        $now = (int) current_time('timestamp');
        $GLOBALS['nowscrobbling_last_source'] = 'miss';
        $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
        $meta = nowscrobbling_cache_meta($transient_key, [
            'last_access' => $now,
            'service' => $service,
            'last_error' => $e->getMessage(),
            'saved_at' => $now,
            'expires_at' => $now + 60,
            'ttl' => 60,
            'has_error' => 1
        ]);
        $GLOBALS['nowscrobbling_last_meta'] = $meta;
        
        // In-Request-Cache aktualisieren
        $request_cache[$transient_key] = $error_data;
        
        nowscrobbling_log("Fehler-Daten für {$transient_key} im Cache gespeichert (TTL: 60s)");
        return $error_data;
    }
    // Immer Lock aufheben, unabhängig vom Ergebnis
    finally {
        if ( isset($lock_key) ) {
            delete_transient( $lock_key );
        }
    }
}

/**
 * Bereinigt alle Plugin-Caches (Transients, ETags, Sperren)
 * 
 * Diese Funktion wird bei manuellen Cache-Löschungen und bei der Plugin-Deaktivierung verwendet.
 */
function nowscrobbling_clear_all_caches() {
    $cleared_count = 0;
    
    // 1. Primäre und Fallback-Transients aus der Tracking-Liste löschen
    $keys = get_option('nowscrobbling_transient_keys', []);
    if (is_array($keys)) {
        foreach ($keys as $k) {
            if (delete_transient($k)) {
                $cleared_count++;
            }
        }
    }
    
    // 2. ETags mit direkter DB-Abfrage löschen (schneller als einzeln)
    global $wpdb;
    $like_val = $wpdb->esc_like('_transient_nowscrobbling_etag_') . '%';
    $like_timeout = $wpdb->esc_like('_transient_timeout_nowscrobbling_etag_') . '%';
    $etags_deleted = $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 
            $like_val, $like_timeout)
    );
    
    // 3. Spezielle Runtime-Flags löschen
    $flags = [
        'nowscrobbling_cooldown_lastfm',
        'nowscrobbling_cooldown_trakt', 
        'nowscrobbling_cooldown_generic',
        // Alle aktiven Sperren löschen
        'nowscrobbling_lastfm_scrobbles_lock',
        'nowscrobbling_trakt_watching_lock',
        'nowscrobbling_trakt_activities_lock',
    ];
    
    foreach ($flags as $flag) {
        if (delete_transient($flag)) {
            $cleared_count++;
        }
    }
    
    // 4. Zusätzlich alle Transients mit dem Prefix direkt löschen (falls welche übrig sind)
    $like_val_ns = $wpdb->esc_like('_transient_nowscrobbling_') . '%';
    $like_timeout_ns = $wpdb->esc_like('_transient_timeout_nowscrobbling_') . '%';
    $extras_deleted = $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 
            $like_val_ns, $like_timeout_ns)
    );
    
    nowscrobbling_log(sprintf(
        'Alle NowScrobbling Caches gelöscht: %d Transients, %d ETags, %d weitere Caches', 
        $cleared_count, 
        (int) ($etags_deleted / 2), // Teilen durch 2, da für jeden ETag zwei Einträge gelöscht werden
        (int) ($extras_deleted / 2)  // Teilen durch 2, da für jeden Transient zwei Einträge gelöscht werden
    ));
    
    // Setze "Now-Playing"-Status-Flags zurück
    update_option('ns_flag_lastfm_nowplaying', 0, false);
    update_option('ns_flag_trakt_watching', 0, false);
    
    // Cache-Meta ebenfalls zurücksetzen
    update_option('ns_cache_meta', [], false);
    
    return true;
}

/**
 * Hintergrundaktualisierungsfunktion für Cron-Jobs
 * 
 * Diese Funktion wird alle 5 Minuten ausgeführt, um alle Caches aktuell zu halten,
 * auch wenn keine Besucher auf der Seite sind. Dies stellt sicher, dass die Daten
 * immer aktuell sind, wenn ein Besucher die Seite lädt.
 */
function nowscrobbling_background_refresh() {
    $start_time = microtime(true);
    nowscrobbling_log('=== Starte Hintergrundaktualisierung ===');
    
    $errors = [];
    $updates = 0;
    
    // Last.fm-Daten aktualisieren, falls konfiguriert
    if (get_option('lastfm_api_key') && get_option('lastfm_user')) {
        // Nur aktualisieren, wenn kein Cooldown aktiv ist
        if (!nowscrobbling_should_cooldown('lastfm')) {
            try {
                // Zuerst scrobbles aktualisieren (wichtigster Datenpunkt)
                $scrobbles = nowscrobbling_fetch_lastfm_scrobbles('background_refresh');
                if ($scrobbles !== null) {
                    $updates++;
                    nowscrobbling_log('Last.fm Scrobbles aktualisiert: ' . count($scrobbles) . ' Einträge');
                    
                    // Top-Daten nur aktualisieren, wenn Scrobbles erfolgreich waren
                    // Hier nur wöchentliche Daten laden - für längere Zeiträume ist API-Aufruf unnötig
                    $top_types = ['topartists', 'topalbums', 'toptracks', 'lovedtracks'];
                    foreach ($top_types as $type) {
                        $result = nowscrobbling_fetch_lastfm_top_data($type, 5, '7day', "lastfm_{$type}");
                        if ($result !== null) {
                            $updates++;
                        }
                    }
                    nowscrobbling_log('Last.fm Top-Daten aktualisiert');
                }
            } catch (Exception $e) {
                $errors[] = 'Last.fm: ' . $e->getMessage();
                nowscrobbling_log('Last.fm Aktualisierung fehlgeschlagen: ' . $e->getMessage());
            }
        } else {
            nowscrobbling_log('Last.fm Aktualisierung übersprungen: Cooldown aktiv');
        }
    } else {
        nowscrobbling_log('Last.fm Aktualisierung übersprungen: Keine Credentials konfiguriert');
    }
    
    // Trakt-Daten aktualisieren, falls konfiguriert
    if (get_option('trakt_client_id') && get_option('trakt_user')) {
        // Nur aktualisieren, wenn kein Cooldown aktiv ist
        if (!nowscrobbling_should_cooldown('trakt')) {
            try {
                // Watching-Status hat Priorität, danach Activities
                $watching = nowscrobbling_fetch_trakt_watching();
                if ($watching !== null) {
                    $updates++;
                    
                    // Aktivitäten nur aktualisieren, wenn Watching erfolgreich war
                    $activities = nowscrobbling_fetch_trakt_activities('background_refresh');
                    if ($activities !== null) {
                        $updates++;
                        nowscrobbling_log('Trakt Aktivitäten aktualisiert');
                        
                        // Historie nur aktualisieren, wenn Activities erfolgreich waren
                        $history_types = ['movies', 'shows', 'episodes'];
                        foreach ($history_types as $type) {
                            $user = get_option('trakt_user');
                            $result = nowscrobbling_fetch_trakt_data("users/$user/history/$type", 
                                ['limit' => 3], 
                                "trakt_last_{$type}");
                            
                            if ($result !== null) {
                                $updates++;
                            }
                        }
                        nowscrobbling_log('Trakt Historie aktualisiert');
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Trakt: ' . $e->getMessage();
                nowscrobbling_log('Trakt Aktualisierung fehlgeschlagen: ' . $e->getMessage());
            }
        } else {
            nowscrobbling_log('Trakt Aktualisierung übersprungen: Cooldown aktiv');
        }
    } else {
        nowscrobbling_log('Trakt Aktualisierung übersprungen: Keine Credentials konfiguriert');
    }
    
    // Dauer berechnen und Log-Eintrag erstellen
    $duration = round((microtime(true) - $start_time) * 1000);
    
    if (!empty($errors)) {
        nowscrobbling_log('Hintergrundaktualisierung abgeschlossen mit ' . count($errors) . ' Fehlern in ' . $duration . 'ms');
    } else {
        nowscrobbling_log('Hintergrundaktualisierung erfolgreich abgeschlossen: ' . $updates . ' Updates in ' . $duration . 'ms');
    }
}
add_action('nowscrobbling_background_refresh', 'nowscrobbling_background_refresh');


?>