<?php
/**
 * Now Playing Badge Component
 *
 * Displays an animated badge indicating currently playing content.
 *
 * @package NowScrobbling
 * @since   1.4.0
 *
 * @var string $type   Type of media (music, movie, show).
 * @var string $label  Optional custom label.
 * @var bool   $pulse  Whether to show pulse animation.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$type  = $type ?? 'music';
$label = $label ?? '';
$pulse = $pulse ?? true;

// Default labels.
$default_labels = [
    'music' => __( 'Now Playing', 'nowscrobbling' ),
    'movie' => __( 'Watching', 'nowscrobbling' ),
    'show'  => __( 'Watching', 'nowscrobbling' ),
];

$display_label = $label ?: ( $default_labels[ $type ] ?? $default_labels['music'] );
?>

<span class="ns-badge ns-badge--nowplaying ns-badge--<?php echo esc_attr( $type ); ?>">
    <?php if ( $pulse ) : ?>
        <span class="ns-badge__pulse" aria-hidden="true">
            <span class="ns-pulse"></span>
        </span>
    <?php endif; ?>
    <span class="ns-badge__label"><?php echo esc_html( $display_label ); ?></span>
</span>
