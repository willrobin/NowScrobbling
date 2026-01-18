<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Environment;

/**
 * Interface for environment-specific cache adapters
 *
 * Each adapter handles the specific cache headers and behaviors
 * for its hosting environment (Cloudflare, LiteSpeed, etc.)
 *
 * @package NowScrobbling\Cache\Environment
 */
interface EnvironmentAdapterInterface
{
    /**
     * Get the environment name
     *
     * @return string Human-readable name (e.g., "Cloudflare", "LiteSpeed")
     */
    public function getName(): string;

    /**
     * Get the environment identifier
     *
     * @return string Machine-readable identifier (e.g., "cloudflare", "litespeed")
     */
    public function getId(): string;

    /**
     * Set cache control headers
     *
     * @param int  $maxAge    Cache duration in seconds
     * @param bool $isPrivate Whether to use private caching
     */
    public function setHeaders(int $maxAge, bool $isPrivate = false): void;

    /**
     * Send cache tags for targeted invalidation
     *
     * @param array<string> $tags Cache tags
     */
    public function setCacheTags(array $tags): void;

    /**
     * Signal that the response should not be cached
     */
    public function setNoCache(): void;

    /**
     * Check if this environment supports cache purging
     */
    public function supportsPurge(): bool;

    /**
     * Check if this environment is currently active
     */
    public function isActive(): bool;

    /**
     * Get environment-specific diagnostics
     *
     * @return array<string, mixed>
     */
    public function getDiagnostics(): array;
}
