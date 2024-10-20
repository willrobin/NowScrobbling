<?php

/**
 * Version:             1.2.5
 * File:                nowscrobbling/includes/api-functions.php
 */

// Ensure the script is not accessed directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants for API URLs
define('LASTFM_API_URL', 'https://ws.audioscrobbler.com/2.0/');
define('TRAKT_API_URL', 'https://api.trakt.tv/');

// Define constants for options
define('LASTFM_API_KEY', get_option('lastfm_api_key'));
define('LASTFM_USER', get_option('lastfm_user'));
define('TRAKT_CLIENT_ID', get_option('trakt_client_id'));
define('TRAKT_USER', get_option('trakt_user'));

/**
 * Handle API request errors
 *
 * @param WP_Error $error The error object.
 * @param string $message The custom error message.
 * @return null Always returns null.
 */
function nowscrobbling_handle_api_error($error, $message)
{
    error_log("$message: " . $error->get_error_message());
    return null;
}

/**
 * Fetch API Data
 *
 * @param string $url The API endpoint URL.
 * @param array $headers The headers to send with the request.
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_api_data($url, $headers = [])
{
    $response = wp_remote_get($url, ['headers' => $headers]);
    if (is_wp_error($response)) {
        return nowscrobbling_handle_api_error($response, 'API request error');
    }
    if (200 != wp_remote_retrieve_response_code($response)) {
        error_log('API request error: Invalid response code ' . wp_remote_retrieve_response_code($response));
        return null;
    }
    return json_decode(wp_remote_retrieve_body($response), true);
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
    $params = array_merge(['api_key' => LASTFM_API_KEY, 'user' => LASTFM_USER, 'format' => 'json'], $params);
    $url = LASTFM_API_URL . "?method=user.$method&" . http_build_query($params);
    return nowscrobbling_fetch_api_data($url);
}

/**
 * Fetch and Display Last.fm Scrobbles
 *
 * @return array The scrobbles data or error message.
 */
function nowscrobbling_fetch_lastfm_scrobbles()
{
    return nowscrobbling_get_or_set_transient('my_lastfm_scrobbles', function () {
        $data = nowscrobbling_fetch_lastfm_data('getrecenttracks', [
            'limit' => get_option('lastfm_activity_limit', 3)
        ]);
        if (!$data || isset($data['error']) || empty($data['recenttracks']['track'])) {
            return ['error' => 'Fehler beim Abrufen der Last.fm-Daten'];
        }
        return array_map(function ($track) {
            return [
                'url' => esc_url($track['url'] ?? '#'),
                'name' => esc_html($track['name'] ?? 'Unbekannter Track'),
                'artist' => esc_html($track['artist']['#text'] ?? 'Unbekannter Künstler'),
                'nowplaying' => $track['@attr']['nowplaying'] ?? false,
                'date' => $track['date']['#text'] ?? null
            ];
        }, $data['recenttracks']['track']);
    }, get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS);
}

/**
 * Fetch Last.fm Top Data
 *
 * @param string $type The type of data to fetch (e.g., topartists, topalbums).
 * @param int $count The number of items to fetch.
 * @param string $period The period to fetch data for.
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_lastfm_top_data($type, $count, $period)
{
    return nowscrobbling_fetch_lastfm_data("get{$type}", [
        'limit' => $count,
        'period' => $period
    ]);
}

/**
 * Fetch Trakt Data
 *
 * @param string $path The API endpoint path.
 * @param array $params The query parameters for the API request.
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_trakt_data($path, $params = [])
{
    $headers = [
        'Content-Type' => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key' => TRAKT_CLIENT_ID,
    ];
    $url = TRAKT_API_URL . "$path?" . http_build_query($params);
    return nowscrobbling_fetch_api_data($url, $headers);
}

/**
 * Fetch and Display Trakt Activities
 *
 * @return array The activities data or error message.
 */
function nowscrobbling_fetch_trakt_activities()
{
    return nowscrobbling_get_or_set_transient('my_trakt_tv_activities', function () {
        $data = nowscrobbling_fetch_trakt_data('users/' . TRAKT_USER . '/history', [
            'limit' => get_option('trakt_activity_limit', 25)
        ]);
        if (!$data || isset($data['error'])) {
            return ['error' => 'Fehler beim Abrufen der Trakt-Daten'];
        }
        return $data;
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);
}

/**
 * Fetch Trakt Watching Data
 *
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_trakt_watching()
{
    $headers = [
        'Content-Type' => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key' => TRAKT_CLIENT_ID,
    ];
    $url = TRAKT_API_URL . "users/" . TRAKT_USER . "/watching";
    return nowscrobbling_fetch_api_data($url, $headers);
}

/**
 * Fetch Trakt Watched Shows
 *
 * @return array|null The response data or null if an error occurred.
 */
function nowscrobbling_fetch_trakt_watched_shows()
{
    $headers = [
        'Content-Type' => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key' => TRAKT_CLIENT_ID,
    ];
    $url = TRAKT_API_URL . "users/" . TRAKT_USER . "/watched/shows";
    return nowscrobbling_fetch_api_data($url, $headers);
}

/** 
 * Fetch specific Trakt Movie Rating 
 * 
 * @param int $movie_id The Trakt ID of the movie.
 * @return int|null The movie rating or null if no rating exists.
 */
function nowscrobbling_fetch_trakt_movie_rating($movie_id) {
    $user = get_option('trakt_user');
    $rating_data = nowscrobbling_fetch_trakt_data("users/$user/ratings/movies/$movie_id");

    if (isset($rating_data['rating'])) {
        return $rating_data['rating'];
    }

    return null; // No rating found
}

/** 
 * Fetch specific Trakt Show Rating 
 * 
 * @param int $show_id The Trakt ID of the show.
 * @return int|null The show rating or null if no rating exists.
 */
function nowscrobbling_fetch_trakt_show_rating($show_id) {
    $user = get_option('trakt_user');
    $rating_data = nowscrobbling_fetch_trakt_data("users/$user/ratings/shows/$show_id");

    if (isset($rating_data['rating'])) {
        return $rating_data['rating'];
    }

    return null; // No rating found
}

/** 
 * Fetch specific Trakt Episode Rating 
 * 
 * @param int $episode_id The Trakt ID of the episode.
 * @return int|null The episode rating or null if no rating exists.
 */
function nowscrobbling_fetch_trakt_episode_rating($episode_id) {
    $user = get_option('trakt_user');
    $rating_data = nowscrobbling_fetch_trakt_data("users/$user/ratings/episodes/$episode_id");

    if (isset($rating_data['rating'])) {
        return $rating_data['rating'];
    }

    return null; // No rating found
}

/**
 * Fetch rewatch count for a movie or episode.
 *
 * @param int $id The ID of the movie or episode.
 * @param string $type The type (e.g., 'movies', 'episodes').
 * @return int The rewatch count.
 */
function nowscrobbling_get_rewatch_count($id, $type) {
    // Hole den Benutzer aus den Optionen
    $user = get_option('trakt_user');
    
    // Baue den API-Pfad basierend auf dem Typ ('movies' oder 'episodes')
    $path = "users/$user/history/$type/$id";
    
    // Hole die Historie von Trakt
    $history = nowscrobbling_fetch_trakt_data($path);
    
    // Überprüfe, ob die Daten gültig sind
    if (!is_array($history)) {
        return 0; // Falls keine Daten vorliegen, gib 0 zurück
    }
    
    // Gib die Anzahl der Wiederholungen zurück (Anzahl der Einträge in der Historie)
    return count($history);
}

?>