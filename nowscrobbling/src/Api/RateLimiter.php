<?php

declare(strict_types=1);

namespace NowScrobbling\Api;

/**
 * Rate Limiter
 *
 * Implements exponential backoff for API rate limiting.
 * Tracks errors and applies cooldown periods.
 *
 * @package NowScrobbling\Api
 */
final class RateLimiter
{
    /**
     * Transient prefix
     */
    private const PREFIX = 'ns_ratelimit_';

    /**
     * Initial cooldown duration in seconds
     */
    private const INITIAL_COOLDOWN = 60;

    /**
     * Maximum cooldown duration in seconds (30 minutes)
     */
    private const MAX_COOLDOWN = 1800;

    /**
     * Error count threshold before cooldown kicks in
     */
    private const ERROR_THRESHOLD = 3;

    /**
     * Service identifier
     */
    private readonly string $service;

    /**
     * Constructor
     *
     * @param string $service Service identifier
     */
    public function __construct(string $service)
    {
        $this->service = $service;
    }

    /**
     * Check if requests should be throttled
     */
    public function shouldThrottle(): bool
    {
        $cooldownUntil = $this->getCooldownUntil();

        if ($cooldownUntil === null) {
            return false;
        }

        return time() < $cooldownUntil;
    }

    /**
     * Get remaining cooldown time in seconds
     */
    public function getRemainingCooldown(): int
    {
        $cooldownUntil = $this->getCooldownUntil();

        if ($cooldownUntil === null) {
            return 0;
        }

        $remaining = $cooldownUntil - time();
        return max(0, $remaining);
    }

    /**
     * Record a successful request
     *
     * Resets the error counter.
     */
    public function recordSuccess(): void
    {
        delete_transient($this->getErrorCountKey());
        delete_transient($this->getCooldownKey());
    }

    /**
     * Record a failed request
     *
     * @param int $httpCode HTTP status code (optional)
     */
    public function recordError(int $httpCode = 0): void
    {
        // Immediate cooldown for rate limit responses
        if ($httpCode === 429) {
            $this->setCooldown(self::MAX_COOLDOWN);
            return;
        }

        // Track errors for exponential backoff
        $errorCount = $this->getErrorCount() + 1;
        set_transient($this->getErrorCountKey(), $errorCount, 3600);

        if ($errorCount >= self::ERROR_THRESHOLD) {
            $this->applyExponentialBackoff($errorCount);
        }
    }

    /**
     * Apply exponential backoff based on error count
     *
     * @param int $errorCount Number of consecutive errors
     */
    private function applyExponentialBackoff(int $errorCount): void
    {
        // Calculate cooldown: initial * 2^(errors - threshold)
        $multiplier = $errorCount - self::ERROR_THRESHOLD;
        $cooldown = self::INITIAL_COOLDOWN * pow(2, $multiplier);

        // Cap at maximum
        $cooldown = min((int) $cooldown, self::MAX_COOLDOWN);

        $this->setCooldown($cooldown);
    }

    /**
     * Set cooldown period
     *
     * @param int $seconds Cooldown duration in seconds
     */
    private function setCooldown(int $seconds): void
    {
        $cooldownUntil = time() + $seconds;
        set_transient($this->getCooldownKey(), $cooldownUntil, $seconds + 60);

        /**
         * Fires when rate limit cooldown is applied
         *
         * @param string $service  The service name
         * @param int    $seconds  Cooldown duration
         * @param int    $until    Unix timestamp when cooldown ends
         */
        do_action('nowscrobbling_rate_limited', $this->service, $seconds, $cooldownUntil);
    }

    /**
     * Get current cooldown end timestamp
     */
    private function getCooldownUntil(): ?int
    {
        $value = get_transient($this->getCooldownKey());

        if ($value === false) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Get current error count
     */
    private function getErrorCount(): int
    {
        $value = get_transient($this->getErrorCountKey());
        return $value === false ? 0 : (int) $value;
    }

    /**
     * Get the cooldown transient key
     */
    private function getCooldownKey(): string
    {
        return self::PREFIX . $this->service . '_cooldown';
    }

    /**
     * Get the error count transient key
     */
    private function getErrorCountKey(): string
    {
        return self::PREFIX . $this->service . '_errors';
    }

    /**
     * Clear all rate limiting state
     */
    public function clear(): void
    {
        delete_transient($this->getErrorCountKey());
        delete_transient($this->getCooldownKey());
    }

    /**
     * Get rate limiter status
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return [
            'service'     => $this->service,
            'throttled'   => $this->shouldThrottle(),
            'error_count' => $this->getErrorCount(),
            'cooldown_remaining' => $this->getRemainingCooldown(),
        ];
    }
}
