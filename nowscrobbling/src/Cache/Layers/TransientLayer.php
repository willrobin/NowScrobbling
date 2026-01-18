<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Layers;

use NowScrobbling\Cache\CacheLayerInterface;

/**
 * WordPress transient cache layer
 *
 * Uses WordPress transients for persistent caching. Works with object cache
 * backends (Redis, Memcached) when available, otherwise uses the database.
 *
 * This is the primary persistent cache layer with configurable TTL.
 *
 * @package NowScrobbling\Cache\Layers
 */
final class TransientLayer implements CacheLayerInterface
{
    /**
     * Prefix for all transient keys
     */
    private const PREFIX = 'ns_';

    /**
     * Prefix for metadata transients (stores created timestamp)
     */
    private const META_PREFIX = 'ns_meta_';

    /**
     * Wrapper to distinguish between "not found" and "cached false"
     */
    private const WRAPPER_KEY = '__ns_wrapped';

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

        // Unwrap if it's a wrapped value (to handle false/null values correctly)
        if (is_array($value) && isset($value[self::WRAPPER_KEY])) {
            return $value['value'];
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $metaKey = $this->metaKey($key);

        // Wrap values that could be confused with "not found" (false)
        // Also wrap null to preserve it correctly
        if ($value === false || $value === null) {
            $value = [self::WRAPPER_KEY => true, 'value' => $value];
        }

        // Store the value
        $result = set_transient($prefixedKey, $value, $ttl);

        // Store creation timestamp for age tracking
        if ($result) {
            set_transient($metaKey, time(), $ttl);
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

        // Delete all transients with our prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $wpdb->esc_like(self::PREFIX) . '%',
                '_transient_timeout_' . $wpdb->esc_like(self::PREFIX) . '%'
            )
        );

        // Delete meta transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $wpdb->esc_like(self::META_PREFIX) . '%',
                '_transient_timeout_' . $wpdb->esc_like(self::META_PREFIX) . '%'
            )
        );

        // Clear object cache if available
        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group('transient');
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'transient';
    }

    /**
     * Get the prefixed cache key
     */
    private function prefixKey(string $key): string
    {
        return self::PREFIX . $key;
    }

    /**
     * Get the metadata key for a cache key
     */
    private function metaKey(string $key): string
    {
        return self::META_PREFIX . $key;
    }

    /**
     * Check if using an external object cache
     */
    public function hasObjectCache(): bool
    {
        return (bool) wp_using_ext_object_cache();
    }

    /**
     * Get storage backend type
     */
    public function getBackendType(): string
    {
        if (!$this->hasObjectCache()) {
            return 'database';
        }

        $class = get_class($GLOBALS['wp_object_cache'] ?? new \stdClass());

        return match (true) {
            str_contains(strtolower($class), 'redis')    => 'redis',
            str_contains(strtolower($class), 'memcache') => 'memcached',
            str_contains(strtolower($class), 'apcu')     => 'apcu',
            default                                      => 'object_cache',
        };
    }
}
