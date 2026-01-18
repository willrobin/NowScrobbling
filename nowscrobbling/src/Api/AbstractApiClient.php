<?php

declare(strict_types=1);

namespace NowScrobbling\Api;

use NowScrobbling\Api\Response\ApiResponse;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Cache\Layers\ETagLayer;
use WP_Error;

/**
 * Abstract API Client
 *
 * Base class for all API clients. Provides:
 * - ETag support for conditional requests
 * - Rate limiting with exponential backoff
 * - Automatic retry on transient failures
 * - Integration with CacheManager
 *
 * @package NowScrobbling\Api
 */
abstract class AbstractApiClient implements ApiClientInterface
{
    /**
     * The service identifier
     */
    protected readonly string $service;

    /**
     * Cache manager
     */
    protected readonly CacheManager $cache;

    /**
     * Rate limiter
     */
    protected readonly RateLimiter $rateLimiter;

    /**
     * ETag layer
     */
    protected readonly ETagLayer $etagLayer;

    /**
     * Default request timeout in seconds
     */
    protected const DEFAULT_TIMEOUT = 10;

    /**
     * Maximum retry attempts
     */
    protected const MAX_RETRIES = 2;

    /**
     * Constructor
     *
     * @param CacheManager $cache Cache manager instance
     */
    public function __construct(CacheManager $cache)
    {
        $this->cache = $cache;
        $this->rateLimiter = new RateLimiter($this->getService());
        $this->etagLayer = $cache->getETagLayer();
    }

    /**
     * @inheritDoc
     */
    abstract public function getService(): string;

    /**
     * @inheritDoc
     */
    abstract public function getBaseUrl(): string;

    /**
     * @inheritDoc
     */
    abstract public function getDefaultTtl(): int;

    /**
     * Get default HTTP headers for requests
     *
     * @return array<string, string>
     */
    abstract protected function getDefaultHeaders(): array;

    /**
     * Fetch data from the API
     *
     * @param string               $endpoint API endpoint
     * @param array<string, mixed> $params   Query parameters
     * @param array<string, mixed> $options  Request options
     *
     * @return ApiResponse
     */
    protected function fetch(
        string $endpoint,
        array $params = [],
        array $options = []
    ): ApiResponse {
        // Check rate limit
        if ($this->rateLimiter->shouldThrottle()) {
            return ApiResponse::rateLimited($this->rateLimiter->getRemainingCooldown());
        }

        $url = $this->buildUrl($endpoint, $params);
        $cacheKey = $this->buildCacheKey($endpoint, $params);
        $ttl = $options['ttl'] ?? $this->getDefaultTtl();

        // Use cache manager with callback
        $result = $this->cache->getOrSet(
            $cacheKey,
            fn() => $this->executeRequest($url, $options),
            $ttl,
            ['service' => $this->getService()]
        );

        // Convert array result back to ApiResponse
        if (is_array($result)) {
            if (isset($result['__ns_error'])) {
                return ApiResponse::error($result['message'] ?? 'Unknown error', $result['http_code'] ?? 500);
            }
            return ApiResponse::cached($result);
        }

        return $result instanceof ApiResponse ? $result : ApiResponse::error('Invalid response');
    }

    /**
     * Execute HTTP request with ETag and retry support
     *
     * @param string               $url     Full URL
     * @param array<string, mixed> $options Request options
     *
     * @return array<string, mixed>
     */
    protected function executeRequest(string $url, array $options = []): array
    {
        $startTime = microtime(true);
        $retries = 0;

        while ($retries <= self::MAX_RETRIES) {
            $args = [
                'timeout' => $options['timeout'] ?? self::DEFAULT_TIMEOUT,
                'headers' => array_merge(
                    $this->getDefaultHeaders(),
                    $this->etagLayer->getRequestHeaders($url),
                    $options['headers'] ?? []
                ),
            ];

            $response = wp_safe_remote_get($url, $args);
            $duration = (microtime(true) - $startTime) * 1000;

            // Handle WP_Error
            if (is_wp_error($response)) {
                $retries++;
                if ($retries <= self::MAX_RETRIES) {
                    usleep(500000 * $retries); // 0.5s, 1s backoff
                    continue;
                }
                $this->rateLimiter->recordError();
                return [
                    '__ns_error' => true,
                    'message' => $response->get_error_message(),
                    'http_code' => 0,
                    'duration' => $duration,
                ];
            }

            $code = wp_remote_retrieve_response_code($response);
            $headers = wp_remote_retrieve_headers($response);

            // Handle 304 Not Modified
            if ($code === 304) {
                $this->rateLimiter->recordSuccess();
                return ['__ns_not_modified' => true];
            }

            // Handle rate limiting
            if ($code === 429) {
                $this->rateLimiter->recordError(429);
                return [
                    '__ns_error' => true,
                    '__ns_rate_limited' => true,
                    'message' => 'Rate limited',
                    'http_code' => 429,
                ];
            }

            // Handle server errors (retry)
            if ($code >= 500 && $retries < self::MAX_RETRIES) {
                $retries++;
                usleep(500000 * $retries);
                continue;
            }

            // Handle client errors
            if ($code >= 400) {
                $this->rateLimiter->recordError($code);
                return [
                    '__ns_error' => true,
                    'message' => "HTTP $code",
                    'http_code' => $code,
                    'duration' => $duration,
                ];
            }

            // Success - store ETag and return data
            $this->etagLayer->storeFromResponse($url, $this->headersToArray($headers));
            $this->rateLimiter->recordSuccess();

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    '__ns_error' => true,
                    'message' => 'Invalid JSON response',
                    'http_code' => $code,
                ];
            }

            return $data ?? [];
        }

        return [
            '__ns_error' => true,
            'message' => 'Max retries exceeded',
            'http_code' => 0,
        ];
    }

    /**
     * Build full URL from endpoint and parameters
     *
     * @param string               $endpoint API endpoint
     * @param array<string, mixed> $params   Query parameters
     */
    protected function buildUrl(string $endpoint, array $params = []): string
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Build cache key from endpoint and parameters
     *
     * @param string               $endpoint API endpoint
     * @param array<string, mixed> $params   Query parameters
     */
    protected function buildCacheKey(string $endpoint, array $params = []): string
    {
        $key = $this->getService() . '_' . $endpoint;

        if (!empty($params)) {
            ksort($params);
            $key .= '_' . md5(serialize($params));
        }

        return $key;
    }

    /**
     * Convert headers object to array
     *
     * @param \Requests_Utility_CaseInsensitiveDictionary|array $headers
     *
     * @return array<string, string>
     */
    protected function headersToArray($headers): array
    {
        if (is_array($headers)) {
            return $headers;
        }

        $result = [];
        foreach ($headers as $key => $value) {
            $result[strtolower($key)] = is_array($value) ? $value[0] : $value;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function testConnection(): ApiResponse
    {
        if (!$this->isConfigured()) {
            return ApiResponse::error('API credentials not configured');
        }

        return $this->performConnectionTest();
    }

    /**
     * Perform service-specific connection test
     */
    abstract protected function performConnectionTest(): ApiResponse;

    /**
     * Get rate limiter status
     *
     * @return array<string, mixed>
     */
    public function getRateLimitStatus(): array
    {
        return $this->rateLimiter->getStatus();
    }

    /**
     * Clear rate limiting state
     */
    public function clearRateLimit(): void
    {
        $this->rateLimiter->clear();
    }
}
