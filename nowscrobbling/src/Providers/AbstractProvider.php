<?php
/**
 * Abstract Provider
 *
 * Base class for all media providers with common functionality.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Providers;

use NowScrobbling\Attribution\Attribution;
use NowScrobbling\Cache\DataType;
use NowScrobbling\Core\Plugin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract Class AbstractProvider
 *
 * Provides common functionality for all providers.
 */
abstract class AbstractProvider implements ProviderInterface {

    /**
     * Provider ID.
     *
     * @var string
     */
    protected string $id;

    /**
     * Provider display name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Provider capabilities.
     *
     * @var array<string>
     */
    protected array $capabilities = [];

    /**
     * Base API URL.
     *
     * @var string
     */
    protected string $apiUrl;

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    protected int $timeout = 5;

    /**
     * Get the unique provider identifier.
     *
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Get the display name.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Get capabilities.
     *
     * @return array<string>
     */
    public function getCapabilities(): array {
        return $this->capabilities;
    }

    /**
     * Check if provider has a capability.
     *
     * @param string $capability The capability to check.
     * @return bool
     */
    public function hasCapability( string $capability ): bool {
        return in_array( $capability, $this->capabilities, true );
    }

    /**
     * Get an option value for this provider.
     *
     * @param string $key     Option key (without provider prefix).
     * @param mixed  $default Default value.
     * @return mixed
     */
    protected function getOption( string $key, mixed $default = '' ): mixed {
        $option_key = 'ns_' . $this->id . '_' . $key;
        $value = get_option( $option_key );

        // Also check legacy option names for backwards compatibility.
        if ( $value === false || $value === '' ) {
            $legacy_key = $this->getLegacyOptionKey( $key );
            if ( $legacy_key ) {
                $value = get_option( $legacy_key );
            }
        }

        return ( $value === false || $value === '' ) ? $default : $value;
    }

    /**
     * Get legacy option key mapping.
     *
     * Override in child classes if needed.
     *
     * @param string $key New option key.
     * @return string|null Legacy key or null.
     */
    protected function getLegacyOptionKey( string $key ): ?string {
        return null;
    }

    /**
     * Build the User-Agent string.
     *
     * @return string
     */
    protected function getUserAgent(): string {
        $version = defined( 'NOWSCROBBLING_VERSION' ) ? NOWSCROBBLING_VERSION : 'dev';
        return 'NowScrobbling/' . $version . '; ' . home_url( '/' );
    }

    /**
     * Make an HTTP request.
     *
     * @param string $url     The URL to request.
     * @param array  $headers Additional headers.
     * @param array  $args    Additional wp_remote_get arguments.
     * @return array{success: bool, data: mixed, status: int, error?: string}
     */
    protected function request( string $url, array $headers = [], array $args = [] ): array {
        $args = wp_parse_args( $args, [
            'timeout'     => $this->timeout,
            'redirection' => 0,
            'headers'     => [],
        ] );

        $args['headers'] = array_merge( [
            'User-Agent'      => $this->getUserAgent(),
            'Accept'          => 'application/json',
            'Accept-Encoding' => 'gzip',
            'Connection'      => 'close',
        ], $headers, $args['headers'] );

        $start = microtime( true );
        $response = wp_safe_remote_get( $url, $args );
        $duration = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            $this->log( sprintf( 'Request failed: %s - %s', $response->get_error_code(), $response->get_error_message() ) );

            return [
                'success'  => false,
                'data'     => null,
                'status'   => 0,
                'error'    => $response->get_error_message(),
                'duration' => $duration,
            ];
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        // Handle 204 No Content.
        if ( $status === 204 ) {
            return [
                'success'  => true,
                'data'     => [],
                'status'   => $status,
                'duration' => $duration,
            ];
        }

        // Handle success responses.
        if ( $status >= 200 && $status < 300 ) {
            $data = json_decode( $body, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $this->log( 'JSON decode error: ' . json_last_error_msg() );

                return [
                    'success'  => false,
                    'data'     => null,
                    'status'   => $status,
                    'error'    => 'Invalid JSON response',
                    'duration' => $duration,
                ];
            }

            return [
                'success'  => true,
                'data'     => $data,
                'status'   => $status,
                'duration' => $duration,
            ];
        }

        // Handle errors.
        $this->log( sprintf( 'Request failed with status %d: %s', $status, substr( $body, 0, 200 ) ) );

        return [
            'success'  => false,
            'data'     => null,
            'status'   => $status,
            'error'    => 'HTTP ' . $status,
            'duration' => $duration,
        ];
    }

    /**
     * Log a message for this provider.
     *
     * @param string $message The message to log.
     * @return void
     */
    protected function log( string $message ): void {
        if ( ! get_option( 'ns_debug_enabled', false ) ) {
            return;
        }

        $log = get_option( 'nowscrobbling_log', [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }

        $log[] = sprintf(
            '[%s] [%s] %s',
            current_time( 'mysql' ),
            strtoupper( $this->id ),
            $message
        );

        // Keep last 200 entries.
        if ( count( $log ) > 200 ) {
            $log = array_slice( $log, -200 );
        }

        update_option( 'nowscrobbling_log', $log, false );
    }

    /**
     * Build a standardized response.
     *
     * @param bool   $success Whether the request succeeded.
     * @param mixed  $data    The response data.
     * @param string $error   Error message if any.
     * @return ProviderResponse
     */
    protected function buildResponse( bool $success, mixed $data = null, string $error = '' ): ProviderResponse {
        return new ProviderResponse(
            success: $success,
            data: $data,
            error: $error ?: null
        );
    }
}
