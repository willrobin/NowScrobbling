<?php
/**
 * PSR-4 Autoloader for NowScrobbling
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Autoloader
 *
 * Handles PSR-4 autoloading for the NowScrobbling namespace.
 */
class Autoloader {

    /**
     * Namespace prefix for this autoloader.
     *
     * @var string
     */
    private const NAMESPACE_PREFIX = 'NowScrobbling\\';

    /**
     * Base directory for the namespace.
     *
     * @var string
     */
    private string $base_dir;

    /**
     * Whether the autoloader has been registered.
     *
     * @var bool
     */
    private static bool $registered = false;

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

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
        $this->base_dir = dirname( __DIR__ ) . '/';
    }

    /**
     * Register the autoloader with SPL.
     *
     * @return bool True if registered successfully.
     */
    public function register(): bool {
        if ( self::$registered ) {
            return true;
        }

        self::$registered = spl_autoload_register( [ $this, 'loadClass' ] );
        return self::$registered;
    }

    /**
     * Unregister the autoloader.
     *
     * @return bool True if unregistered successfully.
     */
    public function unregister(): bool {
        if ( ! self::$registered ) {
            return true;
        }

        $result = spl_autoload_unregister( [ $this, 'loadClass' ] );
        if ( $result ) {
            self::$registered = false;
        }
        return $result;
    }

    /**
     * Load a class file based on PSR-4 naming conventions.
     *
     * @param string $class_name The fully-qualified class name.
     * @return void
     */
    public function loadClass( string $class_name ): void {
        // Check if this class belongs to our namespace.
        $prefix_length = strlen( self::NAMESPACE_PREFIX );
        if ( strncmp( self::NAMESPACE_PREFIX, $class_name, $prefix_length ) !== 0 ) {
            return;
        }

        // Get the relative class name.
        $relative_class = substr( $class_name, $prefix_length );

        // Convert namespace separators to directory separators.
        $file = $this->base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        // If the file exists, require it.
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    /**
     * Check if the autoloader is registered.
     *
     * @return bool
     */
    public static function isRegistered(): bool {
        return self::$registered;
    }

    /**
     * Get the base directory.
     *
     * @return string
     */
    public function getBaseDir(): string {
        return $this->base_dir;
    }
}
