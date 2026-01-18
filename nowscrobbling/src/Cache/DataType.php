<?php
/**
 * Data Type Enum
 *
 * Defines data types with their caching characteristics.
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
 * Enum DataType
 *
 * Each data type has different caching requirements based on volatility.
 * This allows smart caching - "now playing" needs frequent updates,
 * while "top artists of all time" can be cached for days.
 */
enum DataType: string {

    /**
     * Real-time data: Currently playing/watching.
     * Updates very frequently.
     */
    case NOW_PLAYING = 'now_playing';

    /**
     * Recent activity history.
     * Updates frequently but not real-time.
     */
    case HISTORY = 'history';

    /**
     * Top items for last 7 days.
     * Changes daily.
     */
    case TOP_WEEKLY = 'top_weekly';

    /**
     * Top items for last month.
     * Changes less frequently.
     */
    case TOP_MONTHLY = 'top_monthly';

    /**
     * Top items for last 3 months.
     */
    case TOP_QUARTERLY = 'top_quarterly';

    /**
     * Top items for last 6 months.
     */
    case TOP_HALF_YEAR = 'top_half_year';

    /**
     * Top items for last year.
     * Very stable.
     */
    case TOP_YEARLY = 'top_yearly';

    /**
     * Top items of all time.
     * Most stable.
     */
    case TOP_ALLTIME = 'top_alltime';

    /**
     * User ratings.
     * Rarely changes.
     */
    case RATINGS = 'ratings';

    /**
     * Favorites/loved tracks/watchlist.
     * Changes occasionally.
     */
    case FAVORITES = 'favorites';

    /**
     * Artwork URLs (covers, posters).
     * Almost never changes.
     */
    case ARTWORK = 'artwork';

    /**
     * Static metadata.
     * Never changes.
     */
    case METADATA = 'metadata';

    /**
     * Get the primary cache TTL in seconds.
     *
     * @return int TTL in seconds.
     */
    public function getCacheTTL(): int {
        return match ( $this ) {
            self::NOW_PLAYING => 30,                    // 30 seconds
            self::HISTORY => 5 * MINUTE_IN_SECONDS,     // 5 minutes
            self::TOP_WEEKLY => HOUR_IN_SECONDS,        // 1 hour
            self::TOP_MONTHLY => 2 * HOUR_IN_SECONDS,   // 2 hours
            self::TOP_QUARTERLY => 6 * HOUR_IN_SECONDS, // 6 hours
            self::TOP_HALF_YEAR => 12 * HOUR_IN_SECONDS, // 12 hours
            self::TOP_YEARLY,
            self::TOP_ALLTIME,
            self::RATINGS,
            self::FAVORITES => DAY_IN_SECONDS,          // 24 hours
            self::ARTWORK => WEEK_IN_SECONDS,           // 7 days
            self::METADATA => MONTH_IN_SECONDS,         // 30 days
        };
    }

    /**
     * Get the fallback cache TTL in seconds.
     *
     * Fallback cache lives longer and is used when fresh data can't be fetched.
     *
     * @return int TTL in seconds.
     */
    public function getFallbackTTL(): int {
        return match ( $this ) {
            self::NOW_PLAYING => 5 * MINUTE_IN_SECONDS, // 5 minutes
            self::HISTORY => HOUR_IN_SECONDS,           // 1 hour
            self::TOP_WEEKLY => DAY_IN_SECONDS,         // 24 hours
            self::TOP_MONTHLY,
            self::TOP_QUARTERLY => 3 * DAY_IN_SECONDS,  // 3 days
            self::TOP_HALF_YEAR,
            self::TOP_YEARLY,
            self::TOP_ALLTIME => WEEK_IN_SECONDS,       // 7 days
            self::RATINGS,
            self::FAVORITES => WEEK_IN_SECONDS,         // 7 days
            self::ARTWORK => MONTH_IN_SECONDS,          // 30 days
            self::METADATA => 3 * MONTH_IN_SECONDS,     // 90 days
        };
    }

    /**
     * Get a human-readable label for this data type.
     *
     * @return string
     */
    public function getLabel(): string {
        return match ( $this ) {
            self::NOW_PLAYING => __( 'Now Playing', 'nowscrobbling' ),
            self::HISTORY => __( 'History', 'nowscrobbling' ),
            self::TOP_WEEKLY => __( 'Top (7 days)', 'nowscrobbling' ),
            self::TOP_MONTHLY => __( 'Top (1 month)', 'nowscrobbling' ),
            self::TOP_QUARTERLY => __( 'Top (3 months)', 'nowscrobbling' ),
            self::TOP_HALF_YEAR => __( 'Top (6 months)', 'nowscrobbling' ),
            self::TOP_YEARLY => __( 'Top (12 months)', 'nowscrobbling' ),
            self::TOP_ALLTIME => __( 'Top (All Time)', 'nowscrobbling' ),
            self::RATINGS => __( 'Ratings', 'nowscrobbling' ),
            self::FAVORITES => __( 'Favorites', 'nowscrobbling' ),
            self::ARTWORK => __( 'Artwork', 'nowscrobbling' ),
            self::METADATA => __( 'Metadata', 'nowscrobbling' ),
        };
    }

    /**
     * Check if this data type requires polling for updates.
     *
     * @return bool
     */
    public function requiresPolling(): bool {
        return $this === self::NOW_PLAYING;
    }

    /**
     * Check if this data type should use ETag caching.
     *
     * @return bool
     */
    public function supportsETag(): bool {
        // ETag is useful for data that changes occasionally
        // but we want to minimize bandwidth.
        return match ( $this ) {
            self::NOW_PLAYING => false, // Too volatile.
            self::ARTWORK,
            self::METADATA => false,    // Too stable.
            default => true,
        };
    }

    /**
     * Check if fallback data should never be overwritten with errors.
     *
     * @return bool
     */
    public function protectFallback(): bool {
        // These data types should never lose their fallback data.
        return match ( $this ) {
            self::NOW_PLAYING => false, // OK to lose.
            default => true,
        };
    }

    /**
     * Get the data type for a Last.fm period string.
     *
     * @param string $period Last.fm period (7day, 1month, 3month, etc.).
     * @return self
     */
    public static function fromLastFMPeriod( string $period ): self {
        return match ( $period ) {
            '7day' => self::TOP_WEEKLY,
            '1month' => self::TOP_MONTHLY,
            '3month' => self::TOP_QUARTERLY,
            '6month' => self::TOP_HALF_YEAR,
            '12month' => self::TOP_YEARLY,
            'overall' => self::TOP_ALLTIME,
            default => self::TOP_WEEKLY,
        };
    }

    /**
     * Get all top-items data types.
     *
     * @return array<self>
     */
    public static function getTopItemTypes(): array {
        return [
            self::TOP_WEEKLY,
            self::TOP_MONTHLY,
            self::TOP_QUARTERLY,
            self::TOP_HALF_YEAR,
            self::TOP_YEARLY,
            self::TOP_ALLTIME,
        ];
    }
}
