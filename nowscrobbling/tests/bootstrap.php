<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for NowScrobbling.
 *
 * @package NowScrobbling\Tests
 */

declare(strict_types=1);

// Define WordPress constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('NOWSCROBBLING_PATH')) {
    define('NOWSCROBBLING_PATH', dirname(__DIR__) . '/');
}

if (!defined('NOWSCROBBLING_URL')) {
    define('NOWSCROBBLING_URL', 'https://example.com/wp-content/plugins/nowscrobbling/');
}

if (!defined('NOWSCROBBLING_FILE')) {
    define('NOWSCROBBLING_FILE', NOWSCROBBLING_PATH . 'nowscrobbling.php');
}

if (!defined('NOWSCROBBLING_BASENAME')) {
    define('NOWSCROBBLING_BASENAME', 'nowscrobbling/nowscrobbling.php');
}

if (!defined('NOWSCROBBLING_VERSION')) {
    define('NOWSCROBBLING_VERSION', '2.0.0');
}

// WordPress time constants
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

// Load Composer autoloader
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Load WordPress stubs for function mocking
require_once __DIR__ . '/stubs/wordpress-functions.php';

// Load test utilities
require_once __DIR__ . '/TestCase.php';
