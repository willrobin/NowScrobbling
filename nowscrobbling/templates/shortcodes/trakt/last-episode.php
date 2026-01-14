<?php
/**
 * Trakt Last Episode Template
 *
 * Shows the most recently watched episode.
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

$items  = $data['items'] ?? [];
$layout = $atts['layout'] ?? 'inline';

// Delegate to layout template.
\NowScrobbling\Templates\TemplateEngine::render( 'layouts/' . $layout, [
    'data' => [
        'items'      => $items,
        'nowplaying' => false,
    ],
    'atts'      => $atts,
    'shortcode' => $shortcode,
]);
