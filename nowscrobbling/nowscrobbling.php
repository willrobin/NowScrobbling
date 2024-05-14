<?php

/**
 * Plugin Name:         NowScrobbling
 * Plugin URI:          https://github.com/willrobin/NowScrobbling
 * Description:         NowScrobbling is a WordPress plugin designed to manage API settings and display recent activities for last.fm and trakt.tv on your site. It enables users to show their latest scrobbles through shortcodes.
 * Version:             1.2.1
 * Requires at least:   5.0
 * Requires PHP:        7.0
 * Author:              Robin Will
 * Author URI:          https://robinwill.de/
 * License:             GPLv2 or later
 * Text Domain:         nowscrobbling
 * Domain Path:         /languages
 * GitHub Plugin URI:   https://github.com/willrobin/NowScrobbling
 * GitHub Branch:       master
 */

 // Ensure the script is not accessed directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue public CSS
function nowscrobbling_enqueue_styles() {
    wp_enqueue_style(
        'nowscrobbling-public', // Handle for the stylesheet
        plugin_dir_url(__FILE__) . 'public/css/nowscrobbling-public.css', // URL to the stylesheet
        [], // Dependencies (if any)
        '1.2.1' // Version number
    );
}
add_action('wp_enqueue_scripts', 'nowscrobbling_enqueue_styles');

// Include additional files
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-functions.php';

?>
