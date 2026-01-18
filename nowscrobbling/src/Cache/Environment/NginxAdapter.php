<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Environment;

/**
 * Nginx environment adapter
 *
 * Handles Nginx FastCGI cache headers:
 * - X-Accel-Expires for FastCGI cache duration
 * - Cache-Control for browser caching
 *
 * @package NowScrobbling\Cache\Environment
 */
final class NginxAdapter implements EnvironmentAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Nginx';
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return 'nginx';
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

        // Nginx FastCGI cache header
        if (!$isPrivate) {
            header(sprintf('X-Accel-Expires: %d', $maxAge));
        } else {
            header('X-Accel-Expires: 0');
        }
    }

    /**
     * @inheritDoc
     *
     * Nginx doesn't support cache tags natively, but we can set a header
     * for use by custom purge scripts.
     */
    public function setCacheTags(array $tags): void
    {
        if (headers_sent() || empty($tags)) {
            return;
        }

        $tags = array_merge(['nowscrobbling'], $tags);

        // Custom header for potential purge script integration
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
        header('X-Accel-Expires: 0');
        header('Pragma: no-cache');
    }

    /**
     * @inheritDoc
     */
    public function supportsPurge(): bool
    {
        return false; // Requires custom configuration
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        if (!isset($_SERVER['SERVER_SOFTWARE'])) {
            return false;
        }

        return stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false;
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
            'fastcgi'         => $this->isFastCGI(),
        ];
    }

    /**
     * Check if running under FastCGI
     */
    public function isFastCGI(): bool
    {
        return PHP_SAPI === 'fpm-fcgi' || PHP_SAPI === 'cgi-fcgi';
    }
}
