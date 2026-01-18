<?php

declare(strict_types=1);

namespace NowScrobbling\Admin\Tabs;

use NowScrobbling\Security\Sanitizer;

/**
 * Caching Tab
 *
 * Handles cache configuration and management.
 *
 * @package NowScrobbling\Admin\Tabs
 */
final class CachingTab implements TabInterface
{
    private const SECTION = 'ns_section_caching';

    public function getId(): string
    {
        return 'caching';
    }

    public function getTitle(): string
    {
        return __('Caching', 'nowscrobbling');
    }

    public function getIcon(): string
    {
        return 'dashicons-performance';
    }

    public function getOrder(): int
    {
        return 20;
    }

    public function registerSettings(): void
    {
        add_settings_section(
            self::SECTION,
            __('Cache Settings', 'nowscrobbling'),
            fn() => $this->renderSectionDescription(),
            'nowscrobbling_caching'
        );

        // Last.fm Cache Duration
        register_setting('nowscrobbling', 'ns_lastfm_cache_duration', [
            'type' => 'integer',
            'sanitize_callback' => fn($v) => max(1, min(60, absint($v))),
            'default' => 1,
        ]);

        add_settings_field(
            'ns_lastfm_cache_duration',
            __('Last.fm Cache Duration', 'nowscrobbling'),
            fn() => $this->renderNumberField('ns_lastfm_cache_duration', 1, 60, [
                'description' => __('Minutes to cache Last.fm data (1-60).', 'nowscrobbling'),
                'suffix' => __('minutes', 'nowscrobbling'),
            ]),
            'nowscrobbling_caching',
            self::SECTION
        );

        // Trakt Cache Duration
        register_setting('nowscrobbling', 'ns_trakt_cache_duration', [
            'type' => 'integer',
            'sanitize_callback' => fn($v) => max(1, min(60, absint($v))),
            'default' => 5,
        ]);

        add_settings_field(
            'ns_trakt_cache_duration',
            __('Trakt.tv Cache Duration', 'nowscrobbling'),
            fn() => $this->renderNumberField('ns_trakt_cache_duration', 1, 60, [
                'description' => __('Minutes to cache Trakt data (1-60).', 'nowscrobbling'),
                'suffix' => __('minutes', 'nowscrobbling'),
            ]),
            'nowscrobbling_caching',
            self::SECTION
        );

        // Now Playing Interval
        register_setting('nowscrobbling', 'ns_nowplaying_interval', [
            'type' => 'integer',
            'sanitize_callback' => fn($v) => max(5, min(120, absint($v))),
            'default' => 20,
        ]);

        add_settings_field(
            'ns_nowplaying_interval',
            __('Now Playing Refresh', 'nowscrobbling'),
            fn() => $this->renderNumberField('ns_nowplaying_interval', 5, 120, [
                'description' => __('Seconds between AJAX refreshes when something is playing (5-120).', 'nowscrobbling'),
                'suffix' => __('seconds', 'nowscrobbling'),
            ]),
            'nowscrobbling_caching',
            self::SECTION
        );

        // Max Polling Interval
        register_setting('nowscrobbling', 'ns_max_interval', [
            'type' => 'integer',
            'sanitize_callback' => fn($v) => max(60, min(600, absint($v))),
            'default' => 300,
        ]);

        add_settings_field(
            'ns_max_interval',
            __('Max Polling Interval', 'nowscrobbling'),
            fn() => $this->renderNumberField('ns_max_interval', 60, 600, [
                'description' => __('Maximum seconds between polls when nothing is playing (60-600).', 'nowscrobbling'),
                'suffix' => __('seconds', 'nowscrobbling'),
            ]),
            'nowscrobbling_caching',
            self::SECTION
        );
    }

    public function render(): void
    {
        ?>
        <div class="ns-tab-content" id="ns-tab-caching">
            <table class="form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('nowscrobbling_caching', self::SECTION); ?>
                </tbody>
            </table>

            <h3><?php esc_html_e('Cache Management', 'nowscrobbling'); ?></h3>
            <p class="description">
                <?php esc_html_e('Clear cached data to force fresh API requests.', 'nowscrobbling'); ?>
            </p>
            <p class="submit">
                <button type="button" class="button ns-clear-cache" data-type="primary">
                    <?php esc_html_e('Clear Primary Cache', 'nowscrobbling'); ?>
                </button>
                <button type="button" class="button button-secondary ns-clear-cache" data-type="all">
                    <?php esc_html_e('Clear All Caches', 'nowscrobbling'); ?>
                </button>
                <span class="ns-cache-result"></span>
            </p>
        </div>
        <?php
    }

    private function renderSectionDescription(): void
    {
        printf(
            '<p class="description">%s</p>',
            esc_html__('Configure how long data is cached and how often the page refreshes.', 'nowscrobbling')
        );
    }

    /**
     * Render a number field
     *
     * @param string               $name    Option name
     * @param int                  $min     Minimum value
     * @param int                  $max     Maximum value
     * @param array<string, mixed> $options Additional options
     */
    private function renderNumberField(string $name, int $min, int $max, array $options = []): void
    {
        $value = get_option($name, $options['default'] ?? $min);
        $id = esc_attr($name);

        printf(
            '<input type="number" id="%s" name="%s" value="%d" min="%d" max="%d" class="small-text" />',
            $id,
            $id,
            absint($value),
            $min,
            $max
        );

        if (!empty($options['suffix'])) {
            printf(' <span class="description">%s</span>', esc_html($options['suffix']));
        }

        if (!empty($options['description'])) {
            printf('<p class="description">%s</p>', esc_html($options['description']));
        }
    }
}
