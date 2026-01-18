<?php

declare(strict_types=1);

namespace NowScrobbling\Support;

/**
 * Debug Logger
 *
 * Provides debug logging functionality for the plugin.
 * Logs are written to a file in the plugin directory and can be
 * viewed in the Diagnostics tab.
 *
 * @package NowScrobbling\Support
 */
final class Logger
{
    /**
     * Log file name
     */
    private const LOG_FILE = 'debug.log';

    /**
     * Maximum log file size in bytes (1MB)
     */
    private const MAX_SIZE = 1048576;

    /**
     * Maximum number of log entries to keep in memory for display
     */
    private const MAX_DISPLAY_ENTRIES = 100;

    /**
     * In-memory log buffer for current request
     *
     * @var array<array{time: string, level: string, message: string, context: array}>
     */
    private static array $buffer = [];

    /**
     * Whether logging is enabled
     */
    private static ?bool $enabled = null;

    /**
     * Log a debug message
     *
     * @param string               $message The message to log
     * @param string               $context Optional context (e.g., 'cache', 'api', 'shortcode')
     * @param array<string, mixed> $data    Optional additional data
     */
    public static function log(string $message, string $context = 'general', array $data = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::write('DEBUG', $message, $context, $data);
    }

    /**
     * Log an info message
     */
    public static function info(string $message, string $context = 'general', array $data = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::write('INFO', $message, $context, $data);
    }

    /**
     * Log a warning message
     */
    public static function warning(string $message, string $context = 'general', array $data = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::write('WARN', $message, $context, $data);
    }

    /**
     * Log an error message (always logged, even if debug is disabled)
     */
    public static function error(string $message, string $context = 'general', array $data = []): void
    {
        self::write('ERROR', $message, $context, $data);
    }

    /**
     * Write a log entry
     *
     * @param string               $level   Log level
     * @param string               $message Log message
     * @param string               $context Context identifier
     * @param array<string, mixed> $data    Additional data
     */
    private static function write(string $level, string $message, string $context, array $data): void
    {
        $timestamp = gmdate('Y-m-d H:i:s');

        $entry = [
            'time'    => $timestamp,
            'level'   => $level,
            'context' => $context,
            'message' => $message,
            'data'    => $data,
        ];

        // Add to in-memory buffer
        self::$buffer[] = $entry;

        // Format for file
        $dataStr = !empty($data) ? ' ' . wp_json_encode($data) : '';
        $line = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            $timestamp,
            $level,
            strtoupper($context),
            $message,
            $dataStr
        );

        // Write to file
        $logFile = self::getLogFilePath();

        // Rotate log if too large
        if (file_exists($logFile) && filesize($logFile) > self::MAX_SIZE) {
            self::rotateLog();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if debug logging is enabled
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = (bool) get_option('ns_debug_enabled', false);
        }

        return self::$enabled;
    }

    /**
     * Enable logging (for current request)
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Disable logging (for current request)
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Reset enabled state (forces re-check of option)
     */
    public static function reset(): void
    {
        self::$enabled = null;
        self::$buffer = [];
    }

    /**
     * Get the log file path
     */
    public static function getLogFilePath(): string
    {
        return NOWSCROBBLING_PATH . self::LOG_FILE;
    }

    /**
     * Get recent log entries from the file
     *
     * @param int $lines Number of lines to retrieve
     *
     * @return array<string>
     */
    public static function getRecentEntries(int $lines = 50): array
    {
        $logFile = self::getLogFilePath();

        if (!file_exists($logFile)) {
            return [];
        }

        // Read last N lines efficiently
        $file = new \SplFileObject($logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $entries = [];

        $file->seek($startLine);
        while (!$file->eof()) {
            $line = $file->fgets();
            if (trim($line) !== '') {
                $entries[] = $line;
            }
        }

        return $entries;
    }

    /**
     * Get the current request's log buffer
     *
     * @return array<array{time: string, level: string, message: string, context: array}>
     */
    public static function getBuffer(): array
    {
        return self::$buffer;
    }

    /**
     * Clear the log file
     */
    public static function clear(): bool
    {
        $logFile = self::getLogFilePath();

        if (file_exists($logFile)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            return unlink($logFile);
        }

        return true;
    }

    /**
     * Rotate the log file
     */
    private static function rotateLog(): void
    {
        $logFile = self::getLogFilePath();
        $backupFile = $logFile . '.old';

        // Remove old backup if exists
        if (file_exists($backupFile)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink($backupFile);
        }

        // Rename current log to backup
        if (file_exists($logFile)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
            rename($logFile, $backupFile);
        }
    }

    /**
     * Get log file size in human-readable format
     */
    public static function getLogSize(): string
    {
        $logFile = self::getLogFilePath();

        if (!file_exists($logFile)) {
            return '0 B';
        }

        $bytes = filesize($logFile);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Register the logger hooks
     *
     * Call this during plugin initialization to enable logging.
     */
    public static function register(): void
    {
        // Listen for cache debug events
        add_action('nowscrobbling_cache_debug', function (string $message, string $service, array $stats): void {
            self::log($message, 'cache', ['service' => $service, 'stats' => $stats]);
        }, 10, 3);

        // Log plugin boot
        add_action('nowscrobbling_booted', function (): void {
            self::info('Plugin booted', 'core', ['version' => \NowScrobbling\Plugin::VERSION]);
        });
    }
}
