<?php

declare(strict_types=1);

namespace NowScrobbling\Api;

use NowScrobbling\Api\Response\ApiResponse;
use NowScrobbling\Cache\CacheManager;

/**
 * Last.fm API Client
 *
 * Handles all Last.fm API requests:
 * - user.getRecentTracks (now playing, history)
 * - user.getTopArtists/Albums/Tracks
 * - user.getLovedTracks
 *
 * @package NowScrobbling\Api
 */
final class LastFmClient extends AbstractApiClient
{
    /**
     * Constructor
     */
    public function __construct(CacheManager $cache)
    {
        parent::__construct($cache);
    }

    /**
     * @inheritDoc
     */
    public function getService(): string
    {
        return 'lastfm';
    }

    /**
     * @inheritDoc
     */
    public function getBaseUrl(): string
    {
        return 'https://ws.audioscrobbler.com/2.0/';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultTtl(): int
    {
        return (int) get_option('ns_lastfm_cache_duration', 1) * 60;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
            'User-Agent' => 'NowScrobbling/2.0 WordPress Plugin',
        ];
    }

    /**
     * @inheritDoc
     */
    public function isConfigured(): bool
    {
        $apiKey = get_option('ns_lastfm_api_key', '');
        $user = get_option('ns_lastfm_user', '');

        return !empty($apiKey) && !empty($user);
    }

    /**
     * @inheritDoc
     */
    protected function performConnectionTest(): ApiResponse
    {
        return $this->getRecentTracks(1);
    }

    /**
     * Get recent tracks (scrobble history)
     *
     * @param int $limit Number of tracks to retrieve
     */
    public function getRecentTracks(int $limit = 5): ApiResponse
    {
        return $this->fetch('', [
            'method' => 'user.getrecenttracks',
            'user' => get_option('ns_lastfm_user'),
            'api_key' => get_option('ns_lastfm_api_key'),
            'format' => 'json',
            'limit' => $limit,
        ], ['ttl' => 30]); // Shorter TTL for now-playing detection
    }

    /**
     * Get top artists
     *
     * @param string $period Time period (7day, 1month, 3month, 6month, 12month, overall)
     * @param int    $limit  Number of artists to retrieve
     */
    public function getTopArtists(string $period = '7day', int $limit = 5): ApiResponse
    {
        return $this->fetch('', [
            'method' => 'user.gettopartists',
            'user' => get_option('ns_lastfm_user'),
            'api_key' => get_option('ns_lastfm_api_key'),
            'format' => 'json',
            'period' => $period,
            'limit' => $limit,
        ]);
    }

    /**
     * Get top albums
     *
     * @param string $period Time period
     * @param int    $limit  Number of albums to retrieve
     */
    public function getTopAlbums(string $period = '7day', int $limit = 5): ApiResponse
    {
        return $this->fetch('', [
            'method' => 'user.gettopalbums',
            'user' => get_option('ns_lastfm_user'),
            'api_key' => get_option('ns_lastfm_api_key'),
            'format' => 'json',
            'period' => $period,
            'limit' => $limit,
        ]);
    }

    /**
     * Get top tracks
     *
     * @param string $period Time period
     * @param int    $limit  Number of tracks to retrieve
     */
    public function getTopTracks(string $period = '7day', int $limit = 5): ApiResponse
    {
        return $this->fetch('', [
            'method' => 'user.gettoptracks',
            'user' => get_option('ns_lastfm_user'),
            'api_key' => get_option('ns_lastfm_api_key'),
            'format' => 'json',
            'period' => $period,
            'limit' => $limit,
        ]);
    }

    /**
     * Get loved tracks
     *
     * @param int $limit Number of tracks to retrieve
     */
    public function getLovedTracks(int $limit = 5): ApiResponse
    {
        return $this->fetch('', [
            'method' => 'user.getlovedtracks',
            'user' => get_option('ns_lastfm_user'),
            'api_key' => get_option('ns_lastfm_api_key'),
            'format' => 'json',
            'limit' => $limit,
        ]);
    }

    /**
     * Check if user is currently playing a track
     *
     * @param array<string, mixed>|null $recentTracks Recent tracks data (optional)
     */
    public function isNowPlaying(?array $recentTracks = null): bool
    {
        if ($recentTracks === null) {
            $response = $this->getRecentTracks(1);
            if ($response->isError()) {
                return false;
            }
            $recentTracks = $response->data;
        }

        $tracks = $recentTracks['recenttracks']['track'] ?? [];

        if (empty($tracks)) {
            return false;
        }

        $firstTrack = is_array($tracks[0] ?? null) ? $tracks[0] : $tracks;

        return isset($firstTrack['@attr']['nowplaying'])
            && $firstTrack['@attr']['nowplaying'] === 'true';
    }

    /**
     * Get current track info if playing
     *
     * @return array<string, mixed>|null Track info or null
     */
    public function getCurrentTrack(): ?array
    {
        $response = $this->getRecentTracks(1);

        if ($response->isError()) {
            return null;
        }

        $tracks = $response->data['recenttracks']['track'] ?? [];

        if (empty($tracks)) {
            return null;
        }

        $firstTrack = is_array($tracks[0] ?? null) ? $tracks[0] : $tracks;

        if (!isset($firstTrack['@attr']['nowplaying'])) {
            return null;
        }

        return [
            'artist' => $firstTrack['artist']['#text'] ?? $firstTrack['artist'] ?? '',
            'track' => $firstTrack['name'] ?? '',
            'album' => $firstTrack['album']['#text'] ?? '',
            'image' => $this->extractImage($firstTrack),
            'url' => $firstTrack['url'] ?? '',
            'now_playing' => true,
        ];
    }

    /**
     * Extract best image URL from track data
     *
     * @param array<string, mixed> $track Track data
     */
    private function extractImage(array $track): string
    {
        $images = $track['image'] ?? [];

        if (empty($images)) {
            return '';
        }

        // Prefer large or extralarge
        foreach (array_reverse($images) as $image) {
            $url = $image['#text'] ?? '';
            if (!empty($url) && !str_contains($url, 'lastfm/i/u/34s/')) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Format track for display
     *
     * @param array<string, mixed> $track    Track data
     * @param int                  $maxLength Maximum string length
     */
    public function formatTrack(array $track, int $maxLength = 45): string
    {
        $artist = $track['artist']['#text'] ?? $track['artist'] ?? '';
        $name = $track['name'] ?? '';

        $formatted = "$artist - $name";

        if (mb_strlen($formatted) > $maxLength) {
            $formatted = mb_substr($formatted, 0, $maxLength - 1) . '…';
        }

        return $formatted;
    }
}
