<?php

declare(strict_types=1);

namespace NowScrobbling;

use NowScrobbling\Core\Bootstrap;
use NowScrobbling\Cache\CacheManager;
use NowScrobbling\Api\LastFmClient;
use NowScrobbling\Api\TraktClient;
use NowScrobbling\Shortcodes\ShortcodeManager;
use NowScrobbling\Rest\RestController;
use NowScrobbling\Security\NonceManager;
use NowScrobbling\Admin\AdminController;
use NowScrobbling\Support\Logger;
use RuntimeException;

/**
 * Main plugin orchestrator
 *
 * Singleton that initializes the plugin, registers bindings,
 * and coordinates all services.
 *
 * @package NowScrobbling
 */
final class Plugin
{
    /**
     * Plugin version
     */
    public const VERSION = '2.0.0';

    /**
     * Minimum required PHP version
     */
    public const MIN_PHP_VERSION = '8.2.0';

    /**
     * Minimum required WordPress version
     */
    public const MIN_WP_VERSION = '6.0.0';

    /**
     * Singleton instance
     */
    private static ?Plugin $instance = null;

    /**
     * Whether the plugin has been booted
     */
    private bool $booted = false;

    /**
     * The DI container
     */
    private readonly Container $container;

    /**
     * Get the singleton plugin instance
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Reset the singleton instance (mainly for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
        Container::reset();
    }

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->container = Container::getInstance();
    }

    /**
     * Boot the plugin
     *
     * This is called on the 'plugins_loaded' action.
     * All hooks are registered here, not in constructors.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Check requirements before proceeding
        if (!$this->meetsRequirements()) {
            return;
        }

        // Initialize logger hooks
        Logger::register();

        // Register all service bindings
        $this->registerBindings();

        // Initialize the bootstrap (registers all hooks)
        $this->container->make(Bootstrap::class)->init();

        $this->booted = true;

        /**
         * Fires after the plugin has fully booted
         *
         * @param Plugin $plugin The plugin instance
         */
        do_action('nowscrobbling_booted', $this);
    }

    /**
     * Check if the plugin has been booted
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get the DI container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Check if system meets plugin requirements
     */
    private function meetsRequirements(): bool
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function (): void {
                $this->renderRequirementNotice(
                    sprintf(
                        /* translators: 1: Required PHP version, 2: Current PHP version */
                        __('NowScrobbling requires PHP %1$s or higher. You are running PHP %2$s.', 'nowscrobbling'),
                        self::MIN_PHP_VERSION,
                        PHP_VERSION
                    )
                );
            });
            return false;
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, self::MIN_WP_VERSION, '<')) {
            add_action('admin_notices', function () use ($wp_version): void {
                $this->renderRequirementNotice(
                    sprintf(
                        /* translators: 1: Required WP version, 2: Current WP version */
                        __('NowScrobbling requires WordPress %1$s or higher. You are running WordPress %2$s.', 'nowscrobbling'),
                        self::MIN_WP_VERSION,
                        $wp_version
                    )
                );
            });
            return false;
        }

        return true;
    }

    /**
     * Render an admin notice for unmet requirements
     */
    private function renderRequirementNotice(string $message): void
    {
        printf(
            '<div class="notice notice-error"><p><strong>%s</strong></p></div>',
            esc_html($message)
        );
    }

    /**
     * Register all service bindings in the container
     */
    private function registerBindings(): void
    {
        // Cache system (no dependencies)
        $this->container->singleton(
            CacheManager::class,
            fn(): CacheManager => new CacheManager()
        );

        // Security services
        $this->container->singleton(
            NonceManager::class,
            fn(): NonceManager => new NonceManager()
        );

        // API clients (depend on CacheManager)
        $this->container->singleton(
            LastFmClient::class,
            fn(Container $c): LastFmClient => new LastFmClient(
                $c->make(CacheManager::class)
            )
        );

        $this->container->singleton(
            TraktClient::class,
            fn(Container $c): TraktClient => new TraktClient(
                $c->make(CacheManager::class)
            )
        );

        // Shortcode system
        $this->container->singleton(
            ShortcodeManager::class,
            fn(Container $c): ShortcodeManager => new ShortcodeManager($c)
        );

        // REST API
        $this->container->singleton(
            RestController::class,
            fn(Container $c): RestController => new RestController($c)
        );

        // Admin interface
        $this->container->singleton(
            AdminController::class,
            fn(Container $c): AdminController => new AdminController($c)
        );

        // Core services
        $this->container->singleton(
            Bootstrap::class,
            fn(Container $c): Bootstrap => new Bootstrap($c)
        );

        // Register hook listeners for action-based initialization
        $this->registerHookListeners();

        /**
         * Fires after core bindings are registered
         *
         * Use this hook to register additional services in the container.
         *
         * @param Container $container The DI container
         */
        do_action('nowscrobbling_register_bindings', $this->container);
    }

    /**
     * Register hook listeners for deferred initialization
     *
     * These respond to the action hooks fired by Bootstrap.
     */
    private function registerHookListeners(): void
    {
        // Register shortcodes when init fires
        add_action('nowscrobbling_register_shortcodes', function (Container $c): void {
            $c->make(ShortcodeManager::class)->register();
        });

        // Register REST routes when rest_api_init fires
        add_action('nowscrobbling_register_rest_routes', function (Container $c): void {
            $c->make(RestController::class)->register();
        });

        // Register admin menu
        add_action('nowscrobbling_register_admin_menu', function (Container $c): void {
            $c->make(AdminController::class)->registerMenu();
        });

        // Register settings
        add_action('nowscrobbling_register_settings', function (Container $c): void {
            $c->make(AdminController::class)->registerSettings();
        });
    }

    /**
     * Get a service from the container
     *
     * @template T
     *
     * @param class-string<T> $abstract The service class name
     *
     * @return T
     */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    /**
     * Get the plugin file path
     */
    public function getFile(): string
    {
        return NOWSCROBBLING_FILE;
    }

    /**
     * Get the plugin directory path
     */
    public function getPath(string $path = ''): string
    {
        return NOWSCROBBLING_PATH . ltrim($path, '/');
    }

    /**
     * Get the plugin URL
     */
    public function getUrl(string $path = ''): string
    {
        return NOWSCROBBLING_URL . ltrim($path, '/');
    }

    /**
     * Get the plugin version
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebug(): bool
    {
        return (bool) get_option('ns_debug_enabled', false);
    }

    /**
     * Get all registered shortcode tags
     *
     * @return array<string>
     */
    public function getShortcodeTags(): array
    {
        return [
            'nowscr_lastfm_indicator',
            'nowscr_lastfm_history',
            'nowscr_lastfm_top_artists',
            'nowscr_lastfm_top_albums',
            'nowscr_lastfm_top_tracks',
            'nowscr_lastfm_lovedtracks',
            'nowscr_trakt_indicator',
            'nowscr_trakt_history',
            'nowscr_trakt_last_movie',
            'nowscr_trakt_last_show',
            'nowscr_trakt_last_episode',
        ];
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     *
     * @throws RuntimeException Always throws
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }
}
