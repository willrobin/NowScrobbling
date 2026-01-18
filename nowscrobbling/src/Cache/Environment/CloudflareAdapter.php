<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Environment;

/**
 * Cloudflare environment adapter
 *
 * Handles Cloudflare-specific cache headers:
 * - CDN-Cache-Control for edge caching
 * - Cache-Tag for targeted purging
 * - Stale-while-revalidate support
 *
 * Works with Cloudflare free tier (no API key required).
 *
 * @package NowScrobbling\Cache\Environment
 */
final class CloudflareAdapter implements EnvironmentAdapterInterface
{
    /**
     * Default stale-while-revalidate multiplier
     */
    private const SWR_MULTIPLIER = 2;

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Cloudflare';
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return 'cloudflare';
    }

    /**
     * @inheritDoc
     *
     * Sets both standard Cache-Control and Cloudflare-specific CDN-Cache-Control.
     */
    public function setHeaders(int $maxAge, bool $isPrivate = false): void
    {
        if (headers_sent()) {
            return;
        }

        $directive = $isPrivate ? 'private' : 'public';
        $swrDuration = $maxAge * self::SWR_MULTIPLIER;

        // Standard Cache-Control with stale-while-revalidate
        header(sprintf(
            'Cache-Control: %s, max-age=%d, stale-while-revalidate=%d',
            $directive,
            $maxAge,
            $swrDuration
        ));

        // Cloudflare-specific: CDN cache can be longer than browser cache
        if (!$isPrivate) {
            header(sprintf('CDN-Cache-Control: max-age=%d', $maxAge * 2));
        }

        // Vary header for proper cache key generation
        header('Vary: Accept-Encoding', false);
    }

    /**
     * @inheritDoc
     *
     * Sets Cache-Tag header for targeted cache purging via Cloudflare API.
     */
    public function setCacheTags(array $tags): void
    {
        if (headers_sent() || empty($tags)) {
            return;
        }

        // Add default tag
        $tags = array_merge(['nowscrobbling'], $tags);

        // Cloudflare Cache-Tag header (comma-separated)
        header('Cache-Tag: ' . implode(',', array_unique($tags)));
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
        header('CDN-Cache-Control: no-store');
        header('Pragma: no-cache');
    }

    /**
     * @inheritDoc
     *
     * Cloudflare supports purge via API, but we don't require API key.
     */
    public function supportsPurge(): bool
    {
        return false; // API key not required in v2.0
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return isset($_SERVER['HTTP_CF_RAY'])
            || isset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    /**
     * @inheritDoc
     */
    public function getDiagnostics(): array
    {
        return [
            'name'        => $this->getName(),
            'active'      => $this->isActive(),
            'ray_id'      => $_SERVER['HTTP_CF_RAY'] ?? null,
            'country'     => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null,
            'connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            'visitor_scheme' => $_SERVER['HTTP_CF_VISITOR'] ?? null,
        ];
    }

    /**
     * Get Cloudflare ray ID
     */
    public function getRayId(): ?string
    {
        return $_SERVER['HTTP_CF_RAY'] ?? null;
    }

    /**
     * Get visitor's country code
     */
    public function getCountryCode(): ?string
    {
        return $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
    }

    /**
     * Check if request is over HTTPS (via Cloudflare)
     */
    public function isHttps(): bool
    {
        $visitor = $_SERVER['HTTP_CF_VISITOR'] ?? '';

        if ($visitor !== '' && is_string($visitor)) {
            $decoded = json_decode($visitor, true);
            if (is_array($decoded) && ($decoded['scheme'] ?? '') === 'https') {
                return true;
            }
        }

        return false;
    }
}
