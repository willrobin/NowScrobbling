<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes;

use NowScrobbling\Container;
use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Api\TraktClient;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;
use NowScrobbling\Shortcodes\LastFm\IndicatorShortcode as LastFmIndicator;
use NowScrobbling\Shortcodes\LastFm\HistoryShortcode as LastFmHistory;
use NowScrobbling\Shortcodes\LastFm\TopListShortcode;
use NowScrobbling\Shortcodes\LastFm\LovedTracksShortcode;
use NowScrobbling\Shortcodes\Trakt\IndicatorShortcode as TraktIndicator;
use NowScrobbling\Shortcodes\Trakt\HistoryShortcode as TraktHistory;
use NowScrobbling\Shortcodes\Trakt\LastMediaShortcode;

/**
 * Shortcode Manager
 *
 * Registers and manages all shortcodes.
 *
 * @package NowScrobbling\Shortcodes
 */
final class ShortcodeManager
{
    /**
     * @var array<AbstractShortcode>
     */
    private array $shortcodes = [];

    private readonly OutputRenderer $renderer;
    private readonly HashGenerator $hasher;
    private readonly Container $container;

    /**
     * Constructor
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->renderer = new OutputRenderer();
        $this->hasher = new HashGenerator();
    }

    /**
     * Register all shortcodes
     */
    public function register(): void
    {
        $this->registerLastFmShortcodes();
        $this->registerTraktShortcodes();

        // Register each shortcode with WordPress
        foreach ($this->shortcodes as $shortcode) {
            $shortcode->register();
        }

        /**
         * Fires after all shortcodes are registered
         *
         * @param ShortcodeManager $manager The shortcode manager
         */
        do_action('nowscrobbling_shortcodes_registered', $this);
    }

    /**
     * Register Last.fm shortcodes
     */
    private function registerLastFmShortcodes(): void
    {
        $client = $this->container->make(LastFmClient::class);

        // Indicator
        $this->shortcodes[] = new LastFmIndicator(
            $this->renderer,
            $this->hasher,
            $client
        );

        // History
        $this->shortcodes[] = new LastFmHistory(
            $this->renderer,
            $this->hasher,
            $client
        );

        // Top Artists
        $this->shortcodes[] = new TopListShortcode(
            $this->renderer,
            $this->hasher,
            $client,
            'artists'
        );

        // Top Albums
        $this->shortcodes[] = new TopListShortcode(
            $this->renderer,
            $this->hasher,
            $client,
            'albums'
        );

        // Top Tracks
        $this->shortcodes[] = new TopListShortcode(
            $this->renderer,
            $this->hasher,
            $client,
            'tracks'
        );

        // Loved Tracks
        $this->shortcodes[] = new LovedTracksShortcode(
            $this->renderer,
            $this->hasher,
            $client
        );
    }

    /**
     * Register Trakt shortcodes
     */
    private function registerTraktShortcodes(): void
    {
        $client = $this->container->make(TraktClient::class);

        // Indicator
        $this->shortcodes[] = new TraktIndicator(
            $this->renderer,
            $this->hasher,
            $client
        );

        // History
        $this->shortcodes[] = new TraktHistory(
            $this->renderer,
            $this->hasher,
            $client
        );

        // Last Movie
        $this->shortcodes[] = new LastMediaShortcode(
            $this->renderer,
            $this->hasher,
            $client,
            'movie'
        );

        // Last Show
        $this->shortcodes[] = new LastMediaShortcode(
            $this->renderer,
            $this->hasher,
            $client,
            'show'
        );

        // Last Episode
        $this->shortcodes[] = new LastMediaShortcode(
            $this->renderer,
            $this->hasher,
            $client,
            'episode'
        );
    }

    /**
     * Get all registered shortcode tags
     *
     * @return array<string>
     */
    public function getTags(): array
    {
        return array_map(
            fn(AbstractShortcode $sc) => $sc->getTag(),
            $this->shortcodes
        );
    }

    /**
     * Get a shortcode by tag
     *
     * @param string $tag Shortcode tag
     */
    public function getByTag(string $tag): ?AbstractShortcode
    {
        foreach ($this->shortcodes as $shortcode) {
            if ($shortcode->getTag() === $tag) {
                return $shortcode;
            }
        }

        return null;
    }

    /**
     * Check if a tag is a valid shortcode
     *
     * @param string $tag Shortcode tag to check
     */
    public function isValidTag(string $tag): bool
    {
        return in_array($tag, $this->getTags(), true);
    }
}
