<?php
/**
 * File:                nowscrobbling/includes/shortcodes.php
 */

// Ensure the script is not accessed directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// -----------------------------------------------------------------------------
// Helpers for SSR: hash + wrapper
// -----------------------------------------------------------------------------
function nowscrobbling_make_hash( $data ) {
    // Build a stable short hash from structured data or HTML
    $basis = is_string( $data ) ? $data : wp_json_encode( $data );
    return substr( md5( (string) $basis ), 0, 12 );
}

function nowscrobbling_wrap_output( $slug, $html, $hash, $nowplaying = false, $attrs = [], $tag = 'div' ) {
    $slug_attr = esc_attr( $slug );
    $hash_attr = esc_attr( $hash );
    $np_attr   = $nowplaying ? ' data-ns-nowplaying="1"' : '';
    $attrs_json = '';
    if ( ! empty( $attrs ) && is_array( $attrs ) ) {
        $attrs_json = ' data-ns-attrs="' . esc_attr( wp_json_encode( $attrs ) ) . '"';
    }
    $tag = in_array( strtolower( $tag ), [ 'div', 'span' ], true ) ? strtolower( $tag ) : 'div';
    $extra_class = $tag !== 'div' ? ' n-inline' : '';
    return '<' . $tag . ' class="nowscrobbling' . $extra_class . '" data-nowscrobbling-shortcode="' . $slug_attr . '" data-ns-hash="' . $hash_attr . '"' . $np_attr . $attrs_json . '>' . $html . '</' . $tag . '>';
}

function nowscrobbling_generate_shortcode_output($items, $format_callback) {
    if (!is_array($items) || empty($items)) {
        return '';
    }
    $formatted_items = array_map($format_callback, $items);
    if (count($formatted_items) > 1) {
        $last_item = array_pop($formatted_items);
        return implode(' ', $formatted_items) . ' und ' . $last_item;
    }
    return $formatted_items[0] ?? '';
}

// -----------------------------------------------------------------------------
// LAST.FM SHORTCODES
// -----------------------------------------------------------------------------

// Last.fm Indicator Shortcode
function nowscr_lastfm_indicator_shortcode() {
    // Use correct method name and existing fetchers; cache very shortly for SSR
    $now = nowscrobbling_get_or_set_transient('my_lastfm_now_playing', function () {
        return nowscrobbling_fetch_lastfm_data('getrecenttracks', ['limit' => 1]);
    }, max( 30, (int) get_option('lastfm_cache_duration', 1) * MINUTE_IN_SECONDS ) );

    if (!$now || empty($now['recenttracks']['track'])) {
        $html = '<em>Keine kürzlichen Tracks gefunden.</em>';
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_lastfm_indicator', $html, $hash, false, [], 'span');
    }

    $currentTrack = $now['recenttracks']['track'][0];
    $nowPlaying = isset($currentTrack['@attr']['nowplaying']) && $currentTrack['@attr']['nowplaying'] === 'true';

    if ($nowPlaying) {
        // Indicator: concise text only; GIFs sind History-Shortcodes vorbehalten
        $html = '<strong>Scrobbelt gerade</strong>';
        $hash = nowscrobbling_make_hash([ 'state' => 'nowplaying' ]);
    return nowscrobbling_wrap_output('nowscr_lastfm_indicator', $html, $hash, true, [], 'span');
    } else {
        $lastPlayed = $currentTrack['date']['#text'] ?? '';
        if ($lastPlayed) {
            $ts = strtotime($lastPlayed);
            $dt = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $ts );
            $html = 'Zuletzt gehört: ' . esc_html($dt);
        } else {
            $html = 'Zuletzt gehört: Unbekannt';
        }
        $hash = nowscrobbling_make_hash([ 'state' => 'lastplayed', 'time' => $lastPlayed ]);
        return nowscrobbling_wrap_output('nowscr_lastfm_indicator', $html, $hash, false, [], 'span');
    }
}
add_shortcode('nowscr_lastfm_indicator', 'nowscr_lastfm_indicator_shortcode');

// Last.fm History Shortcode
function nowscr_lastfm_history_shortcode($atts) {
    // Standardwerte setzen, einschließlich 'max_length'
    $atts = shortcode_atts([
        'max_length' => 45  // Standardwert für maximale Zeichenlänge
    ], $atts);

    $scrobbles = nowscrobbling_fetch_lastfm_scrobbles('lastfm_history');
    if (isset($scrobbles['error'])) {
        $html = "<em>" . esc_html($scrobbles['error']) . "</em>";
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_lastfm_history', $html, $hash, false, [], 'span');
    }

    $output = '';
    $isNowPlaying = false;
    foreach ($scrobbles as $track) {
        if ($track['nowplaying']) {
            $nowPlaying = '<img src="' . esc_url( ( defined('NOWSCROBBLING_URL') ? NOWSCROBBLING_URL : plugins_url('/', dirname(__DIR__)) ) . 'public/images/nowplaying.gif' ) . '" alt="NOW PLAYING" loading="lazy" decoding="async" /> ';
            $url = esc_url($track['url']);
            $artist = esc_html($track['artist']);
            $name = esc_html($track['name']);
            $title_attr = esc_attr("{$name} von {$artist} auf last.fm");

            // Text kürzen basierend auf 'max_length' (multibyte-safe)
            $fullText = "{$artist} - {$name}";
            $shortText = nowscrobbling_mb_truncate($fullText, (int) $atts['max_length']);
            $isTruncated = ($shortText !== $fullText);

            $class = $isTruncated ? 'bubble truncated' : 'bubble';
            $output = "<a class='{$class}' href='{$url}' title='{$title_attr}' target='_blank'>{$nowPlaying}{$shortText}</a>";
            $isNowPlaying = true;
            break;
        }
    }

    if (empty($output) && !empty($scrobbles)) {
        $lastTrack = $scrobbles[0];
        $url = esc_url($lastTrack['url']);
        $artist = esc_html($lastTrack['artist']);
        $name = esc_html($lastTrack['name']);
        $title_attr = esc_attr("{$name} von {$artist} auf last.fm");

        // Text kürzen basierend auf 'max_length' (multibyte-safe)
        $fullText = "{$artist} - {$name}";
        $shortText = nowscrobbling_mb_truncate($fullText, (int) $atts['max_length']);
        $isTruncated = ($shortText !== $fullText);

        $class = $isTruncated ? 'bubble truncated' : 'bubble';
        $output = "<a class='{$class}' href='{$url}' title='{$title_attr}' target='_blank'>{$shortText}</a>";
    }

    $hash = nowscrobbling_make_hash([ 'tracks' => array_slice($scrobbles, 0, 3) ]);
    return nowscrobbling_wrap_output('nowscr_lastfm_history', $output, $hash, $isNowPlaying, $atts, 'span');
}
add_shortcode('nowscr_lastfm_history', 'nowscr_lastfm_history_shortcode');

// Last.fm Top Artists Shortcode
function nowscr_lastfm_top_artists_shortcode($atts) {
    // Standardwerte setzen, einschließlich 'max_length' und 'limit' (alias 'count')
    $atts = shortcode_atts([
        'period' => '7day',
        'max_length' => 15,
        'limit' => '',
        'count' => '',
    ], $atts);

    $configured_default = (int) get_option('top_artists_count', 5);
    $limit = $atts['limit'] !== '' ? (int) $atts['limit'] : ($atts['count'] !== '' ? (int) $atts['count'] : $configured_default);
    $limit = max(1, $limit);

    $data = nowscrobbling_fetch_lastfm_top_data('topartists', $limit, $atts['period'], 'lastfm_top_artists');

    if (!$data || empty($data['topartists']['artist'])) {
        $html = "<em>Keine Top-Künstler gefunden.</em>";
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_lastfm_top_artists', $html, $hash, false, [], 'span');
    }

    // Text kürzen basierend auf 'max_length'
    $output = nowscrobbling_generate_shortcode_output($data['topartists']['artist'], function ($artist) use ($atts) {
        $name = esc_html($artist['name']);
        $url = esc_url($artist['url']);

        // Prüfen, ob der Künstlername gekürzt wird (multibyte-safe)
        $shortName = nowscrobbling_mb_truncate($name, (int) $atts['max_length']);
        $isTruncated = ($shortName !== $name);

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$name auf last.fm' target='_blank'>$shortName</a>";
    });

    $hash = nowscrobbling_make_hash([ 'period' => $atts['period'], 'count' => count($data['topartists']['artist']) ]);
    return nowscrobbling_wrap_output('nowscr_lastfm_top_artists', $output, $hash, false, [ 'period' => $atts['period'], 'limit' => $limit, 'max_length' => (int) $atts['max_length'] ], 'span');
}
add_shortcode('nowscr_lastfm_top_artists', 'nowscr_lastfm_top_artists_shortcode');

// Last.fm Top Albums Shortcode
function nowscr_lastfm_top_albums_shortcode($atts) {
    $atts = shortcode_atts([
        'period' => '7day', 
        'max_length' => 45,
        'limit' => '',
        'count' => '',
    ], $atts);

    $configured_default = (int) get_option('top_albums_count', 5);
    $limit = $atts['limit'] !== '' ? (int) $atts['limit'] : ($atts['count'] !== '' ? (int) $atts['count'] : $configured_default);
    $limit = max(1, $limit);

    $data = nowscrobbling_fetch_lastfm_top_data('topalbums', $limit, $atts['period'], 'lastfm_top_albums');

    if (!$data || empty($data['topalbums']['album'])) {
        $html = "<em>Keine Top-Alben gefunden.</em>";
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_lastfm_top_albums', $html, $hash, false, [], 'span');
    }

    $output = nowscrobbling_generate_shortcode_output($data['topalbums']['album'], function ($album) use ($atts) {
        $artistName = esc_html($album['artist']['name']);
        $albumName = esc_html($album['name']);
        $url = esc_url($album['url']);
        $fullText = "{$artistName} - {$albumName}";

        // Prüfen, ob der Text gekürzt wird (multibyte-safe)
        $shortText = nowscrobbling_mb_truncate($fullText, (int) $atts['max_length']);
        $isTruncated = ($shortText !== $fullText);

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$albumName von $artistName auf last.fm' target='_blank'>$shortText</a>";
    });

    $hash = nowscrobbling_make_hash([ 'period' => $atts['period'], 'count' => count($data['topalbums']['album']) ]);
    return nowscrobbling_wrap_output('nowscr_lastfm_top_albums', $output, $hash, false, [ 'period' => $atts['period'], 'limit' => $limit, 'max_length' => (int) $atts['max_length'] ], 'span');
}
add_shortcode('nowscr_lastfm_top_albums', 'nowscr_lastfm_top_albums_shortcode');

// Last.fm Top Tracks Shortcode
function nowscr_lastfm_top_tracks_shortcode($atts) {
    // Standardwerte setzen, einschließlich 'max_length' und 'limit'
    $atts = shortcode_atts([
        'period' => '7day',
        'max_length' => 45,
        'limit' => '',
        'count' => '',
    ], $atts);

    $configured_default = (int) get_option('top_tracks_count', 5);
    $limit = $atts['limit'] !== '' ? (int) $atts['limit'] : ($atts['count'] !== '' ? (int) $atts['count'] : $configured_default);
    $limit = max(1, $limit);

    $data = nowscrobbling_fetch_lastfm_top_data('toptracks', $limit, $atts['period'], 'lastfm_top_tracks');

    if (!$data || empty($data['toptracks']['track'])) {
        $html = "<em>Keine Top-Titel gefunden.</em>";
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_lastfm_top_tracks', $html, $hash, false, [], 'span');
    }

    $output = nowscrobbling_generate_shortcode_output($data['toptracks']['track'], function ($track) use ($atts) {
        $artistName = esc_html($track['artist']['name']);
        $trackName = esc_html($track['name']);
        $url = esc_url($track['url']);
        $fullText = "{$artistName} - {$trackName}";

        // Prüfen, ob der Text (Künstler + Trackname) gekürzt wird (multibyte-safe)
        $shortText = nowscrobbling_mb_truncate($fullText, (int) $atts['max_length']);
        $isTruncated = ($shortText !== $fullText);

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$trackName von $artistName auf last.fm' target='_blank'>$shortText</a>";
    });

    $hash = nowscrobbling_make_hash([ 'period' => $atts['period'], 'count' => count($data['toptracks']['track']) ]);
    return nowscrobbling_wrap_output('nowscr_lastfm_top_tracks', $output, $hash, false, [ 'period' => $atts['period'], 'limit' => $limit, 'max_length' => (int) $atts['max_length'] ], 'span');
}
add_shortcode('nowscr_lastfm_top_tracks', 'nowscr_lastfm_top_tracks_shortcode');

// Last.fm Loved Tracks Shortcode
function nowscr_lastfm_lovedtracks_shortcode($atts) {
    // Standardwerte setzen, einschließlich 'max_length' und 'limit'
    $atts = shortcode_atts([
        'max_length' => 45,
        'limit' => '',
        'count' => '',
    ], $atts);

    $configured_default = (int) get_option('lovedtracks_count', 5);
    $limit = $atts['limit'] !== '' ? (int) $atts['limit'] : ($atts['count'] !== '' ? (int) $atts['count'] : $configured_default);
    $limit = max(1, $limit);

    $data = nowscrobbling_fetch_lastfm_top_data('lovedtracks', $limit, 'overall', 'lastfm_lovedtracks');

    if (!$data || empty($data['lovedtracks']['track'])) {
        $html = "<em>Keine Lieblingslieder gefunden.</em>";
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_lastfm_lovedtracks', $html, $hash, false, [], 'span');
    }

    $output = nowscrobbling_generate_shortcode_output($data['lovedtracks']['track'], function ($track) use ($atts) {
        $artistName = esc_html($track['artist']['name']);
        $trackName = esc_html($track['name']);
        $url = esc_url($track['url']);
        $fullText = "{$artistName} - {$trackName}";

        // Prüfen, ob der Text (Künstler + Trackname) gekürzt wird (multibyte-safe)
        $shortText = nowscrobbling_mb_truncate($fullText, (int) $atts['max_length']);
        $isTruncated = ($shortText !== $fullText);

        // CSS-Klasse 'truncated' hinzufügen, wenn der Text gekürzt ist
        $class = $isTruncated ? 'bubble truncated' : 'bubble';

        return "<a class='{$class}' href='$url' title='$trackName von $artistName auf last.fm' target='_blank'>$shortText</a>";
    });

    $hash = nowscrobbling_make_hash([ 'count' => count($data['lovedtracks']['track']) ]);
    return nowscrobbling_wrap_output('nowscr_lastfm_lovedtracks', $output, $hash, false, [ 'limit' => $limit, 'max_length' => (int) $atts['max_length'] ], 'span');
}
add_shortcode('nowscr_lastfm_lovedtracks', 'nowscr_lastfm_lovedtracks_shortcode');

// -----------------------------------------------------------------------------
// TRAKT SHORTCODES
// -----------------------------------------------------------------------------

function nowscrobbling_format_output($title, $year, $url, $rating = '', $rewatch = '', $is_now_playing = false, $prepend = '', $max_length = 0) {
    // Escape variables
    $display_title = $title;
    if ($max_length && (int)$max_length > 0) {
        $display_title = nowscrobbling_mb_truncate($display_title, (int)$max_length);
    }
    $escaped_title = esc_html($display_title);
    $escaped_year = esc_html($year);
    $escaped_url = esc_url($url);
    $escaped_rating = esc_html($rating);
    $escaped_rewatch = esc_html($rewatch);

    // Define individual elements with title attributes
    $elements = [
        'title' => $prepend . $escaped_title,
        'year' => $year ? " <span title='" . esc_attr("Die Veröffentlichung von $title war im Jahr $year") . "' style='font-style: italic; opacity: 0.66;'>($escaped_year)</span>" : '',
        // Only show rating if $rating is not an empty string (allows 0, but not null/'')
        'rating' => $rating !== '' ? " <span title='" . esc_attr("Ich bewerte $title mit $rating von 10") . "'><span style='font-size: 1rem;'>★</span>$escaped_rating</span>" : '',
        'rewatch' => $rewatch ? "<span title='" . esc_attr("Ich " . ($is_now_playing ? "schaue" : "schaute") . " $title zum $rewatch. mal") . "' style='font-style: italic; opacity: 0.33;'>↩$escaped_rewatch</span>" : ''
    ];

    // Escape the title attribute for the <a> element
    $link_title_attr = esc_attr("$title auf Trakt");

    // Customize the order and separators of elements here
    return "<a class='bubble' href='{$escaped_url}' title='{$link_title_attr}' target='_blank'>{$elements['title']}{$elements['year']}{$elements['rewatch']}{$elements['rating']}</a>";
}

// Trakt Indicator Shortcode
function nowscr_trakt_indicator_shortcode() {
    // Persisted HTML key for reliable fallback rendering
    $html_key = nowscrobbling_build_cache_key('shortcode_trakt_indicator_html');

    $watching = nowscrobbling_fetch_trakt_watching();
    if (is_array($watching) && !empty($watching)) {
        $html = '<strong>Scrobbelt gerade</strong>';
        $type = isset($watching['type']) ? $watching['type'] : 'unknown';
        $id   = (isset($watching[$type]['ids']['trakt'])) ? $watching[$type]['ids']['trakt'] : '';
        $hash = nowscrobbling_make_hash([ 'type' => $type, 'id' => $id ]);
        // Store last good HTML for fallback usage
        set_transient($html_key, $html, 12 * HOUR_IN_SECONDS);
        return nowscrobbling_wrap_output('nowscr_trakt_indicator', $html, $hash, true, [], 'span');
    }

    $activities = nowscrobbling_fetch_trakt_activities('trakt_indicator');
    $valid = null;
    if (is_array($activities)) {
        foreach ($activities as $row) {
            if (!is_array($row)) { continue; }
            if (!isset($row['watched_at'])) { continue; }
            $valid = $row;
            break;
        }
    }

    if (!is_array($valid)) {
        // Try previously rendered HTML (persisted) to avoid empty output
        $prev = get_transient($html_key);
        if (is_string($prev) && $prev !== '') {
            $hash = nowscrobbling_make_hash($prev);
            return nowscrobbling_wrap_output('nowscr_trakt_indicator', $prev, $hash, false, [], 'span');
        }
        $html = '<em>Keine kürzlichen Aktivitäten gefunden</em>';
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_trakt_indicator', $html, $hash, false, [], 'span');
    }

    $when = is_string($valid['watched_at']) ? $valid['watched_at'] : '';
    // Fallback to current time if format is missing
    try {
        $date = new DateTime($when ?: 'now');
    } catch (Exception $e) {
        $date = new DateTime('now');
    }
    $date->setTimezone(new DateTimeZone(get_option('timezone_string') ?: 'UTC'));
    $html = 'Zuletzt geschaut: ' . esc_html($date->format(get_option('date_format') . ' ' . get_option('time_format')));
    $hash = nowscrobbling_make_hash([ 'watched_at' => $when ]);
    set_transient($html_key, $html, 12 * HOUR_IN_SECONDS);
    return nowscrobbling_wrap_output('nowscr_trakt_indicator', $html, $hash, false, [], 'span');
}
add_shortcode('nowscr_trakt_indicator', 'nowscr_trakt_indicator_shortcode');

// Trakt History Shortcode
function nowscr_trakt_history_shortcode($atts) {
    $atts = shortcode_atts([
        'show_year' => false,
        'show_rewatch' => false,
        'show_rating' => false,
        'max_length' => 0,
        'limit' => 1,
    ], $atts);
    $atts['show_year']    = filter_var($atts['show_year'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_rewatch'] = filter_var($atts['show_rewatch'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_rating']  = filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN);
    $limit = max(1, (int) $atts['limit']);

    // Persisted HTML key for reliable fallback rendering
    $html_key = nowscrobbling_build_cache_key('shortcode_trakt_history_html', [
        'limit' => $limit,
        'show_year' => (bool) $atts['show_year'],
        'show_rating' => (bool) $atts['show_rating'],
        'show_rewatch' => (bool) $atts['show_rewatch'],
    ]);

    $watching = nowscrobbling_fetch_trakt_watching();
    
    if (!empty($watching)) {
        $type = $watching['type'];
        $id = $type === 'episode'
            ? $watching['episode']['ids']['trakt']
            : ($type === 'movie' ? $watching['movie']['ids']['trakt'] : null);

        $rating = '';
        if ($atts['show_rating'] && $id !== null) {
            // Only use ratings map; avoid synchronous direct fetches during render
            $rating = match ($type) {
                'movie' => nowscrobbling_get_trakt_rating_from_map('movies', $id),
                'episode' => nowscrobbling_get_trakt_rating_from_map('episodes', $id),
                'show' => nowscrobbling_get_trakt_rating_from_map('shows', $id),
                default => null,
            };
        }
        $rewatch = $atts['show_rewatch'] ? nowscrobbling_get_rewatch_count($id, $type === 'movie' ? 'movies' : 'episodes') : '';

        $title = $type === 'movie'
            ? $watching['movie']['title']
            : "{$watching['show']['title']} - S{$watching['episode']['season']}E{$watching['episode']['number']}: {$watching['episode']['title']}";
        $link = $type === 'movie'
            ? "https://trakt.tv/movies/{$watching['movie']['ids']['slug']}"
            : "https://trakt.tv/shows/{$watching['show']['ids']['slug']}/seasons/{$watching['episode']['season']}/episodes/{$watching['episode']['number']}";

        $nowPlaying = '<img src="' . esc_url( ( defined('NOWSCROBBLING_URL') ? NOWSCROBBLING_URL : plugins_url('/', dirname(__DIR__)) ) . 'public/images/nowplaying.gif' ) . '" alt="NOW PLAYING" loading="lazy" decoding="async" style="vertical-align: text-bottom; height: 1em;" /> ';

        $output = nowscrobbling_format_output(
            $title,
            ($atts['show_year'] && $type === 'movie') ? "{$watching['movie']['year']}" : '',
            $link,
            $atts['show_rating'] && $rating !== null ? (string) $rating : '',
            $atts['show_rewatch'] && $rewatch > 1 ? $rewatch : '',
            true,
            $nowPlaying,
            isset($atts['max_length']) ? (int) $atts['max_length'] : 0
        );
        // Store last good HTML for fallback usage
        set_transient($html_key, $output, 12 * HOUR_IN_SECONDS);
        $hash = nowscrobbling_make_hash([ 'watching' => $type, 'id' => $type === 'movie' ? ($watching['movie']['ids']['trakt'] ?? '') : ($watching['episode']['ids']['trakt'] ?? '') ]);
        return nowscrobbling_wrap_output('nowscr_trakt_history', $output, $hash, true, $atts, 'span');
    }
    
    $activities = nowscrobbling_fetch_trakt_activities('trakt_history');
    // Normalize activities list: must be a list of arrays with expected structure
    $valid_list = [];
    if (is_array($activities)) {
        if (isset($activities['error'])) {
            $valid_list = [];
        } else {
            foreach ($activities as $row) {
                if (!is_array($row) || !isset($row['type'])) { continue; }
                $t = $row['type'];
                if (!isset($row[$t]['ids']['trakt'])) { continue; }
                $valid_list[] = $row;
            }
        }
    }
    if (empty($valid_list)) {
        // Try previously rendered HTML (persisted) to avoid empty output
        $prev = get_transient($html_key);
        if (is_string($prev) && $prev !== '') {
            $hash = nowscrobbling_make_hash($prev);
            return nowscrobbling_wrap_output('nowscr_trakt_history', $prev, $hash, false, $atts, 'span');
        }
        $html = '<em>Keine kürzlichen Aktivitäten gefunden</em>';
        $hash = nowscrobbling_make_hash($html);
        return nowscrobbling_wrap_output('nowscr_trakt_history', $html, $hash, false, [], 'span');
    }

    $slice = array_slice($valid_list, 0, $limit);
    $output = nowscrobbling_generate_shortcode_output($slice, function($activity) use ($atts) {
        $type = isset($activity['type']) ? $activity['type'] : '';
        $id = ($type && isset($activity[$type]['ids']['trakt'])) ? $activity[$type]['ids']['trakt'] : null;
        $rating_val = '';
        if ($atts['show_rating']) {
            // Ratings map only; no per-item direct fetch during render
            $mapType = ($type === 'movie') ? 'movies' : (($type === 'episode') ? 'episodes' : 'shows');
            $fetched = nowscrobbling_get_trakt_rating_from_map($mapType, $id);
            if ($fetched !== null && $fetched !== '') {
                $rating_val = (string) $fetched;
            }
        }
        $title = '';
        $year = '';
        $link = '';
        if ($type === 'movie' && isset($activity['movie'])) {
            $title = $activity['movie']['title'] ?? '';
            $year  = ($atts['show_year'] && isset($activity['movie']['year'])) ? (string) $activity['movie']['year'] : '';
            $link  = isset($activity['movie']['ids']['slug']) ? ('https://trakt.tv/movies/' . $activity['movie']['ids']['slug']) : '';
        } elseif ($type === 'episode' && isset($activity['episode'], $activity['show'])) {
            $s = $activity['episode']['season'] ?? '';
            $e = $activity['episode']['number'] ?? '';
            $t = $activity['episode']['title'] ?? '';
            $showTitle = $activity['show']['title'] ?? '';
            $title = trim($showTitle . ' - ' . 'S' . $s . 'E' . $e . ': ' . $t);
            if (isset($activity['show']['ids']['slug'])) {
                $link = 'https://trakt.tv/shows/' . $activity['show']['ids']['slug'] . '/seasons/' . $s . '/episodes/' . $e;
            }
        } elseif ($type === 'show' && isset($activity['show'])) {
            $title = $activity['show']['title'] ?? '';
            $link  = isset($activity['show']['ids']['slug']) ? ('https://trakt.tv/shows/' . $activity['show']['ids']['slug']) : '';
        }
        $rewatch_text = '';
        if ($atts['show_rewatch']) {
            $rewatchType = ($type === 'movie') ? 'movies' : (($type === 'episode') ? 'episodes' : 'shows');
            $count = nowscrobbling_get_rewatch_count($id, $rewatchType);
            $rewatch_text = ($count > 1) ? (string) $count : '';
        }
        return nowscrobbling_format_output($title, $year, $link, $rating_val, $rewatch_text, false, '', isset($atts['max_length']) ? (int) $atts['max_length'] : 0);
    });
    if (!empty($output)) {
        set_transient($html_key, $output, 12 * HOUR_IN_SECONDS);
    }
    $hash = nowscrobbling_make_hash([ 'count' => count($slice), 'first' => $slice[0]['watched_at'] ?? '' ]);
    return nowscrobbling_wrap_output('nowscr_trakt_history', $output, $hash, false, $atts, 'span');
}
add_shortcode('nowscr_trakt_history', 'nowscr_trakt_history_shortcode');

// Fetch specific Trakt rating for movies, shows, or episodes
function nowscrobbling_fetch_specific_trakt_rating($type, $id)
{
    switch ($type) {
        case 'movie':
            return nowscrobbling_fetch_trakt_movie_rating($id);
        case 'show':
            return nowscrobbling_fetch_trakt_show_rating($id);
        case 'episode':
            return nowscrobbling_fetch_trakt_episode_rating($id);
        default:
            return null;
    }
}

// Trakt Last Movie Shortcode with optional Year, Rating, and Rewatch display
function nowscr_trakt_last_movie_shortcode($atts) {
    // Set default attributes and merge with user attributes
    $atts = shortcode_atts([
        'show_year' => false,
        'show_rewatch' => false,
        'show_rating' => false,
        'limit' => '',
    ], $atts);

    $configured_default = (int) get_option('last_movies_count', 3);
    $limit = $atts['limit'] !== '' ? max(1, (int) $atts['limit']) : $configured_default;

    $user = (string) get_option('trakt_user');

    // Fetch movies with dedicated cache key to avoid overwriting with empties
    $movies_key = nowscrobbling_build_cache_key('trakt_last_movies', ['limit' => $limit]);
    $movies = nowscrobbling_fetch_trakt_data("users/$user/history/movies", ['limit' => $limit], 'trakt_last_movies');
    if (!is_array($movies) || count($movies) === 0) {
        // Try fallback data to avoid empty UI
        $fallback_movies = get_transient($movies_key . '_fallback');
        if (is_array($fallback_movies) && count($fallback_movies) > 0) {
            $movies = $fallback_movies;
        }
    }

    // Ratings map (cached) to avoid an extra full ratings request each render
    $ratings_map = [];
    if (filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN)) {
        $ratings_map = nowscrobbling_get_trakt_ratings_map('movies');
    }

    // Optional: precompute rewatch counts only when explicitly requested
    $enable_rewatch = (bool) get_option('ns_enable_rewatch', 0);
    $compute_rewatch = $enable_rewatch && filter_var($atts['show_rewatch'], FILTER_VALIDATE_BOOLEAN);
    $rewatch_counts = [];
    if ($compute_rewatch && is_array($movies)) {
        foreach ($movies as $movie) {
            if (!isset($movie['movie']['ids']['trakt'])) { continue; }
            $id = (int) $movie['movie']['ids']['trakt'];
            $val = nowscrobbling_get_rewatch_count($id, 'movies');
            $rewatch_counts[$id] = (int) $val;
        }
    }

    // Track the position in the history to compute per-item rewatch delta
    $history_positions = [];

    // Render output; if no data, reuse last rendered HTML to avoid empty output
    $output = '';
    if (is_array($movies) && count($movies) > 0) {
        $output = nowscrobbling_generate_shortcode_output(array_slice($movies, 0, $limit), function ($movie) use ($ratings_map, $rewatch_counts, &$history_positions, $atts, $compute_rewatch) {
            $id = $movie['movie']['ids']['trakt'];
            $rating_val = '';
            if (filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN)) {
                $rating_val = isset($ratings_map[(int)$id]) ? (string) $ratings_map[(int)$id] : '';
            }
            $rewatch_text = '';
            if ($compute_rewatch) {
                if (!isset($history_positions[$id])) { $history_positions[$id] = 0; }
                $total = isset($rewatch_counts[(int)$id]) ? (int)$rewatch_counts[(int)$id] : 0;
                $rewatch = $total - $history_positions[$id];
                $rewatch_text = $rewatch > 1 ? (string)$rewatch : '';
                $history_positions[$id]++;
            }

            $title = $movie['movie']['title'];
            $year  = filter_var($atts['show_year'], FILTER_VALIDATE_BOOLEAN) ? (string)$movie['movie']['year'] : '';
            $url   = "https://trakt.tv/movies/{$movie['movie']['ids']['slug']}";

            return nowscrobbling_format_output($title, $year, $url, $rating_val, $rewatch_text);
        });
    }

    // Persist last good HTML to ensure non-empty output on transient API issues
    $html_key = nowscrobbling_build_cache_key('shortcode_trakt_last_movie_html', [
        'limit' => $limit,
        'show_year' => (bool) filter_var($atts['show_year'], FILTER_VALIDATE_BOOLEAN),
        'show_rating' => (bool) filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN),
        'show_rewatch' => (bool) filter_var($atts['show_rewatch'], FILTER_VALIDATE_BOOLEAN),
    ]);

    if (!empty($output)) {
        set_transient($html_key, $output, 12 * HOUR_IN_SECONDS);
        return $output;
    }

    $prev = get_transient($html_key);
    if (is_string($prev) && $prev !== '') {
        return $prev;
    }

    // As a last resort, show a minimal placeholder instead of empty output
    return '<em>' . esc_html__('Keine Filme gefunden.', 'nowscrobbling') . '</em>';
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

    $limit = (int) get_option('last_shows_count', 3);
    $user  = (string) get_option('trakt_user');

    $shows_key = nowscrobbling_build_cache_key('trakt_last_shows', ['limit' => $limit]);
    $shows = nowscrobbling_fetch_trakt_data("users/$user/history/shows", ['limit' => $limit], 'trakt_last_shows');
    if (!is_array($shows) || count($shows) === 0) {
        $fallback = get_transient($shows_key . '_fallback');
        if (is_array($fallback) && count($fallback) > 0) {
            $shows = $fallback;
        }
    }

    $ratings_map = [];
    if (filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN)) {
        $ratings_map = nowscrobbling_get_trakt_ratings_map('shows');
    }

    $enable_rewatch = (bool) get_option('ns_enable_rewatch', 0);
    $compute_rewatch = $enable_rewatch && filter_var($atts['show_rewatch'], FILTER_VALIDATE_BOOLEAN);
    $rewatch_counts = [];
    if ($compute_rewatch && is_array($shows)) {
        foreach ($shows as $row) {
            if (!isset($row['show']['ids']['trakt'])) { continue; }
            $id = (int) $row['show']['ids']['trakt'];
            $rewatch_counts[$id] = (int) nowscrobbling_get_rewatch_count($id, 'shows');
        }
    }

    $history_positions = [];
    $output = '';
    if (is_array($shows) && count($shows) > 0) {
        $output = nowscrobbling_generate_shortcode_output($shows, function ($row) use ($ratings_map, $rewatch_counts, &$history_positions, $atts, $compute_rewatch) {
            $id = (int) $row['show']['ids']['trakt'];
            $rating_val = '';
            if (filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN)) {
                $rating_val = isset($ratings_map[$id]) ? (string) $ratings_map[$id] : '';
            }
            $rewatch_text = '';
            if ($compute_rewatch) {
                if (!isset($history_positions[$id])) { $history_positions[$id] = 0; }
                $total = isset($rewatch_counts[$id]) ? (int)$rewatch_counts[$id] : 0;
                $rewatch = $total - $history_positions[$id];
                $rewatch_text = $rewatch > 1 ? (string)$rewatch : '';
                $history_positions[$id]++;
            }

            $title = $row['show']['title'];
            $year  = filter_var($atts['show_year'], FILTER_VALIDATE_BOOLEAN) ? (string)$row['show']['year'] : '';
            $url   = "https://trakt.tv/shows/{$row['show']['ids']['slug']}";
            return nowscrobbling_format_output($title, $year, $url, $rating_val, $rewatch_text);
        });
    }

    $html_key = nowscrobbling_build_cache_key('shortcode_trakt_last_show_html', [
        'show_year' => (bool) filter_var($atts['show_year'], FILTER_VALIDATE_BOOLEAN),
        'show_rating' => (bool) filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN),
        'show_rewatch' => (bool) filter_var($atts['show_rewatch'], FILTER_VALIDATE_BOOLEAN),
    ]);

    if (!empty($output)) {
        set_transient($html_key, $output, 12 * HOUR_IN_SECONDS);
        return $output;
    }
    $prev = get_transient($html_key);
    if (is_string($prev) && $prev !== '') { return $prev; }
    return "<em>Keine Serien gefunden.</em>";
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

    $limit = (int) get_option('last_episodes_count', 3);
    $user  = (string) get_option('trakt_user');

    $eps_key = nowscrobbling_build_cache_key('trakt_last_episodes', ['limit' => $limit]);
    $episodes = nowscrobbling_fetch_trakt_data("users/$user/history/episodes", ['limit' => $limit], 'trakt_last_episodes');
    if (!is_array($episodes) || count($episodes) === 0) {
        $fallback = get_transient($eps_key . '_fallback');
        if (is_array($fallback) && count($fallback) > 0) {
            $episodes = $fallback;
        }
    }

    $ratings_map = [];
    if (filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN)) {
        $ratings_map = nowscrobbling_get_trakt_ratings_map('episodes');
    }

    $enable_rewatch = (bool) get_option('ns_enable_rewatch', 0);
    $compute_rewatch = $enable_rewatch && filter_var($atts['show_rewatch'], FILTER_VALIDATE_BOOLEAN);

    $output = '';
    if (is_array($episodes) && count($episodes) > 0) {
        $output = nowscrobbling_generate_shortcode_output($episodes, function ($episode) use ($ratings_map, $compute_rewatch, $atts) {
            $season = $episode['episode']['season'];
            $episodeNumber = $episode['episode']['number'];
            $title = "S{$season}E{$episodeNumber}: {$episode['episode']['title']}";
            $url = "https://trakt.tv/shows/{$episode['show']['ids']['slug']}/seasons/{$season}/episodes/{$episodeNumber}";
            $id = (int) $episode['episode']['ids']['trakt'];
            $rating_text = '';
            if (filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN)) {
                $rating_val = isset($ratings_map[$id]) ? (int)$ratings_map[$id] : '';
                $rating_text = $rating_val !== '' ? (string)$rating_val : '';
            }

            // Estimation for episode rewatch is costly; use simple count via helper, but avoid per-item offset complexity
            $rewatch_text = '';
            if ($compute_rewatch) {
                $count = (int) nowscrobbling_get_rewatch_count($id, 'episodes');
                $rewatch_text = $count > 1 ? (string) $count : '';
            }

            return nowscrobbling_format_output($title, '', $url, $rating_text, $rewatch_text);
        });
    }

    $html_key = nowscrobbling_build_cache_key('shortcode_trakt_last_episode_html', [
        'show_year' => (bool) filter_var($atts['show_year'], FILTER_VALIDATE_BOOLEAN),
        'show_rating' => (bool) filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN),
        'show_rewatch' => (bool) filter_var($atts['show_rewatch'], FILTER_VALIDATE_BOOLEAN),
    ]);

    if (!empty($output)) {
        set_transient($html_key, $output, 12 * HOUR_IN_SECONDS);
        return $output;
    }
    $prev = get_transient($html_key);
    if (is_string($prev) && $prev !== '') { return $prev; }
    return "<em>Keine Episoden gefunden.</em>";
}
add_shortcode('nowscr_trakt_last_episode', 'nowscr_trakt_last_episode_shortcode');

// -----------------------------------------------------------------------------
// Helpers: Multibyte-safe truncate with ellipsis
// -----------------------------------------------------------------------------
if (!function_exists('nowscrobbling_mb_truncate')) {
    function nowscrobbling_mb_truncate($text, $maxLength) {
        $text = (string) $text;
        $max = max(1, (int) $maxLength);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $max) return $text;
            $cut = max(0, $max - 1);
            return mb_substr($text, 0, $cut, 'UTF-8') . '…';
        }
        // Fallback to byte-based
        if (strlen($text) <= $max) return $text;
        $cut = max(0, $max - 2);
        return substr($text, 0, $cut) . '…';
    }
}

?>