<?php

declare(strict_types=1);

namespace NowScrobbling\Api\Response;

/**
 * API Response DTO
 *
 * Immutable data transfer object for API responses.
 * Provides a consistent interface for success and error responses.
 *
 * @package NowScrobbling\Api\Response
 */
final readonly class ApiResponse
{
    /**
     * @param bool                   $success   Whether the request succeeded
     * @param array<string, mixed>   $data      Response data
     * @param string|null            $error     Error message if failed
     * @param int                    $httpCode  HTTP status code
     * @param string|null            $etag      ETag header value
     * @param bool                   $fromCache Whether served from cache
     * @param float                  $duration  Request duration in ms
     */
    public function __construct(
        public bool $success,
        public array $data = [],
        public ?string $error = null,
        public int $httpCode = 200,
        public ?string $etag = null,
        public bool $fromCache = false,
        public float $duration = 0.0,
    ) {
    }

    /**
     * Create a successful response
     *
     * @param array<string, mixed> $data     Response data
     * @param int                  $httpCode HTTP status code
     * @param string|null          $etag     ETag value
     * @param float                $duration Request duration in ms
     */
    public static function success(
        array $data,
        int $httpCode = 200,
        ?string $etag = null,
        float $duration = 0.0
    ): self {
        return new self(
            success: true,
            data: $data,
            httpCode: $httpCode,
            etag: $etag,
            duration: $duration
        );
    }

    /**
     * Create an error response
     *
     * @param string $message  Error message
     * @param int    $httpCode HTTP status code
     * @param float  $duration Request duration in ms
     */
    public static function error(
        string $message,
        int $httpCode = 500,
        float $duration = 0.0
    ): self {
        return new self(
            success: false,
            data: ['__ns_error' => true, 'message' => $message],
            error: $message,
            httpCode: $httpCode,
            duration: $duration
        );
    }

    /**
     * Create a rate-limited response
     *
     * @param int $retryAfter Seconds until retry is allowed
     */
    public static function rateLimited(int $retryAfter = 60): self
    {
        return new self(
            success: false,
            data: ['__ns_error' => true, '__ns_rate_limited' => true],
            error: sprintf('Rate limited. Retry after %d seconds.', $retryAfter),
            httpCode: 429
        );
    }

    /**
     * Create a not-modified response (304)
     *
     * Used when ETag matches and data hasn't changed.
     */
    public static function notModified(): self
    {
        return new self(
            success: true,
            data: ['__ns_not_modified' => true],
            httpCode: 304
        );
    }

    /**
     * Create a cached response
     *
     * @param array<string, mixed> $data Cached data
     */
    public static function cached(array $data): self
    {
        return new self(
            success: true,
            data: $data,
            fromCache: true
        );
    }

    /**
     * Check if response is an error
     */
    public function isError(): bool
    {
        return !$this->success;
    }

    /**
     * Check if response indicates rate limiting
     */
    public function isRateLimited(): bool
    {
        return $this->httpCode === 429 || ($this->data['__ns_rate_limited'] ?? false);
    }

    /**
     * Check if response is not modified (304)
     */
    public function isNotModified(): bool
    {
        return $this->httpCode === 304 || ($this->data['__ns_not_modified'] ?? false);
    }

    /**
     * Get data value by key
     *
     * @param string $key     Key to retrieve
     * @param mixed  $default Default value if key doesn't exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if data has a key
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Convert to array for caching
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'    => $this->success,
            'data'       => $this->data,
            'error'      => $this->error,
            'http_code'  => $this->httpCode,
            'etag'       => $this->etag,
            'from_cache' => $this->fromCache,
            'duration'   => $this->duration,
        ];
    }

    /**
     * Create from cached array
     *
     * @param array<string, mixed> $cached Cached array data
     */
    public static function fromArray(array $cached): self
    {
        return new self(
            success: $cached['success'] ?? false,
            data: $cached['data'] ?? [],
            error: $cached['error'] ?? null,
            httpCode: $cached['http_code'] ?? 200,
            etag: $cached['etag'] ?? null,
            fromCache: true,
            duration: 0.0
        );
    }
}
