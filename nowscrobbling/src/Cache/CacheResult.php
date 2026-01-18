<?php
/**
 * Cache Result
 *
 * Represents the result of a cache lookup.
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
 * Enum CacheSource
 *
 * Indicates where the cached data came from.
 */
enum CacheSource: string {
    case MEMORY   = 'memory';   // In-memory cache (same request).
    case PRIMARY  = 'primary';  // Primary transient cache.
    case FALLBACK = 'fallback'; // Fallback cache (stale data).
    case FRESH    = 'fresh';    // Freshly fetched.
    case MISS     = 'miss';     // Cache miss, no data available.
}

/**
 * Class CacheResult
 *
 * Value object representing the result of a cache operation.
 */
class CacheResult {

    /**
     * The cached data.
     *
     * @var mixed
     */
    public mixed $data;

    /**
     * Where the data came from.
     *
     * @var CacheSource
     */
    public CacheSource $source;

    /**
     * Age of the cached data in seconds.
     *
     * @var int
     */
    public int $age;

    /**
     * Whether the data is stale (from fallback).
     *
     * @var bool
     */
    public bool $isStale;

    /**
     * Constructor.
     *
     * @param mixed       $data    Cached data.
     * @param CacheSource $source  Cache source.
     * @param int         $age     Age in seconds.
     * @param bool        $isStale Whether data is stale.
     */
    public function __construct(
        mixed $data,
        CacheSource $source,
        int $age = 0,
        bool $isStale = false
    ) {
        $this->data    = $data;
        $this->source  = $source;
        $this->age     = $age;
        $this->isStale = $isStale;
    }

    /**
     * Check if cache lookup was successful.
     *
     * @return bool
     */
    public function hasData(): bool {
        return $this->data !== null && $this->source !== CacheSource::MISS;
    }

    /**
     * Check if data was served from cache (not fresh).
     *
     * @return bool
     */
    public function wasFromCache(): bool {
        return in_array(
            $this->source,
            [ CacheSource::MEMORY, CacheSource::PRIMARY, CacheSource::FALLBACK ],
            true
        );
    }

    /**
     * Check if this was a cache miss.
     *
     * @return bool
     */
    public function isMiss(): bool {
        return $this->source === CacheSource::MISS;
    }

    /**
     * Get human-readable age string.
     *
     * @return string
     */
    public function getAgeString(): string {
        if ( $this->age === 0 ) {
            return __( 'just now', 'nowscrobbling' );
        }

        if ( $this->age < 60 ) {
            return sprintf(
                /* translators: %d: seconds */
                _n( '%d second ago', '%d seconds ago', $this->age, 'nowscrobbling' ),
                $this->age
            );
        }

        $minutes = (int) floor( $this->age / 60 );
        if ( $minutes < 60 ) {
            return sprintf(
                /* translators: %d: minutes */
                _n( '%d minute ago', '%d minutes ago', $minutes, 'nowscrobbling' ),
                $minutes
            );
        }

        $hours = (int) floor( $minutes / 60 );
        if ( $hours < 24 ) {
            return sprintf(
                /* translators: %d: hours */
                _n( '%d hour ago', '%d hours ago', $hours, 'nowscrobbling' ),
                $hours
            );
        }

        $days = (int) floor( $hours / 24 );
        return sprintf(
            /* translators: %d: days */
            _n( '%d day ago', '%d days ago', $days, 'nowscrobbling' ),
            $days
        );
    }

    /**
     * Get the source as a string.
     *
     * @return string
     */
    public function getSourceLabel(): string {
        return match ( $this->source ) {
            CacheSource::MEMORY   => __( 'Memory', 'nowscrobbling' ),
            CacheSource::PRIMARY  => __( 'Cache', 'nowscrobbling' ),
            CacheSource::FALLBACK => __( 'Fallback', 'nowscrobbling' ),
            CacheSource::FRESH    => __( 'Fresh', 'nowscrobbling' ),
            CacheSource::MISS     => __( 'Miss', 'nowscrobbling' ),
        };
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'has_data'  => $this->hasData(),
            'source'    => $this->source->value,
            'age'       => $this->age,
            'age_human' => $this->getAgeString(),
            'is_stale'  => $this->isStale,
        ];
    }
}
