<?php
/**
 * AJAX Handler
 *
 * Handles all AJAX requests for the plugin.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Ajax;

use NowScrobbling\Core\Plugin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AjaxHandler
 *
 * Central handler for all AJAX requests.
 */
class AjaxHandler {

    /**
     * Register AJAX handlers.
     *
     * @return void
     */
    public static function register(): void {
        // Public AJAX (for shortcode refresh).
        add_action( 'wp_ajax_nowscrobbling_render', [ self::class, 'handleRender' ] );
        add_action( 'wp_ajax_nopriv_nowscrobbling_render', [ self::class, 'handleRender' ] );

        // Admin AJAX.
        add_action( 'wp_ajax_nowscrobbling_admin', [ self::class, 'handleAdmin' ] );
    }

    /**
     * Handle shortcode render AJAX request.
     *
     * @return void
     */
    public static function handleRender(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'nowscrobbling_public', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'nowscrobbling' ), 403 );
        }

        // Get parameters.
        $shortcode  = isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : '';
        $attributes = [];

        if ( isset( $_POST['attributes'] ) ) {
            $raw_attributes = wp_unslash( $_POST['attributes'] );
            $decoded        = json_decode( $raw_attributes, true );

            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $attributes = array_map( 'sanitize_text_field', $decoded );
            }
        }

        if ( empty( $shortcode ) ) {
            wp_send_json_error( __( 'Missing shortcode parameter.', 'nowscrobbling' ), 400 );
        }

        // Get shortcode instance.
        $registry  = Plugin::getInstance()->getShortcodes();
        $instance  = $registry->get( $shortcode );

        if ( ! $instance ) {
            wp_send_json_error( __( 'Unknown shortcode.', 'nowscrobbling' ), 400 );
        }

        // Render shortcode.
        $html = $instance->render( $attributes ?: [] );

        wp_send_json_success( [
            'html'      => $html,
            'shortcode' => $shortcode,
        ] );
    }

    /**
     * Handle admin AJAX requests.
     *
     * @return void
     */
    public static function handleAdmin(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'nowscrobbling_admin', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'nowscrobbling' ), 403 );
        }

        // Check capabilities.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'nowscrobbling' ), 403 );
        }

        // Get sub-action.
        $sub_action = isset( $_POST['sub_action'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_action'] ) ) : '';

        match ( $sub_action ) {
            'clear_cache'       => self::handleClearCache(),
            'clear_log'         => self::handleClearLog(),
            'test_connection'   => self::handleTestConnection(),
            'preview_shortcode' => self::handlePreviewShortcode(),
            default             => wp_send_json_error( __( 'Unknown action.', 'nowscrobbling' ), 400 ),
        };
    }

    /**
     * Handle clear cache request.
     *
     * @return void
     */
    private static function handleClearCache(): void {
        $cache = Plugin::getInstance()->getCache();
        $cache->clearAll();

        wp_send_json_success( [
            'message' => __( 'Cache cleared successfully.', 'nowscrobbling' ),
        ] );
    }

    /**
     * Handle clear log request.
     *
     * @return void
     */
    private static function handleClearLog(): void {
        delete_option( 'nowscrobbling_log' );

        wp_send_json_success( [
            'message' => __( 'Log cleared successfully.', 'nowscrobbling' ),
        ] );
    }

    /**
     * Handle test connection request.
     *
     * @return void
     */
    private static function handleTestConnection(): void {
        $provider_id = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';

        if ( empty( $provider_id ) ) {
            wp_send_json_error( __( 'Missing provider parameter.', 'nowscrobbling' ), 400 );
        }

        $provider = Plugin::getInstance()->getProviders()->get( $provider_id );

        if ( ! $provider ) {
            wp_send_json_error( __( 'Unknown provider.', 'nowscrobbling' ), 400 );
        }

        if ( ! $provider->isConfigured() ) {
            wp_send_json_error( __( 'Provider not configured.', 'nowscrobbling' ), 400 );
        }

        $result = $provider->testConnection();

        if ( $result->success ) {
            wp_send_json_success( [
                'message' => sprintf(
                    /* translators: %s: Provider name */
                    __( 'Connected to %s successfully.', 'nowscrobbling' ),
                    $provider->getName()
                ),
                'user' => $result->data['user'] ?? null,
            ] );
        } else {
            wp_send_json_error( $result->error ?: __( 'Connection failed.', 'nowscrobbling' ), 500 );
        }
    }

    /**
     * Handle shortcode preview request.
     *
     * @return void
     */
    private static function handlePreviewShortcode(): void {
        $shortcode  = isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : '';
        $attributes = [];

        if ( isset( $_POST['attributes'] ) ) {
            $raw_attributes = wp_unslash( $_POST['attributes'] );
            $decoded        = json_decode( $raw_attributes, true );

            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $attributes = array_map( 'sanitize_text_field', $decoded );
            }
        }

        if ( empty( $shortcode ) ) {
            wp_send_json_error( __( 'Missing shortcode parameter.', 'nowscrobbling' ), 400 );
        }

        $registry = Plugin::getInstance()->getShortcodes();
        $instance = $registry->get( $shortcode );

        if ( ! $instance ) {
            wp_send_json_error( __( 'Unknown shortcode.', 'nowscrobbling' ), 400 );
        }

        // Render shortcode.
        $html = $instance->render( $attributes ?: [] );

        wp_send_json_success( [
            'html' => $html,
        ] );
    }
}
