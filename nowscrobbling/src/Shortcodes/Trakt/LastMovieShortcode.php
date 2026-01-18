<?php
/**
 * Trakt Last Movie Shortcode
 *
 * Displays the last watched movie.
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
 * Class LastMovieShortcode
 *
 * Shows the last watched movie.
 */
class LastMovieShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_trakt_last_movie';

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
    protected string $label = 'Trakt Last Movie';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Displays the last watched movie.';

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

        $response = $provider->fetchData( 'last_movie' );

        if ( ! $response->success || empty( $response->data['movie'] ) ) {
            return null;
        }

        return $response->data['movie'];
    }

    /**
     * Get the template name.
     *
     * @return string
     */
    protected function getTemplateName(): string {
        return 'shortcodes/trakt/last-movie';
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
        return __( 'No movies watched yet.', 'nowscrobbling' );
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
