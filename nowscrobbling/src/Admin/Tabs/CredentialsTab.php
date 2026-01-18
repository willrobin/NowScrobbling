<?php

declare(strict_types=1);

namespace NowScrobbling\Admin\Tabs;

use NowScrobbling\Security\Sanitizer;

/**
 * Credentials Tab
 *
 * Handles API credentials for Last.fm and Trakt.tv.
 *
 * @package NowScrobbling\Admin\Tabs
 */
final class CredentialsTab implements TabInterface
{
    private const SECTION_LASTFM = 'ns_section_lastfm';
    private const SECTION_TRAKT = 'ns_section_trakt';

    public function getId(): string
    {
        return 'credentials';
    }

    public function getTitle(): string
    {
        return __('Credentials', 'nowscrobbling');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-network';
    }

    public function getOrder(): int
    {
        return 10;
    }

    public function registerSettings(): void
    {
        // Last.fm Section
        add_settings_section(
            self::SECTION_LASTFM,
            __('Last.fm API', 'nowscrobbling'),
            fn() => $this->renderSectionDescription('lastfm'),
            'nowscrobbling'
        );

        // Last.fm API Key
        register_setting('nowscrobbling', 'ns_lastfm_api_key', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => Sanitizer::sanitize($v, 'text'),
            'default' => '',
        ]);

        add_settings_field(
            'ns_lastfm_api_key',
            __('API Key', 'nowscrobbling'),
            fn() => $this->renderTextField('ns_lastfm_api_key', 'password'),
            'nowscrobbling',
            self::SECTION_LASTFM
        );

        // Last.fm Username
        register_setting('nowscrobbling', 'ns_lastfm_user', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => Sanitizer::sanitize($v, 'text'),
            'default' => '',
        ]);

        add_settings_field(
            'ns_lastfm_user',
            __('Username', 'nowscrobbling'),
            fn() => $this->renderTextField('ns_lastfm_user', 'text', [
                'description' => __('Your Last.fm username.', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION_LASTFM
        );

        // Test Last.fm Connection Button
        add_settings_field(
            'ns_lastfm_test',
            __('Test Connection', 'nowscrobbling'),
            fn() => $this->renderTestButton('lastfm'),
            'nowscrobbling',
            self::SECTION_LASTFM
        );

        // Trakt.tv Section
        add_settings_section(
            self::SECTION_TRAKT,
            __('Trakt.tv API', 'nowscrobbling'),
            fn() => $this->renderSectionDescription('trakt'),
            'nowscrobbling'
        );

        // Trakt Client ID
        register_setting('nowscrobbling', 'ns_trakt_client_id', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => Sanitizer::sanitize($v, 'text'),
            'default' => '',
        ]);

        add_settings_field(
            'ns_trakt_client_id',
            __('Client ID', 'nowscrobbling'),
            fn() => $this->renderTextField('ns_trakt_client_id', 'password'),
            'nowscrobbling',
            self::SECTION_TRAKT
        );

        // Trakt Username
        register_setting('nowscrobbling', 'ns_trakt_user', [
            'type' => 'string',
            'sanitize_callback' => fn($v) => Sanitizer::sanitize($v, 'text'),
            'default' => '',
        ]);

        add_settings_field(
            'ns_trakt_user',
            __('Username', 'nowscrobbling'),
            fn() => $this->renderTextField('ns_trakt_user', 'text', [
                'description' => __('Your Trakt.tv username (slug).', 'nowscrobbling'),
            ]),
            'nowscrobbling',
            self::SECTION_TRAKT
        );

        // Test Trakt Connection Button
        add_settings_field(
            'ns_trakt_test',
            __('Test Connection', 'nowscrobbling'),
            fn() => $this->renderTestButton('trakt'),
            'nowscrobbling',
            self::SECTION_TRAKT
        );
    }

    public function render(): void
    {
        ?>
        <div class="ns-tab-content" id="ns-tab-credentials">
            <?php do_settings_sections('nowscrobbling'); ?>
        </div>
        <?php
    }

    /**
     * Render section description
     */
    private function renderSectionDescription(string $service): void
    {
        $descriptions = [
            'lastfm' => sprintf(
                /* translators: %s: URL to Last.fm API */
                __('Get your API key from %s.', 'nowscrobbling'),
                '<a href="https://www.last.fm/api/account/create" target="_blank" rel="noopener">Last.fm API</a>'
            ),
            'trakt' => sprintf(
                /* translators: %s: URL to Trakt API */
                __('Get your Client ID from %s.', 'nowscrobbling'),
                '<a href="https://trakt.tv/oauth/applications" target="_blank" rel="noopener">Trakt.tv API</a>'
            ),
        ];

        printf('<p class="description">%s</p>', wp_kses_post($descriptions[$service] ?? ''));
    }

    /**
     * Render a text field
     *
     * @param string               $name    Option name
     * @param string               $type    Input type (text, password)
     * @param array<string, mixed> $options Additional options
     */
    private function renderTextField(string $name, string $type = 'text', array $options = []): void
    {
        $value = get_option($name, '');
        $id = esc_attr($name);

        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr($type),
            $id,
            $id,
            esc_attr($value)
        );

        if (!empty($options['description'])) {
            printf('<p class="description">%s</p>', esc_html($options['description']));
        }
    }

    /**
     * Render a test connection button
     */
    private function renderTestButton(string $service): void
    {
        printf(
            '<button type="button" class="button ns-test-connection" data-service="%s">%s</button>
            <span class="ns-test-result" data-service="%s"></span>',
            esc_attr($service),
            esc_html__('Test Connection', 'nowscrobbling'),
            esc_attr($service)
        );
    }
}
