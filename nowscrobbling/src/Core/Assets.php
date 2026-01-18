<?php

declare(strict_types=1);

namespace NowScrobbling\Core;

use NowScrobbling\Plugin;

/**
 * Asset management for conditional loading
 *
 * Handles registration and conditional loading of CSS and JavaScript assets.
 * Assets are only loaded when shortcodes are present on the page.
 *
 * @package NowScrobbling\Core
 */
final class Assets
{
    /**
     * Whether assets have been loaded for this request
     */
    private bool $loaded = false;

    /**
     * Cached result of shortcode detection
     */
    private ?bool $hasShortcodes = null;

    /**
     * Register asset hooks
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', $this->enqueuePublic(...));
        add_action('admin_enqueue_scripts', $this->enqueueAdmin(...));
    }

    /**
     * Enqueue public-facing assets
     *
     * Only loads when shortcodes are detected on the current page.
     */
    public function enqueuePublic(): void
    {
        if (!$this->shouldLoadPublicAssets()) {
            return;
        }

        $this->enqueuePublicStyles();
        $this->enqueuePublicScripts();

        $this->loaded = true;
    }

    /**
     * Enqueue admin assets
     *
     * Only loads on the plugin settings page.
     *
     * @param string $hookSuffix The current admin page hook suffix
     */
    public function enqueueAdmin(string $hookSuffix): void
    {
        if ($hookSuffix !== 'settings_page_nowscrobbling') {
            return;
        }

        $this->enqueueAdminStyles();
        $this->enqueueAdminScripts();
    }

    /**
     * Check if public assets should be loaded
     */
    private function shouldLoadPublicAssets(): bool
    {
        if (is_admin()) {
            return false;
        }

        return $this->pageHasShortcodes();
    }

    /**
     * Check if current page has any NowScrobbling shortcodes
     */
    private function pageHasShortcodes(): bool
    {
        if ($this->hasShortcodes !== null) {
            return $this->hasShortcodes;
        }

        global $posts;

        if (empty($posts) || !is_array($posts)) {
            $this->hasShortcodes = false;
            return false;
        }

        $tags = Plugin::getInstance()->getShortcodeTags();

        foreach ($posts as $post) {
            if (!isset($post->post_content) || !is_string($post->post_content)) {
                continue;
            }

            foreach ($tags as $tag) {
                if (has_shortcode($post->post_content, $tag)) {
                    $this->hasShortcodes = true;
                    return true;
                }
            }
        }

        $this->hasShortcodes = false;
        return false;
    }

    /**
     * Enqueue public stylesheets
     */
    private function enqueuePublicStyles(): void
    {
        $plugin = Plugin::getInstance();

        $cssFile = 'assets/css/public.css';
        $cssPath = $plugin->getPath($cssFile);

        if (!file_exists($cssPath)) {
            return;
        }

        wp_enqueue_style(
            'nowscrobbling',
            $plugin->getUrl($cssFile),
            [],
            $this->getFileVersion($cssPath)
        );
    }

    /**
     * Enqueue public scripts
     */
    private function enqueuePublicScripts(): void
    {
        $plugin = Plugin::getInstance();

        $jsFile = 'assets/js/nowscrobbling.js';
        $jsPath = $plugin->getPath($jsFile);

        if (!file_exists($jsPath)) {
            return;
        }

        wp_enqueue_script(
            'nowscrobbling',
            $plugin->getUrl($jsFile),
            [], // No jQuery dependency in v2.0
            $this->getFileVersion($jsPath),
            ['in_footer' => true, 'strategy' => 'defer']
        );

        wp_localize_script('nowscrobbling', 'nowscrobblingConfig', $this->getPublicConfig());
    }

    /**
     * Enqueue admin stylesheets
     */
    private function enqueueAdminStyles(): void
    {
        $plugin = Plugin::getInstance();

        $cssFile = 'assets/css/admin.css';
        $cssPath = $plugin->getPath($cssFile);

        if (!file_exists($cssPath)) {
            return;
        }

        wp_enqueue_style(
            'nowscrobbling-admin',
            $plugin->getUrl($cssFile),
            [],
            $this->getFileVersion($cssPath)
        );
    }

    /**
     * Enqueue admin scripts
     */
    private function enqueueAdminScripts(): void
    {
        $plugin = Plugin::getInstance();

        $jsFile = 'assets/js/admin.js';
        $jsPath = $plugin->getPath($jsFile);

        if (!file_exists($jsPath)) {
            return;
        }

        wp_enqueue_script(
            'nowscrobbling-admin',
            $plugin->getUrl($jsFile),
            [],
            $this->getFileVersion($jsPath),
            ['in_footer' => true]
        );

        wp_localize_script('nowscrobbling-admin', 'nowscrobblingAdmin', $this->getAdminConfig());
    }

    /**
     * Get file version based on modification time
     */
    private function getFileVersion(string $path): string
    {
        return (string) filemtime($path);
    }

    /**
     * Get public JavaScript configuration
     *
     * @return array<string, mixed>
     */
    private function getPublicConfig(): array
    {
        // Get polling intervals with validation
        $baseInterval = max(5, (int) get_option('ns_nowplaying_interval', 20));
        $maxInterval = max(30, (int) get_option('ns_max_interval', 300));
        $backoffMultiplier = max(1.0, (float) get_option('ns_backoff_multiplier', 2.0));

        return [
            'restUrl' => rest_url('nowscrobbling/v1'),
            'nonces'  => [
                'render'  => wp_create_nonce('ns_render'),
                'refresh' => wp_create_nonce('ns_refresh'),
            ],
            'polling' => [
                'baseInterval'      => $baseInterval * 1000, // Convert to ms
                'maxInterval'       => $maxInterval * 1000,
                'backoffMultiplier' => $backoffMultiplier,
            ],
            'debug'   => Plugin::getInstance()->isDebug(),
            'version' => Plugin::VERSION,
        ];
    }

    /**
     * Get admin JavaScript configuration
     *
     * @return array<string, mixed>
     */
    private function getAdminConfig(): array
    {
        return [
            'restUrl' => rest_url('nowscrobbling/v1'),
            'nonces'  => [
                'cacheClear' => wp_create_nonce('ns_cache_clear'),
                'apiTest'    => wp_create_nonce('ns_api_test'),
                'settings'   => wp_create_nonce('ns_settings'),
            ],
            'version' => Plugin::VERSION,
        ];
    }

    /**
     * Check if assets have been loaded
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Force asset loading (for AJAX requests)
     */
    public function forceLoad(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->enqueuePublicStyles();
        $this->enqueuePublicScripts();

        $this->loaded = true;
    }
}
