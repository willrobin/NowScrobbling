<?php

declare(strict_types=1);

namespace NowScrobbling\Admin\Tabs;

use NowScrobbling\Plugin;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Cache\Environment\EnvironmentDetector;

/**
 * Diagnostics Tab
 *
 * Displays system information, cache statistics, and debug options.
 *
 * @package NowScrobbling\Admin\Tabs
 */
final class DiagnosticsTab implements TabInterface
{
    private const SECTION = 'ns_section_diagnostics';

    public function getId(): string
    {
        return 'diagnostics';
    }

    public function getTitle(): string
    {
        return __('Diagnostics', 'nowscrobbling');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-tools';
    }

    public function getOrder(): int
    {
        return 40;
    }

    public function registerSettings(): void
    {
        add_settings_section(
            self::SECTION,
            __('Debug Settings', 'nowscrobbling'),
            fn() => $this->renderSectionDescription(),
            'nowscrobbling_diagnostics'
        );

        // Debug mode
        register_setting('nowscrobbling', 'ns_debug_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => fn($v) => (bool) $v,
            'default' => false,
        ]);

        add_settings_field(
            'ns_debug_enabled',
            __('Debug Mode', 'nowscrobbling'),
            fn() => $this->renderCheckbox('ns_debug_enabled', [
                'label' => __('Enable debug logging', 'nowscrobbling'),
                'description' => __('Logs API requests, cache operations, and errors.', 'nowscrobbling'),
            ]),
            'nowscrobbling_diagnostics',
            self::SECTION
        );
    }

    public function render(): void
    {
        $plugin = Plugin::getInstance();
        $container = $plugin->getContainer();
        $environment = new EnvironmentDetector();

        // Get cache diagnostics if available
        $cacheDiagnostics = [];
        if ($container->has(CacheManager::class)) {
            $cache = $container->make(CacheManager::class);
            $cacheDiagnostics = $cache->getDiagnostics();
        }

        ?>
        <div class="ns-tab-content" id="ns-tab-diagnostics">
            <table class="form-table" role="presentation">
                <tbody>
                    <?php do_settings_fields('nowscrobbling_diagnostics', self::SECTION); ?>
                </tbody>
            </table>

            <h3><?php esc_html_e('System Information', 'nowscrobbling'); ?></h3>
            <table class="widefat ns-diagnostics-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Plugin Version', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html(Plugin::VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('PHP Version', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WordPress Version', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Environment', 'nowscrobbling'); ?></th>
                        <td>
                            <?php
                            $envName = $environment->detect();
                            $envLabels = [
                                'cloudflare' => 'Cloudflare',
                                'litespeed' => 'LiteSpeed',
                                'nginx' => 'Nginx FastCGI',
                                'object_cache' => 'Object Cache',
                                'default' => 'Standard',
                            ];
                            echo esc_html($envLabels[$envName] ?? ucfirst($envName));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Object Cache', 'nowscrobbling'); ?></th>
                        <td>
                            <?php
                            if (wp_using_ext_object_cache()) {
                                $cacheType = $environment->getObjectCacheType();
                                printf(
                                    '<span class="ns-status ns-status-ok">%s</span> (%s)',
                                    esc_html__('Active', 'nowscrobbling'),
                                    esc_html(ucfirst($cacheType ?? 'unknown'))
                                );
                            } else {
                                printf(
                                    '<span class="ns-status ns-status-info">%s</span>',
                                    esc_html__('Not configured', 'nowscrobbling')
                                );
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e('Cache Statistics', 'nowscrobbling'); ?></h3>
            <table class="widefat ns-diagnostics-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cache Hits', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html($cacheDiagnostics['stats']['hits'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cache Misses', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html($cacheDiagnostics['stats']['misses'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Hit Rate', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html(($cacheDiagnostics['stats']['hit_rate'] ?? 0) . '%'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Fallback Uses', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html($cacheDiagnostics['stats']['fallbacks'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Errors', 'nowscrobbling'); ?></th>
                        <td><?php echo esc_html($cacheDiagnostics['stats']['errors'] ?? 0); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e('API Configuration Status', 'nowscrobbling'); ?></h3>
            <table class="widefat ns-diagnostics-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Last.fm', 'nowscrobbling'); ?></th>
                        <td>
                            <?php
                            $lastfmConfigured = !empty(get_option('ns_lastfm_api_key')) && !empty(get_option('ns_lastfm_user'));
                            if ($lastfmConfigured) {
                                printf(
                                    '<span class="ns-status ns-status-ok">%s</span>',
                                    esc_html__('Configured', 'nowscrobbling')
                                );
                            } else {
                                printf(
                                    '<span class="ns-status ns-status-warning">%s</span>',
                                    esc_html__('Not configured', 'nowscrobbling')
                                );
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Trakt.tv', 'nowscrobbling'); ?></th>
                        <td>
                            <?php
                            $traktConfigured = !empty(get_option('ns_trakt_client_id')) && !empty(get_option('ns_trakt_user'));
                            if ($traktConfigured) {
                                printf(
                                    '<span class="ns-status ns-status-ok">%s</span>',
                                    esc_html__('Configured', 'nowscrobbling')
                                );
                            } else {
                                printf(
                                    '<span class="ns-status ns-status-warning">%s</span>',
                                    esc_html__('Not configured', 'nowscrobbling')
                                );
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderSectionDescription(): void
    {
        printf(
            '<p class="description">%s</p>',
            esc_html__('Enable debug logging to troubleshoot issues.', 'nowscrobbling')
        );
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
