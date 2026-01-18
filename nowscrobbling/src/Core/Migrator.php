<?php

declare(strict_types=1);

namespace NowScrobbling\Core;

use NowScrobbling\Plugin;

/**
 * Migration Handler for v1.x to v2.0
 *
 * Handles all data migrations between plugin versions including:
 * - Option key remapping
 * - Transient cleanup
 * - Database structure updates
 * - Legacy data removal
 *
 * @package NowScrobbling\Core
 */
final class Migrator
{
    /**
     * Current migration version
     */
    private const MIGRATION_VERSION = '2.0.0';

    /**
     * Option key mappings: v1.x => v2.0
     *
     * @var array<string, string>
     */
    private const OPTION_MAPPINGS = [
        // API Credentials
        'lastfm_api_key'           => 'ns_lastfm_api_key',
        'lastfm_user'              => 'ns_lastfm_user',
        'trakt_client_id'          => 'ns_trakt_client_id',
        'trakt_user'               => 'ns_trakt_user',

        // Cache Settings
        'cache_duration'           => 'ns_cache_duration',
        'lastfm_cache_duration'    => 'ns_lastfm_cache_duration',
        'trakt_cache_duration'     => 'ns_trakt_cache_duration',

        // Display Counts
        'top_tracks_count'         => 'ns_top_tracks_count',
        'top_artists_count'        => 'ns_top_artists_count',
        'top_albums_count'         => 'ns_top_albums_count',
        'lovedtracks_count'        => 'ns_lovedtracks_count',
        'last_movies_count'        => 'ns_last_movies_count',
        'last_shows_count'         => 'ns_last_shows_count',
        'last_episodes_count'      => 'ns_last_episodes_count',
        'lastfm_activity_limit'    => 'ns_lastfm_activity_limit',
        'trakt_activity_limit'     => 'ns_trakt_activity_limit',

        // Feature Flags
        'nowscrobbling_debug_log'  => 'ns_debug_enabled',
        'enable_rewatch'           => 'ns_enable_rewatch',

        // Text Customizations
        'currently_playing_text'   => 'ns_currently_playing_text',
        'currently_watching_text'  => 'ns_currently_watching_text',
        'last_played_text'         => 'ns_last_played_text',
        'last_watched_text'        => 'ns_last_watched_text',
    ];

    /**
     * Legacy option keys to delete after migration
     *
     * @var array<string>
     */
    private const LEGACY_OPTIONS = [
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
        'enable_rewatch',
        'currently_playing_text',
        'currently_watching_text',
        'last_played_text',
        'last_watched_text',
        'nowscrobbling_version',
    ];

    /**
     * Legacy transient prefixes to clean up
     *
     * @var array<string>
     */
    private const LEGACY_TRANSIENT_PREFIXES = [
        'lastfm_',
        'trakt_',
        'nowscrobbling_',
    ];

    /**
     * Legacy cron hooks to remove
     *
     * @var array<string>
     */
    private const LEGACY_CRON_HOOKS = [
        'every_5_minutes',
        'every_1_minute',
        'nowscrobbling_cache_refresh',
        'nowscrobbling_nowplaying_tick',
    ];

    /**
     * Migration log
     *
     * @var array<string, mixed>
     */
    private array $log = [];

    /**
     * Check if migration is needed
     */
    public function needsMigration(): bool
    {
        $currentVersion = get_option('ns_migration_version', '0.0.0');
        return version_compare($currentVersion, self::MIGRATION_VERSION, '<');
    }

    /**
     * Run all migrations
     *
     * @return array{success: bool, log: array<string, mixed>}
     */
    public function migrate(): array
    {
        if (!$this->needsMigration()) {
            return [
                'success' => true,
                'log' => ['message' => 'No migration needed'],
            ];
        }

        $this->log = ['started_at' => gmdate('c')];

        try {
            // Step 1: Migrate options
            $this->migrateOptions();

            // Step 2: Clean up legacy transients
            $this->cleanupTransients();

            // Step 3: Remove legacy cron hooks
            $this->cleanupCronHooks();

            // Step 4: Delete legacy options (optional, after verification)
            // $this->deleteLegacyOptions();

            // Mark migration complete
            update_option('ns_migration_version', self::MIGRATION_VERSION);
            $this->log['completed_at'] = gmdate('c');
            $this->log['success'] = true;

            /**
             * Fires after successful migration
             *
             * @param array $log Migration log
             */
            do_action('nowscrobbling_migration_complete', $this->log);

            return ['success' => true, 'log' => $this->log];

        } catch (\Throwable $e) {
            $this->log['error'] = $e->getMessage();
            $this->log['success'] = false;

            /**
             * Fires on migration failure
             *
             * @param \Throwable $e   The exception
             * @param array      $log Migration log
             */
            do_action('nowscrobbling_migration_failed', $e, $this->log);

            return ['success' => false, 'log' => $this->log];
        }
    }

    /**
     * Migrate options from v1.x to v2.0 format
     */
    private function migrateOptions(): void
    {
        $migrated = [];
        $skipped = [];

        foreach (self::OPTION_MAPPINGS as $oldKey => $newKey) {
            $oldValue = get_option($oldKey);

            // Skip if old option doesn't exist
            if ($oldValue === false) {
                $skipped[] = $oldKey;
                continue;
            }

            // Skip if new option already exists (don't overwrite)
            if (get_option($newKey) !== false) {
                $skipped[] = "{$oldKey} (new key exists)";
                continue;
            }

            // Sanitize and migrate
            $sanitizedValue = $this->sanitizeOptionValue($newKey, $oldValue);
            add_option($newKey, $sanitizedValue);
            $migrated[] = "{$oldKey} => {$newKey}";
        }

        $this->log['options'] = [
            'migrated' => $migrated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Sanitize option value based on expected type
     *
     * @param string $key   Option key
     * @param mixed  $value Raw value
     *
     * @return mixed Sanitized value
     */
    private function sanitizeOptionValue(string $key, mixed $value): mixed
    {
        // Boolean options
        if (str_contains($key, 'enabled') || str_contains($key, 'enable_')) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Integer options (counts, durations, intervals)
        if (str_contains($key, 'count') || str_contains($key, 'duration') ||
            str_contains($key, 'limit') || str_contains($key, 'interval')) {
            return max(0, (int) $value);
        }

        // Float options
        if (str_contains($key, 'multiplier')) {
            return max(1.0, (float) $value);
        }

        // API keys - preserve as-is but sanitize
        if (str_contains($key, 'api_key') || str_contains($key, 'client_id')) {
            return sanitize_text_field((string) $value);
        }

        // Text fields
        return sanitize_text_field((string) $value);
    }

    /**
     * Clean up legacy transients
     */
    private function cleanupTransients(): void
    {
        global $wpdb;

        $deleted = 0;

        foreach (self::LEGACY_TRANSIENT_PREFIXES as $prefix) {
            // Delete transients
            $pattern = "_transient_{$prefix}%";
            $count = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
            $deleted += (int) $count;

            // Delete transient timeouts
            $timeoutPattern = "_transient_timeout_{$prefix}%";
            $count = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $timeoutPattern
                )
            );
            $deleted += (int) $count;
        }

        $this->log['transients_deleted'] = $deleted;
    }

    /**
     * Remove legacy cron hooks
     */
    private function cleanupCronHooks(): void
    {
        $cleared = [];

        foreach (self::LEGACY_CRON_HOOKS as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_clear_scheduled_hook($hook);
                $cleared[] = $hook;
            }
        }

        $this->log['cron_hooks_cleared'] = $cleared;
    }

    /**
     * Delete legacy options
     *
     * Call this after verifying migration was successful
     */
    public function deleteLegacyOptions(): void
    {
        $deleted = [];

        foreach (self::LEGACY_OPTIONS as $key) {
            if (delete_option($key)) {
                $deleted[] = $key;
            }
        }

        $this->log['legacy_options_deleted'] = $deleted;
    }

    /**
     * Rollback migration (restore v1.x option keys)
     *
     * @return array{success: bool, log: array<string, mixed>}
     */
    public function rollback(): array
    {
        $rollbackLog = ['started_at' => gmdate('c')];
        $restored = [];

        // Reverse the mapping: copy v2.0 values back to v1.x keys
        foreach (self::OPTION_MAPPINGS as $oldKey => $newKey) {
            $newValue = get_option($newKey);

            if ($newValue !== false) {
                update_option($oldKey, $newValue);
                $restored[] = "{$newKey} => {$oldKey}";
            }
        }

        // Remove migration marker
        delete_option('ns_migration_version');

        $rollbackLog['restored'] = $restored;
        $rollbackLog['completed_at'] = gmdate('c');

        return ['success' => true, 'log' => $rollbackLog];
    }

    /**
     * Get migration status
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return [
            'current_version' => get_option('ns_migration_version', '0.0.0'),
            'target_version' => self::MIGRATION_VERSION,
            'needs_migration' => $this->needsMigration(),
            'plugin_version' => Plugin::VERSION,
        ];
    }

    /**
     * Get list of v1.x options that would be migrated
     *
     * @return array<string, mixed>
     */
    public function getV1Options(): array
    {
        $options = [];

        foreach (self::OPTION_MAPPINGS as $oldKey => $newKey) {
            $value = get_option($oldKey);
            if ($value !== false) {
                $options[$oldKey] = [
                    'value' => $value,
                    'new_key' => $newKey,
                    'new_exists' => get_option($newKey) !== false,
                ];
            }
        }

        return $options;
    }
}
