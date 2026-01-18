<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Layers;

use NowScrobbling\Cache\CacheLayerInterface;

/**
 * In-memory cache layer
 *
 * Provides per-request caching using a static array. Data is lost
 * when the request ends. This is the fastest cache layer and is
 * checked first in the cache hierarchy.
 *
 * @package NowScrobbling\Cache\Layers
 */
final class InMemoryLayer implements CacheLayerInterface
{
    /**
     * Cache storage
     *
     * @var array<string, array{value: mixed, expires: int, created: int}>
     */
    private static array $cache = [];

    /**
     * Maximum number of items to store
     */
    private const MAX_ITEMS = 100;

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        $item = self::$cache[$key];

        // Check if expired
        if ($item['expires'] > 0 && $item['expires'] < time()) {
            unset(self::$cache[$key]);
            return null;
        }

        return $item['value'];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        // Enforce max items limit (LRU-style cleanup)
        if (count(self::$cache) >= self::MAX_ITEMS && !isset(self::$cache[$key])) {
            $this->evictOldest();
        }

        self::$cache[$key] = [
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time(),
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        if (!isset(self::$cache[$key])) {
            return false;
        }

        $item = self::$cache[$key];

        // Check if expired
        if ($item['expires'] > 0 && $item['expires'] < time()) {
            unset(self::$cache[$key]);
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        if (isset(self::$cache[$key])) {
            unset(self::$cache[$key]);
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getAge(string $key): ?int
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        return time() - self::$cache[$key]['created'];
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        self::$cache = [];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'memory';
    }

    /**
     * Evict the oldest item from cache
     */
    private function evictOldest(): void
    {
        $oldestKey = null;
        $oldestTime = PHP_INT_MAX;

        foreach (self::$cache as $key => $item) {
            if ($item['created'] < $oldestTime) {
                $oldestTime = $item['created'];
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            unset(self::$cache[$oldestKey]);
        }
    }

    /**
     * Get current cache size
     */
    public function getSize(): int
    {
        return count(self::$cache);
    }

    /**
     * Get cache statistics
     *
     * @return array{size: int, max_items: int, keys: array<string>}
     */
    public function getStats(): array
    {
        return [
            'size'      => count(self::$cache),
            'max_items' => self::MAX_ITEMS,
            'keys'      => array_keys(self::$cache),
        ];
    }
}
