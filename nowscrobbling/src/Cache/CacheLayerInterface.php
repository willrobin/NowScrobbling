<?php

declare(strict_types=1);

namespace NowScrobbling\Cache;

/**
 * Interface for cache layer implementations
 *
 * All cache layers (memory, transient, fallback, etag) implement this interface
 * to provide a consistent API for the CacheManager.
 *
 * @package NowScrobbling\Cache
 */
interface CacheLayerInterface
{
    /**
     * Get a value from the cache
     *
     * @param string $key The cache key
     *
     * @return mixed The cached value, or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Set a value in the cache
     *
     * @param string $key   The cache key
     * @param mixed  $value The value to cache
     * @param int    $ttl   Time-to-live in seconds
     *
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, int $ttl): bool;

    /**
     * Check if a key exists in the cache
     *
     * @param string $key The cache key
     *
     * @return bool True if the key exists, false otherwise
     */
    public function has(string $key): bool;

    /**
     * Delete a value from the cache
     *
     * @param string $key The cache key
     *
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Get the age of a cached item in seconds
     *
     * Used for stale-while-revalidate logic.
     *
     * @param string $key The cache key
     *
     * @return int|null Age in seconds, or null if not found
     */
    public function getAge(string $key): ?int;

    /**
     * Clear all items from this cache layer
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;

    /**
     * Get the name of this cache layer
     *
     * Used for logging and debugging.
     *
     * @return string The layer name
     */
    public function getName(): string;
}
