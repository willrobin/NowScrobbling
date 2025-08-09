<?php
/**
 * Plugin Name:         NowScrobbling
 * Plugin URI:          https://github.com/willrobin/NowScrobbling
 * Description:         NowScrobbling is a WordPress plugin designed to manage API settings and display recent activities for last.fm and trakt.tv on your site. It enables users to show their latest scrobbles through shortcodes.
 * Version:             1.3.1.1
 * File: nowscrobbling/nowscrobbling.php
 * Requires at least:   5.0
 * Requires PHP:        7.0
 * Author:              Robin Will
 * Author URI:          https://robinwill.de/
 * License:             GPLv2 or later
 * Text Domain:         nowscrobbling
 * Domain Path:         /languages
 * GitHub Plugin URI:   https://github.com/willrobin/NowScrobbling
 * GitHub Branch:       main
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
if ( ! defined( 'NOWSCROBBLING_VERSION' ) ) {
    define( 'NOWSCROBBLING_VERSION', '1.3.1.1' );
}
if ( ! defined( 'NOWSCROBBLING_FILE' ) ) {
	define( 'NOWSCROBBLING_FILE', __FILE__ );
}
if ( ! defined( 'NOWSCROBBLING_PATH' ) ) {
	define( 'NOWSCROBBLING_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'NOWSCROBBLING_URL' ) ) {
	define( 'NOWSCROBBLING_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'NOWSCROBBLING_BASENAME' ) ) {
	define( 'NOWSCROBBLING_BASENAME', plugin_basename( __FILE__ ) );
}

// -----------------------------------------------------------------------------
// i18n
// -----------------------------------------------------------------------------
add_action( 'init', function () {
	load_plugin_textdomain( 'nowscrobbling', false, dirname( NOWSCROBBLING_BASENAME ) . '/languages' );
} );

// -----------------------------------------------------------------------------
// Conditional asset loading
// -----------------------------------------------------------------------------
function nowscrobbling_list_shortcodes() : array {
    return [
        'nowscr_lastfm_indicator',
        'nowscr_lastfm_history',
        'nowscr_lastfm_top_artists',
        'nowscr_lastfm_top_albums',
        'nowscr_lastfm_top_tracks',
        'nowscr_lastfm_lovedtracks',
        'nowscr_trakt_indicator',
        'nowscr_trakt_history',
        'nowscr_trakt_last_movie',
        'nowscr_trakt_last_show',
        'nowscr_trakt_last_episode',
    ];
}

function nowscrobbling_page_has_shortcodes() : bool {
    if ( is_admin() ) return false;
    global $posts;
    if ( empty( $posts ) || ! is_array( $posts ) ) return false;
    $tags = nowscrobbling_list_shortcodes();
    foreach ( $posts as $p ) {
        if ( ! isset( $p->post_content ) ) continue;
        foreach ( $tags as $tag ) {
            if ( has_shortcode( $p->post_content, $tag ) ) {
                return true;
            }
        }
    }
    return false;
}

function nowscrobbling_should_load_assets() : bool {
    // Don't load on admin pages
    if ( is_admin() ) {
        return false;
    }

    // Load only if a NowScrobbling shortcode is present on the page
    return nowscrobbling_page_has_shortcodes();
}

function nowscrobbling_enqueue_styles() : void {
	if ( ! nowscrobbling_should_load_assets() ) {
		return;
	}
	
	$css_rel_path = 'public/css/nowscrobbling-public.css';
	$css_path     = NOWSCROBBLING_PATH . $css_rel_path;
	$version      = file_exists( $css_path ) ? (string) filemtime( $css_path ) : NOWSCROBBLING_VERSION;

	wp_enqueue_style(
		'nowscrobbling-public',
		NOWSCROBBLING_URL . $css_rel_path,
		[],
		$version
	);
}
add_action( 'wp_enqueue_scripts', 'nowscrobbling_enqueue_styles' );

function nowscrobbling_enqueue_scripts() : void {
    // Always enqueue when relevant shortcodes are present on the page
    if ( ! nowscrobbling_should_load_assets() ) {
        return;
    }
	
	$js_rel_path = 'public/js/ajax-load.js';
	$js_path     = NOWSCROBBLING_PATH . $js_rel_path;
	$version     = file_exists( $js_path ) ? (string) filemtime( $js_path ) : NOWSCROBBLING_VERSION;

	wp_enqueue_script(
		'nowscrobbling-ajax',
		NOWSCROBBLING_URL . $js_rel_path,
		[ 'jquery' ],
		$version,
		true
	);

    // Enhanced localization with admin-configurable polling intervals
    $nowplaying_interval_s = (int) get_option( 'ns_nowplaying_interval', 20 );
    $max_interval_s        = (int) get_option( 'ns_max_interval', 300 );
    $backoff_multiplier    = (float) get_option( 'ns_backoff_multiplier', 2 );

    wp_localize_script( 'nowscrobbling-ajax', 'nowscrobbling_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'nowscrobbling_nonce' ),
        'polling'  => [
            'nowplaying_interval' => max( 5, $nowplaying_interval_s ) * 1000,
            'max_interval'        => max( 30, $max_interval_s ) * 1000,
            'backoff_multiplier'  => max( 1, $backoff_multiplier ),
        ],
        'debug'    => (bool) get_option( 'nowscrobbling_debug_log', false ),
    ] );
}
add_action( 'wp_enqueue_scripts', 'nowscrobbling_enqueue_scripts' );

// Removed resource hints to ensure browsers do not connect directly to external APIs

// -----------------------------------------------------------------------------
// Cron job for background cache refresh
// -----------------------------------------------------------------------------
function nowscrobbling_schedule_cron() {
	if ( ! wp_next_scheduled( 'nowscrobbling_cache_refresh' ) ) {
		wp_schedule_event( time(), 'every_5_minutes', 'nowscrobbling_cache_refresh' );
	}
}
add_action( 'wp', 'nowscrobbling_schedule_cron' );

function nowscrobbling_cache_refresh_cron() {
	// Only refresh if we have API credentials configured
	if ( get_option( 'lastfm_api_key' ) || get_option( 'trakt_client_id' ) ) {
		do_action( 'nowscrobbling_background_refresh' );
	}
}
add_action( 'nowscrobbling_cache_refresh', 'nowscrobbling_cache_refresh_cron' );

// Add custom cron interval
function nowscrobbling_cron_intervals( $schedules ) {
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => __( 'Every 5 Minutes', 'nowscrobbling' ),
    ];
    // Add a shorter interval to support now playing freshness if desired
    $schedules['every_1_minute'] = [
        'interval' => 60,
        'display'  => __( 'Every 1 Minute', 'nowscrobbling' ),
    ];
	return $schedules;
}
add_filter( 'cron_schedules', 'nowscrobbling_cron_intervals' );

// -----------------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------------
require_once NOWSCROBBLING_PATH . 'includes/ajax.php';
require_once NOWSCROBBLING_PATH . 'includes/admin-settings.php';
require_once NOWSCROBBLING_PATH . 'includes/shortcodes.php';
require_once NOWSCROBBLING_PATH . 'includes/api-functions.php';

// -----------------------------------------------------------------------------
// Activation/Deactivation hooks
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, 'nowscrobbling_activate' );
register_deactivation_hook( __FILE__, 'nowscrobbling_deactivate' );

function nowscrobbling_activate() {
	// Schedule cron job
	nowscrobbling_schedule_cron();
	
	// Set default options if they don't exist
	$defaults = [
		'cache_duration'           => 60,
		'lastfm_cache_duration'    => 1,
		'trakt_cache_duration'     => 5,
		'top_tracks_count'         => 5,
		'top_artists_count'        => 5,
		'top_albums_count'         => 5,
		'lovedtracks_count'        => 5,
		'last_movies_count'        => 3,
		'last_shows_count'         => 3,
		'last_episodes_count'      => 3,
		'lastfm_activity_limit'    => 5,
		'trakt_activity_limit'     => 5,
		'nowscrobbling_debug_log'  => 0,
        'ns_nowplaying_interval'   => 20,
        'ns_max_interval'          => 300,
        'ns_backoff_multiplier'    => 2,
        'ns_enable_rewatch'        => 0,
	];
	
	foreach ( $defaults as $key => $value ) {
		if ( get_option( $key ) === false ) {
			add_option( $key, $value );
		}
	}
}

/**
 * On each admin load, enforce defaults for non-credential options.
 * Keeps API credentials intact while normalizing feature flags.
 */
add_action( 'admin_init', function(){
    // Normalize numeric options only; never override user-set booleans here
    $defaults = [
        'ns_nowplaying_interval'    => 20,
        'ns_max_interval'           => 300,
        'ns_backoff_multiplier'     => 2.0,
        'cache_duration'            => 60,
        'lastfm_cache_duration'     => 1,
        'trakt_cache_duration'      => 5,
        'top_tracks_count'          => 5,
        'top_artists_count'         => 5,
        'top_albums_count'          => 5,
        'lovedtracks_count'         => 5,
        'last_movies_count'         => 3,
        'last_shows_count'          => 3,
        'last_episodes_count'       => 3,
        'lastfm_activity_limit'     => 5,
        'trakt_activity_limit'      => 5,
    ];

    foreach ( $defaults as $key => $def ) {
        $current = get_option( $key );
        // If option missing, seed with default
        if ( $current === false ) {
            update_option( $key, $def );
            continue;
        }
        // Coerce ranges for integers
        if ( is_int( $def ) ) {
            $val = absint( $current );
            if ( $key === 'ns_nowplaying_interval' && $val < 5 ) { $val = 20; }
            if ( $key === 'ns_max_interval' && $val < 30 ) { $val = 300; }
            if ( strpos( $key, '_count' ) !== false && $val < 1 ) { $val = $def; }
            if ( strpos( $key, '_duration' ) !== false && $val < 1 ) { $val = $def; }
            if ( $val !== (int) $current ) { update_option( $key, $val ); }
            continue;
        }
        // Coerce ranges for floats
        if ( is_float( $def ) ) {
            $val = (float) $current;
            if ( $key === 'ns_backoff_multiplier' && $val < 1 ) { $val = 2.0; }
            if ( $val !== (float) $current ) { update_option( $key, $val ); }
            continue;
        }
        // Do not normalize booleans here to avoid overriding user preferences
    }
});

function nowscrobbling_deactivate() {
	// Clear scheduled cron job
	wp_clear_scheduled_hook( 'nowscrobbling_cache_refresh' );
	
	// Clear all transients
	nowscrobbling_clear_all_caches();
}

// No closing PHP tag to avoid accidental output.
