<?php
/**
 * Plugin Name:         NowScrobbling
 * Plugin URI:          https://github.com/willrobin/NowScrobbling
 * Description:         Display your Last.fm scrobbles and Trakt.tv activity beautifully on your WordPress site. Multiple layouts, artwork support, and smart caching.
 * Version:             1.4.0
 * Requires at least:   5.0
 * Requires PHP:        8.0
 * Author:              Robin Will
 * Author URI:          https://robinwill.de/
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         nowscrobbling
 * Domain Path:         /languages
 * GitHub Plugin URI:   https://github.com/willrobin/NowScrobbling
 * GitHub Branch:       main
 *
 * @package NowScrobbling
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------------
// PHP Version Check
// -----------------------------------------------------------------------------
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    add_action( 'admin_notices', function() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                esc_html__( 'NowScrobbling requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade your PHP version.', 'nowscrobbling' ),
                '8.0',
                PHP_VERSION
            )
        );
    });
    return;
}

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
define( 'NOWSCROBBLING_VERSION', '1.4.0' );
define( 'NOWSCROBBLING_FILE', __FILE__ );
define( 'NOWSCROBBLING_PATH', plugin_dir_path( __FILE__ ) );
define( 'NOWSCROBBLING_URL', plugin_dir_url( __FILE__ ) );
define( 'NOWSCROBBLING_BASENAME', plugin_basename( __FILE__ ) );

// -----------------------------------------------------------------------------
// PSR-4 Autoloader
// -----------------------------------------------------------------------------
require_once NOWSCROBBLING_PATH . 'src/Core/Autoloader.php';
\NowScrobbling\Core\Autoloader::getInstance()->register();

// -----------------------------------------------------------------------------
// Initialize Plugin
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', function() {
    \NowScrobbling\Core\Plugin::getInstance()->init();
}, 5 );

// No closing PHP tag to avoid accidental output.
