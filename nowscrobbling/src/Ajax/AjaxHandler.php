<?php

declare(strict_types=1);

namespace NowScrobbling\Ajax;

use NowScrobbling\Container;
use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Api\TraktClient;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Shortcodes\ShortcodeManager;

/**
 * AJAX Handler
 *
 * Provides admin-ajax.php fallback for environments where REST API is disabled.
 *
 * @package NowScrobbling\Ajax
 */
final class AjaxHandler
{
    private readonly Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register AJAX handlers
     */
    public function register(): void
    {
        // API Test (admin only)
        add_action('wp_ajax_nowscrobbling_test_api', $this->handleApiTest(...));

        // Cache Clear (admin only)
        add_action('wp_ajax_nowscrobbling_clear_cache', $this->handleCacheClear(...));

        // Render shortcode (public)
        add_action('wp_ajax_nowscrobbling_render', $this->handleRender(...));
        add_action('wp_ajax_nopriv_nowscrobbling_render', $this->handleRender(...));
    }

    /**
     * Handle API test request
     */
    public function handleApiTest(): void
    {
        // Verify nonce
        if (!check_ajax_referer('ns_api_test', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $service = sanitize_text_field(wp_unslash($_POST['service'] ?? ''));

        if (!in_array($service, ['lastfm', 'trakt'], true)) {
            wp_send_json_error(['message' => 'Invalid service'], 400);
        }

        try {
            if ($service === 'lastfm') {
                $result = $this->testLastFm();
            } else {
                $result = $this->testTrakt();
            }

            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle cache clear request
     */
    public function handleCacheClear(): void
    {
        // Verify nonce
        if (!check_ajax_referer('ns_cache_clear', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        try {
            $cacheManager = $this->container->make(CacheManager::class);
            $cacheManager->clearAll();

            wp_send_json_success(['message' => 'Cache cleared successfully']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle shortcode render request
     */
    public function handleRender(): void
    {
        // Verify nonce
        if (!check_ajax_referer('ns_render', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $shortcode = sanitize_text_field(wp_unslash($_POST['shortcode'] ?? ''));

        if (empty($shortcode)) {
            wp_send_json_error(['message' => 'Missing shortcode'], 400);
        }

        // Validate shortcode is allowed
        $allowedShortcodes = [
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

        if (!in_array($shortcode, $allowedShortcodes, true)) {
            wp_send_json_error(['message' => 'Invalid shortcode'], 400);
        }

        // Get attributes
        $atts = [];
        if (!empty($_POST['atts'])) {
            $atts = json_decode(stripslashes($_POST['atts']), true) ?: [];
        }

        // Build shortcode string
        $shortcodeStr = '[' . $shortcode;
        foreach ($atts as $key => $value) {
            $shortcodeStr .= ' ' . sanitize_key($key) . '="' . esc_attr($value) . '"';
        }
        $shortcodeStr .= ']';

        // Execute shortcode
        $html = do_shortcode($shortcodeStr);

        wp_send_json_success([
            'html' => $html,
            'shortcode' => $shortcode,
        ]);
    }

    /**
     * Test Last.fm connection
     */
    private function testLastFm(): array
    {
        $apiKey = get_option('ns_lastfm_api_key', '');
        $user = get_option('ns_lastfm_user', '');

        if (empty($apiKey) || empty($user)) {
            return [
                'status' => 'error',
                'message' => 'API Key or Username not configured',
            ];
        }

        $url = sprintf(
            'https://ws.audioscrobbler.com/2.0/?method=user.getinfo&user=%s&api_key=%s&format=json',
            urlencode($user),
            urlencode($apiKey)
        );

        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || isset($body['error'])) {
            return [
                'status' => 'error',
                'message' => $body['message'] ?? 'Unknown error',
            ];
        }

        return [
            'status' => 'success',
            'message' => sprintf('Connected as %s', $body['user']['name'] ?? $user),
            'data' => [
                'name' => $body['user']['name'] ?? '',
                'playcount' => $body['user']['playcount'] ?? 0,
            ],
        ];
    }

    /**
     * Test Trakt connection
     */
    private function testTrakt(): array
    {
        $clientId = get_option('ns_trakt_client_id', '');
        $user = get_option('ns_trakt_user', '');

        if (empty($clientId) || empty($user)) {
            return [
                'status' => 'error',
                'message' => 'Client ID or Username not configured',
            ];
        }

        $url = sprintf('https://api.trakt.tv/users/%s', urlencode($user));

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'trakt-api-version' => '2',
                'trakt-api-key' => $clientId,
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return [
                'status' => 'error',
                'message' => $body['error'] ?? 'Unknown error (HTTP ' . $code . ')',
            ];
        }

        return [
            'status' => 'success',
            'message' => sprintf('Connected as %s', $body['username'] ?? $user),
            'data' => [
                'username' => $body['username'] ?? '',
                'name' => $body['name'] ?? '',
            ],
        ];
    }
}
