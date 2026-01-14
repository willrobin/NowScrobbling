<?php
/**
 * Rating Partial Template
 *
 * Displays a star rating or numeric rating.
 *
 * @package NowScrobbling
 * @since   1.4.0
 *
 * @var int|float $rating  The rating value (0-10).
 * @var bool      $compact Whether to show compact version.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$rating = $rating ?? 0;
$compact = $compact ?? false;
$max_rating = 10;

// Convert to 5-star scale for display.
$stars = $rating / 2;
$full_stars = floor( $stars );
$half_star = ( $stars - $full_stars ) >= 0.5;
$empty_stars = 5 - $full_stars - ( $half_star ? 1 : 0 );
?>

<?php if ( $compact ) : ?>
    <span class="ns-rating ns-rating--compact">
        <span class="ns-rating__star ns-rating__star--filled">★</span>
        <span class="ns-rating__value"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
    </span>
<?php else : ?>
    <span class="ns-rating" title="<?php echo esc_attr( sprintf( __( 'Rating: %s out of %s', 'nowscrobbling' ), $rating, $max_rating ) ); ?>">
        <span class="ns-rating__stars" aria-hidden="true">
            <?php
            // Full stars.
            for ( $i = 0; $i < $full_stars; $i++ ) {
                echo '<span class="ns-rating__star ns-rating__star--filled">★</span>';
            }

            // Half star.
            if ( $half_star ) {
                echo '<span class="ns-rating__star ns-rating__star--half">★</span>';
            }

            // Empty stars.
            for ( $i = 0; $i < $empty_stars; $i++ ) {
                echo '<span class="ns-rating__star ns-rating__star--empty">☆</span>';
            }
            ?>
        </span>
        <span class="screen-reader-text">
            <?php
            printf(
                /* translators: 1: Rating value, 2: Maximum rating */
                esc_html__( 'Rated %1$s out of %2$s', 'nowscrobbling' ),
                esc_html( number_format_i18n( $rating, 1 ) ),
                esc_html( $max_rating )
            );
            ?>
        </span>
    </span>
<?php endif; ?>
