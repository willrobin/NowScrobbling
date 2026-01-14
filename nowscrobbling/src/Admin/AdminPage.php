<?php
/**
 * Admin Page
 *
 * Main admin settings page for the plugin.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Admin;

use NowScrobbling\Core\Plugin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminPage
 *
 * Handles the admin dashboard and settings.
 */
class AdminPage {

    /**
     * Menu slug for the admin page.
     *
     * @var string
     */
    public const MENU_SLUG = 'nowscrobbling';

    /**
     * Option group name.
     *
     * @var string
     */
    public const OPTION_GROUP = 'nowscrobbling_options';

    /**
     * Register the admin menu.
     *
     * @return void
     */
    public static function register(): void {
        add_menu_page(
            __( 'NowScrobbling', 'nowscrobbling' ),
            __( 'NowScrobbling', 'nowscrobbling' ),
            'manage_options',
            self::MENU_SLUG,
            [ self::class, 'renderPage' ],
            'dashicons-format-audio',
            80
        );
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public static function registerSettings(): void {
        // Last.fm Settings.
        self::registerLastFmSettings();

        // Trakt Settings.
        self::registerTraktSettings();

        // Display Settings.
        self::registerDisplaySettings();

        // Cache Settings.
        self::registerCacheSettings();

        // Advanced Settings.
        self::registerAdvancedSettings();
    }

    /**
     * Register Last.fm settings.
     *
     * @return void
     */
    private static function registerLastFmSettings(): void {
        // API Key.
        register_setting( self::OPTION_GROUP, 'lastfm_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Username.
        register_setting( self::OPTION_GROUP, 'lastfm_user', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Cache duration.
        register_setting( self::OPTION_GROUP, 'ns_lastfm_cache_duration', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ]);
    }

    /**
     * Register Trakt settings.
     *
     * @return void
     */
    private static function registerTraktSettings(): void {
        // Client ID.
        register_setting( self::OPTION_GROUP, 'trakt_client_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Username.
        register_setting( self::OPTION_GROUP, 'trakt_user', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Cache duration.
        register_setting( self::OPTION_GROUP, 'ns_trakt_cache_duration', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 5,
        ]);

        // TMDb API Key (for posters).
        register_setting( self::OPTION_GROUP, 'ns_tmdb_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
    }

    /**
     * Register display settings.
     *
     * @return void
     */
    private static function registerDisplaySettings(): void {
        register_setting( self::OPTION_GROUP, 'ns_default_layout', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'inline',
        ]);

        register_setting( self::OPTION_GROUP, 'ns_show_images', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
    }

    /**
     * Register cache settings.
     *
     * @return void
     */
    private static function registerCacheSettings(): void {
        register_setting( self::OPTION_GROUP, 'ns_nowplaying_interval', [
            'type'              => 'integer',
            'sanitize_callback' => fn( $v ) => max( 5, absint( $v ) ),
            'default'           => 20,
        ]);

        register_setting( self::OPTION_GROUP, 'ns_max_interval', [
            'type'              => 'integer',
            'sanitize_callback' => fn( $v ) => max( 30, absint( $v ) ),
            'default'           => 300,
        ]);

        register_setting( self::OPTION_GROUP, 'ns_backoff_multiplier', [
            'type'              => 'number',
            'sanitize_callback' => fn( $v ) => max( 1.0, (float) $v ),
            'default'           => 2.0,
        ]);
    }

    /**
     * Register advanced settings.
     *
     * @return void
     */
    private static function registerAdvancedSettings(): void {
        register_setting( self::OPTION_GROUP, 'ns_debug_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
    }

    /**
     * Render the main admin page.
     *
     * @return void
     */
    public static function renderPage(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $current_tab = self::getCurrentTab();

        ?>
        <div class="wrap ns-admin-wrap">
            <div class="ns-admin-header">
                <h1><?php esc_html_e( 'NowScrobbling', 'nowscrobbling' ); ?></h1>
                <span class="ns-admin-version">v<?php echo esc_html( NOWSCROBBLING_VERSION ); ?></span>
            </div>

            <?php settings_errors( 'nowscrobbling_messages' ); ?>

            <nav class="nav-tab-wrapper ns-admin-tabs">
                <?php foreach ( self::getTabs() as $tab_id => $tab_label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $tab_id ) ); ?>"
                       class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="ns-tab-content">
                <?php
                match ( $current_tab ) {
                    'lastfm'     => self::renderLastFmTab(),
                    'trakt'      => self::renderTraktTab(),
                    'display'    => self::renderDisplayTab(),
                    'cache'      => self::renderCacheTab(),
                    'shortcodes' => self::renderShortcodesTab(),
                    'debug'      => self::renderDebugTab(),
                    default      => self::renderOverviewTab(),
                };
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get available tabs.
     *
     * @return array
     */
    private static function getTabs(): array {
        return [
            'overview'   => __( 'Overview', 'nowscrobbling' ),
            'lastfm'     => __( 'Last.fm', 'nowscrobbling' ),
            'trakt'      => __( 'Trakt', 'nowscrobbling' ),
            'display'    => __( 'Display', 'nowscrobbling' ),
            'cache'      => __( 'Cache', 'nowscrobbling' ),
            'shortcodes' => __( 'Shortcodes', 'nowscrobbling' ),
            'debug'      => __( 'Debug', 'nowscrobbling' ),
        ];
    }

    /**
     * Get current tab.
     *
     * @return string
     */
    private static function getCurrentTab(): string {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
        return array_key_exists( $tab, self::getTabs() ) ? $tab : 'overview';
    }

    /**
     * Render Overview tab.
     *
     * @return void
     */
    private static function renderOverviewTab(): void {
        $plugin    = Plugin::getInstance();
        $providers = $plugin->getProviders();
        ?>
        <div class="ns-cards">
            <div class="ns-card">
                <h2><?php esc_html_e( 'Provider Status', 'nowscrobbling' ); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Provider', 'nowscrobbling' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nowscrobbling' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nowscrobbling' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $providers->getAll() as $provider ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $provider->getName() ); ?></strong></td>
                                <td>
                                    <?php if ( $provider->isConfigured() ) : ?>
                                        <span class="ns-status ns-status--success">
                                            <?php esc_html_e( 'Configured', 'nowscrobbling' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="ns-status ns-status--warning">
                                            <?php esc_html_e( 'Not configured', 'nowscrobbling' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $provider->isConfigured() ) : ?>
                                        <button type="button" class="button ns-test-connection"
                                                data-provider="<?php echo esc_attr( $provider->getId() ); ?>">
                                            <?php esc_html_e( 'Test Connection', 'nowscrobbling' ); ?>
                                        </button>
                                        <span class="ns-connection-status"></span>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $provider->getId() ) ); ?>"
                                           class="button">
                                            <?php esc_html_e( 'Configure', 'nowscrobbling' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="ns-card">
                <h2><?php esc_html_e( 'Quick Stats', 'nowscrobbling' ); ?></h2>
                <div class="ns-stats">
                    <div class="ns-stat">
                        <span class="ns-stat__value"><?php echo esc_html( $plugin->getShortcodes()->count() ); ?></span>
                        <span class="ns-stat__label"><?php esc_html_e( 'Shortcodes', 'nowscrobbling' ); ?></span>
                    </div>
                    <div class="ns-stat">
                        <span class="ns-stat__value"><?php echo esc_html( $providers->countConfigured() ); ?>/<?php echo esc_html( $providers->count() ); ?></span>
                        <span class="ns-stat__label"><?php esc_html_e( 'Providers', 'nowscrobbling' ); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Last.fm tab.
     *
     * @return void
     */
    private static function renderLastFmTab(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::OPTION_GROUP ); ?>

            <div class="ns-card">
                <h2><?php esc_html_e( 'Last.fm API Settings', 'nowscrobbling' ); ?></h2>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: Link to Last.fm API page */
                        esc_html__( 'Get your API key from %s', 'nowscrobbling' ),
                        '<a href="https://www.last.fm/api/account/create" target="_blank" rel="noopener">last.fm/api</a>'
                    );
                    ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="lastfm_api_key"><?php esc_html_e( 'API Key', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="lastfm_api_key" name="lastfm_api_key"
                                   value="<?php echo esc_attr( get_option( 'lastfm_api_key', '' ) ); ?>"
                                   class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lastfm_user"><?php esc_html_e( 'Username', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="lastfm_user" name="lastfm_user"
                                   value="<?php echo esc_attr( get_option( 'lastfm_user', '' ) ); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ns_lastfm_cache_duration"><?php esc_html_e( 'Cache Duration', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ns_lastfm_cache_duration" name="ns_lastfm_cache_duration"
                                   value="<?php echo esc_attr( get_option( 'ns_lastfm_cache_duration', 1 ) ); ?>"
                                   min="1" max="60" class="small-text">
                            <span class="description"><?php esc_html_e( 'minutes', 'nowscrobbling' ); ?></span>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Last.fm Settings', 'nowscrobbling' ) ); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render Trakt tab.
     *
     * @return void
     */
    private static function renderTraktTab(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::OPTION_GROUP ); ?>

            <div class="ns-card">
                <h2><?php esc_html_e( 'Trakt API Settings', 'nowscrobbling' ); ?></h2>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: Link to Trakt API page */
                        esc_html__( 'Create an app at %s to get your Client ID.', 'nowscrobbling' ),
                        '<a href="https://trakt.tv/oauth/applications" target="_blank" rel="noopener">trakt.tv/oauth/applications</a>'
                    );
                    ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="trakt_client_id"><?php esc_html_e( 'Client ID', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="trakt_client_id" name="trakt_client_id"
                                   value="<?php echo esc_attr( get_option( 'trakt_client_id', '' ) ); ?>"
                                   class="regular-text code">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="trakt_user"><?php esc_html_e( 'Username', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="trakt_user" name="trakt_user"
                                   value="<?php echo esc_attr( get_option( 'trakt_user', '' ) ); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ns_trakt_cache_duration"><?php esc_html_e( 'Cache Duration', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ns_trakt_cache_duration" name="ns_trakt_cache_duration"
                                   value="<?php echo esc_attr( get_option( 'ns_trakt_cache_duration', 5 ) ); ?>"
                                   min="1" max="60" class="small-text">
                            <span class="description"><?php esc_html_e( 'minutes', 'nowscrobbling' ); ?></span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ns-card">
                <h2><?php esc_html_e( 'TMDb Settings', 'nowscrobbling' ); ?></h2>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: Link to TMDb */
                        esc_html__( 'Optional: Get an API key from %s for movie/TV show posters.', 'nowscrobbling' ),
                        '<a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener">themoviedb.org</a>'
                    );
                    ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ns_tmdb_api_key"><?php esc_html_e( 'TMDb API Key', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ns_tmdb_api_key" name="ns_tmdb_api_key"
                                   value="<?php echo esc_attr( get_option( 'ns_tmdb_api_key', '' ) ); ?>"
                                   class="regular-text code">
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Trakt Settings', 'nowscrobbling' ) ); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render Display tab.
     *
     * @return void
     */
    private static function renderDisplayTab(): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::OPTION_GROUP ); ?>

            <div class="ns-card">
                <h2><?php esc_html_e( 'Display Settings', 'nowscrobbling' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ns_default_layout"><?php esc_html_e( 'Default Layout', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <select id="ns_default_layout" name="ns_default_layout">
                                <?php
                                $current = get_option( 'ns_default_layout', 'inline' );
                                $layouts = [
                                    'inline' => __( 'Inline', 'nowscrobbling' ),
                                    'card'   => __( 'Card', 'nowscrobbling' ),
                                    'grid'   => __( 'Grid', 'nowscrobbling' ),
                                    'list'   => __( 'List', 'nowscrobbling' ),
                                ];
                                foreach ( $layouts as $value => $label ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Default layout for shortcodes when not specified.', 'nowscrobbling' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Artwork', 'nowscrobbling' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ns_show_images" value="1"
                                       <?php checked( get_option( 'ns_show_images', false ) ); ?>>
                                <?php esc_html_e( 'Show album covers and posters by default', 'nowscrobbling' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Display Settings', 'nowscrobbling' ) ); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render Cache tab.
     *
     * @return void
     */
    private static function renderCacheTab(): void {
        ?>
        <div class="ns-card">
            <h2><?php esc_html_e( 'Cache Management', 'nowscrobbling' ); ?></h2>

            <p>
                <button type="button" class="button button-secondary ns-clear-cache">
                    <?php esc_html_e( 'Clear All Caches', 'nowscrobbling' ); ?>
                </button>
            </p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields( self::OPTION_GROUP ); ?>

            <div class="ns-card">
                <h2><?php esc_html_e( 'Polling Settings', 'nowscrobbling' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Configure how often the frontend checks for updates.', 'nowscrobbling' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ns_nowplaying_interval"><?php esc_html_e( 'Now Playing Interval', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ns_nowplaying_interval" name="ns_nowplaying_interval"
                                   value="<?php echo esc_attr( get_option( 'ns_nowplaying_interval', 20 ) ); ?>"
                                   min="5" max="120" class="small-text">
                            <span class="description"><?php esc_html_e( 'seconds (minimum: 5)', 'nowscrobbling' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ns_max_interval"><?php esc_html_e( 'Maximum Interval', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ns_max_interval" name="ns_max_interval"
                                   value="<?php echo esc_attr( get_option( 'ns_max_interval', 300 ) ); ?>"
                                   min="30" max="600" class="small-text">
                            <span class="description"><?php esc_html_e( 'seconds (after backoff)', 'nowscrobbling' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ns_backoff_multiplier"><?php esc_html_e( 'Backoff Multiplier', 'nowscrobbling' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ns_backoff_multiplier" name="ns_backoff_multiplier"
                                   value="<?php echo esc_attr( get_option( 'ns_backoff_multiplier', 2.0 ) ); ?>"
                                   min="1" max="5" step="0.1" class="small-text">
                            <p class="description"><?php esc_html_e( 'Multiplier applied when no changes detected.', 'nowscrobbling' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Cache Settings', 'nowscrobbling' ) ); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render Shortcodes tab.
     *
     * @return void
     */
    private static function renderShortcodesTab(): void {
        $shortcodes = Plugin::getInstance()->getShortcodes()->getAll();
        ?>
        <div class="ns-card">
            <h2><?php esc_html_e( 'Available Shortcodes', 'nowscrobbling' ); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Shortcode', 'nowscrobbling' ); ?></th>
                        <th><?php esc_html_e( 'Provider', 'nowscrobbling' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'nowscrobbling' ); ?></th>
                        <th><?php esc_html_e( 'Copy', 'nowscrobbling' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $shortcodes as $tag => $shortcode ) : ?>
                        <tr>
                            <td><code>[<?php echo esc_html( $tag ); ?>]</code></td>
                            <td><?php echo esc_html( ucfirst( $shortcode->getProvider() ) ); ?></td>
                            <td><?php echo esc_html( $shortcode->getDescription() ); ?></td>
                            <td>
                                <button type="button" class="button button-small ns-copy"
                                        data-copy="[<?php echo esc_attr( $tag ); ?>]">
                                    <?php esc_html_e( 'Copy', 'nowscrobbling' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="ns-card">
            <h2><?php esc_html_e( 'Common Parameters', 'nowscrobbling' ); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Parameter', 'nowscrobbling' ); ?></th>
                        <th><?php esc_html_e( 'Default', 'nowscrobbling' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'nowscrobbling' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>layout</code></td>
                        <td><code>inline</code></td>
                        <td><?php esc_html_e( 'Display layout: inline, card, grid, list', 'nowscrobbling' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_image</code></td>
                        <td><code>false</code></td>
                        <td><?php esc_html_e( 'Show artwork/poster images', 'nowscrobbling' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>limit</code></td>
                        <td><code>5</code></td>
                        <td><?php esc_html_e( 'Number of items to display', 'nowscrobbling' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>max_length</code></td>
                        <td><code>0</code></td>
                        <td><?php esc_html_e( 'Truncate text (0 = no truncation)', 'nowscrobbling' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>class</code></td>
                        <td><code></code></td>
                        <td><?php esc_html_e( 'Additional CSS class', 'nowscrobbling' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>period</code></td>
                        <td><code>7day</code></td>
                        <td><?php esc_html_e( 'Time period (Last.fm): 7day, 1month, 3month, 6month, 12month, overall', 'nowscrobbling' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="ns-card">
            <h2><?php esc_html_e( 'Example', 'nowscrobbling' ); ?></h2>
            <pre><code>[nowscr_lastfm_history layout="card" show_image="true" limit="5"]</code></pre>
        </div>
        <?php
    }

    /**
     * Render Debug tab.
     *
     * @return void
     */
    private static function renderDebugTab(): void {
        $debug_enabled = get_option( 'ns_debug_enabled', false );
        $log           = get_option( 'nowscrobbling_log', [] );

        if ( ! is_array( $log ) ) {
            $log = [];
        }

        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::OPTION_GROUP ); ?>

            <div class="ns-card">
                <h2><?php esc_html_e( 'Debug Settings', 'nowscrobbling' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Debug Mode', 'nowscrobbling' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ns_debug_enabled" value="1"
                                       <?php checked( $debug_enabled ); ?>>
                                <?php esc_html_e( 'Enable debug logging', 'nowscrobbling' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Logs API requests and errors for troubleshooting.', 'nowscrobbling' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Debug Settings', 'nowscrobbling' ) ); ?>
            </div>
        </form>

        <div class="ns-card">
            <h2><?php esc_html_e( 'Debug Log', 'nowscrobbling' ); ?></h2>

            <?php if ( empty( $log ) ) : ?>
                <p><?php esc_html_e( 'No log entries yet.', 'nowscrobbling' ); ?></p>
            <?php else : ?>
                <div class="ns-log-controls">
                    <button type="button" class="button ns-clear-log">
                        <?php esc_html_e( 'Clear Log', 'nowscrobbling' ); ?>
                    </button>
                </div>

                <div class="ns-log-viewer">
                    <pre><?php echo esc_html( implode( "\n", array_reverse( $log ) ) ); ?></pre>
                </div>
            <?php endif; ?>
        </div>

        <div class="ns-card">
            <h2><?php esc_html_e( 'System Info', 'nowscrobbling' ); ?></h2>

            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e( 'Plugin Version', 'nowscrobbling' ); ?></strong></td>
                        <td><?php echo esc_html( NOWSCROBBLING_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'WordPress Version', 'nowscrobbling' ); ?></strong></td>
                        <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'PHP Version', 'nowscrobbling' ); ?></strong></td>
                        <td><?php echo esc_html( PHP_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Timezone', 'nowscrobbling' ); ?></strong></td>
                        <td><?php echo esc_html( wp_timezone_string() ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
