<?php
/**
 * Trakt Last Show Shortcode
 *
 * Displays the last watched show.
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
 * Class LastShowShortcode
 *
 * Shows the last watched show.
 */
class LastShowShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_trakt_last_show';

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
    protected string $label = 'Trakt Last Show';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Displays the last watched TV show.';

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    protected function getDefaults(): array {
        return [
            'show_rating' => false,
            'show_year'   => true,
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

        $response = $provider->fetchData( 'last_show' );

        if ( ! $response->success || empty( $response->data['show'] ) ) {
            return null;
        }

        return $response->data['show'];
    }

    /**
     * Get the template name.
     *
     * @return string
     */
    protected function getTemplateName(): string {
        return 'shortcodes/trakt/last-show';
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
        return __( 'No shows watched yet.', 'nowscrobbling' );
    }

    /**
     * Get custom attribute definitions.
     *
     * @return array
     */
    protected function getCustomAttributeDefinitions(): array {
        return [
            'show_rating' => [
                'type'    => 'checkbox',
                'label'   => __( 'Show Rating', 'nowscrobbling' ),
                'default' => false,
            ],
            'show_year' => [
                'type'    => 'checkbox',
                'label'   => __( 'Show Year', 'nowscrobbling' ),
                'default' => true,
            ],
        ];
    }
}
