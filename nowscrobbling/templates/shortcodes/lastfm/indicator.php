<?php
/**
 * Last.fm Indicator Template
 *
 * Shows current playing status with now playing indicator.
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

$is_playing = ! empty( $data['nowplaying'] );
$track      = $data['track'] ?? '';
$artist     = $data['artist'] ?? '';
$album      = $data['album'] ?? '';
$url        = $data['url'] ?? '#';
$image      = $data['image'] ?? '';
$layout     = $atts['layout'] ?? 'inline';

// Build display text.
$display_text = $track;
if ( $artist ) {
    $display_text .= ' – ' . $artist;
}

// Truncate if needed.
$max_length = (int) ( $atts['max_length'] ?? 0 );
if ( $max_length > 0 && mb_strlen( $display_text ) > $max_length ) {
    $display_text = mb_substr( $display_text, 0, $max_length ) . '…';
}

// Use layout template or render inline.
if ( $layout !== 'inline' ) {
    \NowScrobbling\Templates\TemplateEngine::render( 'layouts/' . $layout, [
        'data'      => [
            'items' => [[
                'title'      => $track,
                'artist'     => $artist,
                'album'      => $album,
                'url'        => $url,
                'image'      => $image,
                'nowplaying' => $is_playing,
            ]],
            'nowplaying' => $is_playing,
        ],
        'atts'      => $atts,
        'shortcode' => $shortcode,
    ]);
    return;
}

// Inline layout (default).
?>
<span class="ns-indicator ns-indicator--lastfm <?php echo $is_playing ? 'ns-indicator--playing' : 'ns-indicator--idle'; ?>">
    <?php if ( $is_playing ) : ?>
        <span class="ns-pulse" aria-hidden="true"></span>
    <?php endif; ?>

    <a href="<?php echo esc_url( $url ); ?>"
       class="ns-indicator__link"
       target="_blank"
       rel="noopener noreferrer"
       title="<?php echo esc_attr( $track . ' by ' . $artist ); ?>">
        <?php echo esc_html( $display_text ); ?>
    </a>
</span>
