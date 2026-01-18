<?php
/**
 * Last.fm Provider
 *
 * Handles all Last.fm API interactions.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Providers\LastFM;

use NowScrobbling\Providers\AbstractProvider;
use NowScrobbling\Providers\ConnectionResult;
use NowScrobbling\Providers\ProviderResponse;
use NowScrobbling\Attribution\Attribution;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class LastFMProvider
 *
 * Last.fm API integration for scrobble data.
 */
class LastFMProvider extends AbstractProvider {

    /**
     * Provider ID.
     *
     * @var string
     */
    protected string $id = 'lastfm';

    /**
     * Provider display name.
     *
     * @var string
     */
    protected string $name = 'Last.fm';

    /**
     * Provider capabilities.
     *
     * @var array<string>
     */
    protected array $capabilities = [
        'now_playing',
        'history',
        'top_items',
        'favorites',
        'artwork',
    ];

    /**
     * Base API URL.
     *
     * @var string
     */
    protected string $apiUrl = 'https://ws.audioscrobbler.com/2.0/';

    /**
     * Get the provider icon.
     *
     * @return string
     */
    public function getIcon(): string {
        return 'dashicons-format-audio';
    }

    /**
     * Get the provider's base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string {
        return 'https://www.last.fm';
    }

    /**
     * Get settings fields for the admin panel.
     *
     * @return array
     */
    public function getSettingsFields(): array {
        return [
            'api_key' => [
                'label'       => __( 'API Key', 'nowscrobbling' ),
                'type'        => 'text',
                'description' => __( 'Get your API key from last.fm/api', 'nowscrobbling' ),
                'required'    => true,
            ],
            'user' => [
                'label'       => __( 'Username', 'nowscrobbling' ),
                'type'        => 'text',
                'description' => __( 'Your Last.fm username', 'nowscrobbling' ),
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
        return ! empty( $this->getApiKey() ) && ! empty( $this->getUsername() );
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
                message: __( 'API key or username not configured', 'nowscrobbling' )
            );
        }

        $response = $this->apiRequest( 'user.getinfo' );

        if ( ! $response->success ) {
            return new ConnectionResult(
                success: false,
                message: $response->error ?? __( 'Connection failed', 'nowscrobbling' )
            );
        }

        $user = $response->data['user'] ?? null;
        if ( ! $user ) {
            return new ConnectionResult(
                success: false,
                message: __( 'Invalid response from Last.fm', 'nowscrobbling' )
            );
        }

        return new ConnectionResult(
            success: true,
            message: sprintf(
                /* translators: %s: username */
                __( 'Connected as %s', 'nowscrobbling' ),
                $user['name'] ?? $this->getUsername()
            ),
            data: [
                'user'       => $user['name'],
                'playcount'  => $user['playcount'] ?? 0,
                'registered' => $user['registered']['unixtime'] ?? null,
            ]
        );
    }

    /**
     * Get attribution information.
     *
     * @return Attribution
     */
    public function getAttribution(): Attribution {
        return Attribution::lastfm();
    }

    /**
     * Get rate limit configuration.
     *
     * Last.fm allows 5 requests per second per API key.
     *
     * @return array{requests: int, period: int}
     */
    public function getRateLimit(): array {
        return [
            'requests' => 5,
            'period'   => 1,
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
            'recent_tracks', 'scrobbles' => $this->getRecentTracks( $params ),
            'now_playing'                => $this->getNowPlaying(),
            'top_artists'                => $this->getTopArtists( $params ),
            'top_albums'                 => $this->getTopAlbums( $params ),
            'top_tracks'                 => $this->getTopTracks( $params ),
            'loved_tracks'               => $this->getLovedTracks( $params ),
            'user_info'                  => $this->getUserInfo(),
            default                      => $this->buildResponse( false, null, 'Unknown endpoint: ' . $endpoint ),
        };
    }

    /**
     * Get recent tracks (scrobbles).
     *
     * @param array $params Parameters (limit, page).
     * @return ProviderResponse
     */
    public function getRecentTracks( array $params = [] ): ProviderResponse {
        $limit = $params['limit'] ?? 10;

        $response = $this->apiRequest( 'user.getrecenttracks', [
            'limit'    => min( 50, max( 1, (int) $limit ) ),
            'extended' => 1,
        ] );

        if ( ! $response->success ) {
            return $response;
        }

        $tracks = $response->data['recenttracks']['track'] ?? [];

        // Normalize to array of tracks.
        if ( isset( $tracks['name'] ) ) {
            $tracks = [ $tracks ];
        }

        $items = [];
        $nowPlaying = false;

        foreach ( $tracks as $track ) {
            $isNowPlaying = isset( $track['@attr']['nowplaying'] ) && $track['@attr']['nowplaying'] === 'true';

            if ( $isNowPlaying ) {
                $nowPlaying = true;
            }

            $items[] = [
                'title'       => $track['name'] ?? '',
                'artist'      => $track['artist']['name'] ?? $track['artist']['#text'] ?? '',
                'album'       => $track['album']['#text'] ?? '',
                'url'         => $track['url'] ?? '',
                'image'       => $this->extractImage( $track['image'] ?? [] ),
                'timestamp'   => isset( $track['date']['uts'] ) ? (int) $track['date']['uts'] : null,
                'nowplaying'  => $isNowPlaying,
                'loved'       => isset( $track['loved'] ) && $track['loved'] === '1',
            ];
        }

        return $this->buildResponse( true, [
            'items'      => $items,
            'nowplaying' => $nowPlaying,
            'total'      => $response->data['recenttracks']['@attr']['total'] ?? count( $items ),
        ] );
    }

    /**
     * Get currently playing track.
     *
     * @return ProviderResponse
     */
    public function getNowPlaying(): ProviderResponse {
        $response = $this->getRecentTracks( [ 'limit' => 1 ] );

        if ( ! $response->success ) {
            return $response;
        }

        $items = $response->data['items'] ?? [];

        if ( empty( $items ) || ! $items[0]['nowplaying'] ) {
            return $this->buildResponse( true, [
                'nowplaying' => false,
                'track'      => null,
            ] );
        }

        return $this->buildResponse( true, [
            'nowplaying' => true,
            'track'      => $items[0],
        ] );
    }

    /**
     * Get top artists.
     *
     * @param array $params Parameters (period, limit).
     * @return ProviderResponse
     */
    public function getTopArtists( array $params = [] ): ProviderResponse {
        $period = $this->validatePeriod( $params['period'] ?? '7day' );
        $limit  = $params['limit'] ?? 10;

        $response = $this->apiRequest( 'user.gettopartists', [
            'period' => $period,
            'limit'  => min( 50, max( 1, (int) $limit ) ),
        ] );

        if ( ! $response->success ) {
            return $response;
        }

        $artists = $response->data['topartists']['artist'] ?? [];

        // Normalize to array.
        if ( isset( $artists['name'] ) ) {
            $artists = [ $artists ];
        }

        $items = [];
        foreach ( $artists as $artist ) {
            $items[] = [
                'name'      => $artist['name'] ?? '',
                'title'     => $artist['name'] ?? '',
                'url'       => $artist['url'] ?? '',
                'playcount' => (int) ( $artist['playcount'] ?? 0 ),
                'image'     => $this->extractImage( $artist['image'] ?? [] ),
                'rank'      => (int) ( $artist['@attr']['rank'] ?? 0 ),
            ];
        }

        return $this->buildResponse( true, [
            'items'  => $items,
            'period' => $period,
            'total'  => $response->data['topartists']['@attr']['total'] ?? count( $items ),
        ] );
    }

    /**
     * Get top albums.
     *
     * @param array $params Parameters (period, limit).
     * @return ProviderResponse
     */
    public function getTopAlbums( array $params = [] ): ProviderResponse {
        $period = $this->validatePeriod( $params['period'] ?? '7day' );
        $limit  = $params['limit'] ?? 10;

        $response = $this->apiRequest( 'user.gettopalbums', [
            'period' => $period,
            'limit'  => min( 50, max( 1, (int) $limit ) ),
        ] );

        if ( ! $response->success ) {
            return $response;
        }

        $albums = $response->data['topalbums']['album'] ?? [];

        if ( isset( $albums['name'] ) ) {
            $albums = [ $albums ];
        }

        $items = [];
        foreach ( $albums as $album ) {
            $items[] = [
                'name'      => $album['name'] ?? '',
                'title'     => $album['name'] ?? '',
                'artist'    => $album['artist']['name'] ?? '',
                'url'       => $album['url'] ?? '',
                'playcount' => (int) ( $album['playcount'] ?? 0 ),
                'image'     => $this->extractImage( $album['image'] ?? [] ),
                'rank'      => (int) ( $album['@attr']['rank'] ?? 0 ),
            ];
        }

        return $this->buildResponse( true, [
            'items'  => $items,
            'period' => $period,
            'total'  => $response->data['topalbums']['@attr']['total'] ?? count( $items ),
        ] );
    }

    /**
     * Get top tracks.
     *
     * @param array $params Parameters (period, limit).
     * @return ProviderResponse
     */
    public function getTopTracks( array $params = [] ): ProviderResponse {
        $period = $this->validatePeriod( $params['period'] ?? '7day' );
        $limit  = $params['limit'] ?? 10;

        $response = $this->apiRequest( 'user.gettoptracks', [
            'period' => $period,
            'limit'  => min( 50, max( 1, (int) $limit ) ),
        ] );

        if ( ! $response->success ) {
            return $response;
        }

        $tracks = $response->data['toptracks']['track'] ?? [];

        if ( isset( $tracks['name'] ) ) {
            $tracks = [ $tracks ];
        }

        $items = [];
        foreach ( $tracks as $track ) {
            $items[] = [
                'name'      => $track['name'] ?? '',
                'title'     => $track['name'] ?? '',
                'artist'    => $track['artist']['name'] ?? '',
                'url'       => $track['url'] ?? '',
                'playcount' => (int) ( $track['playcount'] ?? 0 ),
                'image'     => $this->extractImage( $track['image'] ?? [] ),
                'rank'      => (int) ( $track['@attr']['rank'] ?? 0 ),
                'duration'  => (int) ( $track['duration'] ?? 0 ),
            ];
        }

        return $this->buildResponse( true, [
            'items'  => $items,
            'period' => $period,
            'total'  => $response->data['toptracks']['@attr']['total'] ?? count( $items ),
        ] );
    }

    /**
     * Get loved tracks.
     *
     * @param array $params Parameters (limit).
     * @return ProviderResponse
     */
    public function getLovedTracks( array $params = [] ): ProviderResponse {
        $limit = $params['limit'] ?? 10;

        $response = $this->apiRequest( 'user.getlovedtracks', [
            'limit' => min( 50, max( 1, (int) $limit ) ),
        ] );

        if ( ! $response->success ) {
            return $response;
        }

        $tracks = $response->data['lovedtracks']['track'] ?? [];

        if ( isset( $tracks['name'] ) ) {
            $tracks = [ $tracks ];
        }

        $items = [];
        foreach ( $tracks as $track ) {
            $items[] = [
                'name'      => $track['name'] ?? '',
                'title'     => $track['name'] ?? '',
                'artist'    => $track['artist']['name'] ?? '',
                'url'       => $track['url'] ?? '',
                'image'     => $this->extractImage( $track['image'] ?? [] ),
                'timestamp' => isset( $track['date']['uts'] ) ? (int) $track['date']['uts'] : null,
                'loved'     => true,
            ];
        }

        return $this->buildResponse( true, [
            'items' => $items,
            'total' => $response->data['lovedtracks']['@attr']['total'] ?? count( $items ),
        ] );
    }

    /**
     * Get user info.
     *
     * @return ProviderResponse
     */
    public function getUserInfo(): ProviderResponse {
        $response = $this->apiRequest( 'user.getinfo' );

        if ( ! $response->success ) {
            return $response;
        }

        $user = $response->data['user'] ?? [];

        return $this->buildResponse( true, [
            'name'       => $user['name'] ?? '',
            'realname'   => $user['realname'] ?? '',
            'url'        => $user['url'] ?? '',
            'image'      => $this->extractImage( $user['image'] ?? [] ),
            'playcount'  => (int) ( $user['playcount'] ?? 0 ),
            'registered' => isset( $user['registered']['unixtime'] )
                ? (int) $user['registered']['unixtime']
                : null,
        ] );
    }

    /**
     * Make an API request to Last.fm.
     *
     * @param string $method API method (e.g., 'user.getrecenttracks').
     * @param array  $params Additional parameters.
     * @return ProviderResponse
     */
    protected function apiRequest( string $method, array $params = [] ): ProviderResponse {
        if ( ! $this->isConfigured() ) {
            return $this->buildResponse( false, null, 'Provider not configured' );
        }

        // Build parameters.
        $params = array_merge( [
            'method'  => $method,
            'api_key' => $this->getApiKey(),
            'user'    => $this->getUsername(),
            'format'  => 'json',
        ], $params );

        // Sort for consistent cache keys.
        ksort( $params );

        $url = $this->apiUrl . '?' . http_build_query( $params );

        $result = $this->request( $url );

        if ( ! $result['success'] ) {
            return $this->buildResponse( false, null, $result['error'] ?? 'Request failed' );
        }

        // Check for Last.fm error response.
        if ( isset( $result['data']['error'] ) ) {
            $errorMessage = $result['data']['message'] ?? 'Unknown Last.fm error';
            $this->log( sprintf( 'API error %d: %s', $result['data']['error'], $errorMessage ) );

            return $this->buildResponse( false, null, $errorMessage );
        }

        return $this->buildResponse( true, $result['data'] );
    }

    /**
     * Get API key.
     *
     * @return string
     */
    protected function getApiKey(): string {
        return (string) $this->getOption( 'api_key' );
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
            'api_key' => 'lastfm_api_key',
            'user'    => 'lastfm_user',
            default   => null,
        };
    }

    /**
     * Validate period parameter.
     *
     * @param string $period The period to validate.
     * @return string Valid period.
     */
    protected function validatePeriod( string $period ): string {
        $valid = [ 'overall', '7day', '1month', '3month', '6month', '12month' ];
        return in_array( $period, $valid, true ) ? $period : '7day';
    }

    /**
     * Extract best image URL from Last.fm image array.
     *
     * @param array $images Array of image objects.
     * @param string $preferredSize Preferred size (small, medium, large, extralarge).
     * @return string Image URL or empty string.
     */
    protected function extractImage( array $images, string $preferredSize = 'large' ): string {
        if ( empty( $images ) ) {
            return '';
        }

        $sizeOrder = [ 'extralarge', 'large', 'medium', 'small' ];

        // Find preferred size first.
        foreach ( $images as $image ) {
            if ( ( $image['size'] ?? '' ) === $preferredSize && ! empty( $image['#text'] ) ) {
                return $image['#text'];
            }
        }

        // Fall back to largest available.
        foreach ( $sizeOrder as $size ) {
            foreach ( $images as $image ) {
                if ( ( $image['size'] ?? '' ) === $size && ! empty( $image['#text'] ) ) {
                    return $image['#text'];
                }
            }
        }

        // Return first non-empty image.
        foreach ( $images as $image ) {
            if ( ! empty( $image['#text'] ) ) {
                return $image['#text'];
            }
        }

        return '';
    }
}
