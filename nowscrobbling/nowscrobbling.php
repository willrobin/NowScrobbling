<?php

/**
 * Plugin Name:       NowScrobbling
 * Plugin URI:        https://github.com/willrobin/NowScrobbling
 * Description:       Display Last.fm and Trakt.tv activity via shortcodes. Privacy-first: all API calls are server-side.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Robin Will
 * Author URI:        https://robinwill.de/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nowscrobbling
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/willrobin/NowScrobbling
 * GitHub Branch:     main
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('NOWSCROBBLING_VERSION', '2.0.0');
define('NOWSCROBBLING_FILE', __FILE__);
define('NOWSCROBBLING_PATH', plugin_dir_path(__FILE__));
define('NOWSCROBBLING_URL', plugin_dir_url(__FILE__));
define('NOWSCROBBLING_BASENAME', plugin_basename(__FILE__));

// Load autoloader (Composer or fallback)
$composerAutoloader = NOWSCROBBLING_PATH . 'vendor/autoload.php';
$fallbackAutoloader = NOWSCROBBLING_PATH . 'autoload.php';

if (file_exists($composerAutoloader)) {
    require_once $composerAutoloader;
} elseif (file_exists($fallbackAutoloader)) {
    require_once $fallbackAutoloader;
} else {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'NowScrobbling: Autoloader not found. Please reinstall the plugin.',
                'nowscrobbling'
            )
        );
    });
    return;
}

// Boot plugin after WordPress is fully loaded
add_action(
    'plugins_loaded',
    static fn() => NowScrobbling\Plugin::getInstance()->boot(),
    10
);

// Activation hook
register_activation_hook(
    __FILE__,
    [NowScrobbling\Core\Activator::class, 'activate']
);

// Deactivation hook
register_deactivation_hook(
    __FILE__,
    [NowScrobbling\Core\Deactivator::class, 'deactivate']
);
