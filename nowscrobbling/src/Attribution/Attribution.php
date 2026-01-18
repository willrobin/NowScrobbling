<?php
/**
 * Attribution
 *
 * Represents attribution information for a provider.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Attribution;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Attribution
 *
 * Value object containing all attribution requirements for a provider.
 * Ensures legal compliance with API terms of service.
 */
class Attribution {

    /**
     * Provider ID.
     *
     * @var string
     */
    public string $providerId;

    /**
     * Provider display name.
     *
     * @var string
     */
    public string $providerName;

    /**
     * Short attribution text.
     *
     * Example: "Data from Last.fm"
     *
     * @var string
     */
    public string $text;

    /**
     * Link to the provider.
     *
     * @var string
     */
    public string $url;

    /**
     * Path to provider logo (if usage is allowed).
     *
     * @var string|null
     */
    public ?string $logo;

    /**
     * Whether logo usage is allowed without written permission.
     *
     * @var bool
     */
    public bool $logoAllowed;

    /**
     * Required disclaimer text (if any).
     *
     * Example: "This product uses the TMDB API but is not endorsed or certified by TMDB."
     *
     * @var string|null
     */
    public ?string $disclaimer;

    /**
     * Whether attribution is legally required.
     *
     * @var bool
     */
    public bool $required;

    /**
     * Constructor.
     *
     * @param string      $provider_id   Provider ID.
     * @param string      $provider_name Provider display name.
     * @param string      $text          Attribution text.
     * @param string      $url           Provider URL.
     * @param string|null $logo          Logo path.
     * @param bool        $logo_allowed  Whether logo can be used.
     * @param string|null $disclaimer    Required disclaimer.
     * @param bool        $required      Whether attribution is required.
     */
    public function __construct(
        string $provider_id,
        string $provider_name,
        string $text,
        string $url,
        ?string $logo = null,
        bool $logo_allowed = false,
        ?string $disclaimer = null,
        bool $required = true
    ) {
        $this->providerId   = $provider_id;
        $this->providerName = $provider_name;
        $this->text         = $text;
        $this->url          = $url;
        $this->logo         = $logo;
        $this->logoAllowed  = $logo_allowed;
        $this->disclaimer   = $disclaimer;
        $this->required     = $required;
    }

    /**
     * Create attribution for Last.fm.
     *
     * @return self
     */
    public static function lastfm(): self {
        return new self(
            provider_id: 'lastfm',
            provider_name: 'Last.fm',
            text: __( 'Music data from Last.fm', 'nowscrobbling' ),
            url: 'https://www.last.fm',
            logo: null, // Logo requires written permission.
            logo_allowed: false,
            disclaimer: null,
            required: true
        );
    }

    /**
     * Create attribution for Trakt.
     *
     * @return self
     */
    public static function trakt(): self {
        return new self(
            provider_id: 'trakt',
            provider_name: 'Trakt.tv',
            text: __( 'TV & Movie data from Trakt.tv', 'nowscrobbling' ),
            url: 'https://trakt.tv',
            logo: NOWSCROBBLING_URL . 'assets/images/providers/trakt.svg',
            logo_allowed: true, // Allowed with branding guidelines.
            disclaimer: null,
            required: true
        );
    }

    /**
     * Create attribution for TMDb.
     *
     * @return self
     */
    public static function tmdb(): self {
        return new self(
            provider_id: 'tmdb',
            provider_name: 'The Movie Database',
            text: __( 'Posters provided by TMDb', 'nowscrobbling' ),
            url: 'https://www.themoviedb.org',
            logo: NOWSCROBBLING_URL . 'assets/images/providers/tmdb.svg',
            logo_allowed: true, // Allowed with attribution.
            disclaimer: __( 'This product uses the TMDB API but is not endorsed or certified by TMDB.', 'nowscrobbling' ),
            required: true
        );
    }

    /**
     * Render the attribution as HTML.
     *
     * @param bool $include_logo Whether to include the logo.
     * @return string
     */
    public function render( bool $include_logo = true ): string {
        $html = '<span class="ns-attribution ns-attribution--' . esc_attr( $this->providerId ) . '">';

        // Logo (if allowed and requested).
        if ( $include_logo && $this->logoAllowed && $this->logo ) {
            $html .= sprintf(
                '<img src="%s" alt="%s" class="ns-attribution__logo" width="24" height="24" loading="lazy">',
                esc_url( $this->logo ),
                esc_attr( $this->providerName )
            );
        }

        // Text with link.
        $html .= sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="ns-attribution__link">%s</a>',
            esc_url( $this->url ),
            esc_html( $this->text )
        );

        $html .= '</span>';

        return $html;
    }

    /**
     * Render the disclaimer (if any).
     *
     * @return string
     */
    public function renderDisclaimer(): string {
        if ( empty( $this->disclaimer ) ) {
            return '';
        }

        return sprintf(
            '<span class="ns-attribution__disclaimer">%s</span>',
            esc_html( $this->disclaimer )
        );
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'provider_id'   => $this->providerId,
            'provider_name' => $this->providerName,
            'text'          => $this->text,
            'url'           => $this->url,
            'logo'          => $this->logo,
            'logo_allowed'  => $this->logoAllowed,
            'disclaimer'    => $this->disclaimer,
            'required'      => $this->required,
        ];
    }
}
