<?php
/**
 * Template Engine
 *
 * Handles template rendering with theme override support.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Templates;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TemplateEngine
 *
 * Provides template loading and rendering functionality.
 */
class TemplateEngine {

    /**
     * Template directory within the plugin.
     *
     * @var string
     */
    private const PLUGIN_TEMPLATE_DIR = 'templates';

    /**
     * Template directory within themes.
     *
     * @var string
     */
    private const THEME_TEMPLATE_DIR = 'nowscrobbling';

    /**
     * Render a template with given data.
     *
     * @param string $template Template path relative to templates directory.
     * @param array  $data     Data to pass to the template.
     * @return string Rendered HTML.
     */
    public static function render( string $template, array $data = [] ): string {
        $template_path = self::locate( $template );

        if ( ! $template_path ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                return sprintf(
                    '<!-- NowScrobbling: Template "%s" not found -->',
                    esc_html( $template )
                );
            }
            return '';
        }

        // Start output buffering.
        ob_start();

        // Make data available to template.
        // Templates access data via $data array or individual variables.
        // Using a closure to prevent variable pollution.
        $render = static function( string $_template_path, array $data ): void {
            // Common variables extracted for convenience.
            $items      = $data['items'] ?? [];
            $atts       = $data['atts'] ?? [];
            $provider   = $data['provider'] ?? '';
            $nowplaying = $data['nowplaying'] ?? false;
            $track      = $data['track'] ?? null;
            $item       = $data['item'] ?? null;

            include $_template_path;
        };

        $render( $template_path, $data );

        // Return the buffered content.
        return ob_get_clean();
    }

    /**
     * Locate a template file.
     *
     * Searches in the following order:
     * 1. Child theme: /nowscrobbling/{template}
     * 2. Parent theme: /nowscrobbling/{template}
     * 3. Plugin: /templates/{template}
     *
     * @param string $template Template path.
     * @return string|null Full path to template or null if not found.
     */
    public static function locate( string $template ): ?string {
        // Ensure .php extension.
        if ( ! str_ends_with( $template, '.php' ) ) {
            $template .= '.php';
        }

        // Sanitize template path.
        $template = self::sanitizePath( $template );

        // Define allowed base directories.
        $allowed_bases = [
            realpath( get_stylesheet_directory() . '/' . self::THEME_TEMPLATE_DIR ),
            realpath( get_template_directory() . '/' . self::THEME_TEMPLATE_DIR ),
            realpath( NOWSCROBBLING_PATH . self::PLUGIN_TEMPLATE_DIR ),
        ];

        // Check child theme.
        $child_path = self::validateTemplatePath(
            get_stylesheet_directory() . '/' . self::THEME_TEMPLATE_DIR . '/' . $template,
            $allowed_bases
        );
        if ( $child_path !== null ) {
            return $child_path;
        }

        // Check parent theme.
        $parent_path = self::validateTemplatePath(
            get_template_directory() . '/' . self::THEME_TEMPLATE_DIR . '/' . $template,
            $allowed_bases
        );
        if ( $parent_path !== null ) {
            return $parent_path;
        }

        // Check plugin directory.
        $plugin_path = self::validateTemplatePath(
            NOWSCROBBLING_PATH . self::PLUGIN_TEMPLATE_DIR . '/' . $template,
            $allowed_bases
        );
        if ( $plugin_path !== null ) {
            return $plugin_path;
        }

        return null;
    }

    /**
     * Validate that a template path is within allowed directories.
     *
     * @param string $path          Path to validate.
     * @param array  $allowed_bases Allowed base directories.
     * @return string|null Validated path or null if invalid.
     */
    private static function validateTemplatePath( string $path, array $allowed_bases ): ?string {
        if ( ! file_exists( $path ) ) {
            return null;
        }

        $real_path = realpath( $path );
        if ( $real_path === false ) {
            return null;
        }

        // Verify the resolved path is within an allowed directory.
        foreach ( $allowed_bases as $base ) {
            if ( $base !== false && str_starts_with( $real_path, $base . DIRECTORY_SEPARATOR ) ) {
                return $real_path;
            }
        }

        return null;
    }

    /**
     * Check if a template exists.
     *
     * @param string $template Template path.
     * @return bool
     */
    public static function exists( string $template ): bool {
        return self::locate( $template ) !== null;
    }

    /**
     * Get template from theme with plugin fallback.
     *
     * @param string $template Template path.
     * @return string|null
     */
    public static function getThemeTemplate( string $template ): ?string {
        if ( ! str_ends_with( $template, '.php' ) ) {
            $template .= '.php';
        }

        $template = self::sanitizePath( $template );

        // Only check theme directories.
        $child_path = get_stylesheet_directory() . '/' . self::THEME_TEMPLATE_DIR . '/' . $template;
        if ( file_exists( $child_path ) ) {
            return $child_path;
        }

        $parent_path = get_template_directory() . '/' . self::THEME_TEMPLATE_DIR . '/' . $template;
        if ( file_exists( $parent_path ) ) {
            return $parent_path;
        }

        return null;
    }

    /**
     * Check if template is being overridden by theme.
     *
     * @param string $template Template path.
     * @return bool
     */
    public static function isOverridden( string $template ): bool {
        return self::getThemeTemplate( $template ) !== null;
    }

    /**
     * Render a layout template.
     *
     * @param string $layout Layout name (inline, card, grid, list).
     * @param array  $data   Data to pass to the template.
     * @return string
     */
    public static function renderLayout( string $layout, array $data = [] ): string {
        $valid_layouts = [ 'inline', 'card', 'grid', 'list' ];

        if ( ! in_array( $layout, $valid_layouts, true ) ) {
            $layout = 'inline';
        }

        return self::render( "layouts/{$layout}", $data );
    }

    /**
     * Render a component template.
     *
     * @param string $component Component name.
     * @param array  $data      Data to pass to the template.
     * @return string
     */
    public static function renderComponent( string $component, array $data = [] ): string {
        return self::render( "components/{$component}", $data );
    }

    /**
     * Render a shortcode-specific template.
     *
     * @param string $shortcode Shortcode name (e.g., 'lastfm/now-playing').
     * @param array  $data      Data to pass to the template.
     * @return string
     */
    public static function renderShortcode( string $shortcode, array $data = [] ): string {
        return self::render( "shortcodes/{$shortcode}", $data );
    }

    /**
     * Sanitize a template path to prevent directory traversal.
     *
     * @param string $path Template path.
     * @return string Sanitized path.
     */
    private static function sanitizePath( string $path ): string {
        // Remove any directory traversal attempts.
        $path = str_replace( [ '..', './' ], '', $path );

        // Remove leading slashes.
        $path = ltrim( $path, '/' );

        // Only allow alphanumeric, dashes, underscores, and forward slashes.
        $path = preg_replace( '/[^a-zA-Z0-9\-_\/\.]/', '', $path );

        return $path;
    }

    /**
     * Get all available templates in a directory.
     *
     * @param string $directory Subdirectory within templates.
     * @return array List of template names.
     */
    public static function getAvailable( string $directory = '' ): array {
        $templates = [];
        $base_path = NOWSCROBBLING_PATH . self::PLUGIN_TEMPLATE_DIR;

        if ( $directory ) {
            $base_path .= '/' . self::sanitizePath( $directory );
        }

        if ( ! is_dir( $base_path ) ) {
            return $templates;
        }

        $files = glob( $base_path . '/*.php' );

        foreach ( $files as $file ) {
            $templates[] = basename( $file, '.php' );
        }

        return $templates;
    }

    /**
     * Include a partial template (for use within templates).
     *
     * @param string $partial Partial template path.
     * @param array  $data    Data to pass.
     * @return void
     */
    public static function partial( string $partial, array $data = [] ): void {
        echo self::render( "partials/{$partial}", $data );
    }

    /**
     * Escape and format a template variable.
     *
     * @param mixed  $value   Value to format.
     * @param string $context Context for escaping (html, attr, url, js).
     * @return string
     */
    public static function escape( $value, string $context = 'html' ): string {
        if ( $value === null ) {
            return '';
        }

        return match ( $context ) {
            'attr'  => esc_attr( (string) $value ),
            'url'   => esc_url( (string) $value ),
            'js'    => esc_js( (string) $value ),
            default => esc_html( (string) $value ),
        };
    }
}
