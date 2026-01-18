<?php
/**
 * Abstract Shortcode
 *
 * Base class for all shortcodes.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Shortcodes;

use NowScrobbling\Core\Assets;
use NowScrobbling\Templates\TemplateEngine;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract Class AbstractShortcode
 *
 * Provides common functionality for all shortcodes.
 */
abstract class AbstractShortcode {

    /**
     * Shortcode tag (e.g., 'nowscr_lastfm_history').
     *
     * @var string
     */
    protected string $tag;

    /**
     * Provider ID (e.g., 'lastfm', 'trakt').
     *
     * @var string
     */
    protected string $provider;

    /**
     * Human-readable label.
     *
     * @var string
     */
    protected string $label;

    /**
     * Description for admin UI.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Default attributes shared by all shortcodes.
     *
     * @var array<string, mixed>
     */
    protected array $baseDefaults = [
        'layout'     => 'inline',
        'show_image' => false,
        'image_size' => 'medium',
        'limit'      => 5,
        'max_length' => 0,
        'class'      => '',
        'id'         => '',
    ];

    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render( array $atts = [] ): string {
        // Parse attributes.
        $atts = $this->parseAttributes( $atts );

        // Enqueue layout-specific CSS.
        Assets::enqueueLayout( $atts['layout'] );

        // Fetch data.
        $data = $this->fetchData( $atts );

        // Handle no data case.
        if ( $data === null ) {
            return $this->renderEmpty( $atts );
        }

        // Render template.
        $html = $this->renderTemplate( $data, $atts );

        // Wrap output.
        return $this->wrapOutput( $html, $data, $atts );
    }

    /**
     * Parse and validate shortcode attributes.
     *
     * @param array $atts Raw attributes.
     * @return array Parsed attributes.
     */
    protected function parseAttributes( array $atts ): array {
        $defaults = array_merge(
            $this->baseDefaults,
            $this->getDefaults()
        );

        $atts = shortcode_atts( $defaults, $atts, $this->tag );

        // Validate layout.
        $valid_layouts = [ 'inline', 'card', 'grid', 'list' ];
        if ( ! in_array( $atts['layout'], $valid_layouts, true ) ) {
            $atts['layout'] = 'inline';
        }

        // Validate booleans.
        $atts['show_image'] = filter_var( $atts['show_image'], FILTER_VALIDATE_BOOLEAN );

        // Validate numeric values.
        $atts['limit']      = max( 1, min( 50, (int) $atts['limit'] ) );
        $atts['max_length'] = max( 0, (int) $atts['max_length'] );

        // Sanitize strings.
        $atts['class'] = sanitize_html_class( $atts['class'] );
        $atts['id']    = sanitize_html_class( $atts['id'] );

        /**
         * Filter shortcode attributes.
         *
         * @since 1.4.0
         * @param array  $atts     Parsed attributes.
         * @param string $tag      Shortcode tag.
         * @param array  $defaults Default values.
         */
        return apply_filters( 'nowscrobbling_shortcode_atts', $atts, $this->tag, $defaults );
    }

    /**
     * Wrap the output with standard container.
     *
     * @param string $html Content HTML.
     * @param mixed  $data Fetched data.
     * @param array  $atts Attributes.
     * @return string
     */
    protected function wrapOutput( string $html, $data, array $atts ): string {
        $hash       = $this->computeHash( $data );
        $nowPlaying = $this->isNowPlaying( $data );

        $classes = [
            'nowscrobbling',
            'nowscrobbling--' . $atts['layout'],
            'nowscrobbling--' . $this->provider,
        ];

        if ( $nowPlaying ) {
            $classes[] = 'nowscrobbling--now-playing';
        }

        if ( $atts['class'] ) {
            $classes[] = $atts['class'];
        }

        $wrapper_atts = [
            'class'                       => implode( ' ', $classes ),
            'data-nowscrobbling-shortcode' => $this->tag,
            'data-ns-hash'                => $hash,
            'data-ns-attrs'               => wp_json_encode( $atts ),
        ];

        if ( $nowPlaying ) {
            $wrapper_atts['data-ns-nowplaying'] = '1';
        }

        if ( $atts['id'] ) {
            $wrapper_atts['id'] = $atts['id'];
        }

        $attr_string = '';
        foreach ( $wrapper_atts as $key => $value ) {
            $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
        }

        return sprintf(
            '<div%s>%s</div>',
            $attr_string,
            $html
        );
    }

    /**
     * Render empty state.
     *
     * @param array $atts Attributes.
     * @return string
     */
    protected function renderEmpty( array $atts ): string {
        $message = $this->getEmptyMessage();

        return sprintf(
            '<div class="nowscrobbling nowscrobbling--empty nowscrobbling--%s"><em>%s</em></div>',
            esc_attr( $this->provider ),
            esc_html( $message )
        );
    }

    /**
     * Render using template engine.
     *
     * @param mixed $data Fetched data.
     * @param array $atts Attributes.
     * @return string
     */
    protected function renderTemplate( $data, array $atts ): string {
        $template = $this->getTemplateName();

        return TemplateEngine::render( $template, [
            'data'      => $data,
            'atts'      => $atts,
            'shortcode' => $this,
        ]);
    }

    /**
     * Compute hash for change detection.
     *
     * @param mixed $data Data to hash.
     * @return string
     */
    protected function computeHash( $data ): string {
        return md5( wp_json_encode( $data ) );
    }

    /**
     * Get the shortcode tag.
     *
     * @return string
     */
    public function getTag(): string {
        return $this->tag;
    }

    /**
     * Get the provider ID.
     *
     * @return string
     */
    public function getProvider(): string {
        return $this->provider;
    }

    /**
     * Get the human-readable label.
     *
     * @return string
     */
    public function getLabel(): string {
        return $this->label;
    }

    /**
     * Get the description.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Get attribute definitions for the admin UI.
     *
     * @return array
     */
    public function getAttributeDefinitions(): array {
        $base = [
            'layout' => [
                'type'    => 'select',
                'label'   => __( 'Layout', 'nowscrobbling' ),
                'options' => [
                    'inline' => __( 'Inline', 'nowscrobbling' ),
                    'card'   => __( 'Card', 'nowscrobbling' ),
                    'grid'   => __( 'Grid', 'nowscrobbling' ),
                    'list'   => __( 'List', 'nowscrobbling' ),
                ],
                'default' => 'inline',
            ],
            'show_image' => [
                'type'    => 'checkbox',
                'label'   => __( 'Show Artwork', 'nowscrobbling' ),
                'default' => false,
            ],
            'limit' => [
                'type'    => 'number',
                'label'   => __( 'Limit', 'nowscrobbling' ),
                'min'     => 1,
                'max'     => 50,
                'default' => 5,
            ],
        ];

        return array_merge( $base, $this->getCustomAttributeDefinitions() );
    }

    // Abstract methods to be implemented by child classes.

    /**
     * Get shortcode-specific default attributes.
     *
     * @return array
     */
    abstract protected function getDefaults(): array;

    /**
     * Fetch data for this shortcode.
     *
     * @param array $atts Parsed attributes.
     * @return mixed
     */
    abstract protected function fetchData( array $atts );

    /**
     * Get the template name for this shortcode.
     *
     * @return string Template path relative to templates directory.
     */
    abstract protected function getTemplateName(): string;

    /**
     * Check if data represents "now playing" state.
     *
     * @param mixed $data Fetched data.
     * @return bool
     */
    abstract protected function isNowPlaying( $data ): bool;

    /**
     * Get the empty state message.
     *
     * @return string
     */
    abstract protected function getEmptyMessage(): string;

    /**
     * Get custom attribute definitions for this shortcode.
     *
     * @return array
     */
    protected function getCustomAttributeDefinitions(): array {
        return [];
    }
}
