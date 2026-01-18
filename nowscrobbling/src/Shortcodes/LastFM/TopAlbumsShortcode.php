<?php
/**
 * Last.fm Top Albums Shortcode
 *
 * Displays top albums for a given period.
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
 * Class TopAlbumsShortcode
 *
 * Shows top albums.
 */
class TopAlbumsShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_lastfm_top_albums';

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
    protected string $label = 'Last.fm Top Albums';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Displays top albums for a specified time period.';

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    protected function getDefaults(): array {
        return [
            'period' => '7day',
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

        $response = $provider->fetchData( 'top_albums', [
            'limit'  => $atts['limit'],
            'period' => $atts['period'],
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
        return 'shortcodes/lastfm/top-albums';
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
        return __( 'No top albums found for this period.', 'nowscrobbling' );
    }

    /**
     * Get custom attribute definitions.
     *
     * @return array
     */
    protected function getCustomAttributeDefinitions(): array {
        return [
            'period' => [
                'type'    => 'select',
                'label'   => __( 'Time Period', 'nowscrobbling' ),
                'options' => [
                    '7day'    => __( 'Last 7 days', 'nowscrobbling' ),
                    '1month'  => __( 'Last month', 'nowscrobbling' ),
                    '3month'  => __( 'Last 3 months', 'nowscrobbling' ),
                    '6month'  => __( 'Last 6 months', 'nowscrobbling' ),
                    '12month' => __( 'Last year', 'nowscrobbling' ),
                    'overall' => __( 'All time', 'nowscrobbling' ),
                ],
                'default' => '7day',
            ],
        ];
    }
}
