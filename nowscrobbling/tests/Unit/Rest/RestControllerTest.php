<?php

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit\Rest;

use NowScrobbling\Container;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Rest\RestController;
use NowScrobbling\Tests\TestCase;
use ReflectionClass;

final class RestControllerTest extends TestCase
{
    public function testValidateShortcodeAllowsKnownTagsOnly(): void
    {
        $controller = new RestController(Container::getInstance());

        self::assertTrue($this->invokePrivate($controller, 'validateShortcode', ['nowscr_trakt_history']));
        self::assertFalse($this->invokePrivate($controller, 'validateShortcode', ['nowscr_unknown']));
    }

    public function testParseAttrsSanitizesAndFiltersByAllowlist(): void
    {
        $controller = new RestController(Container::getInstance());

        $raw = json_encode([
            'MAX_LENGTH' => '<b>120</b>',
            'style' => 'bubble',
            'period' => '7day',
            'limit' => '10',
            'type' => 'movies',
            'not_allowed' => 'drop-me',
            'limit_array' => ['invalid'],
        ]);

        $actual = $this->invokePrivate($controller, 'parseAttrs', [$raw]);

        self::assertSame([
            'max_length' => '120',
            'style' => 'bubble',
            'period' => '7day',
            'limit' => '10',
            'type' => 'movies',
        ], $actual);
    }

    public function testParseAttrsReturnsEmptyForInvalidJson(): void
    {
        $controller = new RestController(Container::getInstance());

        self::assertSame([], $this->invokePrivate($controller, 'parseAttrs', ['not-json']));
        self::assertSame([], $this->invokePrivate($controller, 'parseAttrs', ['"string"']));
        self::assertSame([], $this->invokePrivate($controller, 'parseAttrs', [null]));
    }

    public function testBuildShortcodeEscapesAttributeValues(): void
    {
        $controller = new RestController(Container::getInstance());

        $shortcode = $this->invokePrivate($controller, 'buildShortcode', [
            'nowscr_lastfm_history',
            [
                'max_length' => '45" onclick="alert(1)',
                'style' => 'bubble<script>',
            ],
        ]);

        self::assertStringStartsWith('[nowscr_lastfm_history ', $shortcode);
        self::assertStringContainsString('max_length="45&quot; onclick=&quot;alert(1)"', $shortcode);
        self::assertStringContainsString('style="bubble&lt;script&gt;"', $shortcode);
    }

    public function testRegisterAddsExpectedRoutes(): void
    {
        global $wp_mock_rest_routes;

        $controller = new RestController(Container::getInstance());
        $controller->register();

        $routes = array_column($wp_mock_rest_routes, 'route');

        self::assertContains('/render/(?P<shortcode>[a-z_]+)', $routes);
        self::assertContains('/status', $routes);
        self::assertContains('/cache/clear', $routes);
        self::assertContains('/test/(?P<service>lastfm|trakt)', $routes);
    }

    public function testRenderShortcodeReturnsHtmlAndHash(): void
    {
        $controller = new RestController(Container::getInstance());
        $request = new \WP_REST_Request('GET', '/nowscrobbling/v1/render/nowscr_lastfm_history');
        $request->set_param('shortcode', 'nowscr_lastfm_history');
        $request->set_param('attrs', json_encode(['max_length' => '55', 'not_allowed' => 'x']));

        $response = $controller->renderShortcode($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertArrayHasKey('html', $data);
        self::assertArrayHasKey('hash', $data);
        self::assertSame(['max_length' => '55'], $data['attrs']);
        self::assertStringContainsString('data-nowscrobbling-shortcode="nowscr_lastfm_history"', $data['html']);
    }

    public function testGetStatusReturnsCacheDiagnosticsForAdmin(): void
    {
        $container = Container::getInstance();
        $container->singleton(CacheManager::class, fn(): CacheManager => new CacheManager());

        $controller = new RestController($container);
        $request = new \WP_REST_Request('GET', '/nowscrobbling/v1/status');

        $response = $controller->getStatus($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertSame('2.0.0', $data['version']);
        self::assertArrayHasKey('cache', $data);
    }

    public function testClearCacheReturnsSuccessWhenCacheManagerExists(): void
    {
        global $wpdb;
        $wpdb = new class {
            public string $options = 'wp_options';

            public function query(string $query): int
            {
                return 1;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return vsprintf(str_replace('%s', "'%s'", $query), $args);
            }

            public function esc_like(string $text): string
            {
                return $text;
            }
        };

        $container = Container::getInstance();
        $container->singleton(CacheManager::class, fn(): CacheManager => new CacheManager());

        $controller = new RestController($container);
        $request = new \WP_REST_Request('POST', '/nowscrobbling/v1/cache/clear');
        $request->set_param('type', 'primary');

        $response = $controller->clearCache($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertTrue($data['success']);
    }

    public function testTestConnectionReturns400ForUnavailableService(): void
    {
        $controller = new RestController(Container::getInstance());
        $request = new \WP_REST_Request('POST', '/nowscrobbling/v1/test/lastfm');
        $request->set_param('service', 'lastfm');

        $response = $controller->testConnection($request);
        $data = $response->get_data();

        self::assertSame(400, $response->get_status());
        self::assertFalse($data['success']);
    }

    public function testTestConnectionReturnsSuccessForConfiguredLastFmClient(): void
    {
        $this->setOption('ns_lastfm_api_key', 'key');
        $this->setOption('ns_lastfm_user', 'user');

        wp_mock_http_set_handler(static fn(string $url, array $args): array => [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
            'body' => '{"recenttracks":{"track":[]}}',
        ]);

        $container = Container::getInstance();
        $container->singleton(CacheManager::class, fn(): CacheManager => new CacheManager());
        $container->singleton(
            LastFmClient::class,
            fn(Container $c): LastFmClient => new LastFmClient($c->make(CacheManager::class))
        );

        $controller = new RestController($container);
        $request = new \WP_REST_Request('POST', '/nowscrobbling/v1/test/lastfm');
        $request->set_param('service', 'lastfm');

        $response = $controller->testConnection($request);
        $data = $response->get_data();

        self::assertSame(200, $response->get_status());
        self::assertTrue($data['success']);
    }

    private function invokePrivate(object $target, string $method, array $args): mixed
    {
        $reflection = new ReflectionClass($target);
        $instance = $reflection->getMethod($method);
        $instance->setAccessible(true);

        return $instance->invokeArgs($target, $args);
    }
}
