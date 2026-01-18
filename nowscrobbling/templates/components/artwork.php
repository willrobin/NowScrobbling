<?php
/**
 * Artwork Component Template
 *
 * Displays artwork/cover with proper sizing and loading attributes.
 *
 * @package NowScrobbling
 * @since   1.4.0
 *
 * @var string $src         Image source URL.
 * @var string $alt         Alt text for the image.
 * @var string $size        Size variant (small, medium, large).
 * @var string $url         Optional link URL.
 * @var bool   $lazy        Whether to lazy load.
 * @var string $placeholder Optional placeholder image.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$src         = $src ?? '';
$alt         = $alt ?? '';
$size        = $size ?? 'medium';
$url         = $url ?? '';
$lazy        = $lazy ?? true;
$placeholder = $placeholder ?? '';

// Size dimensions.
$sizes = [
    'small'  => [ 'width' => 64, 'height' => 64 ],
    'medium' => [ 'width' => 150, 'height' => 150 ],
    'large'  => [ 'width' => 300, 'height' => 300 ],
];

$dimensions = $sizes[ $size ] ?? $sizes['medium'];

// CSS classes.
$classes = [
    'ns-artwork',
    'ns-artwork--' . $size,
];

if ( ! $src ) {
    $classes[] = 'ns-artwork--placeholder';
}
?>

<figure class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
    <?php if ( $url ) : ?>
        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
    <?php endif; ?>

    <?php if ( $src ) : ?>
        <img
            class="ns-artwork__image"
            src="<?php echo esc_url( $src ); ?>"
            alt="<?php echo esc_attr( $alt ); ?>"
            width="<?php echo esc_attr( $dimensions['width'] ); ?>"
            height="<?php echo esc_attr( $dimensions['height'] ); ?>"
            <?php echo $lazy ? 'loading="lazy"' : ''; ?>
            decoding="async"
        >
    <?php elseif ( $placeholder ) : ?>
        <img
            class="ns-artwork__image ns-artwork__image--placeholder"
            src="<?php echo esc_url( $placeholder ); ?>"
            alt=""
            width="<?php echo esc_attr( $dimensions['width'] ); ?>"
            height="<?php echo esc_attr( $dimensions['height'] ); ?>"
            aria-hidden="true"
        >
    <?php else : ?>
        <span class="ns-artwork__fallback" aria-hidden="true">
            <?php if ( $alt ) : ?>
                <?php echo esc_html( mb_substr( $alt, 0, 1 ) ); ?>
            <?php else : ?>
                ♪
            <?php endif; ?>
        </span>
    <?php endif; ?>

    <?php if ( $url ) : ?>
        </a>
    <?php endif; ?>
</figure>
