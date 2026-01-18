<?php
/**
 * Provider Interface
 *
 * Contract that all media providers must implement.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Providers;

use NowScrobbling\Attribution\Attribution;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface ProviderInterface
 *
 * Defines the contract for all media data providers.
 * Implementing this interface allows seamless integration of new services
 * like Spotify, Letterboxd, Goodreads, Steam, etc.
 */
interface ProviderInterface {

    /**
     * Get the unique provider identifier.
     *
     * Used for internal references, option keys, and cache keys.
     * Should be lowercase, no spaces (e.g., 'lastfm', 'trakt', 'spotify').
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get the display name of the provider.
     *
     * Human-readable name for UI display (e.g., 'Last.fm', 'Trakt.tv').
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the provider icon.
     *
     * Can be a dashicon class, SVG path, or URL.
     *
     * @return string
     */
    public function getIcon(): string;

    /**
     * Get the provider's base URL.
     *
     * Used for linking back to the service.
     *
     * @return string
     */
    public function getBaseUrl(): string;

    /**
     * Get settings fields for the admin panel.
     *
     * Returns an array of field definitions for credentials and options.
     *
     * @return array<string, array{
     *     label: string,
     *     type: string,
     *     description?: string,
     *     required?: bool,
     *     default?: mixed
     * }>
     */
    public function getSettingsFields(): array;

    /**
     * Check if the provider is properly configured.
     *
     * Returns true if all required credentials are present.
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Test the connection to the provider's API.
     *
     * @return ConnectionResult
     */
    public function testConnection(): ConnectionResult;

    /**
     * Get attribution information for legal compliance.
     *
     * @return Attribution
     */
    public function getAttribution(): Attribution;

    /**
     * Get the capabilities of this provider.
     *
     * Possible capabilities:
     * - 'now_playing': Real-time "currently listening/watching"
     * - 'history': Recent activity history
     * - 'top_items': Top artists/albums/tracks/movies/shows
     * - 'ratings': User ratings
     * - 'favorites': Loved tracks, watchlist, etc.
     * - 'artwork': Provides cover art or posters
     *
     * @return array<string>
     */
    public function getCapabilities(): array;

    /**
     * Check if the provider has a specific capability.
     *
     * @param string $capability The capability to check.
     * @return bool
     */
    public function hasCapability( string $capability ): bool;

    /**
     * Fetch data from the provider.
     *
     * @param string $endpoint The endpoint to fetch (e.g., 'recent_tracks', 'watching').
     * @param array  $params   Optional parameters for the request.
     * @return ProviderResponse
     */
    public function fetchData( string $endpoint, array $params = [] ): ProviderResponse;

    /**
     * Get the rate limit configuration for this provider.
     *
     * @return array{requests: int, period: int}
     */
    public function getRateLimit(): array;
}
