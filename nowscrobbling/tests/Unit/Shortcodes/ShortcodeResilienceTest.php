<?php

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit\Shortcodes;

use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Api\TraktClient;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Shortcodes\LastFm\HistoryShortcode as LastFmHistoryShortcode;
use NowScrobbling\Shortcodes\Renderer\HashGenerator;
use NowScrobbling\Shortcodes\Renderer\OutputRenderer;
use NowScrobbling\Shortcodes\Trakt\HistoryShortcode as TraktHistoryShortcode;
use NowScrobbling\Tests\TestCase;

final class ShortcodeResilienceTest extends TestCase
{
    public function testLastFmHistoryRendersEmptyStateOnEmptyApiPayload(): void
    {
        $this->setOption('ns_lastfm_api_key', 'test-key');
        $this->setOption('ns_lastfm_user', 'test-user');

        wp_mock_http_set_handler(static fn(string $url, array $args): array => [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
            'body' => '{}',
        ]);

        $shortcode = new LastFmHistoryShortcode(
            new OutputRenderer(),
            new HashGenerator(),
            new LastFmClient(new CacheManager())
        );

        $html = $shortcode->render([]);

        self::assertStringContainsString('No recent tracks.', $html);
        self::assertStringContainsString('data-nowscrobbling-shortcode="nowscr_lastfm_history"', $html);
    }

    public function testTraktHistoryRendersEmptyStateOnInvalidJsonResponse(): void
    {
        $this->setOption('ns_trakt_client_id', 'test-client');
        $this->setOption('ns_trakt_user', 'test-user');

        wp_mock_http_set_handler(static fn(string $url, array $args): array => [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
            'body' => '{invalid-json',
        ]);

        $shortcode = new TraktHistoryShortcode(
            new OutputRenderer(),
            new HashGenerator(),
            new TraktClient(new CacheManager())
        );

        $html = $shortcode->render([]);

        self::assertStringContainsString('No watch history.', $html);
        self::assertStringContainsString('data-nowscrobbling-shortcode="nowscr_trakt_history"', $html);
    }
}
