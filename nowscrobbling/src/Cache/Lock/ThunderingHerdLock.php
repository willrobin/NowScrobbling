<?php

declare(strict_types=1);

namespace NowScrobbling\Cache\Lock;

/**
 * Thundering Herd Lock
 *
 * Prevents multiple concurrent API calls for the same data when cache expires.
 * Only one request acquires the lock to refresh the cache while others wait
 * or serve stale data.
 *
 * @package NowScrobbling\Cache\Lock
 */
final class ThunderingHerdLock
{
    /**
     * Lock key prefix
     */
    private const PREFIX = 'ns_lock_';

    /**
     * Maximum lock duration in seconds
     *
     * Prevents stuck locks from blocking indefinitely.
     */
    private const LOCK_TTL = 30;

    /**
     * Maximum wait time in seconds
     */
    private const MAX_WAIT = 5;

    /**
     * Wait interval in microseconds (100ms)
     */
    private const WAIT_INTERVAL = 100000;

    /**
     * Currently held locks by this instance
     *
     * @var array<string, int>
     */
    private array $heldLocks = [];

    /**
     * Attempt to acquire a lock
     *
     * @param string $key The key to lock
     *
     * @return bool True if lock acquired, false if already locked
     */
    public function acquire(string $key): bool
    {
        $lockKey = $this->getLockKey($key);

        // Try atomic set-if-not-exists with object cache
        if (wp_using_ext_object_cache()) {
            $acquired = wp_cache_add($lockKey, time(), 'nowscrobbling', self::LOCK_TTL);

            if ($acquired) {
                $this->heldLocks[$key] = time();
            }

            return $acquired;
        }

        // Fallback: transient-based lock
        $existing = get_transient($lockKey);

        if ($existing !== false) {
            // Check if lock is stale (safety mechanism)
            if (is_numeric($existing) && (time() - (int) $existing) > self::LOCK_TTL) {
                // Stale lock, force acquire
                delete_transient($lockKey);
            } else {
                return false;
            }
        }

        // Acquire the lock
        $result = set_transient($lockKey, time(), self::LOCK_TTL);

        if ($result) {
            $this->heldLocks[$key] = time();
        }

        return $result;
    }

    /**
     * Release a lock
     *
     * @param string $key The key to unlock
     */
    public function release(string $key): void
    {
        // Only release if we hold this lock
        if (!isset($this->heldLocks[$key])) {
            return;
        }

        $lockKey = $this->getLockKey($key);

        if (wp_using_ext_object_cache()) {
            wp_cache_delete($lockKey, 'nowscrobbling');
        } else {
            delete_transient($lockKey);
        }

        unset($this->heldLocks[$key]);
    }

    /**
     * Check if a key is locked
     *
     * @param string $key The key to check
     */
    public function isLocked(string $key): bool
    {
        $lockKey = $this->getLockKey($key);

        if (wp_using_ext_object_cache()) {
            return wp_cache_get($lockKey, 'nowscrobbling') !== false;
        }

        return get_transient($lockKey) !== false;
    }

    /**
     * Wait for a lock to be released
     *
     * @param string $key       The key to wait for
     * @param int    $maxWait   Maximum wait time in seconds
     *
     * @return bool True if lock was released, false if timeout
     */
    public function wait(string $key, int $maxWait = 0): bool
    {
        $maxWait = $maxWait > 0 ? $maxWait : self::MAX_WAIT;
        $startTime = microtime(true);

        while ($this->isLocked($key)) {
            if ((microtime(true) - $startTime) >= $maxWait) {
                return false;
            }

            usleep(self::WAIT_INTERVAL);
        }

        return true;
    }

    /**
     * Acquire lock or wait for it to be released
     *
     * @param string $key     The key to lock
     * @param int    $maxWait Maximum wait time
     *
     * @return bool True if lock acquired (either immediately or after wait)
     */
    public function acquireOrWait(string $key, int $maxWait = 0): bool
    {
        // Try immediate acquire
        if ($this->acquire($key)) {
            return true;
        }

        // Wait for existing lock to release
        if ($this->wait($key, $maxWait)) {
            // Try to acquire after wait
            return $this->acquire($key);
        }

        return false;
    }

    /**
     * Execute a callback with lock protection
     *
     * @template T
     *
     * @param string   $key      The key to lock
     * @param callable $callback The callback to execute
     * @param mixed    $default  Default value if lock cannot be acquired
     *
     * @return T|mixed The callback result or default value
     */
    public function withLock(string $key, callable $callback, mixed $default = null): mixed
    {
        if (!$this->acquire($key)) {
            return $default;
        }

        try {
            return $callback();
        } finally {
            $this->release($key);
        }
    }

    /**
     * Release all locks held by this instance
     *
     * Useful for cleanup in case of errors.
     */
    public function releaseAll(): void
    {
        foreach (array_keys($this->heldLocks) as $key) {
            $this->release($key);
        }
    }

    /**
     * Get the lock key for a given cache key
     */
    private function getLockKey(string $key): string
    {
        return self::PREFIX . md5($key);
    }

    /**
     * Get the lock TTL
     */
    public function getLockTtl(): int
    {
        return self::LOCK_TTL;
    }

    /**
     * Get currently held locks
     *
     * @return array<string, int> Keys to timestamps
     */
    public function getHeldLocks(): array
    {
        return $this->heldLocks;
    }

    /**
     * Destructor - release all locks on shutdown
     */
    public function __destruct()
    {
        $this->releaseAll();
    }
}
