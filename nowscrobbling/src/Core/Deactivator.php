<?php

declare(strict_types=1);

namespace NowScrobbling\Core;

/**
 * Plugin deactivation handler
 *
 * Runs on plugin deactivation to clean up scheduled events
 * and perform any necessary cleanup. Does NOT delete user data.
 *
 * @package NowScrobbling\Core
 */
final class Deactivator
{
    /**
     * Cron hooks to clear on deactivation
     *
     * @var array<string>
     */
    private const CRON_HOOKS = [
        'nowscrobbling_cache_refresh',
        'nowscrobbling_nowplaying_tick',
    ];

    /**
     * Transient prefixes to clear
     *
     * @var array<string>
     */
    private const TRANSIENT_PREFIXES = [
        'ns_',
        'nowscrobbling_',
        '_ns_lock_',
    ];

    /**
     * Deactivate the plugin
     *
     * Called by WordPress on plugin deactivation.
     */
    public static function deactivate(): void
    {
        $deactivator = new self();
        $deactivator->run();
    }

    /**
     * Run deactivation tasks
     */
    private function run(): void
    {
        $this->clearCronJobs();
        $this->clearTransients();
        $this->clearStaticCaches();

        /**
         * Fires after plugin deactivation
         */
        do_action('nowscrobbling_deactivated');
    }

    /**
     * Clear all scheduled cron jobs
     */
    private function clearCronJobs(): void
    {
        foreach (self::CRON_HOOKS as $hook) {
            $timestamp = wp_next_scheduled($hook);

            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }

            // Clear all occurrences of the hook
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Clear all plugin transients
     *
     * Uses direct database query for efficiency.
     */
    private function clearTransients(): void
    {
        global $wpdb;

        foreach (self::TRANSIENT_PREFIXES as $prefix) {
            // Clear regular transients
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_' . $wpdb->esc_like($prefix) . '%',
                    '_transient_timeout_' . $wpdb->esc_like($prefix) . '%'
                )
            );

            // Clear site transients (multisite compatibility)
            if (is_multisite()) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
                        '_site_transient_' . $wpdb->esc_like($prefix) . '%',
                        '_site_transient_timeout_' . $wpdb->esc_like($prefix) . '%'
                    )
                );
            }
        }

        // If using object cache, clear the cache group
        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group('nowscrobbling');
        }
    }

    /**
     * Clear any static/runtime caches
     */
    private function clearStaticCaches(): void
    {
        // Reset now-playing flags
        update_option('ns_flag_lastfm_nowplaying', 0);
        update_option('ns_flag_trakt_watching', 0);

        /**
         * Fires to allow clearing of custom caches
         */
        do_action('nowscrobbling_clear_caches');
    }
}
