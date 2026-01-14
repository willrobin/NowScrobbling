<?php
/**
 * Shortcode Registry
 *
 * Central registry for all shortcodes.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Shortcodes;

use NowScrobbling\Shortcodes\LastFM\IndicatorShortcode as LastFMIndicator;
use NowScrobbling\Shortcodes\LastFM\HistoryShortcode as LastFMHistory;
use NowScrobbling\Shortcodes\LastFM\TopArtistsShortcode;
use NowScrobbling\Shortcodes\LastFM\TopAlbumsShortcode;
use NowScrobbling\Shortcodes\LastFM\TopTracksShortcode;
use NowScrobbling\Shortcodes\LastFM\LovedTracksShortcode;
use NowScrobbling\Shortcodes\Trakt\IndicatorShortcode as TraktIndicator;
use NowScrobbling\Shortcodes\Trakt\HistoryShortcode as TraktHistory;
use NowScrobbling\Shortcodes\Trakt\LastMovieShortcode;
use NowScrobbling\Shortcodes\Trakt\LastShowShortcode;
use NowScrobbling\Shortcodes\Trakt\LastEpisodeShortcode;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ShortcodeRegistry
 *
 * Manages registration and lookup of shortcodes.
 */
class ShortcodeRegistry {

    /**
     * Registered shortcodes.
     *
     * @var array<string, AbstractShortcode>
     */
    private array $shortcodes = [];

    /**
     * Constructor - register built-in shortcodes.
     */
    public function __construct() {
        $this->registerBuiltinShortcodes();
    }

    /**
     * Register all built-in shortcodes.
     *
     * @return void
     */
    private function registerBuiltinShortcodes(): void {
        // Last.fm shortcodes.
        $this->add( new LastFMIndicator() );
        $this->add( new LastFMHistory() );
        $this->add( new TopArtistsShortcode() );
        $this->add( new TopAlbumsShortcode() );
        $this->add( new TopTracksShortcode() );
        $this->add( new LovedTracksShortcode() );

        // Trakt shortcodes.
        $this->add( new TraktIndicator() );
        $this->add( new TraktHistory() );
        $this->add( new LastMovieShortcode() );
        $this->add( new LastShowShortcode() );
        $this->add( new LastEpisodeShortcode() );

        /**
         * Fires when shortcodes are being registered.
         * Use this to register custom shortcodes.
         *
         * @since 1.4.0
         * @param ShortcodeRegistry $registry The shortcode registry.
         */
        do_action( 'nowscrobbling_register_shortcodes', $this );
    }

    /**
     * Register a shortcode.
     *
     * @param AbstractShortcode $shortcode The shortcode to register.
     * @return void
     */
    public function add( AbstractShortcode $shortcode ): void {
        $tag = $shortcode->getTag();
        $this->shortcodes[ $tag ] = $shortcode;
    }

    /**
     * Register all shortcodes with WordPress.
     *
     * Called on 'init' hook.
     *
     * @return void
     */
    public function register(): void {
        foreach ( $this->shortcodes as $tag => $shortcode ) {
            add_shortcode( $tag, [ $shortcode, 'render' ] );
        }

        /**
         * Fires after all shortcodes have been registered.
         *
         * @since 1.4.0
         * @param ShortcodeRegistry $registry The shortcode registry.
         */
        do_action( 'nowscrobbling_shortcodes_registered', $this );
    }

    /**
     * Get a shortcode by tag.
     *
     * @param string $tag Shortcode tag.
     * @return AbstractShortcode|null
     */
    public function get( string $tag ): ?AbstractShortcode {
        return $this->shortcodes[ $tag ] ?? null;
    }

    /**
     * Get all registered shortcodes.
     *
     * @return array<string, AbstractShortcode>
     */
    public function getAll(): array {
        return $this->shortcodes;
    }

    /**
     * Get shortcodes by provider.
     *
     * @param string $provider Provider ID.
     * @return array<string, AbstractShortcode>
     */
    public function getByProvider( string $provider ): array {
        return array_filter(
            $this->shortcodes,
            fn( AbstractShortcode $s ) => $s->getProvider() === $provider
        );
    }

    /**
     * Get shortcode data for the admin builder.
     *
     * @return array
     */
    public function getForBuilder(): array {
        $result = [];

        foreach ( $this->shortcodes as $tag => $shortcode ) {
            $result[ $tag ] = [
                'tag'         => $tag,
                'label'       => $shortcode->getLabel(),
                'provider'    => $shortcode->getProvider(),
                'attributes'  => $shortcode->getAttributeDefinitions(),
                'description' => $shortcode->getDescription(),
            ];
        }

        return $result;
    }

    /**
     * Get list of all shortcode tags.
     *
     * @return array<string>
     */
    public function getTags(): array {
        return array_keys( $this->shortcodes );
    }

    /**
     * Check if a shortcode is registered.
     *
     * @param string $tag Shortcode tag.
     * @return bool
     */
    public function has( string $tag ): bool {
        return isset( $this->shortcodes[ $tag ] );
    }

    /**
     * Get the count of registered shortcodes.
     *
     * @return int
     */
    public function count(): int {
        return count( $this->shortcodes );
    }
}
