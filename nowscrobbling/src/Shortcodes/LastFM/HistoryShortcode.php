<?php
/**
 * Last.fm History Shortcode
 *
 * Displays recent scrobbles.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Shortcodes\LastFM;

use NowScrobbling\Shortcodes\AbstractShortcode;
use NowScrobbling\Core\Plugin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class HistoryShortcode
 *
 * Shows recent scrobbles/tracks.
 */
class HistoryShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_lastfm_history';

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
    protected string $label = 'Last.fm History';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Displays recent scrobbles with optional artwork.';

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    protected function getDefaults(): array {
        return [];
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

        $response = $provider->fetchData( 'recent_tracks', [
            'limit' => $atts['limit'],
        ] );

        if ( ! $response->success ) {
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
        return 'shortcodes/lastfm/history';
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
}
