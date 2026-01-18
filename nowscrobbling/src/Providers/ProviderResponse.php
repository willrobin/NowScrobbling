<?php
/**
 * Provider Response
 *
 * Represents a response from a provider API call.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ProviderResponse
 *
 * Unified response object from provider API calls.
 */
class ProviderResponse {

    /**
     * Whether the request was successful.
     *
     * @var bool
     */
    public bool $success;

    /**
     * The response data.
     *
     * @var mixed
     */
    public mixed $data;

    /**
     * Error message (if any).
     *
     * @var string|null
     */
    public ?string $error;

    /**
     * HTTP status code.
     *
     * @var int
     */
    public int $statusCode;

    /**
     * Response headers.
     *
     * @var array
     */
    public array $headers;

    /**
     * Whether this is a cached response.
     *
     * @var bool
     */
    public bool $fromCache;

    /**
     * Cache source (if cached).
     *
     * @var string|null 'memory', 'primary', 'fallback', null
     */
    public ?string $cacheSource;

    /**
     * ETag from response (for conditional requests).
     *
     * @var string|null
     */
    public ?string $etag;

    /**
     * Whether response was "not modified" (304).
     *
     * @var bool
     */
    public bool $notModified;

    /**
     * Response time in milliseconds.
     *
     * @var float|null
     */
    public ?float $responseTime;

    /**
     * Constructor.
     *
     * @param bool        $success       Whether request succeeded.
     * @param mixed       $data          Response data.
     * @param string|null $error         Error message.
     * @param int         $status_code   HTTP status code.
     * @param array       $headers       Response headers.
     */
    public function __construct(
        bool $success,
        mixed $data = null,
        ?string $error = null,
        int $status_code = 200,
        array $headers = []
    ) {
        $this->success      = $success;
        $this->data         = $data;
        $this->error        = $error;
        $this->statusCode   = $status_code;
        $this->headers      = $headers;
        $this->fromCache    = false;
        $this->cacheSource  = null;
        $this->etag         = $this->extractHeader( 'etag' );
        $this->notModified  = $status_code === 304;
        $this->responseTime = null;
    }

    /**
     * Create a successful response.
     *
     * @param mixed $data        Response data.
     * @param int   $status_code HTTP status code.
     * @param array $headers     Response headers.
     * @return self
     */
    public static function success( $data, int $status_code = 200, array $headers = [] ): self {
        return new self( true, $data, null, $status_code, $headers );
    }

    /**
     * Create an error response.
     *
     * @param string $error       Error message.
     * @param int    $status_code HTTP status code.
     * @param array  $headers     Response headers.
     * @return self
     */
    public static function error( string $error, int $status_code = 0, array $headers = [] ): self {
        return new self( false, null, $error, $status_code, $headers );
    }

    /**
     * Create a cached response.
     *
     * @param mixed  $data   Cached data.
     * @param string $source Cache source ('memory', 'primary', 'fallback').
     * @return self
     */
    public static function cached( $data, string $source = 'primary' ): self {
        $response              = new self( true, $data, null, 200, [] );
        $response->fromCache   = true;
        $response->cacheSource = $source;
        return $response;
    }

    /**
     * Create a "not modified" response (304).
     *
     * @return self
     */
    public static function notModified(): self {
        $response              = new self( true, null, null, 304, [] );
        $response->notModified = true;
        return $response;
    }

    /**
     * Mark this response as from cache.
     *
     * @param string $source Cache source.
     * @return self
     */
    public function markAsCached( string $source ): self {
        $this->fromCache   = true;
        $this->cacheSource = $source;
        return $this;
    }

    /**
     * Set response time.
     *
     * @param float $milliseconds Response time in ms.
     * @return self
     */
    public function setResponseTime( float $milliseconds ): self {
        $this->responseTime = $milliseconds;
        return $this;
    }

    /**
     * Extract a header value.
     *
     * @param string $name Header name (case-insensitive).
     * @return string|null
     */
    private function extractHeader( string $name ): ?string {
        $name = strtolower( $name );
        foreach ( $this->headers as $key => $value ) {
            if ( strtolower( $key ) === $name ) {
                return is_array( $value ) ? $value[0] : $value;
            }
        }
        return null;
    }

    /**
     * Get a specific header.
     *
     * @param string $name Header name.
     * @return string|null
     */
    public function getHeader( string $name ): ?string {
        return $this->extractHeader( $name );
    }

    /**
     * Check if response indicates rate limiting.
     *
     * @return bool
     */
    public function isRateLimited(): bool {
        return $this->statusCode === 429;
    }

    /**
     * Check if response is a server error.
     *
     * @return bool
     */
    public function isServerError(): bool {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Get retry-after header value (for rate limiting).
     *
     * @return int|null Seconds until retry, or null if not present.
     */
    public function getRetryAfter(): ?int {
        $header = $this->getHeader( 'retry-after' );
        if ( $header === null ) {
            return null;
        }

        // Can be seconds or HTTP date.
        if ( is_numeric( $header ) ) {
            return (int) $header;
        }

        // Parse as date.
        $timestamp = strtotime( $header );
        if ( $timestamp === false ) {
            return null;
        }

        return max( 0, $timestamp - time() );
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'success'       => $this->success,
            'data'          => $this->data,
            'error'         => $this->error,
            'status_code'   => $this->statusCode,
            'from_cache'    => $this->fromCache,
            'cache_source'  => $this->cacheSource,
            'not_modified'  => $this->notModified,
            'response_time' => $this->responseTime,
        ];
    }
}
