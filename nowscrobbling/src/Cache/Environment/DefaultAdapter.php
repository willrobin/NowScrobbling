<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Environment;

/**
 * Default environment adapter
 *
 * Used when no special hosting environment is detected.
 * Provides basic browser caching via standard Cache-Control headers.
 *
 * @package NowScrobbling\Cache\Environment
 */
final class DefaultAdapter implements EnvironmentAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Default';
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return 'default';
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

        // Vary header for proper cache key generation
        header('Vary: Accept-Encoding');
    }

    /**
     * @inheritDoc
     *
     * Default adapter doesn't support cache tags, but we set a header
     * for potential proxy/middleware use.
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
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
    }

    /**
     * @inheritDoc
     */
    public function supportsPurge(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     *
     * Default adapter is always considered "active" as the fallback.
     */
    public function isActive(): bool
    {
        return true;
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
            'php_sapi'        => PHP_SAPI,
        ];
    }
}
