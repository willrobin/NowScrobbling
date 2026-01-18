<?php
/**
 * Trakt Last Episode Shortcode
 *
 * Displays the last watched episode.
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
 * Class LastEpisodeShortcode
 *
 * Shows the last watched episode.
 */
class LastEpisodeShortcode extends AbstractShortcode {

    /**
     * Shortcode tag.
     *
     * @var string
     */
    protected string $tag = 'nowscr_trakt_last_episode';

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
    protected string $label = 'Trakt Last Episode';

    /**
     * Description.
     *
     * @var string
     */
    protected string $description = 'Displays the last watched episode with show info.';

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    protected function getDefaults(): array {
        return [
            'show_rating' => false,
            'format'      => 'full', // full, compact.
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

        $response = $provider->fetchData( 'last_episode' );

        if ( ! $response->success || empty( $response->data['episode'] ) ) {
            return null;
        }

        return $response->data['episode'];
    }

    /**
     * Get the template name.
     *
     * @return string
     */
    protected function getTemplateName(): string {
        return 'shortcodes/trakt/last-episode';
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
        return __( 'No episodes watched yet.', 'nowscrobbling' );
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
            'format' => [
                'type'    => 'select',
                'label'   => __( 'Format', 'nowscrobbling' ),
                'options' => [
                    'full'    => __( 'Full (Show - S01E01: Title)', 'nowscrobbling' ),
                    'compact' => __( 'Compact (Show S01E01)', 'nowscrobbling' ),
                ],
                'default' => 'full',
            ],
        ];
    }
}
