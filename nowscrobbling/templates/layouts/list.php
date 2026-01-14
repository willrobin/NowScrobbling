<?php
/**
 * List Layout Template
 *
 * Displays items as a detailed list with artwork.
 * Shows the most information per item.
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

<div class="ns-layout ns-layout--list">
    <ul class="ns-list">
        <?php foreach ( $items as $index => $item ) : ?>
            <?php
            $title     = $item['title'] ?? $item['name'] ?? '';
            $url       = $item['url'] ?? '#';
            $artist    = $item['artist'] ?? '';
            $image     = $item['image'] ?? '';
            $year      = $item['year'] ?? '';
            $rating    = $item['rating'] ?? null;
            $playcount = $item['playcount'] ?? null;
            $timestamp = $item['timestamp'] ?? null;
            $rewatch   = $item['rewatch_count'] ?? 0;
            ?>

            <li class="ns-list__item <?php echo $is_now_playing && $index === 0 ? 'ns-list__item--nowplaying' : ''; ?>">
                <?php if ( $atts['show_image'] && $image ) : ?>
                    <div class="ns-list__artwork">
                        <a href="<?php echo esc_url( $url ); ?>"
                           target="_blank"
                           rel="noopener noreferrer">
                            <img
                                class="ns-list__image"
                                src="<?php echo esc_url( $image ); ?>"
                                alt="<?php echo esc_attr( $title ); ?>"
                                loading="lazy"
                            >
                        </a>
                    </div>
                <?php endif; ?>

                <div class="ns-list__content">
                    <div class="ns-list__primary">
                        <a href="<?php echo esc_url( $url ); ?>"
                           class="ns-list__title"
                           target="_blank"
                           rel="noopener noreferrer">
                            <?php echo esc_html( $title ); ?>
                            <?php if ( $year ) : ?>
                                <span class="ns-list__year">(<?php echo esc_html( $year ); ?>)</span>
                            <?php endif; ?>
                        </a>

                        <?php if ( $is_now_playing && $index === 0 ) : ?>
                            <span class="ns-list__badge ns-list__badge--nowplaying">
                                <span class="ns-pulse"></span>
                                <?php esc_html_e( 'Now', 'nowscrobbling' ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $artist ) : ?>
                        <div class="ns-list__secondary">
                            <span class="ns-list__artist"><?php echo esc_html( $artist ); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="ns-list__meta">
                        <?php if ( $rating !== null && ! empty( $atts['show_rating'] ) ) : ?>
                            <span class="ns-list__rating" title="<?php esc_attr_e( 'Rating', 'nowscrobbling' ); ?>">
                                <?php TemplateEngine::partial( 'rating', [ 'rating' => $rating, 'compact' => true ] ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( $playcount !== null ) : ?>
                            <span class="ns-list__playcount" title="<?php esc_attr_e( 'Play count', 'nowscrobbling' ); ?>">
                                <?php
                                printf(
                                    /* translators: %s: play count number */
                                    esc_html( _n( '%s play', '%s plays', $playcount, 'nowscrobbling' ) ),
                                    esc_html( number_format_i18n( $playcount ) )
                                );
                                ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( $rewatch > 0 && ! empty( $atts['show_rewatch'] ) ) : ?>
                            <span class="ns-list__rewatch" title="<?php esc_attr_e( 'Rewatched', 'nowscrobbling' ); ?>">
                                <?php
                                printf(
                                    /* translators: %s: rewatch count */
                                    esc_html( _n( '%s rewatch', '%s rewatches', $rewatch, 'nowscrobbling' ) ),
                                    esc_html( number_format_i18n( $rewatch ) )
                                );
                                ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( $timestamp ) : ?>
                            <time class="ns-list__time" datetime="<?php echo esc_attr( date( 'c', $timestamp ) ); ?>">
                                <?php echo esc_html( human_time_diff( $timestamp, current_time( 'timestamp' ) ) ); ?>
                                <?php esc_html_e( 'ago', 'nowscrobbling' ); ?>
                            </time>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
