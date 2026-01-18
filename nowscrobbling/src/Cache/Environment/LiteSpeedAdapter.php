<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Environment;

/**
 * LiteSpeed environment adapter
 *
 * Handles LiteSpeed-specific cache headers (LSCache):
 * - X-LiteSpeed-Cache-Control for edge caching
 * - X-LiteSpeed-Tag for targeted purging
 * - X-LiteSpeed-Vary for cache variation
 *
 * @package NowScrobbling\Cache\Environment
 */
final class LiteSpeedAdapter implements EnvironmentAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'LiteSpeed';
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return 'litespeed';
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

        // Standard Cache-Control
        header(sprintf(
            'Cache-Control: %s, max-age=%d',
            $directive,
            $maxAge
        ));

        // LiteSpeed-specific header
        if (!$isPrivate) {
            header(sprintf(
                'X-LiteSpeed-Cache-Control: public, max-age=%d',
                $maxAge
            ));
        } else {
            header('X-LiteSpeed-Cache-Control: no-cache');
        }
    }

    /**
     * @inheritDoc
     */
    public function setCacheTags(array $tags): void
    {
        if (headers_sent() || empty($tags)) {
            return;
        }

        $tags = array_merge(['nowscrobbling'], $tags);

        // LiteSpeed tag header
        header('X-LiteSpeed-Tag: ' . implode(',', array_unique($tags)));
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
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('Pragma: no-cache');
    }

    /**
     * @inheritDoc
     */
    public function supportsPurge(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        if (!isset($_SERVER['SERVER_SOFTWARE'])) {
            return false;
        }

        return stripos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false;
    }

    /**
     * @inheritDoc
     */
    public function getDiagnostics(): array
    {
        return [
            'name'            => $this->getName(),
            'active'          => $this->isActive(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'supports_purge'  => $this->supportsPurge(),
        ];
    }

    /**
     * Purge cache by tag
     *
     * @param string $tag Tag to purge
     */
    public function purgeByTag(string $tag): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-LiteSpeed-Purge: tag=' . $tag);
    }

    /**
     * Purge all LiteSpeed cache
     */
    public function purgeAll(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-LiteSpeed-Purge: *');
    }
}
