<?php
/**
 * Trakt Indicator Template
 *
 * Shows current watching status.
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

$is_watching = ! empty( $data['watching'] );
$title       = $data['title'] ?? '';
$show        = $data['show'] ?? '';
$year        = $data['year'] ?? '';
$type        = $data['type'] ?? 'movie'; // movie, show, episode
$url         = $data['url'] ?? '#';
$image       = $data['image'] ?? '';
$layout      = $atts['layout'] ?? 'inline';

// Build display text.
$display_text = $title;
if ( $show && $type === 'episode' ) {
    $display_text = $show . ': ' . $title;
}
if ( $year && ! empty( $atts['show_year'] ) ) {
    $display_text .= ' (' . $year . ')';
}

// Truncate if needed.
$max_length = (int) ( $atts['max_length'] ?? 0 );
if ( $max_length > 0 && mb_strlen( $display_text ) > $max_length ) {
    $display_text = mb_substr( $display_text, 0, $max_length ) . '…';
}

// Use layout template or render inline.
if ( $layout !== 'inline' ) {
    \NowScrobbling\Templates\TemplateEngine::render( 'layouts/' . $layout, [
        'data' => [
            'items' => [[
                'title'      => $title,
                'show'       => $show,
                'year'       => $year,
                'type'       => $type,
                'url'        => $url,
                'image'      => $image,
                'nowplaying' => $is_watching,
            ]],
            'nowplaying' => $is_watching,
        ],
        'atts'      => $atts,
        'shortcode' => $shortcode,
    ]);
    return;
}

// Inline layout (default).
?>
<span class="ns-indicator ns-indicator--trakt <?php echo $is_watching ? 'ns-indicator--watching' : 'ns-indicator--idle'; ?>">
    <?php if ( $is_watching ) : ?>
        <span class="ns-pulse" aria-hidden="true"></span>
    <?php endif; ?>

    <a href="<?php echo esc_url( $url ); ?>"
       class="ns-indicator__link"
       target="_blank"
       rel="noopener noreferrer"
       title="<?php echo esc_attr( $title ); ?>">
        <?php echo esc_html( $display_text ); ?>
    </a>
</span>
