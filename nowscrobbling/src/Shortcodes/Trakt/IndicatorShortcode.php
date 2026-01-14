<?php
/**
 * Trakt Indicator Shortcode
 *
 * Displays the current watching status.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Shortcodes\Trakt;

use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Core\Plugin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IndicatorShortcode
 *
 * Shows "Watching" or "Last watched: [time]".
 */
class IndicatorShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_trakt_indicator';

    /**
     * Provider ID.
     *
     * @var string
     */
    protected string $provider = 'trakt';

    /**
     * Human-readable label.
     *
     * @var string
     */
    protected string $label = 'Trakt Indicator';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Shows current watching status or last watched time.';

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    protected function getDefaults(): array {
        return [
            'prefix_watching' => __( 'Watching now', 'nowscrobbling' ),
            'prefix_last'     => __( 'Last watched', 'nowscrobbling' ),
        ];
    }

    /**
     * Fetch data for this shortcode.
     *
     * @param array $atts Parsed attributes.
     * @return mixed
     */
    protected function fetchData( array $atts ): mixed {
        $provider = Plugin::getInstance()->getProviders()->get( 'trakt' );

        if ( ! $provider || ! $provider->isConfigured() ) {
            return null;
        }

        // First check if watching.
        $watching = $provider->fetchData( 'watching' );

        if ( $watching->success && ! empty( $watching->data['watching'] ) ) {
            return [
                'watching' => true,
                'item'     => $watching->data['item'],
                'type'     => $watching->data['type'],
            ];
        }

        // Get last watched item.
        $history = $provider->fetchData( 'history', [ 'limit' => 1 ] );

        if ( ! $history->success || empty( $history->data['items'] ) ) {
            return null;
        }

        return [
            'watching' => false,
            'item'     => $history->data['items'][0],
        ];
    }

    /**
     * Get the template name.
     *
     * @return string
     */
    protected function getTemplateName(): string {
        return 'shortcodes/trakt/indicator';
    }

    /**
     * Check if data represents "now playing" state.
     *
     * @param mixed $data Fetched data.
     * @return bool
     */
    protected function isNowPlaying( $data ): bool {
        return ! empty( $data['watching'] );
    }

    /**
     * Get the empty state message.
     *
     * @return string
     */
    protected function getEmptyMessage(): string {
        return __( 'No recent activity found.', 'nowscrobbling' );
    }

    /**
     * Override render for inline output.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render( array $atts = [] ): string {
        $atts = $this->parseAttributes( $atts );
        $data = $this->fetchData( $atts );

        if ( $data === null ) {
            return $this->renderEmpty( $atts );
        }

        $isWatching = $this->isNowPlaying( $data );

        if ( $isWatching ) {
            $html = '<strong>' . esc_html( $atts['prefix_watching'] ) . '</strong>';
        } else {
            $item = $data['item'];
            $timestamp = $item['timestamp'] ?? null;
            $timeAgo = $timestamp ? human_time_diff( $timestamp, current_time( 'timestamp' ) ) : '';

            if ( $timeAgo ) {
                $html = sprintf(
                    '%s: <time datetime="%s">%s %s</time>',
                    esc_html( $atts['prefix_last'] ),
                    esc_attr( date( 'c', $timestamp ) ),
                    esc_html( $timeAgo ),
                    esc_html__( 'ago', 'nowscrobbling' )
                );
            } else {
                $html = esc_html( $atts['prefix_last'] );
            }
        }

        return $this->wrapOutput( $html, $data, $atts );
    }

    /**
     * Override wrapper for inline display.
     *
     * @param string $html    Content HTML.
     * @param mixed  $data    Fetched data.
     * @param array  $atts    Attributes.
     * @return string
     */
    protected function wrapOutput( string $html, $data, array $atts ): string {
        $hash       = $this->computeHash( $data );
        $nowPlaying = $this->isNowPlaying( $data );

        $classes = [
            'nowscrobbling',
            'nowscrobbling--inline',
            'nowscrobbling--' . $this->provider,
        ];

        if ( $nowPlaying ) {
            $classes[] = 'nowscrobbling--now-playing';
        }

        if ( $atts['class'] ) {
            $classes[] = $atts['class'];
        }

        $wrapper_atts = [
            'class'                        => implode( ' ', $classes ),
            'data-nowscrobbling-shortcode' => $this->tag,
            'data-ns-hash'                 => $hash,
        ];

        if ( $nowPlaying ) {
            $wrapper_atts['data-ns-nowplaying'] = '1';
        }

        if ( $atts['id'] ) {
            $wrapper_atts['id'] = $atts['id'];
        }

        $attr_string = '';
        foreach ( $wrapper_atts as $key => $value ) {
            $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
        }

        return sprintf( '<span%s>%s</span>', $attr_string, $html );
    }
}
