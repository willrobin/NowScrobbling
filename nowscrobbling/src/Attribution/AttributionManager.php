<?php
/**
 * Attribution Manager
 *
 * Manages attribution for all active providers.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Attribution;

use NowScrobbling\Core\Plugin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AttributionManager
 *
 * Handles rendering of attribution notices for legal compliance.
 */
class AttributionManager {

    /**
     * Collected attributions from active providers.
     *
     * @var array<string, Attribution>
     */
    private array $attributions = [];

    /**
     * Get attributions for all configured providers.
     *
     * @return array<string, Attribution>
     */
    public function getAll(): array {
        if ( empty( $this->attributions ) ) {
            $this->collectAttributions();
        }

        return $this->attributions;
    }

    /**
     * Collect attributions from all configured providers.
     *
     * @return void
     */
    private function collectAttributions(): void {
        $plugin = Plugin::getInstance();

        foreach ( $plugin->getProviders()->getConfigured() as $id => $provider ) {
            $this->attributions[ $id ] = $provider->getAttribution();
        }
    }

    /**
     * Get attributions for specific providers.
     *
     * @param array<string> $provider_ids Provider IDs.
     * @return array<string, Attribution>
     */
    public function getForProviders( array $provider_ids ): array {
        $all = $this->getAll();

        return array_filter(
            $all,
            fn( $key ) => in_array( $key, $provider_ids, true ),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Render the attribution shortcode output.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function renderShortcode( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'show_logos'      => true,
                'show_disclaimer' => true,
                'class'           => '',
            ],
            $atts,
            'nowscr_attribution'
        );

        $attributions = $this->getAll();

        if ( empty( $attributions ) ) {
            return '';
        }

        $show_logos      = filter_var( $atts['show_logos'], FILTER_VALIDATE_BOOLEAN );
        $show_disclaimer = filter_var( $atts['show_disclaimer'], FILTER_VALIDATE_BOOLEAN );
        $extra_class     = sanitize_html_class( $atts['class'] );

        $html = sprintf(
            '<div class="ns-attributions%s">',
            $extra_class ? ' ' . $extra_class : ''
        );

        // Attribution text.
        $html .= '<p class="ns-attributions__text">';
        $parts = [];
        foreach ( $attributions as $attribution ) {
            $parts[] = $attribution->render( $show_logos );
        }
        $html .= implode( ' &middot; ', $parts );
        $html .= '</p>';

        // Disclaimers.
        if ( $show_disclaimer ) {
            $disclaimers = array_filter(
                array_map(
                    fn( Attribution $a ) => $a->renderDisclaimer(),
                    $attributions
                )
            );

            if ( ! empty( $disclaimers ) ) {
                $html .= '<p class="ns-attributions__disclaimers">';
                $html .= implode( ' ', $disclaimers );
                $html .= '</p>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render attribution for the footer.
     *
     * Can be called via hook or manually in theme.
     *
     * @return void
     */
    public function renderFooter(): void {
        if ( ! get_option( 'ns_show_footer_attribution', true ) ) {
            return;
        }

        echo $this->renderShortcode( [
            'show_logos'      => true,
            'show_disclaimer' => true,
        ] );
    }

    /**
     * Register the attribution shortcode.
     *
     * @return void
     */
    public function registerShortcode(): void {
        add_shortcode( 'nowscr_attribution', [ $this, 'renderShortcode' ] );
    }

    /**
     * Get inline attribution text (for use in other contexts).
     *
     * @return string Plain text attribution.
     */
    public function getInlineText(): string {
        $attributions = $this->getAll();

        if ( empty( $attributions ) ) {
            return '';
        }

        $parts = array_map(
            fn( Attribution $a ) => $a->text,
            $attributions
        );

        return implode( '. ', $parts ) . '.';
    }

    /**
     * Check if any active provider requires attribution.
     *
     * @return bool
     */
    public function isRequired(): bool {
        foreach ( $this->getAll() as $attribution ) {
            if ( $attribution->required ) {
                return true;
            }
        }

        return false;
    }
}
