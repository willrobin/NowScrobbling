<?php

declare(strict_types=1);

namespace NowScrobbling\Admin\Tabs;

use NowScrobbling\Security\Sanitizer;

/**
 * Display Tab
 *
 * Handles display and formatting options.
 *
 * @package NowScrobbling\Admin\Tabs
 */
final class DisplayTab implements TabInterface
{
    private const SECTION = 'ns_section_display';

    public function getId(): string
    {
        return 'display';
    }

    public function getTitle(): string
    {
        return __('Display', 'nowscrobbling');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-appearance';
    }

    public function getOrder(): int
    {
        return 30;
    }

    public function registerSettings(): void
    {
        add_settings_section(
            self::SECTION,
            __('Display Options', 'nowscrobbling'),
            fn() => $this->renderSectionDescription(),
            'nowscrobbling'
        );

        // Max text length
        register_setting('nowscrobbling', 'ns_max_length', [
            'type' => 'integer',
            'sanitize_callback' => fn($v) => max(20, min(200, absint($v))),
            'default' => 45,
        ]);

        add_settings_field(
            'ns_max_length',
            __('Max Text Length', 'nowscrobbling'),
            fn() => $this->renderNumberField('ns_max_length', 20, 200, [
                'description' => __('Maximum characters for track/movie titles (20-200).', 'nowscrobbling'),
                'suffix' => __('characters', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION
        );

        // Default period for top lists
        register_setting('nowscrobbling', 'ns_default_period', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => in_array($v, ['7day', '1month', '3month', '6month', '12month', 'overall'], true) ? $v : '7day',
            'default' => '7day',
        ]);

        add_settings_field(
            'ns_default_period',
            __('Default Period', 'nowscrobbling'),
            fn() => $this->renderSelectField('ns_default_period', [
                '7day' => __('Last 7 Days', 'nowscrobbling'),
                '1month' => __('Last Month', 'nowscrobbling'),
                '3month' => __('Last 3 Months', 'nowscrobbling'),
                '6month' => __('Last 6 Months', 'nowscrobbling'),
                '12month' => __('Last Year', 'nowscrobbling'),
                'overall' => __('All Time', 'nowscrobbling'),
            ], [
                'description' => __('Default time period for top artists/albums/tracks.', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION
        );

        // Default limit
        register_setting('nowscrobbling', 'ns_default_limit', [
            'type' => 'integer',
            'sanitize_callback' => fn($v) => max(1, min(20, absint($v))),
            'default' => 5,
        ]);

        add_settings_field(
            'ns_default_limit',
            __('Default Limit', 'nowscrobbling'),
            fn() => $this->renderNumberField('ns_default_limit', 1, 20, [
                'description' => __('Default number of items to display (1-20).', 'nowscrobbling'),
                'suffix' => __('items', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION
        );

        // Date format
        register_setting('nowscrobbling', 'ns_date_format', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => in_array($v, ['relative', 'absolute', 'both'], true) ? $v : 'relative',
            'default' => 'relative',
        ]);

        add_settings_field(
            'ns_date_format',
            __('Date Format', 'nowscrobbling'),
            fn() => $this->renderSelectField('ns_date_format', [
                'relative' => __('Relative (e.g., "2 hours ago")', 'nowscrobbling'),
                'absolute' => __('Absolute (e.g., "Jan 18, 2026")', 'nowscrobbling'),
                'both' => __('Both', 'nowscrobbling'),
            ], [
                'description' => __('How to display timestamps.', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION
        );

        // Display style
        register_setting('nowscrobbling', 'ns_display_style', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => in_array($v, ['inline', 'bubble'], true) ? $v : 'inline',
            'default' => 'inline',
        ]);

        add_settings_field(
            'ns_display_style',
            __('Display Style', 'nowscrobbling'),
            fn() => $this->renderSelectField('ns_display_style', [
                'inline' => __('Inline (like normal text)', 'nowscrobbling'),
                'bubble' => __('Bubble (highlighted with border)', 'nowscrobbling'),
            ], [
                'description' => __('How indicator shortcodes are displayed. Can be overridden per shortcode with style="inline" or style="bubble".', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION
        );

        // Show links
        register_setting('nowscrobbling', 'ns_show_links', [
            'type' => 'boolean',
            'sanitize_callback' => fn($v) => (bool) $v,
            'default' => true,
        ]);

        add_settings_field(
            'ns_show_links',
            __('Show Links', 'nowscrobbling'),
            fn() => $this->renderCheckbox('ns_show_links', [
                'label' => __('Link track/movie names to Last.fm/Trakt.tv', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION
        );
    }

    public function render(): void
    {
        ?>
        <div class="ns-tab-content" id="ns-tab-display">
            <table class="form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('nowscrobbling', self::SECTION); ?>
                </tbody>
            </table>

            <h3><?php esc_html_e('Shortcode Reference', 'nowscrobbling'); ?></h3>
            <div class="ns-shortcode-reference">
                <h4><?php esc_html_e('Last.fm Shortcodes', 'nowscrobbling'); ?></h4>
                <ul>
                    <li><code>[nowscr_lastfm_indicator]</code> - <?php esc_html_e('Now playing indicator', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_lastfm_history]</code> - <?php esc_html_e('Recent scrobbles', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_lastfm_top_artists]</code> - <?php esc_html_e('Top artists', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_lastfm_top_albums]</code> - <?php esc_html_e('Top albums', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_lastfm_top_tracks]</code> - <?php esc_html_e('Top tracks', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_lastfm_lovedtracks]</code> - <?php esc_html_e('Loved tracks', 'nowscrobbling'); ?></li>
                </ul>

                <h4><?php esc_html_e('Trakt.tv Shortcodes', 'nowscrobbling'); ?></h4>
                <ul>
                    <li><code>[nowscr_trakt_indicator]</code> - <?php esc_html_e('Currently watching', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_trakt_history]</code> - <?php esc_html_e('Watch history', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_trakt_last_movie]</code> - <?php esc_html_e('Last watched movie', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_trakt_last_show]</code> - <?php esc_html_e('Last watched show', 'nowscrobbling'); ?></li>
                    <li><code>[nowscr_trakt_last_episode]</code> - <?php esc_html_e('Last watched episode', 'nowscrobbling'); ?></li>
                </ul>

                <h4><?php esc_html_e('Common Attributes', 'nowscrobbling'); ?></h4>
                <ul>
                    <li><code>limit="5"</code> - <?php esc_html_e('Number of items', 'nowscrobbling'); ?></li>
                    <li><code>period="7day"</code> - <?php esc_html_e('Time period (7day, 1month, 3month, 6month, 12month, overall)', 'nowscrobbling'); ?></li>
                    <li><code>max_length="45"</code> - <?php esc_html_e('Max text length', 'nowscrobbling'); ?></li>
                    <li><code>style="inline|bubble"</code> - <?php esc_html_e('Display style (overrides global setting)', 'nowscrobbling'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    private function renderSectionDescription(): void
    {
        printf(
            '<p class="description">%s</p>',
            esc_html__('Configure how content is displayed.', 'nowscrobbling')
        );
    }

    /**
     * Render a number field
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

    /**
     * Render a select field
     *
     * @param string                $name    Option name
     * @param array<string, string> $choices Choice values and labels
     * @param array<string, mixed>  $options Additional options
     */
    private function renderSelectField(string $name, array $choices, array $options = []): void
    {
        $value = get_option($name, array_key_first($choices));
        $id = esc_attr($name);

        printf('<select id="%s" name="%s">', $id, $id);

        foreach ($choices as $val => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($val),
                selected($value, $val, false),
                esc_html($label)
            );
        }

        echo '</select>';

        if (!empty($options['description'])) {
            printf('<p class="description">%s</p>', esc_html($options['description']));
        }
    }

    /**
     * Render a checkbox
     */
    private function renderCheckbox(string $name, array $options = []): void
    {
        $value = get_option($name, false);
        $id = esc_attr($name);

        printf(
            '<label for="%s"><input type="checkbox" id="%s" name="%s" value="1"%s /> %s</label>',
            $id,
            $id,
            $id,
            checked($value, true, false),
            esc_html($options['label'] ?? '')
        );

        if (!empty($options['description'])) {
            printf('<p class="description">%s</p>', esc_html($options['description']));
        }
    }
}
