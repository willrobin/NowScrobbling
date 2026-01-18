<?php
/**
 * Trakt History Shortcode
 *
 * Displays watch history.
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
 * Class HistoryShortcode
 *
 * Shows watch history (movies and shows).
 */
class HistoryShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_trakt_history';

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
    protected string $label = 'Trakt History';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Displays your recent watch history.';

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    protected function getDefaults(): array {
        return [
            'type'        => '', // movies, shows, episodes, or empty for all.
            'show_rating' => false,
            'show_year'   => false,
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

        $params = [ 'limit' => $atts['limit'] ];

        if ( ! empty( $atts['type'] ) ) {
            $params['type'] = $atts['type'];
        }

        $response = $provider->fetchData( 'history', $params );

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
        return 'shortcodes/trakt/history';
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
        return __( 'No watch history found.', 'nowscrobbling' );
    }

    /**
     * Get custom attribute definitions.
     *
     * @return array
     */
    protected function getCustomAttributeDefinitions(): array {
        return [
            'type' => [
                'type'    => 'select',
                'label'   => __( 'Content Type', 'nowscrobbling' ),
                'options' => [
                    ''         => __( 'All', 'nowscrobbling' ),
                    'movies'   => __( 'Movies only', 'nowscrobbling' ),
                    'shows'    => __( 'Shows only', 'nowscrobbling' ),
                    'episodes' => __( 'Episodes only', 'nowscrobbling' ),
                ],
                'default' => '',
            ],
            'show_rating' => [
                'type'    => 'checkbox',
                'label'   => __( 'Show Rating', 'nowscrobbling' ),
                'default' => false,
            ],
            'show_year' => [
                'type'    => 'checkbox',
                'label'   => __( 'Show Year', 'nowscrobbling' ),
                'default' => false,
            ],
        ];
    }
}
