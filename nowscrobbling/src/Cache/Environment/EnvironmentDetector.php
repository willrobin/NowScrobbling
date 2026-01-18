<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Environment;

/**
 * Environment Detector
 *
 * Auto-detects the hosting environment (Cloudflare, LiteSpeed, etc.)
 * and returns the appropriate adapter for cache header management.
 *
 * Detection order:
 * 1. Cloudflare (via CF-Ray header)
 * 2. LiteSpeed (via SERVER_SOFTWARE)
 * 3. Nginx (via SERVER_SOFTWARE)
 * 4. Object Cache (Redis/Memcached)
 * 5. Default (browser cache only)
 *
 * @package NowScrobbling\Cache\Environment
 */
final class EnvironmentDetector
{
    /**
     * Cached detection result
     */
    private ?string $detectedEnv = null;

    /**
     * Cached adapter instance
     */
    private ?EnvironmentAdapterInterface $adapter = null;

    /**
     * Detect the current environment
     *
     * @return string Environment identifier
     */
    public function detect(): string
    {
        if ($this->detectedEnv !== null) {
            return $this->detectedEnv;
        }

        $this->detectedEnv = match (true) {
            $this->isCloudflare()  => 'cloudflare',
            $this->isLiteSpeed()   => 'litespeed',
            $this->isNginxFastCGI() => 'nginx',
            $this->hasObjectCache() => 'object_cache',
            default                 => 'default',
        };

        return $this->detectedEnv;
    }

    /**
     * Get the appropriate adapter for the detected environment
     */
    public function getAdapter(): EnvironmentAdapterInterface
    {
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        $this->adapter = match ($this->detect()) {
            'cloudflare'   => new CloudflareAdapter(),
            'litespeed'    => new LiteSpeedAdapter(),
            'nginx'        => new NginxAdapter(),
            'object_cache' => new ObjectCacheAdapter(),
            default        => new DefaultAdapter(),
        };

        return $this->adapter;
    }

    /**
     * Check if running behind Cloudflare
     */
    public function isCloudflare(): bool
    {
        return isset($_SERVER['HTTP_CF_RAY'])
            || isset($_SERVER['HTTP_CF_CONNECTING_IP'])
            || isset($_SERVER['HTTP_CF_IPCOUNTRY']);
    }

    /**
     * Check if running on LiteSpeed server
     */
    public function isLiteSpeed(): bool
    {
        if (!isset($_SERVER['SERVER_SOFTWARE'])) {
            return false;
        }

        return stripos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false;
    }

    /**
     * Check if running on Nginx with FastCGI cache
     */
    public function isNginxFastCGI(): bool
    {
        if (!isset($_SERVER['SERVER_SOFTWARE'])) {
            return false;
        }

        return stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false;
    }

    /**
     * Check if using an external object cache
     */
    public function hasObjectCache(): bool
    {
        return wp_using_ext_object_cache();
    }

    /**
     * Get the object cache type if available
     *
     * @return string|null Cache type (redis, memcached, apcu) or null
     */
    public function getObjectCacheType(): ?string
    {
        if (!$this->hasObjectCache()) {
            return null;
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
     * Get Cloudflare ray ID if available
     */
    public function getCloudflareRayId(): ?string
    {
        return $_SERVER['HTTP_CF_RAY'] ?? null;
    }

    /**
     * Get Cloudflare country code if available
     */
    public function getCloudflareCountry(): ?string
    {
        return $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
    }

    /**
     * Get the real client IP (handles proxies)
     */
    public function getClientIp(): string
    {
        // Cloudflare
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Generic proxy
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get comprehensive environment diagnostics
     *
     * @return array<string, mixed>
     */
    public function getDiagnostics(): array
    {
        return [
            'detected_env'     => $this->detect(),
            'adapter'          => $this->getAdapter()->getName(),
            'cloudflare'       => [
                'active'  => $this->isCloudflare(),
                'ray_id'  => $this->getCloudflareRayId(),
                'country' => $this->getCloudflareCountry(),
            ],
            'litespeed'        => $this->isLiteSpeed(),
            'nginx'            => $this->isNginxFastCGI(),
            'object_cache'     => [
                'active' => $this->hasObjectCache(),
                'type'   => $this->getObjectCacheType(),
            ],
            'server_software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'php_sapi'         => PHP_SAPI,
        ];
    }

    /**
     * Reset cached detection (mainly for testing)
     */
    public function reset(): void
    {
        $this->detectedEnv = null;
        $this->adapter = null;
    }
}
