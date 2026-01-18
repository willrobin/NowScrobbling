<?php

declare(strict_types=1);

namespace NowScrobbling\Rest;

use NowScrobbling\Container;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Security\NonceManager;
use NowScrobbling\Shortcodes\ShortcodeManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller
 *
 * Replaces admin-ajax.php with proper REST API endpoints.
 *
 * Endpoints:
 * - GET  /nowscrobbling/v1/render/{shortcode} - Render shortcode
 * - GET  /nowscrobbling/v1/status             - Get plugin status
 * - POST /nowscrobbling/v1/cache/clear        - Clear cache
 *
 * @package NowScrobbling\Rest
 */
final class RestController
{
    private const NAMESPACE = 'nowscrobbling/v1';

    private readonly Container $container;
    private readonly NonceManager $nonces;

    /**
     * Allowed shortcodes for REST API
     *
     * @var array<string>
     */
    private const ALLOWED_SHORTCODES = [
        'nowscr_lastfm_indicator',
        'nowscr_lastfm_history',
        'nowscr_lastfm_top_artists',
        'nowscr_lastfm_top_albums',
        'nowscr_lastfm_top_tracks',
        'nowscr_lastfm_lovedtracks',
        'nowscr_trakt_indicator',
        'nowscr_trakt_history',
        'nowscr_trakt_last_movie',
        'nowscr_trakt_last_show',
        'nowscr_trakt_last_episode',
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->nonces = new NonceManager();
    }

    /**
     * Register REST API routes
     */
    public function register(): void
    {
        // Public: Render shortcode
        register_rest_route(self::NAMESPACE, '/render/(?P<shortcode>[a-z_]+)', [
            'methods' => 'GET',
            'callback' => $this->renderShortcode(...),
            'permission_callback' => '__return_true',
            'args' => [
                'shortcode' => [
                    'required' => true,
                    'validate_callback' => $this->validateShortcode(...),
                    'sanitize_callback' => 'sanitize_key',
                ],
                'hash' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Public: Get status (limited info)
        register_rest_route(self::NAMESPACE, '/status', [
            'methods' => 'GET',
            'callback' => $this->getStatus(...),
            'permission_callback' => '__return_true',
        ]);

        // Admin: Clear cache
        register_rest_route(self::NAMESPACE, '/cache/clear', [
            'methods' => 'POST',
            'callback' => $this->clearCache(...),
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Admin: Test API connection
        register_rest_route(self::NAMESPACE, '/test/(?P<service>lastfm|trakt)', [
            'methods' => 'POST',
            'callback' => $this->testConnection(...),
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'service' => [
                    'required' => true,
                    'validate_callback' => fn($v) => in_array($v, ['lastfm', 'trakt'], true),
                ],
            ],
        ]);
    }

    /**
     * Render a shortcode via REST API
     */
    public function renderShortcode(WP_REST_Request $request): WP_REST_Response
    {
        $shortcode = $request->get_param('shortcode');
        $clientHash = $request->get_param('hash');

        // Render the shortcode
        $html = do_shortcode("[{$shortcode}]");

        // Extract hash from output
        preg_match('/data-ns-hash="([^"]+)"/', $html, $matches);
        $hash = $matches[1] ?? md5($html);

        // If hash matches, return 304-like response
        if ($clientHash !== null && $clientHash === $hash) {
            return new WP_REST_Response([
                'unchanged' => true,
                'hash' => $hash,
            ], 200);
        }

        return new WP_REST_Response([
            'html' => $html,
            'hash' => $hash,
            'timestamp' => time(),
        ], 200);
    }

    /**
     * Get plugin status
     */
    public function getStatus(WP_REST_Request $request): WP_REST_Response
    {
        $isAdmin = current_user_can('manage_options');

        $data = [
            'version' => NOWSCROBBLING_VERSION,
            'timestamp' => time(),
        ];

        // Add more details for admins
        if ($isAdmin && $this->container->has(CacheManager::class)) {
            $cache = $this->container->make(CacheManager::class);
            $data['cache'] = $cache->getDiagnostics();
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Clear cache
     */
    public function clearCache(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->container->has(CacheManager::class)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Cache manager not available',
            ], 500);
        }

        $cache = $this->container->make(CacheManager::class);
        $type = $request->get_param('type') ?? 'all';

        $success = match ($type) {
            'primary' => $cache->clearPrimary(),
            default => $cache->clearAll(),
        };

        return new WP_REST_Response([
            'success' => $success,
            'message' => $success
                ? __('Cache cleared successfully.', 'nowscrobbling')
                : __('Failed to clear cache.', 'nowscrobbling'),
            'timestamp' => time(),
        ], $success ? 200 : 500);
    }

    /**
     * Test API connection
     */
    public function testConnection(WP_REST_Request $request): WP_REST_Response
    {
        $service = $request->get_param('service');

        $clientClass = match ($service) {
            'lastfm' => \NowScrobbling\Api\LastFmClient::class,
            'trakt' => \NowScrobbling\Api\TraktClient::class,
            default => null,
        };

        if ($clientClass === null || !$this->container->has($clientClass)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Service not available',
            ], 400);
        }

        $client = $this->container->make($clientClass);
        $response = $client->testConnection();

        return new WP_REST_Response([
            'success' => $response->success,
            'message' => $response->success
                ? __('Connection successful!', 'nowscrobbling')
                : ($response->error ?? __('Connection failed.', 'nowscrobbling')),
            'http_code' => $response->httpCode,
        ], $response->success ? 200 : 400);
    }

    /**
     * Validate shortcode parameter
     */
    private function validateShortcode(string $shortcode): bool
    {
        return in_array($shortcode, self::ALLOWED_SHORTCODES, true);
    }
}
