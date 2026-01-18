<?php
/**
 * PSR-4 Autoloader for NowScrobbling
 *
 * This is a fallback autoloader that works without Composer.
 * If Composer's vendor/autoload.php exists, it will be used instead.
 *
 * @package NowScrobbling
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    // Only handle NowScrobbling namespace
    $prefix = 'NowScrobbling\\';
    $prefixLength = strlen($prefix);

    if (strncmp($prefix, $class, $prefixLength) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $prefixLength);

    // Build the file path
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
