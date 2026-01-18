<?php

declare(strict_types=1);

namespace NowScrobbling\Core;

use NowScrobbling\Plugin;

/**
 * Plugin activation handler
 *
 * Runs on plugin activation to set up default options,
 * schedule cron jobs, and perform any necessary migrations.
 *
 * @package NowScrobbling\Core
 */
final class Activator
{
    /**
     * Default option values
     *
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        // Cache durations (minutes)
        'ns_cache_duration'        => 60,
        'ns_lastfm_cache_duration' => 1,
        'ns_trakt_cache_duration'  => 5,

        // Display counts
        'ns_top_tracks_count'      => 5,
        'ns_top_artists_count'     => 5,
        'ns_top_albums_count'      => 5,
        'ns_lovedtracks_count'     => 5,
        'ns_last_movies_count'     => 3,
        'ns_last_shows_count'      => 3,
        'ns_last_episodes_count'   => 3,
        'ns_lastfm_activity_limit' => 5,
        'ns_trakt_activity_limit'  => 5,

        // Polling configuration
        'ns_nowplaying_interval'   => 20,
        'ns_max_interval'          => 300,
        'ns_backoff_multiplier'    => 2.0,

        // Feature flags
        'ns_debug_enabled'         => false,
        'ns_enable_rewatch'        => false,

        // State flags (internal)
        'ns_flag_lastfm_nowplaying' => 0,
        'ns_flag_trakt_watching'    => 0,
    ];

    /**
     * Activate the plugin
     *
     * Called by WordPress on plugin activation.
     */
    public static function activate(): void
    {
        $activator = new self();
        $activator->run();
    }

    /**
     * Run activation tasks
     */
    private function run(): void
    {
        $this->checkRequirements();
        $this->setDefaults();
        $this->scheduleCronJobs();
        $this->runMigration();

        // Store activation timestamp
        update_option('ns_activated_at', time());
        update_option('ns_version', Plugin::VERSION);

        // Flush rewrite rules for REST API
        flush_rewrite_rules();

        /**
         * Fires after plugin activation
         */
        do_action('nowscrobbling_activated');
    }

    /**
     * Check system requirements
     *
     * Deactivates the plugin if requirements are not met.
     */
    private function checkRequirements(): void
    {
        if (version_compare(PHP_VERSION, Plugin::MIN_PHP_VERSION, '<')) {
            deactivate_plugins(NOWSCROBBLING_BASENAME);
            wp_die(
                sprintf(
                    /* translators: 1: Required PHP version, 2: Current PHP version */
                    esc_html__(
                        'NowScrobbling requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP and try again.',
                        'nowscrobbling'
                    ),
                    Plugin::MIN_PHP_VERSION,
                    PHP_VERSION
                ),
                esc_html__('Plugin Activation Error', 'nowscrobbling'),
                ['back_link' => true]
            );
        }

        global $wp_version;
        if (version_compare($wp_version, Plugin::MIN_WP_VERSION, '<')) {
            deactivate_plugins(NOWSCROBBLING_BASENAME);
            wp_die(
                sprintf(
                    /* translators: 1: Required WP version, 2: Current WP version */
                    esc_html__(
                        'NowScrobbling requires WordPress %1$s or higher. You are running WordPress %2$s. Please upgrade WordPress and try again.',
                        'nowscrobbling'
                    ),
                    Plugin::MIN_WP_VERSION,
                    $wp_version
                ),
                esc_html__('Plugin Activation Error', 'nowscrobbling'),
                ['back_link' => true]
            );
        }
    }

    /**
     * Set default option values
     *
     * Only sets options that don't already exist (preserves user settings).
     */
    private function setDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Schedule cron jobs
     */
    private function scheduleCronJobs(): void
    {
        // Background cache refresh
        if (!wp_next_scheduled('nowscrobbling_cache_refresh')) {
            wp_schedule_event(time(), 'nowscrobbling_5_minutes', 'nowscrobbling_cache_refresh');
        }

        // Now-playing tick
        if (!wp_next_scheduled('nowscrobbling_nowplaying_tick')) {
            wp_schedule_event(time(), 'nowscrobbling_1_minute', 'nowscrobbling_nowplaying_tick');
        }
    }

    /**
     * Run database/option migration if needed
     */
    private function runMigration(): void
    {
        $previousVersion = get_option('ns_version', '0.0.0');

        // Migrate from v1.x to v2.0
        if (version_compare($previousVersion, '2.0.0', '<')) {
            $this->migrateFromV1();
        }

        /**
         * Fires after migration completes
         *
         * @param string $previousVersion The previous plugin version
         * @param string $newVersion      The new plugin version
         */
        do_action('nowscrobbling_migrated', $previousVersion, Plugin::VERSION);
    }

    /**
     * Migrate options from v1.x to v2.0 format
     */
    private function migrateFromV1(): void
    {
        // Option key mappings: old => new
        $mappings = [
            'lastfm_api_key'        => 'ns_lastfm_api_key',
            'lastfm_user'           => 'ns_lastfm_user',
            'trakt_client_id'       => 'ns_trakt_client_id',
            'trakt_user'            => 'ns_trakt_user',
            'cache_duration'        => 'ns_cache_duration',
            'lastfm_cache_duration' => 'ns_lastfm_cache_duration',
            'trakt_cache_duration'  => 'ns_trakt_cache_duration',
            'top_tracks_count'      => 'ns_top_tracks_count',
            'top_artists_count'     => 'ns_top_artists_count',
            'top_albums_count'      => 'ns_top_albums_count',
            'lovedtracks_count'     => 'ns_lovedtracks_count',
            'last_movies_count'     => 'ns_last_movies_count',
            'last_shows_count'      => 'ns_last_shows_count',
            'last_episodes_count'   => 'ns_last_episodes_count',
            'lastfm_activity_limit' => 'ns_lastfm_activity_limit',
            'trakt_activity_limit'  => 'ns_trakt_activity_limit',
            'nowscrobbling_debug_log' => 'ns_debug_enabled',
        ];

        foreach ($mappings as $oldKey => $newKey) {
            $value = get_option($oldKey);

            if ($value !== false && get_option($newKey) === false) {
                // Migrate the value
                add_option($newKey, $value);
            }
        }

        // Clear old cron hooks
        wp_clear_scheduled_hook('nowscrobbling_cache_refresh');
        wp_clear_scheduled_hook('nowscrobbling_nowplaying_tick');
        wp_clear_scheduled_hook('every_5_minutes');
        wp_clear_scheduled_hook('every_1_minute');

        // Mark migration complete
        update_option('ns_migration_version', '2.0.0');
    }
}
