<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Layers;

use NowScrobbling\Cache\CacheLayerInterface;

/**
 * Fallback cache layer
 *
 * Long-term emergency cache that stores successful API responses for 7 days.
 * This layer is NEVER overwritten with error responses - it only stores
 * valid data to ensure we always have something to show.
 *
 * Philosophy: "Old data is better than error messages"
 *
 * @package NowScrobbling\Cache\Layers
 */
final class FallbackLayer implements CacheLayerInterface
{
    /**
     * Prefix for fallback transients
     */
    private const PREFIX = 'ns_fallback_';

    /**
     * Prefix for metadata transients
     */
    private const META_PREFIX = 'ns_fbmeta_';

    /**
     * Default TTL: 7 days in seconds
     */
    private const DEFAULT_TTL = 604800;

    /**
     * Maximum TTL: 30 days in seconds
     */
    private const MAX_TTL = 2592000;

    /**
     * Wrapper to distinguish between "not found" and "cached false"
     */
    private const WRAPPER_KEY = '__ns_fb_wrapped';

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        $prefixedKey = $this->prefixKey($key);
        $value = get_transient($prefixedKey);

        // Transient doesn't exist
        if ($value === false) {
            return null;
        }

        // Unwrap if it's a wrapped value (to handle false values correctly)
        if (is_array($value) && isset($value[self::WRAPPER_KEY])) {
            return $value['value'];
        }

        return $value;
    }

    /**
     * @inheritDoc
     *
     * Note: The $ttl parameter is ignored. Fallback cache always uses DEFAULT_TTL.
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        // Only store non-null, non-error values
        if ($value === null || $this->isErrorResponse($value)) {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);
        $metaKey = $this->metaKey($key);

        // Wrap false values to distinguish from "not found"
        if ($value === false) {
            $value = [self::WRAPPER_KEY => true, 'value' => $value];
        }

        // Always use the default TTL for fallback storage
        $result = set_transient($prefixedKey, $value, self::DEFAULT_TTL);

        if ($result) {
            // Store creation timestamp
            set_transient($metaKey, time(), self::DEFAULT_TTL);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $value = get_transient($prefixedKey);

        // Transient doesn't exist
        if ($value === false) {
            return false;
        }

        // If we got here, either we have a real value or a wrapped value
        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $metaKey = $this->metaKey($key);

        delete_transient($metaKey);
        return delete_transient($prefixedKey);
    }

    /**
     * @inheritDoc
     */
    public function getAge(string $key): ?int
    {
        $metaKey = $this->metaKey($key);
        $created = get_transient($metaKey);

        if ($created === false) {
            return null;
        }

        return time() - (int) $created;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        global $wpdb;

        // Delete all fallback transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $wpdb->esc_like(self::PREFIX) . '%',
                '_transient_timeout_' . $wpdb->esc_like(self::PREFIX) . '%'
            )
        );

        // Delete metadata transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $wpdb->esc_like(self::META_PREFIX) . '%',
                '_transient_timeout_' . $wpdb->esc_like(self::META_PREFIX) . '%'
            )
        );

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'fallback';
    }

    /**
     * Get the prefixed cache key
     */
    private function prefixKey(string $key): string
    {
        return self::PREFIX . $key;
    }

    /**
     * Get the metadata key
     */
    private function metaKey(string $key): string
    {
        return self::META_PREFIX . $key;
    }

    /**
     * Check if a value represents an error response
     *
     * @param mixed $value The value to check
     */
    private function isErrorResponse(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        // Ignore wrapper arrays - they're not errors
        if (isset($value[self::WRAPPER_KEY])) {
            return false;
        }

        // Check for error markers
        if (isset($value['error'])) {
            return true;
        }

        if (isset($value['__ns_error'])) {
            return true;
        }

        return false;
    }

    /**
     * Get the default TTL for fallback storage
     */
    public function getDefaultTtl(): int
    {
        return self::DEFAULT_TTL;
    }

    /**
     * Check if fallback data is stale (older than primary TTL)
     *
     * @param string $key        The cache key
     * @param int    $primaryTtl The primary cache TTL to compare against
     */
    public function isStale(string $key, int $primaryTtl): bool
    {
        $age = $this->getAge($key);

        if ($age === null) {
            return true;
        }

        return $age > $primaryTtl;
    }

    /**
     * Get approximate age category for logging
     *
     * @param string $key The cache key
     */
    public function getAgeCategory(string $key): string
    {
        $age = $this->getAge($key);

        if ($age === null) {
            return 'unknown';
        }

        return match (true) {
            $age < 3600      => 'fresh',      // < 1 hour
            $age < 86400     => 'recent',     // < 1 day
            $age < 259200    => 'stale',      // < 3 days
            $age < 604800    => 'very_stale', // < 7 days
            default          => 'expired',
        };
    }
}
