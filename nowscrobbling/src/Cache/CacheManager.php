<?php

declare(strict_types=1);

namespace NowScrobbling\Cache;

use NowScrobbling\Cache\Layers\InMemoryLayer;
use NowScrobbling\Cache\Layers\TransientLayer;
use NowScrobbling\Cache\Layers\FallbackLayer;
use NowScrobbling\Cache\Layers\ETagLayer;
use NowScrobbling\Cache\Lock\ThunderingHerdLock;

/**
 * Cache Manager - Multi-layer cache orchestration
 *
 * Coordinates the multi-layer caching strategy:
 * 1. In-Memory (per-request, fastest)
 * 2. Transient (primary persistent cache)
 * 3. Fallback (7-day emergency cache)
 * 4. ETag (HTTP conditional requests)
 *
 * Implements stale-while-revalidate and thundering herd protection.
 *
 * @package NowScrobbling\Cache
 */
final class CacheManager
{
    /**
     * Cache layers
     *
     * @var array<string, CacheLayerInterface>
     */
    private array $layers;

    /**
     * Thundering herd lock
     */
    private readonly ThunderingHerdLock $lock;

    /**
     * Cache statistics
     *
     * @var array{hits: int, misses: int, fallbacks: int, errors: int}
     */
    private array $stats = [
        'hits'      => 0,
        'misses'    => 0,
        'fallbacks' => 0,
        'errors'    => 0,
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->layers = [
            'memory'    => new InMemoryLayer(),
            'transient' => new TransientLayer(),
            'fallback'  => new FallbackLayer(),
            'etag'      => new ETagLayer(),
        ];

        $this->lock = new ThunderingHerdLock();
    }

    /**
     * Get or set a cached value
     *
     * Implements the full cache hierarchy with stale-while-revalidate
     * and thundering herd protection.
     *
     * @param string                $key        Cache key
     * @param callable              $callback   Function to fetch fresh data
     * @param int                   $primaryTtl Primary cache TTL in seconds
     * @param array<string, mixed>  $options    Additional options
     *
     * @return mixed The cached or fresh value
     */
    public function getOrSet(
        string $key,
        callable $callback,
        int $primaryTtl = 300,
        array $options = []
    ): mixed {
        $service = $options['service'] ?? 'unknown';

        // 1. Check in-memory cache (fastest)
        $value = $this->layers['memory']->get($key);
        if ($value !== null) {
            $this->stats['hits']++;
            $this->logDebug("Cache hit (memory): $key", $service);
            return $value;
        }

        // 2. Check primary transient cache
        $value = $this->layers['transient']->get($key);
        if ($value !== null) {
            $this->stats['hits']++;
            $this->layers['memory']->set($key, $value, $primaryTtl);
            $this->logDebug("Cache hit (transient): $key", $service);
            return $value;
        }

        // 3. Check fallback cache for stale data
        $fallback = $this->layers['fallback']->get($key);
        $hasFallback = $fallback !== null;

        // 4. Try to acquire lock for refresh
        if (!$this->lock->acquire($key)) {
            // Another request is refreshing, return fallback if available
            if ($hasFallback) {
                $this->stats['fallbacks']++;
                $this->logDebug("Lock held, serving fallback: $key", $service);
                return $fallback;
            }

            // No fallback, wait for lock
            $this->lock->wait($key);

            // Check if data is now in cache
            $value = $this->layers['memory']->get($key)
                  ?? $this->layers['transient']->get($key);

            if ($value !== null) {
                $this->stats['hits']++;
                return $value;
            }
        }

        try {
            // 5. Fetch fresh data
            $this->stats['misses']++;
            $this->logDebug("Cache miss, fetching: $key", $service);

            $value = $callback();

            // 6. Handle errors - return fallback if available
            if ($this->isError($value)) {
                $this->stats['errors']++;
                $this->logDebug("Fetch error, checking fallback: $key", $service);

                if ($hasFallback) {
                    $this->stats['fallbacks']++;
                    return $fallback;
                }

                // No fallback, return the error
                return $value;
            }

            // 7. Store in all cache layers
            $this->storeAll($key, $value, $primaryTtl);

            return $value;

        } finally {
            $this->lock->release($key);
        }
    }

    /**
     * Get a value from cache (no refresh on miss)
     *
     * @param string $key Cache key
     *
     * @return mixed The cached value, or null if not found
     */
    public function get(string $key): mixed
    {
        // Check layers in order
        foreach (['memory', 'transient', 'fallback'] as $layerName) {
            $value = $this->layers[$layerName]->get($key);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Set a value in cache
     *
     * @param string $key   Cache key
     * @param mixed  $value Value to cache
     * @param int    $ttl   TTL in seconds
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        return $this->storeAll($key, $value, $ttl);
    }

    /**
     * Delete a value from all cache layers
     *
     * @param string $key Cache key
     */
    public function delete(string $key): bool
    {
        $success = true;

        foreach ($this->layers as $layer) {
            if (!$layer->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clear all cache layers
     */
    public function clearAll(): bool
    {
        $success = true;

        foreach ($this->layers as $layer) {
            if (!$layer->clear()) {
                $success = false;
            }
        }

        $this->resetStats();

        /**
         * Fires after all caches are cleared
         */
        do_action('nowscrobbling_caches_cleared');

        return $success;
    }

    /**
     * Clear primary caches (keep fallback intact)
     *
     * Useful when you want fresh data but keep emergency fallbacks.
     */
    public function clearPrimary(): bool
    {
        $success = true;

        $success = $this->layers['memory']->clear() && $success;
        $success = $this->layers['transient']->clear() && $success;
        $success = $this->layers['etag']->clear() && $success;

        return $success;
    }

    /**
     * Store value in all appropriate layers
     *
     * @param string $key   Cache key
     * @param mixed  $value Value to store
     * @param int    $ttl   Primary TTL in seconds
     */
    private function storeAll(string $key, mixed $value, int $ttl): bool
    {
        $success = true;

        // Store in memory (uses same TTL)
        $this->layers['memory']->set($key, $value, $ttl);

        // Store in primary transient
        if (!$this->layers['transient']->set($key, $value, $ttl)) {
            $success = false;
        }

        // Store in fallback (only if not an error)
        if (!$this->isError($value)) {
            $this->layers['fallback']->set($key, $value, 0); // Fallback uses its own TTL
        }

        return $success;
    }

    /**
     * Check if a value represents an error response
     *
     * @param mixed $value Value to check
     */
    private function isError(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        return isset($value['error']) || isset($value['__ns_error']);
    }

    /**
     * Get a specific cache layer
     *
     * @param string $name Layer name (memory, transient, fallback, etag)
     */
    public function getLayer(string $name): ?CacheLayerInterface
    {
        return $this->layers[$name] ?? null;
    }

    /**
     * Get the ETag layer for HTTP conditional requests
     */
    public function getETagLayer(): ETagLayer
    {
        /** @var ETagLayer */
        return $this->layers['etag'];
    }

    /**
     * Get the thundering herd lock
     */
    public function getLock(): ThunderingHerdLock
    {
        return $this->lock;
    }

    /**
     * Get cache statistics
     *
     * @return array{hits: int, misses: int, fallbacks: int, errors: int, hit_rate: float}
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0.0;

        return [
            ...$this->stats,
            'hit_rate' => round($hitRate, 2),
        ];
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits'      => 0,
            'misses'    => 0,
            'fallbacks' => 0,
            'errors'    => 0,
        ];
    }

    /**
     * Log debug message
     *
     * @param string $message Message to log
     * @param string $service Service context
     */
    private function logDebug(string $message, string $service): void
    {
        if (!(bool) get_option('ns_debug_enabled', false)) {
            return;
        }

        /**
         * Fires to log cache debug messages
         *
         * @param string $message The debug message
         * @param string $service The service name
         * @param array  $stats   Current cache statistics
         */
        do_action('nowscrobbling_cache_debug', $message, $service, $this->stats);
    }

    /**
     * Get diagnostic information
     *
     * @return array<string, mixed>
     */
    public function getDiagnostics(): array
    {
        $transientLayer = $this->layers['transient'];

        return [
            'stats'           => $this->getStats(),
            'object_cache'    => wp_using_ext_object_cache(),
            'backend_type'    => $transientLayer instanceof TransientLayer
                                 ? $transientLayer->getBackendType()
                                 : 'unknown',
            'memory_items'    => $this->layers['memory'] instanceof InMemoryLayer
                                 ? $this->layers['memory']->getSize()
                                 : 0,
            'locks_held'      => count($this->lock->getHeldLocks()),
        ];
    }
}
