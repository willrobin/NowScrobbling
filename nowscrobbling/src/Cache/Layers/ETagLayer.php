<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Layers;

use NowScrobbling\Cache\CacheLayerInterface;

/**
 * ETag cache layer
 *
 * Stores HTTP ETag values for conditional requests (If-None-Match headers).
 * When an API returns a 304 Not Modified response, we can renew the cache
 * TTL without re-fetching the data.
 *
 * This layer saves bandwidth and reduces API rate limit usage.
 *
 * @package NowScrobbling\Cache\Layers
 */
final class ETagLayer implements CacheLayerInterface
{
    /**
     * Prefix for ETag transients
     */
    private const PREFIX = 'ns_etag_';

    /**
     * Default TTL: 24 hours in seconds
     */
    private const DEFAULT_TTL = 86400;

    /**
     * @inheritDoc
     *
     * Returns the stored ETag string for a URL/key.
     */
    public function get(string $key): mixed
    {
        $prefixedKey = $this->prefixKey($key);
        $data = get_transient($prefixedKey);

        if ($data === false || !is_array($data)) {
            return null;
        }

        return $data['etag'] ?? null;
    }

    /**
     * @inheritDoc
     *
     * Stores an ETag value along with metadata.
     * The $value should be the ETag string.
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);

        $data = [
            'etag'    => $value,
            'created' => time(),
        ];

        return set_transient($prefixedKey, $data, $ttl > 0 ? $ttl : self::DEFAULT_TTL);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $data = get_transient($prefixedKey);

        return $data !== false && is_array($data) && isset($data['etag']);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefixKey($key);
        return delete_transient($prefixedKey);
    }

    /**
     * @inheritDoc
     */
    public function getAge(string $key): ?int
    {
        $prefixedKey = $this->prefixKey($key);
        $data = get_transient($prefixedKey);

        if ($data === false || !is_array($data) || !isset($data['created'])) {
            return null;
        }

        return time() - (int) $data['created'];
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $wpdb->esc_like(self::PREFIX) . '%',
                '_transient_timeout_' . $wpdb->esc_like(self::PREFIX) . '%'
            )
        );

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'etag';
    }

    /**
     * Get the prefixed cache key
     */
    private function prefixKey(string $key): string
    {
        return self::PREFIX . md5($key);
    }

    /**
     * Get ETag for HTTP request headers
     *
     * Returns the If-None-Match header value if an ETag exists.
     *
     * @param string $url The URL to get the ETag for
     *
     * @return array<string, string> Headers array, empty if no ETag
     */
    public function getRequestHeaders(string $url): array
    {
        $etag = $this->get($url);

        if ($etag === null) {
            return [];
        }

        return ['If-None-Match' => $etag];
    }

    /**
     * Store ETag from HTTP response headers
     *
     * @param string               $url      The URL that was requested
     * @param array<string,string> $headers  Response headers
     * @param int                  $ttl      TTL in seconds
     */
    public function storeFromResponse(string $url, array $headers, int $ttl = 0): bool
    {
        // Normalize header keys to lowercase
        $headers = array_change_key_case($headers, CASE_LOWER);

        $etag = $headers['etag'] ?? null;

        if ($etag === null || $etag === '') {
            return false;
        }

        // Remove quotes from ETag if present
        $etag = trim($etag, '"');

        return $this->set($url, $etag, $ttl > 0 ? $ttl : self::DEFAULT_TTL);
    }

    /**
     * Check if response indicates not modified (304)
     *
     * @param int $statusCode HTTP status code
     */
    public function isNotModified(int $statusCode): bool
    {
        return $statusCode === 304;
    }

    /**
     * Get default TTL for ETag storage
     */
    public function getDefaultTtl(): int
    {
        return self::DEFAULT_TTL;
    }
}
