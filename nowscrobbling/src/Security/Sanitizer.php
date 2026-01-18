<?php

declare(strict_types=1);

namespace NowScrobbling\Security;

/**
 * Centralized Sanitizer
 *
 * Provides consistent input sanitization with CORRECT order:
 * 1. wp_unslash() FIRST (removes WordPress magic quotes)
 * 2. Then sanitize_*() functions
 *
 * @package NowScrobbling\Security
 */
final class Sanitizer
{
    /**
     * Sanitize input from superglobal
     *
     * @param string $key    Key to retrieve
     * @param string $type   Type of sanitization
     * @param string $method HTTP method (GET, POST, REQUEST)
     *
     * @return mixed Sanitized value or null if not set
     */
    public static function input(
        string $key,
        string $type = 'text',
        string $method = 'POST'
    ): mixed {
        $source = match ($method) {
            'GET' => $_GET,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST,
            default => $_POST,
        };

        if (!isset($source[$key])) {
            return null;
        }

        // Step 1: UNSLASH FIRST
        $value = wp_unslash($source[$key]);

        // Step 2: SANITIZE based on type
        return self::sanitize($value, $type);
    }

    /**
     * Sanitize a value by type
     *
     * @param mixed  $value Raw value
     * @param string $type  Sanitization type
     *
     * @return mixed Sanitized value
     */
    public static function sanitize(mixed $value, string $type = 'text'): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'text' => is_string($value) ? sanitize_text_field($value) : '',
            'textarea' => is_string($value) ? sanitize_textarea_field($value) : '',
            'int' => absint($value),
            'float' => (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'key' => is_string($value) ? sanitize_key($value) : '',
            'email' => is_string($value) ? sanitize_email($value) : '',
            'url' => is_string($value) ? esc_url_raw($value) : '',
            'html' => is_string($value) ? wp_kses_post($value) : '',
            'filename' => is_string($value) ? sanitize_file_name($value) : '',
            'title' => is_string($value) ? sanitize_title($value) : '',
            'json' => self::sanitizeJson($value),
            'array' => self::sanitizeArray($value),
            default => is_string($value) ? sanitize_text_field($value) : '',
        };
    }

    /**
     * Sanitize JSON input
     *
     * @param mixed $value JSON string or array
     *
     * @return array<string, mixed>
     */
    public static function sanitizeJson(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (!is_array($decoded)) {
                return [];
            }

            $value = $decoded;
        }

        if (!is_array($value)) {
            return [];
        }

        return self::sanitizeArray($value);
    }

    /**
     * Recursively sanitize an array
     *
     * @param mixed $value Array to sanitize
     *
     * @return array<string|int, mixed>
     */
    public static function sanitizeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            $sanitizedKey = is_string($key) ? sanitize_key($key) : $key;

            if (is_array($item)) {
                $result[$sanitizedKey] = self::sanitizeArray($item);
            } elseif (is_string($item)) {
                $result[$sanitizedKey] = sanitize_text_field($item);
            } elseif (is_int($item) || is_float($item)) {
                $result[$sanitizedKey] = $item;
            } elseif (is_bool($item)) {
                $result[$sanitizedKey] = $item;
            }
            // Skip other types (objects, resources, etc.)
        }

        return $result;
    }

    /**
     * Validate and sanitize an option value
     *
     * @param string $key     Option key
     * @param mixed  $value   Value to sanitize
     * @param string $type    Expected type
     * @param mixed  $default Default value if invalid
     *
     * @return mixed Sanitized value
     */
    public static function option(
        string $key,
        mixed $value,
        string $type = 'text',
        mixed $default = null
    ): mixed {
        $sanitized = self::sanitize($value, $type);

        // Additional validation for specific option patterns
        if ($sanitized === '' && $default !== null) {
            return $default;
        }

        return $sanitized;
    }

    /**
     * Sanitize shortcode attributes
     *
     * @param array<string, mixed> $atts      Raw attributes
     * @param array<string, mixed> $defaults  Default values with types
     *
     * @return array<string, mixed>
     */
    public static function shortcodeAtts(array $atts, array $defaults): array
    {
        $result = [];

        foreach ($defaults as $key => $default) {
            $type = match (gettype($default)) {
                'integer' => 'int',
                'double' => 'float',
                'boolean' => 'bool',
                default => 'text',
            };

            $value = $atts[$key] ?? $default;
            $result[$key] = self::sanitize($value, $type);
        }

        return $result;
    }
}
