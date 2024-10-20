<?php

/**
 * Version:             1.2.5
 * File:                nowscrobbling/includes/shortcodes.php
 */

// Ensure the script is not accessed directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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


// LAST.FM //


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
function nowscr_lastfm_history_shortcode($atts)
{
    // Standardwerte setzen, einschließlich 'max_length'
    $atts = shortcode_atts([
        'max_length' => 45  // Standardwert für maximale Zeichenlänge
    ], $atts);

    $scrobbles = nowscrobbling_fetch_lastfm_scrobbles();
    if (isset($scrobbles['error'])) {
        return "<em>" . esc_html($scrobbles['error']) . "</em>";
    }

    $output = '';
    foreach ($scrobbles as $track) {
        if ($track['nowplaying']) {
            $nowPlaying = '<img src="' . esc_url(plugins_url('../public/images/nowplaying.gif', __FILE__)) . '" alt="NOW PLAYING" /> ';
            $url = esc_url($track['url']);
            $artist = esc_html($track['artist']);
            $name = esc_html($track['name']);
            $title_attr = esc_attr("{$name} von {$artist} auf last.fm");

            // Text kürzen basierend auf 'max_length'
            $fullText = "{$artist} - {$name}";
            $isTruncated = (strlen($fullText) > $atts['max_length']);
            $shortText = $isTruncated ? substr($fullText, 0, $atts['max_length'] - 3) . '…' : $fullText;

            $class = $isTruncated ? 'bubble truncated' : 'bubble';
            $output = "<a class='{$class}' href='{$url}' title='{$title_attr}' target='_blank'>{$nowPlaying}{$shortText}</a>";
            break;
        }
    }

    if (empty($output) && !empty($scrobbles)) {
        $lastTrack = $scrobbles[0];
        $url = esc_url($lastTrack['url']);
        $artist = esc_html($lastTrack['artist']);
        $name = esc_html($lastTrack['name']);
        $title_attr = esc_attr("{$name} von {$artist} auf last.fm");

        // Text kürzen basierend auf 'max_length'
        $fullText = "{$artist} - {$name}";
        $isTruncated = (strlen($fullText) > $atts['max_length']);
        $shortText = $isTruncated ? substr($fullText, 0, $atts['max_length'] - 3) . '…' : $fullText;

        $class = $isTruncated ? 'bubble truncated' : 'bubble';
        $output = "<a class='{$class}' href='{$url}' title='{$title_attr}' target='_blank'>{$shortText}</a>";
    }

    return $output;
}
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode');


// Last.fm Top Artists Shortcode
function nowscr_lastfm_top_artists_shortcode($atts)
{
    // Standardwerte setzen, einschließlich 'max_length'
    $atts = shortcode_atts([
        'period' => '7day',  // Standardwert für den Zeitraum
        'max_length' => 15   // Standardwert für maximale Zeichenlänge
    ], $atts);

    $data = nowscrobbling_fetch_lastfm_top_data('topartists', get_option('top_artists_count', 5), $atts['period']);

    if (!$data || empty($data['topartists']['artist'])) {
        return "<em>Keine Top-Künstler gefunden.</em>";
    }

    // Text kürzen basierend auf 'max_length'
    return nowscrobbling_generate_shortcode_output($data['topartists']['artist'], function ($artist) use ($atts) {
        $name = esc_html($artist['name']);
        $url = esc_url($artist['url']);

        // Prüfen, ob der Künstlername gekürzt wird
        $isTruncated = (strlen($name) > $atts['max_length']);
        $shortName = $isTruncated ? substr($name, 0, $atts['max_length'] - 3) . '…' : $name;

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$name auf last.fm'>$shortName</a>";
    });
}
add_shortcode('nowscr_lastfm_top_artists', 'nowscr_lastfm_top_artists_shortcode');


// Last.fm Top Albums Shortcode
function nowscr_lastfm_top_albums_shortcode($atts)
{
    $atts = shortcode_atts([
        'period' => '7day', 
        'max_length' => 45
    ], $atts);

    $data = nowscrobbling_fetch_lastfm_top_data('topalbums', get_option('top_albums_count', 5), $atts['period']);

    if (!$data || empty($data['topalbums']['album'])) {
        return "<em>Keine Top-Alben gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['topalbums']['album'], function ($album) use ($atts) {
        $artistName = esc_html($album['artist']['name']);
        $albumName = esc_html($album['name']);
        $url = esc_url($album['url']);
        $fullText = "{$artistName} - {$albumName}";

        // Prüfen, ob der Text gekürzt wird
        $isTruncated = (strlen($fullText) > $atts['max_length']);
        $shortText = $isTruncated ? substr($fullText, 0, $atts['max_length'] - 3) . '…' : $fullText;

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$albumName von $artistName auf last.fm'>$shortText</a>";
    });
}
add_shortcode('nowscr_lastfm_top_albums', 'nowscr_lastfm_top_albums_shortcode');


// Last.fm Top Tracks Shortcode
function nowscr_lastfm_top_tracks_shortcode($atts)
{
    // Standardwerte setzen, einschließlich 'max_length'
    $atts = shortcode_atts([
        'period' => '7day',  // Standardwert für den Zeitraum
        'max_length' => 45   // Standardwert für maximale Zeichenlänge
    ], $atts);

    $data = nowscrobbling_fetch_lastfm_top_data('toptracks', get_option('top_tracks_count', 5), $atts['period']);

    if (!$data || empty($data['toptracks']['track'])) {
        return "<em>Keine Top-Titel gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['toptracks']['track'], function ($track) use ($atts) {
        $artistName = esc_html($track['artist']['name']);
        $trackName = esc_html($track['name']);
        $url = esc_url($track['url']);
        $fullText = "{$artistName} - {$trackName}";

        // Prüfen, ob der Text (Künstler + Trackname) gekürzt wird
        $isTruncated = (strlen($fullText) > $atts['max_length']);
        $shortText = $isTruncated ? substr($fullText, 0, $atts['max_length'] - 3) . '…' : $fullText;

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$trackName von $artistName auf last.fm'>$shortText</a>";
    });
}
add_shortcode('nowscr_lastfm_top_tracks', 'nowscr_lastfm_top_tracks_shortcode');

// Last.fm Loved Tracks Shortcode
function nowscr_lastfm_lovedtracks_shortcode($atts)
{
    // Standardwerte setzen, einschließlich 'max_length'
    $atts = shortcode_atts([
        'max_length' => 45   // Standardwert für maximale Zeichenlänge
    ], $atts);

    $data = nowscrobbling_fetch_lastfm_top_data('lovedtracks', get_option('lovedtracks_count', 5), 'overall');

    if (!$data || empty($data['lovedtracks']['track'])) {
        return "<em>Keine Lieblingslieder gefunden.</em>";
    }

    return nowscrobbling_generate_shortcode_output($data['lovedtracks']['track'], function ($track) use ($atts) {
        $artistName = esc_html($track['artist']['name']);
        $trackName = esc_html($track['name']);
        $url = esc_url($track['url']);
        $fullText = "{$artistName} - {$trackName}";

        // Prüfen, ob der Text (Künstler + Trackname) gekürzt wird
        $isTruncated = (strlen($fullText) > $atts['max_length']);
        $shortText = $isTruncated ? substr($fullText, 0, $atts['max_length'] - 3) . '…' : $fullText;

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$trackName von $artistName auf last.fm'>$shortText</a>";
    });
}
add_shortcode('nowscr_lastfm_lovedtracks', 'nowscr_lastfm_lovedtracks_shortcode');


// TRAKT //


function nowscrobbling_format_output($title, $year, $url, $rating = '', $rewatch = '')
{
    // Escape variables
    $escaped_title = esc_html($title);
    $escaped_year = esc_html($year);
    $escaped_url = esc_url($url);
    $escaped_rating = esc_html($rating);
    $escaped_rewatch = esc_html($rewatch);

    // Define individual elements with title attributes
    $elements = [
        'title' => $escaped_title,
        'year' => $year ? " <span title='" . esc_attr("Die Veröffentlichung von $title war im Jahr $year") . "' style='font-style: italic; opacity: 0.66;'>($escaped_year)</span>" : '',
        'rating' => $rating ? " <span title='" . esc_attr("Ich bewerte $title mit $rating von 10") . "'><span style='font-size: 1rem;'>★</span>$escaped_rating</span>" : '',
        'rewatch' => $rewatch ? "<span title='" . esc_attr("Ich schaute $title zum $rewatch. mal") . "' style='font-style: italic; opacity: 0.33;'>#$escaped_rewatch</span>" : ''
    ];

    // Escape the title attribute for the <a> element
    $link_title_attr = esc_attr("$title auf Trakt");

    // Customize the order and separators of elements here
    return "<a class='bubble' href='{$escaped_url}' title='{$link_title_attr}' target='_blank'>{$elements['title']}{$elements['year']}{$elements['rewatch']}{$elements['rating']}</a>";
}

/**
 * Trakt Indicator Shortcode
 */
function nowscr_trakt_indicator_shortcode() {
    // Check currently watching item
    $watching = nowscrobbling_fetch_trakt_watching();

    if (!empty($watching)) {
        $type = $watching['type'];
        $title = $type === 'movie'
            ? "{$watching['movie']['title']} ({$watching['movie']['year']})"
            : "{$watching['show']['title']} - S{$watching['episode']['season']}E{$watching['episode']['number']}: {$watching['episode']['title']}";
        return '<strong>Scrobbelt gerade</strong>';
    }

    $activities = nowscrobbling_get_or_set_transient('my_trakt_tv_activities', function () {
        return nowscrobbling_fetch_trakt_activities();
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (empty($activities)) {
        return '<em>Keine kürzlichen Aktivitäten gefunden</em>';
    }

    $lastActivity = reset($activities);
    $lastActivityDate = $lastActivity['watched_at'];
    $date = new DateTime($lastActivityDate);
    $date->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));
    $formatted_date = esc_html($date->format(get_option('date_format') . ' ' . get_option('time_format')));

    return 'Zuletzt geschaut: ' . $formatted_date;
}
add_shortcode('nowscr_trakt_indicator', 'nowscr_trakt_indicator_shortcode');


// Trakt History Shortcode with optional Year, Rating, and Rewatch display
function nowscr_trakt_history_shortcode($atts) {
    // Set default attributes and merge with user attributes
    $atts = shortcode_atts([
        'show_year' => false,
        'show_rewatch' => false,
        'show_rating' => false,
    ], $atts);

    // Check currently watching item
    $watching = nowscrobbling_fetch_trakt_watching();
    
    if (!empty($watching)) {
        $type = $watching['type'];
        $id = $watching[$type]['ids']['trakt'];
        $rating = $atts['show_rating'] ? nowscrobbling_fetch_specific_trakt_rating($type, $id) : '';
        $rating_text = $rating ? "<span class='rating'>★{$rating}/10</span>" : '';
        $title = $type === 'movie'
            ? $watching['movie']['title']
            : "{$watching['show']['title']} - S{$watching['episode']['season']}E{$watching['episode']['number']}: {$watching['episode']['title']}";
        $year = $atts['show_year'] && $type === 'movie' ? "{$watching['movie']['year']}" : '';
        $link = $type === 'movie'
            ? "https://trakt.tv/movies/{$watching['movie']['ids']['slug']}"
            : "https://trakt.tv/shows/{$watching['show']['ids']['slug']}/seasons/{$watching['episode']['season']}/episodes/{$watching['episode']['number']}";
        $rewatch = $atts['show_rewatch'] ? nowscrobbling_get_rewatch_count($id, $type === 'movie' ? 'movies' : 'episodes') : '';
        $rewatch_text = $rewatch > 1 ? "{$rewatch}" : '';

        $nowPlaying = '<img src="' . plugins_url('../public/images/nowplaying.gif', __FILE__) . '" alt="NOW PLAYING" /> ';
        return "<a class='bubble' href='$link' title='$title' target='_blank'>{$title}{$year}{$rating_text}{$rewatch_text}</a>";
    }

    // No currently watching item, show last activity
    $activities = nowscrobbling_get_or_set_transient('my_trakt_tv_history', function () {
        return nowscrobbling_fetch_trakt_activities();
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (empty($activities)) {
        return '<em>Keine kürzlichen Aktivitäten gefunden</em>';
    }

    // Last activity output
    $lastActivity = reset($activities);
    $type = $lastActivity['type'];
    $id = $lastActivity[$type]['ids']['trakt'];
    $rating = $atts['show_rating'] ? nowscrobbling_fetch_specific_trakt_rating($type, $id) : '';
    $rating_text = $rating ? "<span class='rating'>★{$rating}/10</span>" : '';
    $title = $type === 'movie'
        ? $lastActivity['movie']['title']
        : "{$lastActivity['show']['title']} - S{$lastActivity['episode']['season']}E{$lastActivity['episode']['number']}: {$lastActivity['episode']['title']}";
    $year = $atts['show_year'] && $type === 'movie' ? "{$lastActivity['movie']['year']}" : '';
    $link = $type === 'movie'
        ? "https://trakt.tv/movies/{$lastActivity['movie']['ids']['slug']}"
        : "https://trakt.tv/shows/{$lastActivity['show']['ids']['slug']}/seasons/{$lastActivity['episode']['season']}/episodes/{$lastActivity['episode']['number']}";
    $rewatch = $atts['show_rewatch'] ? nowscrobbling_get_rewatch_count($id, $type === 'movie' ? 'movies' : 'episodes') : '';
    $rewatch_text = $rewatch > 1 ? "{$rewatch}" : '';

    return nowscrobbling_format_output($title, $year, $link, $rating_text, $rewatch_text);
}
add_shortcode('nowscr_trakt_history', 'nowscr_trakt_history_shortcode');

// Fetch specific Trakt rating for movies, shows, or episodes
function nowscrobbling_fetch_specific_trakt_rating($type, $id)
{
    $user = get_option('trakt_user');
    switch ($type) {
        case 'movie':
            $rating_data = nowscrobbling_fetch_trakt_data("users/$user/ratings/movies/$id");
            break;
        case 'show':
            $rating_data = nowscrobbling_fetch_trakt_data("users/$user/ratings/shows/$id");
            break;
        case 'episode':
            $rating_data = nowscrobbling_fetch_trakt_data("users/$user/ratings/episodes/$id");
            break;
        default:
            return null;
    }
    return $rating_data['rating'] ?? null;
}

// Trakt Last Movie Shortcode with optional Year, Rating, and Rewatch display
function nowscr_trakt_last_movie_shortcode($atts) {
    // Set default attributes and merge with user attributes
    $atts = shortcode_atts([
        'show_year' => false,
        'show_rewatch' => false,
        'show_rating' => false,
    ], $atts);

    $movies = nowscrobbling_get_or_set_transient('my_trakt_tv_movies_with_ratings', function () {
        $user = get_option('trakt_user');
        return [
            'movies' => nowscrobbling_fetch_trakt_data("users/$user/history/movies", ['limit' => get_option('last_movies_count', 3)]),
            'ratings' => nowscrobbling_fetch_trakt_data("users/$user/ratings/movies")
        ];
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (!$movies['movies']) {
        return '<em>' . esc_html__('Keine Filme gefunden.', 'nowscrobbling') . '</em>';
    }

    $ratings = [];
    foreach ($movies['ratings'] as $rating) {
        $ratings[$rating['movie']['ids']['trakt']] = $rating['rating'];
    }

    // Initialize an array to store rewatch counts
    $rewatch_counts = [];

    // Get rewatch counts for each movie
    foreach ($movies['movies'] as $movie) {
        $id = $movie['movie']['ids']['trakt'];
        $rewatch_counts[$id] = nowscrobbling_fetch_trakt_data("users/" . get_option('trakt_user') . "/history/movies/{$id}");
    }

    // Track the position in the history
    $history_positions = [];

    // Format the output
    return nowscrobbling_generate_shortcode_output($movies['movies'], function ($movie) use ($ratings, $rewatch_counts, &$history_positions, $atts) {
        $id = $movie['movie']['ids']['trakt'];
        $rating = $atts['show_rating'] ? ($ratings[$id] ?? '') : '';
        $rating_text = $rating ? "{$rating}" : '';
        $rewatch_total = count($rewatch_counts[$id]);
        $title = $movie['movie']['title'];
        $year = $atts['show_year'] ? "{$movie['movie']['year']}" : '';
        $url = "https://trakt.tv/movies/{$movie['movie']['ids']['slug']}";

        // Adjust the rewatch count based on the position in the history
        if (!isset($history_positions[$id])) {
            $history_positions[$id] = 0;
        }
        $rewatch = $atts['show_rewatch'] ? $rewatch_total - $history_positions[$id] : '';
        $rewatch_text = $rewatch > 1 ? "{$rewatch}" : '';

        // Increment the position for the next movie in the history
        $history_positions[$id]++;

        return nowscrobbling_format_output($title, $year, $url, $rating_text, $rewatch_text);
    });
}
add_shortcode('nowscr_trakt_last_movie', 'nowscr_trakt_last_movie_shortcode');


// Trakt Last Show Shortcode with optional Year, Rating, and Rewatch display
function nowscr_trakt_last_show_shortcode($atts) {
    // Set default attributes and merge with user attributes
    $atts = shortcode_atts([
        'show_year' => false,
        'show_rewatch' => false,
        'show_rating' => false,
    ], $atts);

    $shows = nowscrobbling_get_or_set_transient('my_trakt_tv_shows_with_ratings', function () {
        $user = get_option('trakt_user');
        return [
            'shows' => nowscrobbling_fetch_trakt_data("users/$user/history/shows", ['limit' => get_option('last_shows_count', 3)]),
            'ratings' => nowscrobbling_fetch_trakt_data("users/$user/ratings/shows"),
            'completed' => nowscrobbling_fetch_trakt_data("users/$user/watched/shows")
        ];
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (!$shows['shows']) {
        return "<em>Keine Serien gefunden.</em>";
    }

    $ratings = [];
    foreach ($shows['ratings'] as $rating) {
        $ratings[$rating['show']['ids']['trakt']] = $rating['rating'];
    }

    $completed_shows = [];
    foreach ($shows['completed'] as $completed_show) {
        $show_id = $completed_show['show']['ids']['trakt'];
        $completed_episodes = array_reduce($completed_show['seasons'], function($carry, $season) {
            return $carry + (isset($season['episode_count']) ? $season['episode_count'] : 0);
        }, 0);
        $watched_episodes = array_reduce($completed_show['seasons'], function($carry, $season) {
            return $carry + (isset($season['completed']) ? $season['completed'] : 0);
        }, 0);

        if ($watched_episodes === $completed_episodes) {
            $completed_shows[$show_id] = true;
        }
    }

    // Initialize an array to store rewatch counts
    $rewatch_counts = [];

    // Get rewatch counts for each show
    foreach ($shows['shows'] as $index => $show) {
        $id = $show['show']['ids']['trakt'];
        $rewatch_counts[$id] = nowscrobbling_fetch_trakt_data("users/" . get_option('trakt_user') . "/history/shows/{$id}");
    }

    // Track the position in the history
    $history_positions = [];

    return nowscrobbling_generate_shortcode_output($shows['shows'], function ($show) use ($ratings, $completed_shows, $rewatch_counts, &$history_positions, $atts) {
        $id = $show['show']['ids']['trakt'];
        $rating = $atts['show_rating'] ? ($ratings[$id] ?? '') : '';
        $rating_text = $rating ? "{$rating}" : '';
        $rewatch_total = count($rewatch_counts[$id]);
        $rewatch_text = '';

        if (isset($completed_shows[$id])) {
            if (!isset($history_positions[$id])) {
                $history_positions[$id] = 0;
            }
            $rewatch = $atts['show_rewatch'] ? $rewatch_total - $history_positions[$id] : '';
            $rewatch_text = $rewatch > 1 ? "{$rewatch}" : '';

            // Increment the position for the next show in the history
            $history_positions[$id]++;
        }

        $title = $show['show']['title'];
        $year = $atts['show_year'] ? "{$show['show']['year']}" : '';
        $url = "https://trakt.tv/shows/{$show['show']['ids']['slug']}";

        return nowscrobbling_format_output($title, $year, $url, $rating_text, $rewatch_text);
    });
}
add_shortcode('nowscr_trakt_last_show', 'nowscr_trakt_last_show_shortcode');


// Trakt Last Episode Shortcode with optional Year, Rating, and Rewatch display
function nowscr_trakt_last_episode_shortcode($atts) {
    // Set default attributes and merge with user attributes
    $atts = shortcode_atts([
        'show_year' => false,
        'show_rewatch' => false,
        'show_rating' => false,
    ], $atts);

    $episodes = nowscrobbling_get_or_set_transient('my_trakt_tv_episodes_with_ratings', function () {
        $user = get_option('trakt_user');
        return [
            'episodes' => nowscrobbling_fetch_trakt_data("users/$user/history/episodes", ['limit' => get_option('last_episodes_count', 3)]),
            'ratings' => nowscrobbling_fetch_trakt_data("users/$user/ratings/episodes")
        ];
    }, get_option('trakt_cache_duration', 5) * MINUTE_IN_SECONDS);

    if (!$episodes['episodes']) {
        return "<em>Keine Episoden gefunden.</em>";
    }

    $ratings = [];
    foreach ($episodes['ratings'] as $rating) {
        $ratings[$rating['episode']['ids']['trakt']] = $rating['rating'];
    }

    // Initialize an array to store rewatch counts
    $rewatch_counts = [];

    // Get rewatch counts for each episode
    foreach ($episodes['episodes'] as $index => $episode) {
        $id = $episode['episode']['ids']['trakt'];
        $rewatch_counts[$id] = nowscrobbling_fetch_trakt_data("users/" . get_option('trakt_user') . "/history/episodes/{$id}");
    }

    return nowscrobbling_generate_shortcode_output($episodes['episodes'], function ($episode) use ($ratings, $rewatch_counts, $atts) {
        $season = $episode['episode']['season'];
        $episodeNumber = $episode['episode']['number'];
        $title = "S{$season}E{$episodeNumber}: {$episode['episode']['title']}";
        $url = "https://trakt.tv/shows/{$episode['show']['ids']['slug']}/seasons/{$season}/episodes/{$episodeNumber}";
        $id = $episode['episode']['ids']['trakt'];
        $rating = $atts['show_rating'] ? ($ratings[$id] ?? '') : '';
        $rating_text = $rating ? "{$rating}" : '';
        $rewatch_total = count($rewatch_counts[$id]);

        // Adjust the rewatch count based on the position in the history
        static $rewatch_offset = 0;
        $rewatch = $atts['show_rewatch'] ? $rewatch_total - $rewatch_offset : '';
        $rewatch_text = $rewatch > 1 ? "{$rewatch}" : '';

        // Increment the offset for the next episode in the history
        $rewatch_offset++;

        return nowscrobbling_format_output($title, '', $url, $rating_text, $rewatch_text);
    });
}
add_shortcode('nowscr_trakt_last_episode', 'nowscr_trakt_last_episode_shortcode');

?>