<?php

declare(strict_types=1);

namespace NowScrobbling\Core;

use NowScrobbling\Container;
use NowScrobbling\Plugin;

/**
 * Bootstrap class for hook registration
 *
 * Centralizes all WordPress hook registrations. Hooks are registered
 * here (not in constructors) for better testability.
 *
 * @package NowScrobbling\Core
 */
final class Bootstrap
{
    /**
     * Whether hooks have been registered
     */
    private bool $initialized = false;

    /**
     * Constructor
     *
     * @param Container $container The DI container
     */
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * Initialize the plugin by registering all hooks
     *
     * This method should only be called once.
     */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->registerCoreHooks();
        $this->registerAssetHooks();
        $this->registerCronHooks();

        // Register admin-only hooks
        if (is_admin()) {
            $this->registerAdminHooks();
        }

        $this->initialized = true;
    }

    /**
     * Register core WordPress hooks
     */
    private function registerCoreHooks(): void
    {
        // Load text domain for translations
        add_action('init', $this->loadTextDomain(...), 5);

        // Register shortcodes
        add_action('init', $this->registerShortcodes(...), 10);

        // Register REST API routes
        add_action('rest_api_init', $this->registerRestRoutes(...));
    }

    /**
     * Register asset loading hooks
     */
    private function registerAssetHooks(): void
    {
        // Front-end assets (conditional loading)
        add_action('wp_enqueue_scripts', $this->enqueuePublicAssets(...));
    }

    /**
     * Register cron hooks
     */
    private function registerCronHooks(): void
    {
        // Register custom cron intervals
        add_filter('cron_schedules', $this->addCronIntervals(...));

        // Schedule cron jobs on 'wp' hook (after WordPress is fully loaded)
        add_action('wp', $this->scheduleCronJobs(...));

        // Cron event handlers
        add_action('nowscrobbling_cache_refresh', $this->handleCacheRefresh(...));
        add_action('nowscrobbling_nowplaying_tick', $this->handleNowPlayingTick(...));
    }

    /**
     * Register admin-only hooks
     */
    private function registerAdminHooks(): void
    {
        // Admin menu
        add_action('admin_menu', $this->registerAdminMenu(...));

        // Admin settings
        add_action('admin_init', $this->registerSettings(...));

        // Admin assets
        add_action('admin_enqueue_scripts', $this->enqueueAdminAssets(...));

        // Plugin action links
        add_filter(
            'plugin_action_links_' . NOWSCROBBLING_BASENAME,
            $this->addPluginActionLinks(...)
        );
    }

    /**
     * Load the plugin text domain for translations
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'nowscrobbling',
            false,
            dirname(NOWSCROBBLING_BASENAME) . '/languages'
        );
    }

    /**
     * Register all shortcodes
     *
     * In Phase 1, this is a placeholder. Will be implemented in Phase 5.
     */
    public function registerShortcodes(): void
    {
        /**
         * Fires when shortcodes should be registered
         *
         * @param Container $container The DI container
         */
        do_action('nowscrobbling_register_shortcodes', $this->container);
    }

    /**
     * Register REST API routes
     *
     * In Phase 1, this is a placeholder. Will be implemented in Phase 7.
     */
    public function registerRestRoutes(): void
    {
        /**
         * Fires when REST routes should be registered
         *
         * @param Container $container The DI container
         */
        do_action('nowscrobbling_register_rest_routes', $this->container);
    }

    /**
     * Enqueue public-facing assets
     *
     * Only loads assets when shortcodes are present on the page.
     */
    public function enqueuePublicAssets(): void
    {
        if (!$this->shouldLoadAssets()) {
            return;
        }

        $plugin = Plugin::getInstance();

        // CSS
        $cssFile = 'assets/css/public.css';
        $cssPath = $plugin->getPath($cssFile);

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'nowscrobbling-public',
                $plugin->getUrl($cssFile),
                [],
                (string) filemtime($cssPath)
            );
        }

        // JavaScript
        $jsFile = 'assets/js/nowscrobbling.js';
        $jsPath = $plugin->getPath($jsFile);

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'nowscrobbling',
                $plugin->getUrl($jsFile),
                [], // No jQuery dependency in v2.0
                (string) filemtime($jsPath),
                true // Load in footer
            );

            // Localize script with configuration
            wp_localize_script('nowscrobbling', 'nowscrobblingConfig', $this->getScriptConfig());
        }
    }

    /**
     * Get JavaScript configuration
     *
     * @return array<string, mixed>
     */
    private function getScriptConfig(): array
    {
        $nowplayingInterval = max(5, (int) get_option('ns_nowplaying_interval', 20));
        $maxInterval = max(30, (int) get_option('ns_max_interval', 300));
        $backoffMultiplier = max(1.0, (float) get_option('ns_backoff_multiplier', 2.0));

        return [
            'restUrl'  => rest_url('nowscrobbling/v1'),
            'nonces'   => [
                'render'  => wp_create_nonce('ns_render'),
                'refresh' => wp_create_nonce('ns_refresh'),
            ],
            'polling'  => [
                'baseInterval'      => $nowplayingInterval * 1000,
                'maxInterval'       => $maxInterval * 1000,
                'backoffMultiplier' => $backoffMultiplier,
            ],
            'debug'    => Plugin::getInstance()->isDebug(),
            'version'  => Plugin::VERSION,
        ];
    }

    /**
     * Check if assets should be loaded on the current page
     */
    private function shouldLoadAssets(): bool
    {
        if (is_admin()) {
            return false;
        }

        global $posts;

        if (empty($posts) || !is_array($posts)) {
            return false;
        }

        $shortcodeTags = Plugin::getInstance()->getShortcodeTags();

        foreach ($posts as $post) {
            if (!isset($post->post_content)) {
                continue;
            }

            foreach ($shortcodeTags as $tag) {
                if (has_shortcode($post->post_content, $tag)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add custom cron intervals
     *
     * @param array<string, array{interval: int, display: string}> $schedules Existing schedules
     *
     * @return array<string, array{interval: int, display: string}>
     */
    public function addCronIntervals(array $schedules): array
    {
        $schedules['nowscrobbling_1_minute'] = [
            'interval' => 60,
            'display'  => __('Every 1 Minute', 'nowscrobbling'),
        ];

        $schedules['nowscrobbling_5_minutes'] = [
            'interval' => 300,
            'display'  => __('Every 5 Minutes', 'nowscrobbling'),
        ];

        return $schedules;
    }

    /**
     * Schedule cron jobs
     */
    public function scheduleCronJobs(): void
    {
        // Background cache refresh (every 5 minutes)
        if (!wp_next_scheduled('nowscrobbling_cache_refresh')) {
            wp_schedule_event(time(), 'nowscrobbling_5_minutes', 'nowscrobbling_cache_refresh');
        }

        // Now-playing tick (every 1 minute)
        if (!wp_next_scheduled('nowscrobbling_nowplaying_tick')) {
            wp_schedule_event(time(), 'nowscrobbling_1_minute', 'nowscrobbling_nowplaying_tick');
        }
    }

    /**
     * Handle background cache refresh cron
     */
    public function handleCacheRefresh(): void
    {
        // Only refresh if API credentials are configured
        $hasLastFm = (bool) get_option('ns_lastfm_api_key');
        $hasTrakt = (bool) get_option('ns_trakt_client_id');

        if ($hasLastFm || $hasTrakt) {
            /**
             * Fires during background cache refresh
             *
             * @param bool $hasLastFm Whether Last.fm is configured
             * @param bool $hasTrakt  Whether Trakt is configured
             */
            do_action('nowscrobbling_background_refresh', $hasLastFm, $hasTrakt);
        }
    }

    /**
     * Handle now-playing tick cron
     */
    public function handleNowPlayingTick(): void
    {
        $lastfmActive = (int) get_option('ns_flag_lastfm_nowplaying', 0) === 1;
        $traktActive = (int) get_option('ns_flag_trakt_watching', 0) === 1;

        if ($lastfmActive || $traktActive) {
            /**
             * Fires during the now-playing tick
             *
             * @param bool $lastfmActive Whether Last.fm now-playing is active
             * @param bool $traktActive  Whether Trakt watching is active
             */
            do_action('nowscrobbling_nowplaying_refresh', $lastfmActive, $traktActive);
        }
    }

    /**
     * Register admin menu
     *
     * In Phase 1, this is a placeholder. Will be implemented in Phase 8.
     */
    public function registerAdminMenu(): void
    {
        /**
         * Fires when admin menu should be registered
         *
         * @param Container $container The DI container
         */
        do_action('nowscrobbling_register_admin_menu', $this->container);
    }

    /**
     * Register settings
     *
     * In Phase 1, this is a placeholder. Will be implemented in Phase 8.
     */
    public function registerSettings(): void
    {
        /**
         * Fires when settings should be registered
         *
         * @param Container $container The DI container
         */
        do_action('nowscrobbling_register_settings', $this->container);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hookSuffix): void
    {
        // Only load on our settings page
        if ($hookSuffix !== 'settings_page_nowscrobbling') {
            return;
        }

        $plugin = Plugin::getInstance();

        // Admin CSS
        $cssFile = 'assets/css/admin.css';
        $cssPath = $plugin->getPath($cssFile);

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'nowscrobbling-admin',
                $plugin->getUrl($cssFile),
                [],
                (string) filemtime($cssPath)
            );
        }

        // Admin JavaScript
        $jsFile = 'assets/js/admin.js';
        $jsPath = $plugin->getPath($jsFile);

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'nowscrobbling-admin',
                $plugin->getUrl($jsFile),
                [],
                (string) filemtime($jsPath),
                true
            );

            wp_localize_script('nowscrobbling-admin', 'nowscrobblingAdmin', [
                'restUrl' => rest_url('nowscrobbling/v1'),
                'nonces'  => [
                    'cacheClear' => wp_create_nonce('ns_cache_clear'),
                    'apiTest'    => wp_create_nonce('ns_api_test'),
                ],
            ]);
        }
    }

    /**
     * Add plugin action links
     *
     * @param array<string> $links Existing links
     *
     * @return array<string>
     */
    public function addPluginActionLinks(array $links): array
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=nowscrobbling')),
            esc_html__('Settings', 'nowscrobbling')
        );

        array_unshift($links, $settingsLink);

        return $links;
    }
}
