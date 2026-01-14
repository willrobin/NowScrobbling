<?php
/**
 * Last.fm History Template
 *
 * Shows recent scrobbles/listening history.
 *
 * @package NowScrobbling
 * @since   1.4.0
 *
 * @var array  $data      The fetched data.
 * @var array  $atts      Shortcode attributes.
 * @var object $shortcode The shortcode instance.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$items      = $data['items'] ?? [];
$is_playing = ! empty( $data['nowplaying'] );
$layout     = $atts['layout'] ?? 'inline';

// Delegate to layout template.
\NowScrobbling\Templates\TemplateEngine::render( 'layouts/' . $layout, [
    'data' => [
        'items'      => $items,
        'nowplaying' => $is_playing,
    ],
    'atts'      => $atts,
    'shortcode' => $shortcode,
]);
