<?php
/**
 * Last.fm Loved Tracks Shortcode
 *
 * Displays loved/favorite tracks.
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
 * Class LovedTracksShortcode
 *
 * Shows loved tracks.
 */
class LovedTracksShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_lastfm_lovedtracks';

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
    protected string $label = 'Last.fm Loved Tracks';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Displays your loved/favorite tracks.';

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

        $response = $provider->fetchData( 'loved_tracks', [
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
        return 'shortcodes/lastfm/loved-tracks';
    }

    /**
     * Check if data represents "now playing" state.
     *
     * @param mixed $data Fetched data.
     * @return bool
     */
    protected function isNowPlaying( $data ): bool {
        return false;
    }

    /**
     * Get the empty state message.
     *
     * @return string
     */
    protected function getEmptyMessage(): string {
        return __( 'No loved tracks found.', 'nowscrobbling' );
    }
}
