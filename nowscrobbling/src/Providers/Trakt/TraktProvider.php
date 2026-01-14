<?php
/**
 * Trakt Provider
 *
 * Handles all Trakt.tv API interactions.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Providers\Trakt;

use NowScrobbling\Providers\AbstractProvider;
use NowScrobbling\Providers\ConnectionResult;
use NowScrobbling\Providers\ProviderResponse;
use NowScrobbling\Attribution\Attribution;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TraktProvider
 *
 * Trakt.tv API integration for movie and TV show tracking.
 */
class TraktProvider extends AbstractProvider {

    /**
     * Provider ID.
     *
     * @var string
     */
    protected string $id = 'trakt';

    /**
     * Provider display name.
     *
     * @var string
     */
    protected string $name = 'Trakt.tv';

    /**
     * Provider capabilities.
     *
     * @var array<string>
     */
    protected array $capabilities = [
        'now_playing',
        'history',
        'ratings',
        'favorites',
    ];

    /**
     * Base API URL.
     *
     * @var string
     */
    protected string $apiUrl = 'https://api.trakt.tv/';

    /**
     * API version.
     *
     * @var string
     */
    protected string $apiVersion = '2';

    /**
     * Cached ratings maps.
     *
     * @var array<string, array<int, int>>
     */
    private array $ratingsCache = [];

    /**
     * Get the provider icon.
     *
     * @return string
     */
    public function getIcon(): string {
        return 'dashicons-video-alt2';
    }

    /**
     * Get the provider's base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string {
        return 'https://trakt.tv';
    }

    /**
     * Get settings fields for the admin panel.
     *
     * @return array
     */
    public function getSettingsFields(): array {
        return [
            'client_id' => [
                'label'       => __( 'Client ID', 'nowscrobbling' ),
                'type'        => 'text',
                'description' => __( 'Get your Client ID from trakt.tv/oauth/applications', 'nowscrobbling' ),
                'required'    => true,
            ],
            'user' => [
                'label'       => __( 'Username', 'nowscrobbling' ),
                'type'        => 'text',
                'description' => __( 'Your Trakt.tv username', 'nowscrobbling' ),
                'required'    => true,
            ],
        ];
    }

    /**
     * Check if the provider is properly configured.
     *
     * @return bool
     */
    public function isConfigured(): bool {
        return ! empty( $this->getClientId() ) && ! empty( $this->getUsername() );
    }

    /**
     * Test the connection to the API.
     *
     * @return ConnectionResult
     */
    public function testConnection(): ConnectionResult {
        if ( ! $this->isConfigured() ) {
            return new ConnectionResult(
                success: false,
                message: __( 'Client ID or username not configured', 'nowscrobbling' )
            );
        }

        $response = $this->apiRequest( 'users/' . $this->getUsername() );

        if ( ! $response->success ) {
            return new ConnectionResult(
                success: false,
                message: $response->error ?? __( 'Connection failed', 'nowscrobbling' )
            );
        }

        $user = $response->data;
        if ( ! isset( $user['username'] ) ) {
            return new ConnectionResult(
                success: false,
                message: __( 'Invalid response from Trakt', 'nowscrobbling' )
            );
        }

        return new ConnectionResult(
            success: true,
            message: sprintf(
                /* translators: %s: username */
                __( 'Connected as %s', 'nowscrobbling' ),
                $user['username']
            ),
            data: [
                'user' => $user['username'],
                'name' => $user['name'] ?? '',
                'vip'  => $user['vip'] ?? false,
            ]
        );
    }

    /**
     * Get attribution information.
     *
     * @return Attribution
     */
    public function getAttribution(): Attribution {
        return Attribution::trakt();
    }

    /**
     * Get rate limit configuration.
     *
     * Trakt allows 1000 requests per 5 minutes.
     *
     * @return array{requests: int, period: int}
     */
    public function getRateLimit(): array {
        return [
            'requests' => 1000,
            'period'   => 300,
        ];
    }

    /**
     * Fetch data from the provider.
     *
     * @param string $endpoint The endpoint to fetch.
     * @param array  $params   Optional parameters.
     * @return ProviderResponse
     */
    public function fetchData( string $endpoint, array $params = [] ): ProviderResponse {
        return match ( $endpoint ) {
            'watching', 'now_playing'  => $this->getWatching(),
            'history'                  => $this->getHistory( $params ),
            'last_movie'               => $this->getLastMovie(),
            'last_show'                => $this->getLastShow(),
            'last_episode'             => $this->getLastEpisode(),
            'ratings'                  => $this->getRatings( $params ),
            'user_info'                => $this->getUserInfo(),
            default                    => $this->buildResponse( false, null, 'Unknown endpoint: ' . $endpoint ),
        };
    }

    /**
     * Get currently watching content.
     *
     * @return ProviderResponse
     */
    public function getWatching(): ProviderResponse {
        $response = $this->apiRequest( 'users/' . $this->getUsername() . '/watching' );

        // 204 No Content = not watching anything.
        if ( $response->success && empty( $response->data ) ) {
            return $this->buildResponse( true, [
                'watching' => false,
                'item'     => null,
            ] );
        }

        if ( ! $response->success ) {
            return $response;
        }

        $data = $response->data;
        $type = $data['type'] ?? null;

        if ( ! $type || ! isset( $data[ $type ] ) ) {
            return $this->buildResponse( true, [
                'watching' => false,
                'item'     => null,
            ] );
        }

        $item = $this->normalizeItem( $data, $type );

        return $this->buildResponse( true, [
            'watching' => true,
            'type'     => $type,
            'item'     => $item,
        ] );
    }

    /**
     * Get watch history.
     *
     * @param array $params Parameters (limit, type).
     * @return ProviderResponse
     */
    public function getHistory( array $params = [] ): ProviderResponse {
        $limit = $params['limit'] ?? 10;
        $type  = $params['type'] ?? null; // movies, shows, episodes, or null for all.

        $path = 'users/' . $this->getUsername() . '/history';
        if ( $type && in_array( $type, [ 'movies', 'shows', 'episodes' ], true ) ) {
            $path .= '/' . $type;
        }

        $response = $this->apiRequest( $path, [
            'limit' => min( 50, max( 1, (int) $limit ) ),
        ] );

        if ( ! $response->success ) {
            return $response;
        }

        $items = [];
        foreach ( $response->data as $entry ) {
            $entryType = $entry['type'] ?? 'movie';
            $items[] = $this->normalizeItem( $entry, $entryType );
        }

        return $this->buildResponse( true, [
            'items' => $items,
            'total' => count( $items ),
        ] );
    }

    /**
     * Get last watched movie.
     *
     * @return ProviderResponse
     */
    public function getLastMovie(): ProviderResponse {
        $response = $this->getHistory( [ 'type' => 'movies', 'limit' => 1 ] );

        if ( ! $response->success || empty( $response->data['items'] ) ) {
            return $this->buildResponse( true, [ 'movie' => null ] );
        }

        return $this->buildResponse( true, [
            'movie' => $response->data['items'][0],
        ] );
    }

    /**
     * Get last watched show.
     *
     * @return ProviderResponse
     */
    public function getLastShow(): ProviderResponse {
        $response = $this->getHistory( [ 'type' => 'episodes', 'limit' => 10 ] );

        if ( ! $response->success || empty( $response->data['items'] ) ) {
            return $this->buildResponse( true, [ 'show' => null ] );
        }

        // Return the first unique show.
        $seen = [];
        foreach ( $response->data['items'] as $item ) {
            $showId = $item['show_id'] ?? null;
            if ( $showId && ! isset( $seen[ $showId ] ) ) {
                return $this->buildResponse( true, [ 'show' => $item ] );
            }
            $seen[ $showId ] = true;
        }

        return $this->buildResponse( true, [ 'show' => $response->data['items'][0] ] );
    }

    /**
     * Get last watched episode.
     *
     * @return ProviderResponse
     */
    public function getLastEpisode(): ProviderResponse {
        $response = $this->getHistory( [ 'type' => 'episodes', 'limit' => 1 ] );

        if ( ! $response->success || empty( $response->data['items'] ) ) {
            return $this->buildResponse( true, [ 'episode' => null ] );
        }

        return $this->buildResponse( true, [
            'episode' => $response->data['items'][0],
        ] );
    }

    /**
     * Get user ratings.
     *
     * @param array $params Parameters (type: movies, shows, episodes).
     * @return ProviderResponse
     */
    public function getRatings( array $params = [] ): ProviderResponse {
        $type = $params['type'] ?? 'movies';
        if ( ! in_array( $type, [ 'movies', 'shows', 'episodes' ], true ) ) {
            $type = 'movies';
        }

        $response = $this->apiRequest( 'users/' . $this->getUsername() . '/ratings/' . $type, [
            'limit' => 200,
        ] );

        if ( ! $response->success ) {
            return $response;
        }

        $ratings = [];
        foreach ( $response->data as $entry ) {
            $bucket = ( $type === 'episodes' ) ? 'episode' : rtrim( $type, 's' );
            $id = $entry[ $bucket ]['ids']['trakt'] ?? null;

            if ( $id && isset( $entry['rating'] ) ) {
                $ratings[ $id ] = (int) $entry['rating'];
            }
        }

        return $this->buildResponse( true, [
            'type'    => $type,
            'ratings' => $ratings,
            'total'   => count( $ratings ),
        ] );
    }

    /**
     * Get rating for a specific item.
     *
     * @param string $type Item type (movie, show, episode).
     * @param int    $id   Trakt ID.
     * @return int|null Rating or null if not rated.
     */
    public function getRating( string $type, int $id ): ?int {
        $pluralType = $type . 's';

        // Check cache first.
        if ( ! isset( $this->ratingsCache[ $pluralType ] ) ) {
            $response = $this->getRatings( [ 'type' => $pluralType ] );
            if ( $response->success ) {
                $this->ratingsCache[ $pluralType ] = $response->data['ratings'] ?? [];
            } else {
                $this->ratingsCache[ $pluralType ] = [];
            }
        }

        return $this->ratingsCache[ $pluralType ][ $id ] ?? null;
    }

    /**
     * Get user info.
     *
     * @return ProviderResponse
     */
    public function getUserInfo(): ProviderResponse {
        $response = $this->apiRequest( 'users/' . $this->getUsername() );

        if ( ! $response->success ) {
            return $response;
        }

        $user = $response->data;

        return $this->buildResponse( true, [
            'username' => $user['username'] ?? '',
            'name'     => $user['name'] ?? '',
            'vip'      => $user['vip'] ?? false,
            'private'  => $user['private'] ?? false,
            'avatar'   => $user['images']['avatar']['full'] ?? '',
        ] );
    }

    /**
     * Make an API request to Trakt.
     *
     * @param string $path   API path (e.g., 'users/me/watching').
     * @param array  $params Query parameters.
     * @return ProviderResponse
     */
    protected function apiRequest( string $path, array $params = [] ): ProviderResponse {
        if ( ! $this->isConfigured() ) {
            return $this->buildResponse( false, null, 'Provider not configured' );
        }

        $path = ltrim( $path, '/' );
        $url  = $this->apiUrl . $path;

        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }

        $headers = [
            'Content-Type'      => 'application/json',
            'trakt-api-version' => $this->apiVersion,
            'trakt-api-key'     => $this->getClientId(),
        ];

        $result = $this->request( $url, $headers );

        if ( ! $result['success'] ) {
            return $this->buildResponse( false, null, $result['error'] ?? 'Request failed' );
        }

        return $this->buildResponse( true, $result['data'] );
    }

    /**
     * Get Client ID.
     *
     * @return string
     */
    protected function getClientId(): string {
        return (string) $this->getOption( 'client_id' );
    }

    /**
     * Get username.
     *
     * @return string
     */
    protected function getUsername(): string {
        return (string) $this->getOption( 'user' );
    }

    /**
     * Get legacy option key mapping.
     *
     * @param string $key New option key.
     * @return string|null
     */
    protected function getLegacyOptionKey( string $key ): ?string {
        return match ( $key ) {
            'client_id' => 'trakt_client_id',
            'user'      => 'trakt_user',
            default     => null,
        };
    }

    /**
     * Normalize a Trakt item to a standard format.
     *
     * @param array  $entry The raw API entry.
     * @param string $type  The item type (movie, show, episode).
     * @return array Normalized item.
     */
    protected function normalizeItem( array $entry, string $type ): array {
        $item = [
            'type'      => $type,
            'timestamp' => isset( $entry['watched_at'] )
                ? strtotime( $entry['watched_at'] )
                : null,
        ];

        if ( $type === 'movie' && isset( $entry['movie'] ) ) {
            $movie = $entry['movie'];
            $item = array_merge( $item, [
                'title'    => $movie['title'] ?? '',
                'name'     => $movie['title'] ?? '',
                'year'     => $movie['year'] ?? null,
                'url'      => $this->buildUrl( 'movies', $movie['ids']['slug'] ?? '' ),
                'trakt_id' => $movie['ids']['trakt'] ?? null,
                'imdb_id'  => $movie['ids']['imdb'] ?? null,
                'tmdb_id'  => $movie['ids']['tmdb'] ?? null,
                'rating'   => $this->getRating( 'movie', $movie['ids']['trakt'] ?? 0 ),
            ] );
        }

        if ( $type === 'episode' && isset( $entry['episode'] ) ) {
            $episode = $entry['episode'];
            $show    = $entry['show'] ?? [];

            $item = array_merge( $item, [
                'title'          => $episode['title'] ?? '',
                'name'           => $episode['title'] ?? '',
                'season'         => $episode['season'] ?? 1,
                'episode_number' => $episode['number'] ?? 1,
                'show_title'     => $show['title'] ?? '',
                'show_year'      => $show['year'] ?? null,
                'url'            => $this->buildEpisodeUrl(
                    $show['ids']['slug'] ?? '',
                    $episode['season'] ?? 1,
                    $episode['number'] ?? 1
                ),
                'show_url'       => $this->buildUrl( 'shows', $show['ids']['slug'] ?? '' ),
                'trakt_id'       => $episode['ids']['trakt'] ?? null,
                'show_id'        => $show['ids']['trakt'] ?? null,
                'imdb_id'        => $episode['ids']['imdb'] ?? null,
                'tmdb_id'        => $episode['ids']['tmdb'] ?? null,
                'rating'         => $this->getRating( 'episode', $episode['ids']['trakt'] ?? 0 ),
                'show_rating'    => $this->getRating( 'show', $show['ids']['trakt'] ?? 0 ),
            ] );

            // Format subtitle.
            $item['subtitle'] = sprintf(
                '%s - S%02dE%02d',
                $show['title'] ?? '',
                $episode['season'] ?? 1,
                $episode['number'] ?? 1
            );
        }

        if ( $type === 'show' && isset( $entry['show'] ) ) {
            $show = $entry['show'];
            $item = array_merge( $item, [
                'title'    => $show['title'] ?? '',
                'name'     => $show['title'] ?? '',
                'year'     => $show['year'] ?? null,
                'url'      => $this->buildUrl( 'shows', $show['ids']['slug'] ?? '' ),
                'trakt_id' => $show['ids']['trakt'] ?? null,
                'imdb_id'  => $show['ids']['imdb'] ?? null,
                'tmdb_id'  => $show['ids']['tmdb'] ?? null,
                'rating'   => $this->getRating( 'show', $show['ids']['trakt'] ?? 0 ),
            ] );
        }

        return $item;
    }

    /**
     * Build a Trakt URL.
     *
     * @param string $type Item type (movies, shows).
     * @param string $slug Item slug.
     * @return string
     */
    protected function buildUrl( string $type, string $slug ): string {
        if ( empty( $slug ) ) {
            return $this->getBaseUrl();
        }
        return $this->getBaseUrl() . '/' . $type . '/' . $slug;
    }

    /**
     * Build an episode URL.
     *
     * @param string $showSlug Show slug.
     * @param int    $season   Season number.
     * @param int    $episode  Episode number.
     * @return string
     */
    protected function buildEpisodeUrl( string $showSlug, int $season, int $episode ): string {
        if ( empty( $showSlug ) ) {
            return $this->getBaseUrl();
        }
        return sprintf(
            '%s/shows/%s/seasons/%d/episodes/%d',
            $this->getBaseUrl(),
            $showSlug,
            $season,
            $episode
        );
    }
}
