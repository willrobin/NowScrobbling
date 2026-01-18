<?php
/**
 * Provider Registry
 *
 * Central registry for all media providers.
 *
 * @package NowScrobbling
 * @since   1.4.0
 */

namespace NowScrobbling\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ProviderRegistry
 *
 * Manages registration and retrieval of media providers.
 */
class ProviderRegistry {

    /**
     * Registered providers.
     *
     * @var array<string, ProviderInterface>
     */
    private array $providers = [];

    /**
     * Register a provider.
     *
     * @param ProviderInterface $provider The provider to register.
     * @return void
     * @throws \InvalidArgumentException If provider with same ID already exists.
     */
    public function register( ProviderInterface $provider ): void {
        $id = $provider->getId();

        if ( isset( $this->providers[ $id ] ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Provider with ID "%s" is already registered.', $id )
            );
        }

        $this->providers[ $id ] = $provider;

        /**
         * Fires when a provider is registered.
         *
         * @since 1.4.0
         * @param ProviderInterface $provider The registered provider.
         */
        do_action( 'nowscrobbling_provider_registered', $provider );
    }

    /**
     * Get a provider by ID.
     *
     * @param string $id Provider ID.
     * @return ProviderInterface|null
     */
    public function get( string $id ): ?ProviderInterface {
        return $this->providers[ $id ] ?? null;
    }

    /**
     * Check if a provider is registered.
     *
     * @param string $id Provider ID.
     * @return bool
     */
    public function has( string $id ): bool {
        return isset( $this->providers[ $id ] );
    }

    /**
     * Get all registered providers.
     *
     * @return array<string, ProviderInterface>
     */
    public function getAll(): array {
        return $this->providers;
    }

    /**
     * Get all provider IDs.
     *
     * @return array<string>
     */
    public function getIds(): array {
        return array_keys( $this->providers );
    }

    /**
     * Get all configured providers.
     *
     * Returns only providers that have valid credentials.
     *
     * @return array<string, ProviderInterface>
     */
    public function getConfigured(): array {
        return array_filter(
            $this->providers,
            fn( ProviderInterface $provider ) => $provider->isConfigured()
        );
    }

    /**
     * Get providers by capability.
     *
     * @param string $capability The capability to filter by.
     * @return array<string, ProviderInterface>
     */
    public function getByCapability( string $capability ): array {
        return array_filter(
            $this->providers,
            fn( ProviderInterface $provider ) => $provider->hasCapability( $capability )
        );
    }

    /**
     * Get providers that have artwork capability.
     *
     * @return array<string, ProviderInterface>
     */
    public function getArtworkProviders(): array {
        return $this->getByCapability( 'artwork' );
    }

    /**
     * Get providers for the admin settings page.
     *
     * Returns data suitable for rendering settings forms.
     *
     * @return array
     */
    public function getForSettings(): array {
        $result = [];

        foreach ( $this->providers as $id => $provider ) {
            $result[ $id ] = [
                'id'           => $id,
                'name'         => $provider->getName(),
                'icon'         => $provider->getIcon(),
                'configured'   => $provider->isConfigured(),
                'fields'       => $provider->getSettingsFields(),
                'capabilities' => $provider->getCapabilities(),
            ];
        }

        return $result;
    }

    /**
     * Get providers for the shortcode builder.
     *
     * Returns data suitable for the admin shortcode builder UI.
     *
     * @return array
     */
    public function getForBuilder(): array {
        $result = [];

        foreach ( $this->getConfigured() as $id => $provider ) {
            $result[ $id ] = [
                'id'           => $id,
                'name'         => $provider->getName(),
                'icon'         => $provider->getIcon(),
                'capabilities' => $provider->getCapabilities(),
            ];
        }

        return $result;
    }

    /**
     * Test all configured providers.
     *
     * @return array<string, ConnectionResult>
     */
    public function testAll(): array {
        $results = [];

        foreach ( $this->getConfigured() as $id => $provider ) {
            $results[ $id ] = $provider->testConnection();
        }

        return $results;
    }

    /**
     * Get count of registered providers.
     *
     * @return int
     */
    public function count(): int {
        return count( $this->providers );
    }

    /**
     * Get count of configured providers.
     *
     * @return int
     */
    public function countConfigured(): int {
        return count( $this->getConfigured() );
    }

    /**
     * Unregister a provider.
     *
     * @param string $id Provider ID.
     * @return bool True if provider was unregistered.
     */
    public function unregister( string $id ): bool {
        if ( ! isset( $this->providers[ $id ] ) ) {
            return false;
        }

        unset( $this->providers[ $id ] );
        return true;
    }
}
