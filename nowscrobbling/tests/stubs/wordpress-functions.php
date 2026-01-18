<?php
/**
 * WordPress Function Stubs for Unit Testing
 *
 * Provides mock implementations of WordPress functions
 * so tests can run without a full WordPress installation.
 *
 * @package NowScrobbling\Tests\Stubs
 */

declare(strict_types=1);

// Storage for mock data
global $wp_mock_options, $wp_mock_transients;
$wp_mock_options = [];
$wp_mock_transients = [];

// =============================================================================
// Options API
// =============================================================================

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        global $wp_mock_options;
        return $wp_mock_options[$option] ?? $default;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $option, mixed $value = ''): bool
    {
        global $wp_mock_options;
        if (!isset($wp_mock_options[$option])) {
            $wp_mock_options[$option] = $value;
            return true;
        }
        return false;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value): bool
    {
        global $wp_mock_options;
        $wp_mock_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        global $wp_mock_options;
        if (isset($wp_mock_options[$option])) {
            unset($wp_mock_options[$option]);
            return true;
        }
        return false;
    }
}

// =============================================================================
// Transients API
// =============================================================================

if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        global $wp_mock_transients;
        $data = $wp_mock_transients[$transient] ?? null;

        if ($data === null) {
            return false;
        }

        if ($data['expiration'] > 0 && $data['expiration'] < time()) {
            unset($wp_mock_transients[$transient]);
            return false;
        }

        return $data['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        global $wp_mock_transients;
        $wp_mock_transients[$transient] = [
            'value' => $value,
            'expiration' => $expiration > 0 ? time() + $expiration : 0,
        ];
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        global $wp_mock_transients;
        unset($wp_mock_transients[$transient]);
        return true;
    }
}

// =============================================================================
// Sanitization Functions
// =============================================================================

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        if (is_string($value)) {
            return stripslashes($value);
        }
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }
        return $value;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string
    {
        return strip_tags($data, '<a><br><em><strong><p><ul><ol><li>');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

// =============================================================================
// i18n Functions
// =============================================================================

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html($text);
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return esc_attr($text);
    }
}

// =============================================================================
// Hooks API (Stubs)
// =============================================================================

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): bool
    {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $args = 1): bool
    {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

// =============================================================================
// Nonce Functions
// =============================================================================

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = '-1'): string
    {
        return md5($action . 'test_nonce_salt');
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = '-1'): int|false
    {
        return $nonce === wp_create_nonce($action) ? 1 : false;
    }
}

// =============================================================================
// HTTP Functions (Stubs)
// =============================================================================

if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): array
    {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => '{}',
            'headers' => [],
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return $response['response']['code'] ?? 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header(array $response, string $header): string
    {
        return $response['headers'][$header] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

// =============================================================================
// WP_Error Class
// =============================================================================

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private array $errors = [];
        private array $error_data = [];

        public function __construct(string $code = '', string $message = '', mixed $data = '')
        {
            if ($code) {
                $this->errors[$code][] = $message;
                if ($data) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_message(string $code = ''): string
        {
            if (!$code) {
                $code = array_key_first($this->errors) ?? '';
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_code(): string
        {
            return array_key_first($this->errors) ?? '';
        }
    }
}

// =============================================================================
// Misc Functions
// =============================================================================

if (!function_exists('wp_using_ext_object_cache')) {
    function wp_using_ext_object_cache(): bool
    {
        return false;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook): int|false
    {
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool
    {
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook): int
    {
        return 0;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return false;
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.com/wp-json/' . ltrim($path, '/');
    }
}

/**
 * Reset all mock data between tests
 */
function wp_mock_reset(): void
{
    global $wp_mock_options, $wp_mock_transients;
    $wp_mock_options = [];
    $wp_mock_transients = [];
}
