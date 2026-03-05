<?php

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit\Api;

use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Api\TraktClient;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Tests\TestCase;

final class ApiClientsResilienceTest extends TestCase
{
    public function testTraktWatchingReturnsEmptySuccessOn204Response(): void
    {
        $this->setOption('ns_trakt_client_id', 'client');
        $this->setOption('ns_trakt_user', 'user');

        wp_mock_http_set_handler(static fn(string $url, array $args): array => [
            'response' => ['code' => 204, 'message' => 'No Content'],
            'headers' => [],
            'body' => '',
        ]);

        $client = new TraktClient(new CacheManager());
        $response = $client->getWatching();

        self::assertTrue($response->success);
        self::assertSame([], $response->data);
        self::assertSame(200, $response->httpCode);
    }

    public function testLastFmRecentTracksReturnsErrorOnInvalidJson(): void
    {
        $this->setOption('ns_lastfm_api_key', 'key');
        $this->setOption('ns_lastfm_user', 'user');

        wp_mock_http_set_handler(static fn(string $url, array $args): array => [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
            'body' => '{invalid',
        ]);

        $client = new LastFmClient(new CacheManager());
        $response = $client->getRecentTracks(1);

        self::assertTrue($response->isError());
        self::assertSame('Invalid JSON response', $response->error);
    }

    public function testTraktHistoryTriggersRateLimiterAfter429(): void
    {
        $this->setOption('ns_trakt_client_id', 'client');
        $this->setOption('ns_trakt_user', 'user');

        wp_mock_http_set_handler(static fn(string $url, array $args): array => [
            'response' => ['code' => 429, 'message' => 'Too Many Requests'],
            'headers' => [],
            'body' => '{}',
        ]);

        $client = new TraktClient(new CacheManager());

        $first = $client->getHistory('all', 1);
        self::assertTrue($first->isError());
        self::assertSame(429, $first->httpCode);

        $second = $client->getHistory('all', 1);
        self::assertTrue($second->isRateLimited());
        self::assertTrue($second->isError());
    }

    public function testLastFmRecentTracksReturnsErrorOn304WithoutFallback(): void
    {
        $this->setOption('ns_lastfm_api_key', 'key');
        $this->setOption('ns_lastfm_user', 'user');

        wp_mock_http_set_handler(static fn(string $url, array $args): array => [
            'response' => ['code' => 304, 'message' => 'Not Modified'],
            'headers' => [],
            'body' => '',
        ]);

        $client = new LastFmClient(new CacheManager());
        $response = $client->getRecentTracks(1);

        self::assertTrue($response->isError());
        self::assertSame(500, $response->httpCode);
    }
}
