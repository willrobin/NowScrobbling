<?php

declare(strict_types=1);

namespace NowScrobbling;

use RuntimeException;

/**
 * Lightweight dependency injection container
 *
 * Provides singleton management and factory bindings for the plugin's services.
 * No external dependencies required.
 *
 * @package NowScrobbling
 */
final class Container
{
    /**
     * Singleton instance
     */
    private static ?Container $instance = null;

    /**
     * Registered factory bindings
     *
     * @var array<string, callable>
     */
    private array $bindings = [];

    /**
     * Resolved singleton instances
     *
     * @var array<string, mixed>
     */
    private array $resolved = [];

    /**
     * Get the singleton container instance
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Reset the container (mainly for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
    }

    /**
     * Register a singleton binding
     *
     * The factory will only be called once; subsequent calls return the cached instance.
     *
     * @param string   $abstract The abstract type/interface name
     * @param callable $factory  Factory function that receives the container
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = fn(): mixed =>
            $this->resolved[$abstract] ??= $factory($this);
    }

    /**
     * Register a factory binding (new instance each time)
     *
     * @param string   $abstract The abstract type/interface name
     * @param callable $factory  Factory function that receives the container
     */
    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = fn(): mixed => $factory($this);
    }

    /**
     * Register an existing instance
     *
     * @param string $abstract The abstract type/interface name
     * @param mixed  $instance The instance to register
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->resolved[$abstract] = $instance;
        $this->bindings[$abstract] = fn(): mixed => $this->resolved[$abstract];
    }

    /**
     * Resolve a binding from the container
     *
     * @param string $abstract The abstract type/interface name
     *
     * @return mixed The resolved instance
     *
     * @throws RuntimeException If no binding exists for the abstract
     */
    public function make(string $abstract): mixed
    {
        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException(
                sprintf('No binding registered for: %s', $abstract)
            );
        }

        return $this->bindings[$abstract]();
    }

    /**
     * Check if a binding exists
     *
     * @param string $abstract The abstract type/interface name
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * Get all registered binding keys
     *
     * @return array<string>
     */
    public function getBindings(): array
    {
        return array_keys($this->bindings);
    }

    /**
     * Flush all resolved instances (keep bindings)
     *
     * Useful for testing or when you need fresh instances.
     */
    public function flush(): void
    {
        $this->resolved = [];
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
