<?php

declare(strict_types=1);

namespace NowScrobbling\Security;

/**
 * Nonce Manager
 *
 * Provides granular nonce management for different endpoints.
 * Each action type gets its own nonce for better security.
 *
 * @package NowScrobbling\Security
 */
final class NonceManager
{
    /**
     * Nonce prefix
     */
    private const PREFIX = 'ns_';

    /**
     * Nonce lifetime in seconds (24 hours)
     */
    private const LIFETIME = DAY_IN_SECONDS;

    /**
     * Create a nonce for an action
     *
     * @param string $action Action name
     */
    public function create(string $action): string
    {
        return wp_create_nonce(self::PREFIX . $action);
    }

    /**
     * Verify a nonce
     *
     * @param string $nonce  Nonce to verify
     * @param string $action Action name
     *
     * @return bool True if valid, false otherwise
     */
    public function verify(string $nonce, string $action): bool
    {
        $result = wp_verify_nonce($nonce, self::PREFIX . $action);

        return $result !== false && $result !== 0;
    }

    /**
     * Get all nonces for JavaScript
     *
     * @return array<string, string>
     */
    public function getForJs(): array
    {
        return [
            'render' => $this->create('render'),
            'refresh' => $this->create('refresh'),
            'cache_clear' => $this->create('cache_clear'),
            'api_test' => $this->create('api_test'),
            'settings' => $this->create('settings'),
        ];
    }

    /**
     * Get nonce field for forms
     *
     * @param string $action Action name
     */
    public function field(string $action): string
    {
        return wp_nonce_field(
            self::PREFIX . $action,
            self::PREFIX . $action . '_nonce',
            true,
            false
        );
    }

    /**
     * Verify nonce from request
     *
     * @param string $action Action name
     * @param string $method HTTP method (GET, POST)
     *
     * @return bool True if valid
     */
    public function verifyRequest(string $action, string $method = 'POST'): bool
    {
        $source = $method === 'GET' ? $_GET : $_POST;
        $key = self::PREFIX . $action . '_nonce';

        if (!isset($source[$key])) {
            return false;
        }

        return $this->verify(
            sanitize_text_field(wp_unslash($source[$key])),
            $action
        );
    }

    /**
     * Get the nonce action prefix
     */
    public function getPrefix(): string
    {
        return self::PREFIX;
    }
}
