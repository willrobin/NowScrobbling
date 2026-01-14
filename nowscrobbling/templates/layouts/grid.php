<?php
/**
 * Grid Layout Template
 *
 * Displays items as a responsive poster grid.
 * Inspired by Letterboxd's film diary view.
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

// Grid column count based on limit.
$columns = min( 6, count( $items ) );
?>

<div class="ns-layout ns-layout--grid ns-grid--cols-<?php echo esc_attr( $columns ); ?>">
    <?php foreach ( $items as $index => $item ) : ?>
        <?php
        $title  = $item['title'] ?? $item['name'] ?? '';
        $url    = $item['url'] ?? '#';
        $image  = $item['image'] ?? '';
        $artist = $item['artist'] ?? '';
        $rating = $item['rating'] ?? null;
        ?>

        <div class="ns-grid__item <?php echo $is_now_playing && $index === 0 ? 'ns-grid__item--nowplaying' : ''; ?>">
            <a href="<?php echo esc_url( $url ); ?>"
               class="ns-grid__link"
               target="_blank"
               rel="noopener noreferrer"
               title="<?php echo esc_attr( $title . ( $artist ? ' - ' . $artist : '' ) ); ?>">

                <?php if ( $image ) : ?>
                    <div class="ns-grid__poster">
                        <img
                            class="ns-grid__image"
                            src="<?php echo esc_url( $image ); ?>"
                            alt="<?php echo esc_attr( $title ); ?>"
                            loading="lazy"
                        >

                        <?php if ( $is_now_playing && $index === 0 ) : ?>
                            <span class="ns-grid__badge">
                                <span class="ns-pulse"></span>
                            </span>
                        <?php endif; ?>

                        <?php if ( $rating !== null && ! empty( $atts['show_rating'] ) ) : ?>
                            <span class="ns-grid__rating">
                                <?php echo esc_html( $rating ); ?><span class="ns-grid__rating-max">/10</span>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="ns-grid__poster ns-grid__poster--placeholder">
                        <span class="ns-grid__placeholder-text">
                            <?php echo esc_html( mb_substr( $title, 0, 1 ) ); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </a>

            <div class="ns-grid__meta">
                <span class="ns-grid__title"><?php echo esc_html( $title ); ?></span>
                <?php if ( $artist ) : ?>
                    <span class="ns-grid__artist"><?php echo esc_html( $artist ); ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
