<?php
/**
 * NowScrobbling Uninstall Script
 *
 * This file is executed when the plugin is deleted through the WordPress admin.
 * It cleans up all plugin data including:
 * - Plugin options
 * - Transients (caches)
 * - Scheduled cron jobs
 * - Any custom database tables (if any)
 *
 * Note: This file does NOT run on plugin deactivation, only on deletion.
 *
 * @package NowScrobbling
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up all plugin data
 */
function nowscrobbling_uninstall(): void
{
    global $wpdb;

    // 1. Delete all plugin options (v2.0 format)
    $v2Options = [
        // API Credentials
        'ns_lastfm_api_key',
        'ns_lastfm_user',
        'ns_trakt_client_id',
        'ns_trakt_user',

        // Cache Settings
        'ns_cache_duration',
        'ns_lastfm_cache_duration',
        'ns_trakt_cache_duration',

        // Display Counts
        'ns_top_tracks_count',
        'ns_top_artists_count',
        'ns_top_albums_count',
        'ns_lovedtracks_count',
        'ns_last_movies_count',
        'ns_last_shows_count',
        'ns_last_episodes_count',
        'ns_lastfm_activity_limit',
        'ns_trakt_activity_limit',

        // Polling Settings
        'ns_nowplaying_interval',
        'ns_max_interval',
        'ns_backoff_multiplier',

        // Feature Flags
        'ns_debug_enabled',
        'ns_enable_rewatch',

        // Text Customizations
        'ns_currently_playing_text',
        'ns_currently_watching_text',
        'ns_last_played_text',
        'ns_last_watched_text',
        'ns_period_default',

        // State Flags
        'ns_flag_lastfm_nowplaying',
        'ns_flag_trakt_watching',

        // Internal
        'ns_version',
        'ns_activated_at',
        'ns_migration_version',
    ];

    foreach ($v2Options as $option) {
        delete_option($option);
    }

    // 2. Delete legacy v1.x options (if still present)
    $legacyOptions = [
        'lastfm_api_key',
        'lastfm_user',
        'trakt_client_id',
        'trakt_user',
        'cache_duration',
        'lastfm_cache_duration',
        'trakt_cache_duration',
        'top_tracks_count',
        'top_artists_count',
        'top_albums_count',
        'lovedtracks_count',
        'last_movies_count',
        'last_shows_count',
        'last_episodes_count',
        'lastfm_activity_limit',
        'trakt_activity_limit',
        'nowscrobbling_debug_log',
        'nowscrobbling_version',
        'enable_rewatch',
    ];

    foreach ($legacyOptions as $option) {
        delete_option($option);
    }

    // 3. Delete all transients with our prefixes
    $transientPrefixes = [
        'ns_',
        'nowscrobbling_',
        'lastfm_',
        'trakt_',
    ];

    foreach ($transientPrefixes as $prefix) {
        // Delete transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                "_transient_{$prefix}%"
            )
        );

        // Delete transient timeouts
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                "_transient_timeout_{$prefix}%"
            )
        );
    }

    // 4. Clear scheduled cron jobs
    $cronHooks = [
        'nowscrobbling_cache_refresh',
        'nowscrobbling_nowplaying_tick',
        'every_5_minutes',
        'every_1_minute',
    ];

    foreach ($cronHooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    // 5. Clean up custom cron intervals (they'll be re-added on next activation)
    $cron = get_option('cron', []);
    if (is_array($cron)) {
        foreach ($cron as $timestamp => $hooks) {
            if (!is_array($hooks)) {
                continue;
            }
            foreach (array_keys($hooks) as $hook) {
                if (is_string($hook) && str_starts_with($hook, 'nowscrobbling_')) {
                    unset($cron[$timestamp][$hook]);
                }
            }
            // Remove empty timestamps
            if (empty($cron[$timestamp]) || $cron[$timestamp] === ['version' => 2]) {
                unset($cron[$timestamp]);
            }
        }
        update_option('cron', $cron);
    }

    // 6. Remove any user meta (if we stored any)
    // Currently not used, but included for completeness
    // $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ns_%'");

    // 7. Flush rewrite rules
    flush_rewrite_rules();

    /**
     * Fires after plugin uninstall cleanup
     *
     * Use this hook if you need to clean up additional data
     * added by extensions or customizations.
     */
    do_action('nowscrobbling_uninstalled');
}

// Run the uninstall
nowscrobbling_uninstall();
