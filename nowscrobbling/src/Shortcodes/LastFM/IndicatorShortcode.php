<?php
/**
 * Last.fm Indicator Shortcode
 *
 * Displays the current scrobbling status.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Shortcodes\LastFM;

use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Core\Plugin;
use NowScrobbling\Cache\DataType;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class IndicatorShortcode
 *
 * Shows "Now scrobbling" or "Last heard: [time]".
 */
class IndicatorShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_lastfm_indicator';

    /**
     * Provider ID.
     *
     * @var string
     */
    protected string $provider = 'lastfm';

    /**
     * Human-readable label.
     *
     * @var string
     */
    protected string $label = 'Last.fm Indicator';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Shows current scrobbling status or last played time.';

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    protected function getDefaults(): array {
        return [
            'prefix_now'  => __( 'Scrobbling now', 'nowscrobbling' ),
            'prefix_last' => __( 'Last heard', 'nowscrobbling' ),
        ];
    }

    /**
     * Fetch data for this shortcode.
     *
     * @param array $atts Parsed attributes.
     * @return mixed
     */
    protected function fetchData( array $atts ): mixed {
        $provider = Plugin::getInstance()->getProviders()->get( 'lastfm' );

        if ( ! $provider || ! $provider->isConfigured() ) {
            return null;
        }

        $response = $provider->fetchData( 'recent_tracks', [ 'limit' => 1 ] );

        if ( ! $response->success || empty( $response->data['items'] ) ) {
            return null;
        }

        return $response->data;
    }

    /**
     * Get the template name.
     *
     * @return string
     */
    protected function getTemplateName(): string {
        return 'shortcodes/lastfm/indicator';
    }

    /**
     * Check if data represents "now playing" state.
     *
     * @param mixed $data Fetched data.
     * @return bool
     */
    protected function isNowPlaying( $data ): bool {
        return ! empty( $data['nowplaying'] );
    }

    /**
     * Get the empty state message.
     *
     * @return string
     */
    protected function getEmptyMessage(): string {
        return __( 'No recent tracks found.', 'nowscrobbling' );
    }

    /**
     * Override render to provide simpler inline output.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render( array $atts = [] ): string {
        $atts = $this->parseAttributes( $atts );
        $data = $this->fetchData( $atts );

        if ( $data === null || empty( $data['items'] ) ) {
            return $this->renderEmpty( $atts );
        }

        $track = $data['items'][0];
        $isNowPlaying = $this->isNowPlaying( $data );

        if ( $isNowPlaying ) {
            $html = '<strong>' . esc_html( $atts['prefix_now'] ) . '</strong>';
        } else {
            $timestamp = $track['timestamp'] ?? null;
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
