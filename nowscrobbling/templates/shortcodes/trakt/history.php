<?php
/**
 * Trakt History Template
 *
 * Shows recent watch history.
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

$items       = $data['items'] ?? [];
$is_watching = ! empty( $data['watching'] );
$layout      = $atts['layout'] ?? 'inline';

// Delegate to layout template.
\NowScrobbling\Templates\TemplateEngine::render( 'layouts/' . $layout, [
    'data' => [
        'items'      => $items,
        'nowplaying' => $is_watching,
    ],
    'atts'      => $atts,
    'shortcode' => $shortcode,
]);
