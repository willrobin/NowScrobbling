<?php
/**
 * Inline Layout Template
 *
 * Displays items as inline bubble links.
 * This is the default, most compact layout.
 *
 * @package NowScrobbling
 * @since   1.4.0
 *
 * @var array  $data      The fetched data items.
 * @var array  $atts      Shortcode attributes.
 * @var object $shortcode The shortcode instance.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NowScrobbling\Templates\TemplateEngine;

// Handle single item vs array of items.
$items = isset( $data['items'] ) ? $data['items'] : [ $data ];
$is_now_playing = ! empty( $data['nowplaying'] );
?>

<div class="ns-layout ns-layout--inline">
    <?php foreach ( $items as $index => $item ) : ?>
        <?php
        $title = $item['title'] ?? $item['name'] ?? '';
        $url   = $item['url'] ?? '#';
        $artist = $item['artist'] ?? '';

        // Apply max_length truncation if set.
        if ( ! empty( $atts['max_length'] ) && strlen( $title ) > $atts['max_length'] ) {
            $title = substr( $title, 0, $atts['max_length'] ) . '...';
        }
        ?>

        <span class="ns-item ns-item--inline <?php echo $is_now_playing && $index === 0 ? 'ns-item--nowplaying' : ''; ?>">
            <?php if ( $atts['show_image'] && ! empty( $item['image'] ) ) : ?>
                <img
                    class="ns-item__image ns-item__image--inline"
                    src="<?php echo esc_url( $item['image'] ); ?>"
                    alt="<?php echo esc_attr( $title ); ?>"
                    loading="lazy"
                    width="20"
                    height="20"
                >
            <?php endif; ?>

            <a href="<?php echo esc_url( $url ); ?>"
               class="ns-item__link"
               target="_blank"
               rel="noopener noreferrer">
                <?php echo esc_html( $title ); ?>
            </a>

            <?php if ( $artist ) : ?>
                <span class="ns-item__separator">-</span>
                <span class="ns-item__artist"><?php echo esc_html( $artist ); ?></span>
            <?php endif; ?>
        </span>

        <?php if ( $index < count( $items ) - 1 ) : ?>
            <span class="ns-separator">&middot;</span>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
