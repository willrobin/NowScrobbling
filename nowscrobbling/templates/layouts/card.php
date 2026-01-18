<?php
/**
 * Card Layout Template
 *
 * Displays items as cards with artwork and metadata.
 * Inspired by Letterboxd's film card design.
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

<div class="ns-layout ns-layout--card">
    <?php foreach ( $items as $index => $item ) : ?>
        <?php
        $title    = $item['title'] ?? $item['name'] ?? '';
        $url      = $item['url'] ?? '#';
        $artist   = $item['artist'] ?? '';
        $image    = $item['image'] ?? '';
        $subtitle = $item['subtitle'] ?? $artist;
        $year     = $item['year'] ?? '';
        $rating   = $item['rating'] ?? null;

        // Image size based on attribute.
        $image_class = 'ns-card__image--' . ( $atts['image_size'] ?? 'medium' );
        ?>

        <article class="ns-card <?php echo $is_now_playing && $index === 0 ? 'ns-card--nowplaying' : ''; ?>">
            <?php if ( $atts['show_image'] && $image ) : ?>
                <div class="ns-card__poster">
                    <a href="<?php echo esc_url( $url ); ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                        <img
                            class="ns-card__image <?php echo esc_attr( $image_class ); ?>"
                            src="<?php echo esc_url( $image ); ?>"
                            alt="<?php echo esc_attr( $title ); ?>"
                            loading="lazy"
                        >
                    </a>

                    <?php if ( $is_now_playing && $index === 0 ) : ?>
                        <span class="ns-card__badge ns-card__badge--nowplaying">
                            <?php esc_html_e( 'Now Playing', 'nowscrobbling' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ns-card__content">
                <h3 class="ns-card__title">
                    <a href="<?php echo esc_url( $url ); ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                        <?php echo esc_html( $title ); ?>
                        <?php if ( $year ) : ?>
                            <span class="ns-card__year">(<?php echo esc_html( $year ); ?>)</span>
                        <?php endif; ?>
                    </a>
                </h3>

                <?php if ( $subtitle ) : ?>
                    <p class="ns-card__subtitle">
                        <?php echo esc_html( $subtitle ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $rating !== null && ! empty( $atts['show_rating'] ) ) : ?>
                    <div class="ns-card__rating">
                        <?php TemplateEngine::partial( 'rating', [ 'rating' => $rating ] ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
