<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Environment;

/**
 * Object Cache environment adapter
 *
 * For environments with external object cache (Redis, Memcached)
 * but without a CDN. Focuses on browser caching and leverages
 * the fast transient storage.
 *
 * @package NowScrobbling\Cache\Environment
 */
final class ObjectCacheAdapter implements EnvironmentAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Object Cache';
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return 'object_cache';
    }

    /**
     * @inheritDoc
     */
    public function setHeaders(int $maxAge, bool $isPrivate = false): void
    {
        if (headers_sent()) {
            return;
        }

        $directive = $isPrivate ? 'private' : 'public';

        // Standard Cache-Control for browser caching
        header(sprintf(
            'Cache-Control: %s, max-age=%d',
            $directive,
            $maxAge
        ));

        // ETag for conditional requests
        header('Vary: Accept-Encoding');
    }

    /**
     * @inheritDoc
     *
     * Object cache doesn't support cache tags at the HTTP level,
     * but we set a header for potential middleware use.
     */
    public function setCacheTags(array $tags): void
    {
        if (headers_sent() || empty($tags)) {
            return;
        }

        $tags = array_merge(['nowscrobbling'], $tags);
        header('X-Cache-Tags: ' . implode(',', array_unique($tags)));
    }

    /**
     * @inheritDoc
     */
    public function setNoCache(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * @inheritDoc
     */
    public function supportsPurge(): bool
    {
        return true; // Can purge via wp_cache_flush_group
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return wp_using_ext_object_cache();
    }

    /**
     * @inheritDoc
     */
    public function getDiagnostics(): array
    {
        return [
            'name'       => $this->getName(),
            'active'     => $this->isActive(),
            'type'       => $this->getCacheType(),
            'flush_group' => $this->supportsFlushGroup(),
        ];
    }

    /**
     * Get the object cache type
     */
    public function getCacheType(): string
    {
        if (!$this->isActive()) {
            return 'none';
        }

        $class = get_class($GLOBALS['wp_object_cache'] ?? new \stdClass());

        return match (true) {
            str_contains(strtolower($class), 'redis')    => 'redis',
            str_contains(strtolower($class), 'memcache') => 'memcached',
            str_contains(strtolower($class), 'apcu')     => 'apcu',
            default                                      => 'unknown',
        };
    }

    /**
     * Check if object cache supports group flushing
     */
    public function supportsFlushGroup(): bool
    {
        return function_exists('wp_cache_flush_group');
    }

    /**
     * Flush the nowscrobbling cache group
     */
    public function flushGroup(): bool
    {
        if (!$this->supportsFlushGroup()) {
            return false;
        }

        return wp_cache_flush_group('nowscrobbling');
    }
}
