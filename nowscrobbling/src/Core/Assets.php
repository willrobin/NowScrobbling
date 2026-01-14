<?php
/**
 * Assets Manager
 *
 * Handles enqueueing of CSS and JavaScript files.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Assets
 *
 * Manages script and style registration and enqueueing.
 */
class Assets {

    /**
     * Enqueue public-facing assets.
     *
     * Only loads assets on pages that actually use our shortcodes.
     *
     * @return void
     */
    public static function enqueuePublic(): void {
        // Only load if shortcodes are present on the page.
        if ( ! self::pageHasShortcodes() ) {
            return;
        }

        // CSS Variables (includes dark mode).
        wp_enqueue_style(
            'nowscrobbling-variables',
            NOWSCROBBLING_URL . 'assets/css/public/variables.css',
            [],
            self::getVersion( 'assets/css/public/variables.css' )
        );

        // Base styles.
        wp_enqueue_style(
            'nowscrobbling-base',
            NOWSCROBBLING_URL . 'assets/css/public/base.css',
            [ 'nowscrobbling-variables' ],
            self::getVersion( 'assets/css/public/base.css' )
        );

        // Main JavaScript.
        wp_enqueue_script(
            'nowscrobbling-public',
            NOWSCROBBLING_URL . 'assets/js/public/nowscrobbling.js',
            [],
            self::getVersion( 'assets/js/public/nowscrobbling.js' ),
            true
        );

        // Localize script with AJAX data.
        wp_localize_script( 'nowscrobbling-public', 'nowscrobblingData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'nowscrobbling_public' ),
            'debug'    => (bool) get_option( 'ns_debug_enabled', 0 ),
            'polling'  => [
                'nowPlayingInterval' => (int) get_option( 'ns_nowplaying_interval', 20 ) * 1000,
                'maxInterval'        => (int) get_option( 'ns_max_interval', 300 ) * 1000,
                'backoffMultiplier'  => (float) get_option( 'ns_backoff_multiplier', 2.0 ),
            ],
        ]);
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public static function enqueueAdmin( string $hook_suffix ): void {
        // Only load on our admin page.
        if ( strpos( $hook_suffix, 'nowscrobbling' ) === false ) {
            return;
        }

        // Admin styles.
        wp_enqueue_style(
            'nowscrobbling-admin',
            NOWSCROBBLING_URL . 'assets/css/admin/admin.css',
            [],
            self::getVersion( 'assets/css/admin/admin.css' )
        );

        // Shortcode builder styles.
        wp_enqueue_style(
            'nowscrobbling-builder',
            NOWSCROBBLING_URL . 'assets/css/admin/builder.css',
            [ 'nowscrobbling-admin' ],
            self::getVersion( 'assets/css/admin/builder.css' )
        );

        // Admin JavaScript.
        wp_enqueue_script(
            'nowscrobbling-admin',
            NOWSCROBBLING_URL . 'assets/js/admin/admin.js',
            [ 'jquery' ],
            self::getVersion( 'assets/js/admin/admin.js' ),
            true
        );

        // Shortcode builder JavaScript.
        wp_enqueue_script(
            'nowscrobbling-builder',
            NOWSCROBBLING_URL . 'assets/js/admin/builder.js',
            [ 'nowscrobbling-admin' ],
            self::getVersion( 'assets/js/admin/builder.js' ),
            true
        );

        // Localize script.
        wp_localize_script( 'nowscrobbling-admin', 'nowscrobblingAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'nowscrobbling_admin' ),
            'i18n'    => [
                'confirmClearCache' => __( 'Are you sure you want to clear all caches?', 'nowscrobbling' ),
                'cacheCleared'      => __( 'Cache cleared successfully.', 'nowscrobbling' ),
                'error'             => __( 'An error occurred.', 'nowscrobbling' ),
                'copied'            => __( 'Copied to clipboard!', 'nowscrobbling' ),
            ],
        ]);
    }

    /**
     * Check if the current page/post contains our shortcodes.
     *
     * @return bool
     */
    private static function pageHasShortcodes(): bool {
        global $post;

        // Always load in admin preview context.
        if ( is_admin() || wp_doing_ajax() ) {
            return true;
        }

        // Check post content.
        if ( $post && is_a( $post, 'WP_Post' ) ) {
            $shortcode_pattern = '/\[nowscr[a-z_]*(\s|\])/';
            if ( preg_match( $shortcode_pattern, $post->post_content ) ) {
                return true;
            }
        }

        // Check if widgets might have our shortcodes.
        // This is a fallback - widgets are harder to detect.
        if ( is_active_widget( false, false, 'text', true ) ) {
            return true;
        }

        /**
         * Filter to force-load assets on specific pages.
         *
         * @since 1.4.0
         * @param bool $should_load Whether to load assets.
         */
        return apply_filters( 'nowscrobbling_force_load_assets', false );
    }

    /**
     * Get version string for cache busting.
     *
     * Uses file modification time in development, plugin version in production.
     *
     * @param string $relative_path Path relative to plugin directory.
     * @return string
     */
    private static function getVersion( string $relative_path ): string {
        $file_path = NOWSCROBBLING_PATH . $relative_path;

        if ( file_exists( $file_path ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return (string) filemtime( $file_path );
        }

        return NOWSCROBBLING_VERSION;
    }

    /**
     * Enqueue a layout-specific stylesheet.
     *
     * Called dynamically when a shortcode uses a specific layout.
     *
     * @param string $layout The layout name (inline, card, grid, list).
     * @return void
     */
    public static function enqueueLayout( string $layout ): void {
        $valid_layouts = [ 'inline', 'card', 'grid', 'list' ];

        if ( ! in_array( $layout, $valid_layouts, true ) ) {
            return;
        }

        $handle = "nowscrobbling-layout-{$layout}";
        $path   = "assets/css/public/layout-{$layout}.css";

        if ( ! wp_style_is( $handle, 'enqueued' ) ) {
            wp_enqueue_style(
                $handle,
                NOWSCROBBLING_URL . $path,
                [ 'nowscrobbling-base' ],
                self::getVersion( $path )
            );
        }
    }

    /**
     * Enqueue a component-specific stylesheet.
     *
     * Called dynamically when a component is used.
     *
     * @param string $component The component name (artwork, rating, badges).
     * @return void
     */
    public static function enqueueComponent( string $component ): void {
        $valid_components = [ 'artwork', 'rating', 'badges', 'skeleton' ];

        if ( ! in_array( $component, $valid_components, true ) ) {
            return;
        }

        $handle = "nowscrobbling-component-{$component}";
        $path   = "assets/css/public/components/{$component}.css";

        if ( ! wp_style_is( $handle, 'enqueued' ) ) {
            wp_enqueue_style(
                $handle,
                NOWSCROBBLING_URL . $path,
                [ 'nowscrobbling-base' ],
                self::getVersion( $path )
            );
        }
    }
}
