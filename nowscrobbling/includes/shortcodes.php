<?php

// Fetch or Set Transient
function nowscrobbling_get_or_set_transient($transient_key, $callback, $expiration = 3600)
{
    if (false === ($data = get_transient($transient_key))) {
        $data = call_user_func($callback);
        set_transient($transient_key, $data, $expiration);
    }
    return $data;
}

// Generate Shortcode Output
function nowscrobbling_generate_shortcode_output($items, $format_callback)
{
    $formatted_items = array_map($format_callback, $items);
    if (count($formatted_items) > 1) {
        $last_item = array_pop($formatted_items);
        return implode(' ', $formatted_items) . ' und ' . $last_item;
    }
    return $formatted_items[0];
}

// Last.fm Indicator Shortcode
function nowscr_lastfm_indicator_shortcode()
{
    $scrobbles = nowscrobbling_fetch_lastfm_scrobbles();
    if (isset($scrobbles['error'])) {
        return "<em>{$scrobbles['error']}</em>";
    }

    foreach ($scrobbles as $track) {
        if ($track['nowplaying']) {
            return "<strong>Scrobbelt gerade</strong>";
        }
    }

    $lastTrack = reset($scrobbles);
    if ($lastTrack && isset($lastTrack['date'])) {
        $date = new DateTime($lastTrack['date']);
        $date->setTimezone(new DateTimeZone(get_option('timezone_string')));
        return 'Zuletzt gehört: ' . $date->format(get_option('date_format') . ' ' . get_option('time_format'));
    }
    return "<em>Keine kürzlichen Scrobbles gefunden.</em>";
}
add_shortcode('nowscr_lastfm_indicator', 'nowscr_lastfm_indicator_shortcode');

// Last.fm History Shortcode
function nowscr_lastfm_history_shortcode()
{
    $scrobbles = nowscrobbling_fetch_lastfm_scrobbles();
    if (isset($scrobbles['error'])) {
        return "<em>{$scrobbles['error']}</em>";
    }

    $output = '';
    foreach ($scrobbles as $track) {
        if ($track['nowplaying']) {
            $nowPlaying = '<img src="' . plugins_url('../public/images/nowplaying.gif', __FILE__) . '" alt="NOW PLAYING" /> ';
            $output = "<span class='bubble'>{$nowPlaying}<a href='{$track['url']}' target='_blank'>{$track['artist']} - {$track['name']}</a></span>";
            break;
        }
    }

    if (empty($output) && !empty($scrobbles)) {
        $lastTrack = $scrobbles[0];
        $output = "<a class='bubble' href='{$lastTrack['url']}' target='_blank'>{$lastTrack['artist']} - {$lastTrack['name']}</a>";
    }

    return $output;
}
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode');

// Last.fm Top Artists Shortcode
function nowscr_lastfm_top_artists_shortcode($atts)
{
    $atts = shortcode_atts(['period' => '7day'], $atts);
    $data = nowscrobbling_fetch_lastfm_top_data('topartists', get_option('top_artists_count', 5), $atts['period']);

    if (!$data || empty($data['topartists']['artist'])) {
        return "<em>Keine Top-Künstler gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['topartists']['artist'], function ($artist) {
        return "<a class='bubble' href='" . esc_url($artist['url']) . "'>" . esc_html($artist['name']) . "</a>";
    });
}
add_shortcode('nowscr_lastfm_top_artists', 'nowscr_lastfm_top_artists_shortcode');

// Last.fm Top Albums Shortcode
function nowscr_lastfm_top_albums_shortcode($atts)
{
    $atts = shortcode_atts(['period' => '7day'], $atts);
    $data = nowscrobbling_fetch_lastfm_top_data('topalbums', get_option('top_albums_count', 5), $atts['period']);

    if (!$data || empty($data['topalbums']['album'])) {
        return "<em>Keine Top-Alben gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['topalbums']['album'], function ($album) {
        return "<a class='bubble' href='" . esc_url($album['url']) . "'>" . esc_html($album['artist']['name']) . " - " . esc_html($album['name']) . "</a>";
    });
}
add_shortcode('nowscr_lastfm_top_albums', 'nowscr_lastfm_top_albums_shortcode');

// Last.fm Top Tracks Shortcode
function nowscr_lastfm_top_tracks_shortcode($atts)
{
    $atts = shortcode_atts(['period' => '7day'], $atts);
    $data = nowscrobbling_fetch_lastfm_top_data('toptracks', get_option('top_tracks_count', 5), $atts['period']);

    if (!$data || empty($data['toptracks']['track'])) {
        return "<em>Keine Top-Titel gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['toptracks']['track'], function ($track) {
        return "<a class='bubble' href='" . esc_url($track['url']) . "'>" . esc_html($track['artist']['name']) . " - " . esc_html($track['name']) . "</a>";
    });
}
add_shortcode('nowscr_lastfm_top_tracks', 'nowscr_lastfm_top_tracks_shortcode');

// Last.fm Loved Tracks Shortcode
function nowscr_lastfm_lovedtracks_shortcode()
{
    $data = nowscrobbling_fetch_lastfm_top_data('lovedtracks', get_option('lovedtracks_count', 5), 'overall');

    if (!$data || empty($data['lovedtracks']['track'])) {
        return "<em>Keine Lieblingslieder gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['lovedtracks']['track'], function ($track) {
        return "<a class='bubble' href='" . esc_url($track['url']) . "'>" . esc_html($track['artist']['name']) . " - " . esc_html($track['name']) . "</a>";
    });
}
add_shortcode('nowscr_lastfm_lovedtracks', 'nowscr_lastfm_lovedtracks_shortcode');

// Last.fm Top Tags Shortcode
function nowscr_lastfm_top_tags_shortcode($atts)
{
    $atts = shortcode_atts(['period' => '7day'], $atts);
    $data = nowscrobbling_fetch_lastfm_top_data('toptags', get_option('top_tags_count', 5), $atts['period']);

    if (!$data || empty($data['toptags']['tag'])) {
        return "<em>Keine Top-Tags gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['toptags']['tag'], function ($tag) {
        return "<a class='bubble' href='" . esc_url($tag['url']) . "'>" . esc_html($tag['name']) . "</a>";
    });
}
add_shortcode('nowscr_lastfm_top_tags', 'nowscr_lastfm_top_tags_shortcode');

// Trakt Indicator Shortcode
function nowscr_trakt_indicator_shortcode()
{
    $activities = nowscrobbling_fetch_trakt_activities();
    if (!$activities) {
        return "<em>Keine Aktivitäten gefunden.</em>";
    }

    $lastActivity = reset($activities);
    $watchedAt = strtotime($lastActivity['watched_at']);
    $dateTime = new DateTime("@$watchedAt");
    $dateTime->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));

    return 'Zuletzt gesehen: ' . $dateTime->format(get_option('date_format') . ' ' . get_option('time_format'));
}
add_shortcode('nowscr_trakt_indicator', 'nowscr_trakt_indicator_shortcode');

// Trakt History Shortcode
function nowscr_trakt_history_shortcode()
{
    $activities = nowscrobbling_fetch_trakt_activities();
    if (!$activities) {
        return "<em>Keine Aktivitäten gefunden.</em>";
    }

    $output = '';
    foreach ($activities as $activity) {
        $type = $activity['type'];
        $title = $type == 'movie' ? "{$activity['movie']['title']} ({$activity['movie']['year']})" : "{$activity['show']['title']} - S{$activity['episode']['season']}E{$activity['episode']['number']}: {$activity['episode']['title']}";
        $link = $type == 'movie' ? "https://trakt.tv/movies/{$activity['movie']['ids']['slug']}" : "https://trakt.tv/shows/{$activity['show']['ids']['slug']}/seasons/{$activity['episode']['season']}/episodes/{$activity['episode']['number']}";
        $output .= "<span class='bubble'><a href='{$link}' target='_blank'>{$title}</a></span> ";
    }
    return $output;
}
add_shortcode('nowscr_trakt_history', 'nowscr_trakt_history_shortcode');

// Trakt Last Movie Shortcode
function nowscr_trakt_last_movie_shortcode()
{
    $movies = nowscrobbling_get_or_set_transient('my_trakt_tv_movies', function () {
        return nowscrobbling_fetch_trakt_data('users/' . get_option('trakt_user') . '/history/movies', [
            'limit' => get_option('last_movies_count', 3)
        ]);
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (!$movies) {
        return "<em>Keine Filme gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($movies, function ($movie) {
        return "<a class='bubble' href='https://trakt.tv/movies/{$movie['movie']['ids']['slug']}'>{$movie['movie']['title']} ({$movie['movie']['year']})</a>";
    });
}
add_shortcode('nowscr_trakt_last_movie', 'nowscr_trakt_last_movie_shortcode');

// Trakt Last Movie with Rating Shortcode
function nowscr_trakt_last_movie_with_rating_shortcode()
{
    $movies = nowscrobbling_get_or_set_transient('my_trakt_tv_movies_with_ratings', function () {
        $user = get_option('trakt_user');
        return [
            'movies' => nowscrobbling_fetch_trakt_data("users/$user/history/movies", ['limit' => get_option('last_movies_count', 3)]),
            'ratings' => nowscrobbling_fetch_trakt_data("users/$user/ratings/movies")
        ];
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (!$movies['movies']) {
        return "<em>Keine Filme gefunden.</em>";
    }

    $ratings = [];
    foreach ($movies['ratings'] as $rating) {
        $ratings[$rating['movie']['ids']['trakt']] = $rating['rating'];
    }

    return nowscrobbling_generate_shortcode_output($movies['movies'], function ($movie) use ($ratings) {
        $rating = $ratings[$movie['movie']['ids']['trakt']] ?? '';
        $rating_text = $rating ? " <span style='font-style: italic; font-weight: bold;'>$rating/10</span>" : '';
        return "<span class='bubble'><a href='https://trakt.tv/movies/{$movie['movie']['ids']['slug']}' target='_blank'>{$movie['movie']['title']} ({$movie['movie']['year']})</a>{$rating_text}</span>";
    });
}
add_shortcode('nowscr_trakt_last_movie_with_rating', 'nowscr_trakt_last_movie_with_rating_shortcode');

// Trakt Last Show Shortcode
function nowscr_trakt_last_show_shortcode()
{
    $shows = nowscrobbling_get_or_set_transient('my_trakt_tv_shows', function () {
        return nowscrobbling_fetch_trakt_data('users/' . get_option('trakt_user') . '/history/shows', [
            'limit' => get_option('last_shows_count', 3)
        ]);
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (!$shows) {
        return "<em>Keine Serien gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($shows, function ($show) {
        return "<a class='bubble' href='https://trakt.tv/shows/{$show['show']['ids']['slug']}' target='_blank'>{$show['show']['title']} ({$show['show']['year']})</a>";
    });
}
add_shortcode('nowscr_trakt_last_show', 'nowscr_trakt_last_show_shortcode');

// Trakt Last Episode Shortcode
function nowscr_trakt_last_episode_shortcode()
{
    $episodes = nowscrobbling_get_or_set_transient('my_trakt_tv_episodes', function () {
        return nowscrobbling_fetch_trakt_data('users/' . get_option('trakt_user') . '/history/episodes', [
            'limit' => get_option('last_episodes_count', 3)
        ]);
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (!$episodes) {
        return "<em>Keine Episoden gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($episodes, function ($episode) {
        $season = $episode['episode']['season'];
        $episodeNumber = $episode['episode']['number'];
        $title = "S{$season}E{$episodeNumber}: {$episode['episode']['title']}";
        $url = "https://trakt.tv/shows/{$episode['show']['ids']['slug']}/seasons/{$season}/episodes/{$episodeNumber}";
        return "<a class='bubble' href='{$url}' target='_blank'>{$title}</a>";
    });    
}
add_shortcode('nowscr_trakt_last_episode', 'nowscr_trakt_last_episode_shortcode');

?>