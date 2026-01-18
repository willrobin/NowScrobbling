<?php

declare(strict_types=1);

namespace NowScrobbling\Api;

use NowScrobbling\Api\Response\ApiResponse;

/**
 * Interface for API clients
 *
 * All API clients (Last.fm, Trakt, future services) implement this interface.
 *
 * @package NowScrobbling\Api
 */
interface ApiClientInterface
{
    /**
     * Get the service identifier
     *
     * @return string Service name (e.g., 'lastfm', 'trakt')
     */
    public function getService(): string;

    /**
     * Check if the client is configured with valid credentials
     */
    public function isConfigured(): bool;

    /**
     * Test the API connection
     *
     * @return ApiResponse Response indicating success or failure
     */
    public function testConnection(): ApiResponse;

    /**
     * Get the base URL for API requests
     */
    public function getBaseUrl(): string;

    /**
     * Get the default cache TTL for this service
     *
     * @return int TTL in seconds
     */
    public function getDefaultTtl(): int;
}
