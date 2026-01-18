<?php
/**
 * Main Plugin Class
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Core;

use NowScrobbling\Providers\ProviderRegistry;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Shortcodes\ShortcodeRegistry;
use NowScrobbling\Admin\AdminPage;
use NowScrobbling\Attribution\AttributionManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plugin
 *
 * The main plugin class that bootstraps everything.
 * Follows singleton pattern to ensure only one instance exists.
 */
class Plugin {

    /**
     * Plugin version.
     *
     * @var string
     */
    public const VERSION = '1.4.0';

    /**
     * Minimum WordPress version required.
     *
     * @var string
     */
    public const MIN_WP_VERSION = '5.0';

    /**
     * Minimum PHP version required.
     *
     * @var string
     */
    public const MIN_PHP_VERSION = '8.0';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Provider registry instance.
     *
     * @var ProviderRegistry|null
     */
    private ?ProviderRegistry $providers = null;

    /**
     * Cache manager instance.
     *
     * @var CacheManager|null
     */
    private ?CacheManager $cache = null;

    /**
     * Shortcode registry instance.
     *
     * @var ShortcodeRegistry|null
     */
    private ?ShortcodeRegistry $shortcodes = null;

    /**
     * Attribution manager instance.
     *
     * @var AttributionManager|null
     */
    private ?AttributionManager $attribution = null;

    /**
     * Whether the plugin has been initialized.
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton.
     */
    private function __construct() {
        // Empty - initialization happens in init().
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     *
     * @throws \Exception Always throws.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public function init(): void {
        if ( $this->initialized ) {
            return;
        }

        // Check requirements.
        if ( ! $this->checkRequirements() ) {
            return;
        }

        // Define constants.
        $this->defineConstants();

        // Initialize components.
        $this->initComponents();

        // Register hooks.
        $this->registerHooks();

        // Store version.
        update_option( 'nowscrobbling_version', self::VERSION );

        $this->initialized = true;

        /**
         * Fires after the plugin has been fully initialized.
         *
         * @since 1.4.0
         */
        do_action( 'nowscrobbling_initialized' );
    }

    /**
     * Check if system requirements are met.
     *
     * @return bool
     */
    private function checkRequirements(): bool {
        $errors = [];

        // Check PHP version.
        if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                __( 'NowScrobbling requires PHP %1$s or higher. You are running PHP %2$s.', 'nowscrobbling' ),
                self::MIN_PHP_VERSION,
                PHP_VERSION
            );
        }

        // Check WordPress version.
        global $wp_version;
        if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: Required WP version, 2: Current WP version */
                __( 'NowScrobbling requires WordPress %1$s or higher. You are running WordPress %2$s.', 'nowscrobbling' ),
                self::MIN_WP_VERSION,
                $wp_version
            );
        }

        if ( ! empty( $errors ) ) {
            add_action( 'admin_notices', function() use ( $errors ) {
                foreach ( $errors as $error ) {
                    printf(
                        '<div class="notice notice-error"><p>%s</p></div>',
                        esc_html( $error )
                    );
                }
            });
            return false;
        }

        return true;
    }

    /**
     * Define plugin constants.
     *
     * @return void
     */
    private function defineConstants(): void {
        if ( ! defined( 'NOWSCROBBLING_VERSION' ) ) {
            define( 'NOWSCROBBLING_VERSION', self::VERSION );
        }
    }

    /**
     * Initialize plugin components.
     *
     * @return void
     */
    private function initComponents(): void {
        // Initialize registries.
        $this->providers   = new ProviderRegistry();
        $this->cache       = new CacheManager();
        $this->shortcodes  = new ShortcodeRegistry();
        $this->attribution = new AttributionManager();

        // Register built-in providers.
        $this->registerBuiltinProviders();
    }

    /**
     * Register built-in providers.
     *
     * @return void
     */
    private function registerBuiltinProviders(): void {
        // Register Last.fm provider.
        $this->providers->register( new \NowScrobbling\Providers\LastFM\LastFMProvider() );

        // Register Trakt provider.
        $this->providers->register( new \NowScrobbling\Providers\Trakt\TraktProvider() );

        /**
         * Fires when providers are being registered.
         * Use this to register custom providers.
         *
         * @since 1.4.0
         * @param ProviderRegistry $registry The provider registry.
         */
        do_action( 'nowscrobbling_register_providers', $this->providers );
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function registerHooks(): void {
        // Load text domain.
        add_action( 'init', [ $this, 'loadTextDomain' ] );

        // Register shortcodes.
        add_action( 'init', [ $this->shortcodes, 'register' ] );

        // Enqueue assets.
        add_action( 'wp_enqueue_scripts', [ Assets::class, 'enqueuePublic' ] );
        add_action( 'admin_enqueue_scripts', [ Assets::class, 'enqueueAdmin' ] );

        // Admin menu.
        if ( is_admin() ) {
            add_action( 'admin_menu', [ AdminPage::class, 'register' ] );
            add_action( 'admin_init', [ AdminPage::class, 'registerSettings' ] );
        }

        // AJAX handlers.
        $this->registerAjaxHandlers();

        // Add custom cron interval (for future use).
        add_filter( 'cron_schedules', function( $schedules ) {
            $schedules['nowscrobbling_5min'] = [
                'interval' => 300,
                'display'  => __( 'Every 5 Minutes', 'nowscrobbling' ),
            ];
            return $schedules;
        });

        // Plugin lifecycle hooks.
        register_activation_hook( NOWSCROBBLING_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( NOWSCROBBLING_FILE, [ $this, 'deactivate' ] );
    }

    /**
     * Register AJAX handlers.
     *
     * @return void
     */
    private function registerAjaxHandlers(): void {
        \NowScrobbling\Ajax\AjaxHandler::register();
    }

    /**
     * Load plugin text domain for translations.
     *
     * @return void
     */
    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'nowscrobbling',
            false,
            dirname( NOWSCROBBLING_BASENAME ) . '/languages/'
        );
    }

    /**
     * Plugin activation hook.
     *
     * @return void
     */
    public function activate(): void {
        // Set default options.
        $defaults = [
            'ns_default_layout'  => 'inline',
            'ns_show_images'     => 0,
            'ns_debug_enabled'   => 0,
            'ns_cache_duration_lastfm' => 1,
            'ns_cache_duration_trakt'  => 5,
        ];

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }

        // Clear rewrite rules.
        flush_rewrite_rules();

        /**
         * Fires when the plugin is activated.
         *
         * @since 1.4.0
         */
        do_action( 'nowscrobbling_activated' );
    }

    /**
     * Plugin deactivation hook.
     *
     * @return void
     */
    public function deactivate(): void {
        // Clear rewrite rules.
        flush_rewrite_rules();

        /**
         * Fires when the plugin is deactivated.
         *
         * @since 1.4.0
         */
        do_action( 'nowscrobbling_deactivated' );
    }

    /**
     * Get the provider registry.
     *
     * @return ProviderRegistry
     */
    public function getProviders(): ProviderRegistry {
        return $this->providers;
    }

    /**
     * Get the cache manager.
     *
     * @return CacheManager
     */
    public function getCache(): CacheManager {
        return $this->cache;
    }

    /**
     * Get the shortcode registry.
     *
     * @return ShortcodeRegistry
     */
    public function getShortcodes(): ShortcodeRegistry {
        return $this->shortcodes;
    }

    /**
     * Get the attribution manager.
     *
     * @return AttributionManager
     */
    public function getAttribution(): AttributionManager {
        return $this->attribution;
    }

    /**
     * Check if the plugin has been initialized.
     *
     * @return bool
     */
    public function isInitialized(): bool {
        return $this->initialized;
    }
}
