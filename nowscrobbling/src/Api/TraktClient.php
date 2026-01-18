<?php

declare(strict_types=1);

namespace NowScrobbling\Api;

use NowScrobbling\Api\Response\ApiResponse;
use NowScrobbling\Cache\CacheManager;

/**
 * Trakt.tv API Client
 *
 * Handles all Trakt.tv API requests:
 * - users/{user}/watching (currently watching)
 * - users/{user}/history (watch history)
 * - users/{user}/ratings (user ratings)
 *
 * @package NowScrobbling\Api
 */
final class TraktClient extends AbstractApiClient
{
    /**
     * Trakt API version
     */
    private const API_VERSION = '2';

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
        return 'trakt';
    }

    /**
     * @inheritDoc
     */
    public function getBaseUrl(): string
    {
        return 'https://api.trakt.tv/';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultTtl(): int
    {
        return (int) get_option('ns_trakt_cache_duration', 5) * 60;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'trakt-api-version' => self::API_VERSION,
            'trakt-api-key' => get_option('ns_trakt_client_id', ''),
            'User-Agent' => 'NowScrobbling/2.0 WordPress Plugin',
        ];
    }

    /**
     * @inheritDoc
     */
    public function isConfigured(): bool
    {
        $clientId = get_option('ns_trakt_client_id', '');
        $user = get_option('ns_trakt_user', '');

        return !empty($clientId) && !empty($user);
    }

    /**
     * @inheritDoc
     */
    protected function performConnectionTest(): ApiResponse
    {
        return $this->getWatching();
    }

    /**
     * Get currently watching item
     */
    public function getWatching(): ApiResponse
    {
        $user = get_option('ns_trakt_user');

        return $this->fetch("users/{$user}/watching", [], ['ttl' => 30]);
    }

    /**
     * Get watch history
     *
     * @param string $type  Type filter (movies, shows, episodes, all)
     * @param int    $limit Number of items to retrieve
     */
    public function getHistory(string $type = 'all', int $limit = 10): ApiResponse
    {
        $user = get_option('ns_trakt_user');
        $endpoint = $type === 'all'
            ? "users/{$user}/history"
            : "users/{$user}/history/{$type}";

        return $this->fetch($endpoint, ['limit' => $limit]);
    }

    /**
     * Get movie history
     *
     * @param int $limit Number of movies to retrieve
     */
    public function getMovieHistory(int $limit = 5): ApiResponse
    {
        return $this->getHistory('movies', $limit);
    }

    /**
     * Get show history
     *
     * @param int $limit Number of shows to retrieve
     */
    public function getShowHistory(int $limit = 5): ApiResponse
    {
        return $this->getHistory('shows', $limit);
    }

    /**
     * Get episode history
     *
     * @param int $limit Number of episodes to retrieve
     */
    public function getEpisodeHistory(int $limit = 5): ApiResponse
    {
        return $this->getHistory('episodes', $limit);
    }

    /**
     * Get user ratings
     *
     * @param string $type Type filter (movies, shows, episodes, all)
     */
    public function getRatings(string $type = 'all'): ApiResponse
    {
        $user = get_option('ns_trakt_user');
        $endpoint = $type === 'all'
            ? "users/{$user}/ratings"
            : "users/{$user}/ratings/{$type}";

        return $this->fetch($endpoint);
    }

    /**
     * Check if user is currently watching something
     */
    public function isWatching(): bool
    {
        $response = $this->getWatching();

        // Empty response means not watching
        if ($response->isError() || empty($response->data)) {
            return false;
        }

        // Check for valid watching data
        return isset($response->data['type']);
    }

    /**
     * Get current watching info
     *
     * @return array<string, mixed>|null Watching info or null
     */
    public function getCurrentlyWatching(): ?array
    {
        $response = $this->getWatching();

        if ($response->isError() || empty($response->data)) {
            return null;
        }

        $data = $response->data;
        $type = $data['type'] ?? null;

        if ($type === null) {
            return null;
        }

        return match ($type) {
            'movie' => $this->formatMovie($data),
            'episode' => $this->formatEpisode($data),
            default => null,
        };
    }

    /**
     * Format movie data for display
     *
     * @param array<string, mixed> $data Watching data
     *
     * @return array<string, mixed>
     */
    private function formatMovie(array $data): array
    {
        $movie = $data['movie'] ?? [];

        return [
            'type' => 'movie',
            'title' => $movie['title'] ?? '',
            'year' => $movie['year'] ?? null,
            'ids' => $movie['ids'] ?? [],
            'started_at' => $data['started_at'] ?? null,
        ];
    }

    /**
     * Format episode data for display
     *
     * @param array<string, mixed> $data Watching data
     *
     * @return array<string, mixed>
     */
    private function formatEpisode(array $data): array
    {
        $show = $data['show'] ?? [];
        $episode = $data['episode'] ?? [];

        return [
            'type' => 'episode',
            'show_title' => $show['title'] ?? '',
            'show_year' => $show['year'] ?? null,
            'season' => $episode['season'] ?? 0,
            'episode' => $episode['number'] ?? 0,
            'episode_title' => $episode['title'] ?? '',
            'ids' => [
                'show' => $show['ids'] ?? [],
                'episode' => $episode['ids'] ?? [],
            ],
            'started_at' => $data['started_at'] ?? null,
        ];
    }

    /**
     * Get last watched movie
     *
     * @return array<string, mixed>|null
     */
    public function getLastMovie(): ?array
    {
        $response = $this->getMovieHistory(1);

        if ($response->isError() || empty($response->data)) {
            return null;
        }

        $item = $response->data[0] ?? null;

        if ($item === null) {
            return null;
        }

        return [
            'type' => 'movie',
            'title' => $item['movie']['title'] ?? '',
            'year' => $item['movie']['year'] ?? null,
            'watched_at' => $item['watched_at'] ?? null,
            'ids' => $item['movie']['ids'] ?? [],
        ];
    }

    /**
     * Get last watched show
     *
     * @return array<string, mixed>|null
     */
    public function getLastShow(): ?array
    {
        $response = $this->getShowHistory(1);

        if ($response->isError() || empty($response->data)) {
            return null;
        }

        $item = $response->data[0] ?? null;

        if ($item === null) {
            return null;
        }

        return [
            'type' => 'show',
            'title' => $item['show']['title'] ?? '',
            'year' => $item['show']['year'] ?? null,
            'watched_at' => $item['watched_at'] ?? null,
            'ids' => $item['show']['ids'] ?? [],
        ];
    }

    /**
     * Get last watched episode
     *
     * @return array<string, mixed>|null
     */
    public function getLastEpisode(): ?array
    {
        $response = $this->getEpisodeHistory(1);

        if ($response->isError() || empty($response->data)) {
            return null;
        }

        $item = $response->data[0] ?? null;

        if ($item === null) {
            return null;
        }

        $show = $item['show'] ?? [];
        $episode = $item['episode'] ?? [];

        return [
            'type' => 'episode',
            'show_title' => $show['title'] ?? '',
            'season' => $episode['season'] ?? 0,
            'episode' => $episode['number'] ?? 0,
            'episode_title' => $episode['title'] ?? '',
            'watched_at' => $item['watched_at'] ?? null,
            'ids' => [
                'show' => $show['ids'] ?? [],
                'episode' => $episode['ids'] ?? [],
            ],
        ];
    }

    /**
     * Format episode for display
     *
     * @param array<string, mixed> $data      Episode data
     * @param int                  $maxLength Maximum string length
     */
    public function formatEpisodeString(array $data, int $maxLength = 45): string
    {
        $show = $data['show_title'] ?? '';
        $season = $data['season'] ?? 0;
        $episode = $data['episode'] ?? 0;

        $formatted = sprintf('%s S%02dE%02d', $show, $season, $episode);

        if (mb_strlen($formatted) > $maxLength) {
            $formatted = mb_substr($formatted, 0, $maxLength - 1) . '…';
        }

        return $formatted;
    }
}
