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
    if (count($log) > 100) array_shift($log);
    update_option('nowscrobbling_log', $log);
}

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
 * @return null Always returns null.
 */
function nowscrobbling_handle_api_error($error, $message)
{
    nowscrobbling_log("$message: " . $error->get_error_message());
    return null;
}

/**
 * Fetch API Data with ETag support and improved error handling
 *
 * @param string $url The API endpoint URL.
 * @param array $headers The headers to send with the request.
 * @param array $args Additional arguments for wp_safe_remote_get.
 * @param string $cache_key Optional cache key for ETag storage.
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_api_data($url, $headers = [], $args = [], $cache_key = '') {
    $args = wp_parse_args($args, [
        'timeout'     => 10,
        'redirection' => 3,
        'headers'     => [],
    ]);

    $args['headers'] = array_merge([
        'User-Agent' => nowscrobbling_user_agent(),
        'Accept'     => 'application/json',
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
    $response = wp_safe_remote_get($url, $args);
    if (is_wp_error($response)) {
        nowscrobbling_handle_api_error($response, "API request failed for $url");
        nowscrobbling_metrics_update( $service, 'total_requests' );
        nowscrobbling_metrics_update( $service, [ 'last_ms' => (int) round( ( microtime(true) - $start ) * 1000 ), 'last_status' => 0 ] );
        nowscrobbling_metrics_update( $service, 'total_errors' );
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

    // Handle 304 Not Modified
    if ($response_code === 304) {
        nowscrobbling_log("ETag cache hit for $url");
        nowscrobbling_metrics_update( $service, 'etag_hits' );
        return null; // Return null to indicate no change
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
    if (nowscrobbling_should_cooldown('lastfm')) {
        nowscrobbling_log('Last.fm im Cooldown — überspringe API-Call');
        return null;
    }
    $params = array_merge([
        'api_key' => nowscrobbling_opt('lastfm_api_key'),
        'user'    => nowscrobbling_opt('lastfm_user'),
        'format'  => 'json',
    ], $params);
    $url = NOWSCROBBLING_LASTFM_API_URL . '?method=user.' . rawurlencode($method) . '&' . http_build_query($params);
    // Use ETag per URL to support 304 Not Modified
    $etag_key = 'lastfm:' . substr(md5($url), 0, 24);
    $data = nowscrobbling_fetch_api_data($url, [], [], $etag_key);
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
            if (!$data || isset($data['error']) || empty($data['recenttracks']['track'])) {
                return ['error' => 'Fehler beim Abrufen der Last.fm-Daten'];
            }
            nowscrobbling_log("Last.fm API-Call ausgeführt und Daten gecacht (" . count($data['recenttracks']['track']) . " Einträge).");
            $duration = round((microtime(true) - $start) * 1000);
            nowscrobbling_log("Last.fm API Dauer: {$duration}ms");
            return array_map(function ($track) {
                return [
                    'url' => esc_url($track['url'] ?? '#'),
                    'name' => esc_html($track['name'] ?? 'Unbekannter Track'),
                    'artist' => esc_html($track['artist']['#text'] ?? 'Unbekannter Künstler'),
                    'nowplaying' => $track['@attr']['nowplaying'] ?? false,
                    'date' => $track['date']['#text'] ?? null
                ];
            }, $data['recenttracks']['track']);
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
    if (nowscrobbling_should_cooldown('trakt')) {
        nowscrobbling_log('Trakt im Cooldown — überspringe API-Call');
        return null;
    }
    
    // Ensure $params is always an array
    if (!is_array($params)) {
        $params = [];
    }
    
    if ($cache_key) {
        $result = nowscrobbling_get_or_set_transient(
            nowscrobbling_build_cache_key($cache_key, $params),
            function () use ($path, $params, $cache_key) {
                $headers = [
                    'Content-Type'     => 'application/json',
                    'trakt-api-version' => '2',
                    'trakt-api-key'     => nowscrobbling_opt('trakt_client_id'),
                ];
                $query = is_array($params) ? http_build_query($params) : '';
                $url = NOWSCROBBLING_TRAKT_API_URL . ltrim($path, '/') . ($query ? "?$query" : '');
                // Provide stable ETag key per request
                $etag_key = ($cache_key ? $cache_key : 'trakt') . ':' . substr(md5($url), 0, 24);
                $data = nowscrobbling_fetch_api_data($url, $headers, [], $etag_key);
                return $data;
            },
            nowscrobbling_cache_minutes('trakt') * MINUTE_IN_SECONDS
        );
        return $result;
    }
    
    $headers = [
        'Content-Type'     => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key'     => nowscrobbling_opt('trakt_client_id'),
    ];
    $query = is_array($params) ? http_build_query($params) : '';
    $url = NOWSCROBBLING_TRAKT_API_URL . ltrim($path, '/') . ($query ? "?$query" : '');
    $etag_key = ($cache_key ? $cache_key : 'trakt') . ':' . substr(md5($url), 0, 24);
    $data = nowscrobbling_fetch_api_data($url, $headers, [], $etag_key);
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
    $result = nowscrobbling_get_or_set_transient(
        // Unify cache key across contexts to avoid doppelte API-Calls
        nowscrobbling_build_cache_key('trakt_activities', ['limit' => (int) nowscrobbling_opt('trakt_activity_limit', 25)]),
        function () {
            $start = microtime(true);
            $user = nowscrobbling_opt('trakt_user');
            $data = nowscrobbling_fetch_trakt_data('users/' . $user . '/history', [
                'limit' => nowscrobbling_opt('trakt_activity_limit', 25)
            ], 'trakt_activities');
            if (!$data || isset($data['error'])) {
                return ['error' => 'Fehler beim Abrufen der Trakt-Daten'];
            }
            nowscrobbling_log("Trakt API-Call ausgeführt und Daten gecacht (" . count($data) . " Einträge).");
            $duration = round((microtime(true) - $start) * 1000);
            nowscrobbling_log("Trakt API Dauer: {$duration}ms");
            return $data;
        },
        nowscrobbling_cache_minutes('trakt') * MINUTE_IN_SECONDS
    );
    nowscrobbling_log("Trakt Cache verwendet.");
    return $result;
}

/**
 * Fetch Trakt Watching Data
 *
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_trakt_watching()
{
    $result = nowscrobbling_get_or_set_transient(
        nowscrobbling_build_cache_key('trakt_watching'),
        function () {
            $headers = [
                'Content-Type'     => 'application/json',
                'trakt-api-version' => '2',
                'trakt-api-key'     => nowscrobbling_opt('trakt_client_id'),
            ];
            $url = NOWSCROBBLING_TRAKT_API_URL . "users/" . ltrim(nowscrobbling_opt('trakt_user'), '/') . "/watching";
            return nowscrobbling_fetch_api_data($url, $headers);
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
function nowscrobbling_get_or_set_transient($transient_key, $callback, $expiration, $force_refresh = false) {
    // In-request caching to coalesce duplicate work within the same PHP request
    static $request_cache = [];

    // Allow global/constant override (e.g., via AJAX force_refresh)
    if ( defined('NOWSCROBBLING_FORCE_REFRESH') && NOWSCROBBLING_FORCE_REFRESH ) {
        $force_refresh = true;
    }
    // Check if we should force refresh
    if (!$force_refresh) {
        $data = get_transient($transient_key);
        if ($data !== false) {
            $GLOBALS['nowscrobbling_last_source'] = 'cache';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            // Update metrics per service
            if ( strpos( $transient_key, 'lastfm' ) !== false ) {
                nowscrobbling_metrics_update( 'lastfm', 'cache_hits' );
                $service = 'lastfm';
            } elseif ( strpos( $transient_key, 'trakt' ) !== false ) {
                nowscrobbling_metrics_update( 'trakt', 'cache_hits' );
                $service = 'trakt';
            } else {
                nowscrobbling_metrics_update( 'generic', 'cache_hits' );
                $service = 'generic';
            }
            // Record/access meta for diagnostics
            $fallback_key = $transient_key . '_fallback';
            $fallback_exists = ( get_transient( $fallback_key ) !== false );
            $meta = nowscrobbling_cache_meta( $transient_key, [
                'last_access' => time(),
                'service' => $service,
                'fallback_key' => $fallback_key,
                'fallback_exists' => $fallback_exists ? 1 : 0,
                // keep saved_at/expires_at from previous write; do not overwrite here
            ] );
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            return $data;
        }
    }

    // Try to get fallback data from a longer-lived cache
    $fallback_key = $transient_key . '_fallback';
    $fallback_data = get_transient($fallback_key);

    // If no cache present and live fetch is not allowed in this context, serve fallback immediately
    // Live fetch is allowed in: AJAX, cron (admin is opted-out by default and can enable via filter)
    $allow_live_fetch = apply_filters('nowscrobbling_allow_live_fetch', ( defined('DOING_AJAX') && DOING_AJAX ) || ( function_exists('wp_doing_cron') && wp_doing_cron() ), $transient_key);
    if ( ! $force_refresh && ! $allow_live_fetch ) {
        if ( $fallback_data !== false ) {
            nowscrobbling_log("Using fallback data for {$transient_key} (live fetch disabled)");
            $GLOBALS['nowscrobbling_last_source'] = 'fallback';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            // Update minimal meta
            $meta = nowscrobbling_cache_meta( $transient_key, [
                'last_access' => (int) current_time('timestamp'),
                'fallback_key' => $fallback_key,
                'fallback_exists' => 1,
            ] );
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            return $fallback_data;
        }
    }
    
    try {
        // Prefer fallback data even if live fetch is allowed, unless explicitly forced
        if ( ! $force_refresh && $fallback_data !== false ) {
            nowscrobbling_log("Using fallback data for {$transient_key} (preferred over live fetch)");
            $GLOBALS['nowscrobbling_last_source'] = 'fallback';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            if ( strpos( $transient_key, 'lastfm' ) !== false ) {
                nowscrobbling_metrics_update( 'lastfm', 'fallback_hits' );
            } elseif ( strpos( $transient_key, 'trakt' ) !== false ) {
                nowscrobbling_metrics_update( 'trakt', 'fallback_hits' );
            } else {
                nowscrobbling_metrics_update( 'generic', 'fallback_hits' );
            }
            $meta = nowscrobbling_cache_meta( $transient_key, [
                'last_access' => (int) current_time('timestamp'),
                'fallback_key' => $fallback_key,
                'fallback_exists' => 1,
            ] );
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            return $fallback_data;
        }

        // Coalesce duplicate computations within the same request
        if ( array_key_exists( $transient_key, $request_cache ) ) {
            return $request_cache[$transient_key];
        }
        $data = call_user_func($callback);
        
        if ($data !== null && $data !== false) {
            // Store in main cache
            set_transient($transient_key, $data, $expiration);
            
            // Store in fallback cache with longer expiration (capped)
            $fallback_expiration = min( WEEK_IN_SECONDS, $expiration * 3 );
            set_transient($fallback_key, $data, $fallback_expiration );
            
            // Track the key for later clearing
            $keys = get_option('nowscrobbling_transient_keys', []);
            if (!in_array($transient_key, $keys, true)) {
                $keys[] = $transient_key;
                update_option('nowscrobbling_transient_keys', $keys, false);
            }
            if (!in_array($fallback_key, $keys, true)) {
                $keys[] = $fallback_key;
                update_option('nowscrobbling_transient_keys', $keys, false);
            }
            
            $GLOBALS['nowscrobbling_last_source'] = 'fresh';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            // Use WP-local timestamp for consistency with log timestamps
            $now = (int) current_time('timestamp');
            $expires_local = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $now + (int) $expiration );
            nowscrobbling_log("Transient gesetzt: {$transient_key}, gültig bis " . $expires_local);
            // Save meta for diagnostics
            $service = ( strpos( $transient_key, 'lastfm' ) !== false ) ? 'lastfm' : ( ( strpos( $transient_key, 'trakt' ) !== false ) ? 'trakt' : 'generic' );
            $meta = nowscrobbling_cache_meta( $transient_key, [
                'saved_at' => $now,
                'expires_at' => $now + (int) $expiration,
                'ttl' => (int) $expiration,
                'last_access' => $now,
                'service' => $service,
                'fallback_key' => $fallback_key,
                'fallback_saved_at' => $now,
                'fallback_expires_at' => $now + (int) $fallback_expiration,
                'fallback_exists' => 1,
            ] );
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            // Put into in-request cache for subsequent callers
            $request_cache[$transient_key] = $data;
        } else {
            // If callback failed, try to use fallback data
            if ($fallback_data !== false) {
                nowscrobbling_log("Using fallback data for {$transient_key}");
                $GLOBALS['nowscrobbling_last_source'] = 'fallback';
                $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
                if ( strpos( $transient_key, 'lastfm' ) !== false ) {
                    nowscrobbling_metrics_update( 'lastfm', 'fallback_hits' );
                } elseif ( strpos( $transient_key, 'trakt' ) !== false ) {
                    nowscrobbling_metrics_update( 'trakt', 'fallback_hits' );
                } else {
                    nowscrobbling_metrics_update( 'generic', 'fallback_hits' );
                }
                // Expose meta for diagnostics (read existing and mark fallback used)
                $fk = $transient_key . '_fallback';
                $existing = nowscrobbling_cache_meta( $transient_key );
                $meta = nowscrobbling_cache_meta( $transient_key, [
                    'last_access' => time(),
                    'fallback_key' => $fk,
                    'fallback_exists' => 1,
                ] );
                $GLOBALS['nowscrobbling_last_meta'] = $meta;
                return $fallback_data;
            }
        }
        
        return $data;
    } catch (Exception $e) {
        nowscrobbling_log("Error in callback for {$transient_key}: " . $e->getMessage());
        
        // Return fallback data if available
        if ($fallback_data !== false) {
            nowscrobbling_log("Using fallback data for {$transient_key} after error");
            $GLOBALS['nowscrobbling_last_source'] = 'fallback';
            $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
            if ( strpos( $transient_key, 'lastfm' ) !== false ) {
                nowscrobbling_metrics_update( 'lastfm', 'fallback_hits' );
            } elseif ( strpos( $transient_key, 'trakt' ) !== false ) {
                nowscrobbling_metrics_update( 'trakt', 'fallback_hits' );
            } else {
                nowscrobbling_metrics_update( 'generic', 'fallback_hits' );
            }
            // Expose meta for diagnostics
            $fk = $transient_key . '_fallback';
            $meta = nowscrobbling_cache_meta( $transient_key, [
                'last_access' => time(),
                'fallback_key' => $fk,
                'fallback_exists' => 1,
            ] );
            $GLOBALS['nowscrobbling_last_meta'] = $meta;
            return $fallback_data;
        }
        
        $GLOBALS['nowscrobbling_last_source'] = 'miss';
        $GLOBALS['nowscrobbling_last_source_key'] = $transient_key;
        // Provide minimal meta for diagnostics
        $GLOBALS['nowscrobbling_last_meta'] = [ 'last_access' => time(), 'service' => ( strpos( $transient_key, 'lastfm' ) !== false ) ? 'lastfm' : ( ( strpos( $transient_key, 'trakt' ) !== false ) ? 'trakt' : 'generic' ) ];
        return null;
    }
}

/**
 * Clear all caches (transients and ETags).
 */
function nowscrobbling_clear_all_caches() {
    $keys = get_option('nowscrobbling_transient_keys', []);
    if (is_array($keys)) {
        foreach ($keys as $k) {
            delete_transient($k);
        }
    }
    
    // Clear ETags
    global $wpdb;
    // Remove ETag transients (both value and timeout entries)
    $like_val = $wpdb->esc_like('_transient_nowscrobbling_etag_') . '%';
    $like_timeout = $wpdb->esc_like('_transient_timeout_nowscrobbling_etag_') . '%';
    $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_val, $like_timeout)
    );
    
    // Also clear cooldown flags
    delete_transient('nowscrobbling_cooldown_lastfm');
    delete_transient('nowscrobbling_cooldown_trakt');
    delete_transient('nowscrobbling_cooldown_generic');
    
    nowscrobbling_log('Alle NowScrobbling Transients, ETags und Cooldowns gelöscht.');
}

/**
 * Background refresh function for cron jobs
 */
function nowscrobbling_background_refresh() {
    nowscrobbling_log('Starting background refresh');
    
    // Refresh Last.fm data if configured
    if (get_option('lastfm_api_key') && get_option('lastfm_user')) {
        try {
            // Refresh now playing data more frequently
            nowscrobbling_fetch_lastfm_scrobbles('lastfm_indicator');
            nowscrobbling_fetch_lastfm_scrobbles('lastfm_history');
            
            // Refresh top data less frequently
            $top_types = ['topartists', 'topalbums', 'toptracks', 'lovedtracks'];
            foreach ($top_types as $type) {
                nowscrobbling_fetch_lastfm_top_data($type, 5, '7day', "lastfm_{$type}");
            }
        } catch (Exception $e) {
            nowscrobbling_log('Last.fm background refresh failed: ' . $e->getMessage());
        }
    }
    
    // Refresh Trakt data if configured
    if (get_option('trakt_client_id') && get_option('trakt_user')) {
        try {
            // Refresh watching status more frequently
            nowscrobbling_fetch_trakt_watching();
            nowscrobbling_fetch_trakt_activities('trakt_indicator');
            nowscrobbling_fetch_trakt_activities('trakt_history');
            
            // Refresh history data less frequently
            $history_types = ['movies', 'shows', 'episodes'];
            foreach ($history_types as $type) {
                $user = get_option('trakt_user');
                nowscrobbling_fetch_trakt_data("users/$user/history/$type", ['limit' => 3], "trakt_last_{$type}");
            }
        } catch (Exception $e) {
            nowscrobbling_log('Trakt background refresh failed: ' . $e->getMessage());
        }
    }
    
    nowscrobbling_log('Background refresh completed');
}
add_action('nowscrobbling_background_refresh', 'nowscrobbling_background_refresh');


?>