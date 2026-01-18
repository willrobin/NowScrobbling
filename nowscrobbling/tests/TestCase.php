<?php
/**
 * Base Test Case for NowScrobbling
 *
 * Provides common setup and utilities for all tests.
 *
 * @package NowScrobbling\Tests
 */

declare(strict_types=1);

namespace NowScrobbling\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case class
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset WordPress mocks before each test
        wp_mock_reset();
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void
    {
        // Reset WordPress mocks after each test
        wp_mock_reset();

        // Reset plugin singleton
        \NowScrobbling\Plugin::reset();
        \NowScrobbling\Container::reset();

        parent::tearDown();
    }

    /**
     * Set a WordPress option for testing
     *
     * @param string $key   Option key
     * @param mixed  $value Option value
     */
    protected function setOption(string $key, mixed $value): void
    {
        global $wp_mock_options;
        $wp_mock_options[$key] = $value;
    }

    /**
     * Set a WordPress transient for testing
     *
     * @param string $key        Transient key
     * @param mixed  $value      Transient value
     * @param int    $expiration Expiration in seconds (0 = no expiration)
     */
    protected function setTransient(string $key, mixed $value, int $expiration = 0): void
    {
        global $wp_mock_transients;
        $wp_mock_transients[$key] = [
            'value' => $value,
            'expiration' => $expiration > 0 ? time() + $expiration : 0,
        ];
    }

    /**
     * Assert that an option exists
     *
     * @param string $key Option key
     */
    protected function assertOptionExists(string $key): void
    {
        global $wp_mock_options;
        $this->assertArrayHasKey($key, $wp_mock_options, "Option '{$key}' should exist");
    }

    /**
     * Assert that an option does not exist
     *
     * @param string $key Option key
     */
    protected function assertOptionNotExists(string $key): void
    {
        global $wp_mock_options;
        $this->assertArrayNotHasKey($key, $wp_mock_options, "Option '{$key}' should not exist");
    }

    /**
     * Assert that an option has a specific value
     *
     * @param string $key      Option key
     * @param mixed  $expected Expected value
     */
    protected function assertOptionEquals(string $key, mixed $expected): void
    {
        $this->assertEquals($expected, get_option($key), "Option '{$key}' should equal expected value");
    }
}
