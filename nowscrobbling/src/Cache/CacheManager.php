<?php
/**
 * Cache Manager
 *
 * Central cache management with multi-layer support.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Cache;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CacheManager
 *
 * Manages the multi-layer caching system:
 * 1. In-Memory (per request)
 * 2. Primary Transient (short-lived)
 * 3. Fallback Transient (long-lived backup)
 *
 * Core principle: "Old data is better than error messages"
 */
class CacheManager {

    /**
     * In-memory cache for the current request.
     *
     * @var array<string, mixed>
     */
    private static array $memoryCache = [];

    /**
     * Cache key prefix.
     *
     * @var string
     */
    private const PREFIX = 'ns_';

    /**
     * Get or set cached data.
     *
     * @param string   $key      Cache key.
     * @param callable $fetcher  Function to fetch fresh data.
     * @param DataType $type     Data type (determines TTL).
     * @param string   $provider Provider ID (for metrics).
     * @return CacheResult
     */
    public function get(
        string $key,
        callable $fetcher,
        DataType $type,
        string $provider = 'generic'
    ): CacheResult {
        $full_key = $this->buildKey( $key, $provider );

        // Layer 1: Check in-memory cache.
        if ( isset( self::$memoryCache[ $full_key ] ) ) {
            return new CacheResult(
                data: self::$memoryCache[ $full_key ],
                source: CacheSource::MEMORY,
                age: 0,
                isStale: false
            );
        }

        // Layer 2: Check primary transient.
        $cached = get_transient( $full_key );
        if ( $cached !== false ) {
            self::$memoryCache[ $full_key ] = $cached;

            return new CacheResult(
                data: $cached,
                source: CacheSource::PRIMARY,
                age: $this->getCacheAge( $full_key ),
                isStale: false
            );
        }

        // Layer 3: Try to fetch fresh data.
        try {
            $fresh_data = $fetcher();

            if ( $fresh_data !== null && ! $this->isErrorResponse( $fresh_data ) ) {
                $this->set( $full_key, $fresh_data, $type );

                return new CacheResult(
                    data: $fresh_data,
                    source: CacheSource::FRESH,
                    age: 0,
                    isStale: false
                );
            }
        } catch ( \Throwable $e ) {
            // Log the error but continue to fallback.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "[NowScrobbling] Cache fetch error for {$key}: " . $e->getMessage() );
            }
        }

        // Layer 4: Use fallback cache.
        $fallback = $this->getFallback( $full_key );
        if ( $fallback !== null ) {
            return new CacheResult(
                data: $fallback,
                source: CacheSource::FALLBACK,
                age: $this->getFallbackAge( $full_key ),
                isStale: true
            );
        }

        // No data available.
        return new CacheResult(
            data: null,
            source: CacheSource::MISS,
            age: 0,
            isStale: true
        );
    }

    /**
     * Store data in cache.
     *
     * @param string   $key  Cache key.
     * @param mixed    $data Data to cache.
     * @param DataType $type Data type.
     * @return void
     */
    public function set( string $key, $data, DataType $type ): void {
        // Store in memory.
        self::$memoryCache[ $key ] = $data;

        // Store in primary transient.
        set_transient( $key, $data, $type->getCacheTTL() );

        // Store timestamp for age calculation.
        set_transient( $key . '_time', time(), $type->getCacheTTL() );

        // Update fallback (unless it's protected and we're storing an error).
        if ( ! $type->protectFallback() || ! $this->isErrorResponse( $data ) ) {
            $this->setFallback( $key, $data, $type->getFallbackTTL() );
        }
    }

    /**
     * Get fallback cache.
     *
     * @param string $key Cache key.
     * @return mixed|null
     */
    private function getFallback( string $key ) {
        return get_transient( $key . '_fallback' ) ?: null;
    }

    /**
     * Set fallback cache.
     *
     * @param string $key  Cache key.
     * @param mixed  $data Data to cache.
     * @param int    $ttl  TTL in seconds.
     * @return void
     */
    private function setFallback( string $key, $data, int $ttl ): void {
        set_transient( $key . '_fallback', $data, $ttl );
        set_transient( $key . '_fallback_time', time(), $ttl );
    }

    /**
     * Build a full cache key.
     *
     * @param string $key      Base key.
     * @param string $provider Provider ID.
     * @return string
     */
    private function buildKey( string $key, string $provider ): string {
        return self::PREFIX . $provider . '_' . $key;
    }

    /**
     * Get cache age in seconds.
     *
     * @param string $key Cache key.
     * @return int
     */
    private function getCacheAge( string $key ): int {
        $time = get_transient( $key . '_time' );
        return $time ? ( time() - (int) $time ) : 0;
    }

    /**
     * Get fallback cache age in seconds.
     *
     * @param string $key Cache key.
     * @return int
     */
    private function getFallbackAge( string $key ): int {
        $time = get_transient( $key . '_fallback_time' );
        return $time ? ( time() - (int) $time ) : 0;
    }

    /**
     * Check if response is an error.
     *
     * @param mixed $data Response data.
     * @return bool
     */
    private function isErrorResponse( $data ): bool {
        if ( $data === null || $data === false ) {
            return true;
        }

        if ( is_array( $data ) && isset( $data['error'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Clear all caches.
     *
     * @return int Number of transients deleted.
     */
    public function clearAll(): int {
        global $wpdb;

        $transient_pattern = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
        $timeout_pattern   = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';

        // Delete both transients and timeouts in a single query.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $transient_pattern,
                $timeout_pattern
            )
        );

        // Clear memory cache.
        self::$memoryCache = [];

        return (int) $deleted;
    }

    /**
     * Clear cache for a specific provider.
     *
     * @param string $provider Provider ID.
     * @return int Number of transients deleted.
     */
    public function clearProvider( string $provider ): int {
        global $wpdb;

        $pattern = $wpdb->esc_like( '_transient_' . self::PREFIX . $provider . '_' ) . '%';

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        return (int) $deleted;
    }
}
