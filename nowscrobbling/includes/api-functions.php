<?php

// Fetch API Data
function nowscrobbling_fetch_api_data($url, $headers = [])
{
    $response = wp_remote_get($url, ['headers' => $headers]);
    if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response)) {
        return null;
    }
    return json_decode(wp_remote_retrieve_body($response), true);
}

// Last.fm Data Fetch
function nowscrobbling_fetch_lastfm_data($method, $params = [])
{
    $api_key = get_option('lastfm_api_key');
    $user = get_option('lastfm_user');
    $params = array_merge(['api_key' => $api_key, 'user' => $user, 'format' => 'json'], $params);
    $url = "http://ws.audioscrobbler.com/2.0/?method=user.$method&" . http_build_query($params);
    return nowscrobbling_fetch_api_data($url);
}

// Fetch and Display Last.fm Scrobbles
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

// Fetch Last.fm Top Data
function nowscrobbling_fetch_lastfm_top_data($type, $count, $period)
{
    return nowscrobbling_fetch_lastfm_data("get{$type}", [
        'limit' => $count,
        'period' => $period
    ]);
}

// Trakt Data Fetch
function nowscrobbling_fetch_trakt_data($path, $params = [])
{
    $client_id = get_option('trakt_client_id');
    $headers = [
        'Content-Type' => 'application/json',
        'trakt-api-version' => '2',
        'trakt-api-key' => $client_id,
    ];
    $url = "https://api.trakt.tv/$path?" . http_build_query($params);
    return nowscrobbling_fetch_api_data($url, $headers);
}

// Fetch and Display Trakt Activities
function nowscrobbling_fetch_trakt_activities()
{
    return nowscrobbling_get_or_set_transient('my_trakt_tv_activities', function () {
        return nowscrobbling_fetch_trakt_data('users/' . get_option('trakt_user') . '/history', [
            'limit' => get_option('trakt_activity_limit', 25)
        ]);
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);
}

?>
