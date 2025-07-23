<?php
/**
 * Base Service Provider
 *
 * Abstract base class for service providers. Service providers are responsible
 * for registering services into the container and booting them when needed.
 * They organize related services and handle WordPress integration.
 *
 * @package     Furgo\Sitechips\Core\Container
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Container;

use Furgo\Sitechips\Core\Contracts\ServiceProviderInterface;
use Furgo\Sitechips\Core\Contracts\Bootable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use InvalidArgumentException;
use function DI\autowire;

/**
 * Abstract Service Provider Class
 *
 * Service providers are the central place for registering services.
 * They organize related services and their dependencies, handle the
 * registration phase (defining services) and the boot phase (WordPress integration).
 *
 * @since 1.0.0
 */
abstract class ServiceProvider implements ServiceProviderInterface, Bootable
{
    /**
     * The container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Whether the provider has been registered
     *
     * @var bool
     */
    protected bool $registered = false;

    /**
     * Whether the provider has been booted
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * Create a new service provider instance
     *
     * @param Container $container Container instance
     *
     * @throws InvalidArgumentException If container is null
     *
     * @since 1.0.0
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register services into the container
     *
     * This method should be used to register services into the container.
     * Services registered here should not depend on other services
     * being available yet or on WordPress being fully loaded.
     *
     * @return void
     *
     * @since 1.0.0
     */
    abstract public function register(): void;

    /**
     * Boot services after registration
     *
     * This method is called after all providers have been registered.
     * Use this for WordPress hook registration and initialization that
     * requires other services to be available or WordPress to be fully loaded.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        // Default implementation - override in subclasses if needed
    }

    /**
     * Check if the provider has been registered
     *
     * @return bool True if registered, false otherwise
     *
     * @since 1.0.0
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Check if the provider has been booted
     *
     * @return bool True if booted, false otherwise
     *
     * @since 1.0.0
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Mark the provider as registered
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function markAsRegistered(): void
    {
        $this->registered = true;
    }

    /**
     * Mark the provider as booted
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function markAsBooted(): void
    {
        $this->booted = true;
    }

    /**
     * Register a service in the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     *
     * @return void
     *
     * @throws InvalidArgumentException If abstract is empty
     * @throws ContainerExceptionInterface Error while registering service
     *
     * @since 1.0.0
     */
    protected function bind(string $abstract, mixed $concrete = null): void
    {
        if (empty($abstract)) {
            throw new InvalidArgumentException('Service identifier cannot be empty');
        }

        if ($concrete === null) {
            // Use PHP-DI's autowire for automatic dependency injection
            $this->container->set($abstract, autowire($abstract));
        } else {
            $this->container->set($abstract, $concrete);
        }
    }

    /**
     * Register a shared service (singleton)
     *
     * In PHP-DI, services are shared by default, so this is an alias for bind().
     * Kept for semantic clarity when registering singletons.
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     *
     * @return void
     *
     * @throws InvalidArgumentException If abstract is empty
     * @throws ContainerExceptionInterface Error while registering service
     *
     * @since 1.0.0
     */
    protected function shared(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete);
    }

    /**
     * Register a service alias
     *
     * @param string $alias Alias name
     * @param string $abstract Original service identifier
     *
     * @return void
     *
     * @throws InvalidArgumentException If alias or abstract is empty
     * @throws ContainerExceptionInterface Error while registering alias
     *
     * @since 1.0.0
     */
    protected function alias(string $alias, string $abstract): void
    {
        if (empty($alias) || empty($abstract)) {
            throw new InvalidArgumentException('Alias and abstract cannot be empty');
        }

        $this->container->alias($alias, $abstract);
    }

    /**
     * Register multiple services at once
     *
     * @param array<string, mixed> $services Array of service_id => implementation
     * @param bool $shared Whether to register as shared instances (default: true)
     *
     * @return void
     *
     * @throws InvalidArgumentException If services array is empty
     * @throws ContainerExceptionInterface Error while registering services
     *
     * @since 1.0.0
     */
    protected function registerServices(array $services, bool $shared = true): void
    {
        foreach ($services as $abstract => $concrete) {
            if ($shared) {
                $this->shared($abstract, $concrete);
            } else {
                $this->bind($abstract, $concrete);
            }
        }
    }

    /**
     * Register multiple aliases at once
     *
     * @param array<string, string> $aliases Array of alias => service_id
     *
     * @return void
     *
     * @throws InvalidArgumentException If aliases array is empty
     * @throws ContainerExceptionInterface Error while registering aliases
     *
     * @since 1.0.0
     */
    protected function registerAliases(array $aliases): void
    {
        foreach ($aliases as $alias => $abstract) {
            $this->alias($alias, $abstract);
        }
    }

    /**
     * Call a method with dependency injection
     *
     * @param callable|array<object|string, string> $callback Method to call
     * @param array<string, mixed> $parameters Additional parameters
     *
     * @return mixed
     *
     * @throws ContainerExceptionInterface Error while calling method
     *
     * @since 1.0.0
     */
    protected function call(callable|array $callback, array $parameters = []): mixed
    {
        return $this->container->call($callback, $parameters);
    }

    /**
     * Create an instance with dependency injection
     *
     * @param string $className Class name to instantiate
     * @param array<string, mixed> $parameters Additional constructor parameters
     *
     * @return object
     *
     * @throws InvalidArgumentException If className is empty
     * @throws NotFoundExceptionInterface Class not found
     * @throws ContainerExceptionInterface Error while creating instance
     *
     * @since 1.0.0
     */
    protected function make(string $className, array $parameters = []): object
    {
        if (empty($className)) {
            throw new InvalidArgumentException('Class name cannot be empty');
        }

        return $this->container->make($className, $parameters);
    }

    /**
     * Add WordPress action hook
     *
     * Helper method for WordPress integration.
     *
     * @param string $hook Hook name
     * @param callable|array<object|string, string> $callback Callback function
     * @param int $priority Priority (default: 10)
     * @param int $accepted_args Number of accepted arguments (default: 1)
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function addAction(string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1): void
    {
        if (function_exists('add_action')) {
            add_action($hook, $callback, $priority, $accepted_args);
        }
    }

    /**
     * Add WordPress filter hook
     *
     * Helper method for WordPress integration.
     *
     * @param string $hook Hook name
     * @param callable|array<object|string, string> $callback Callback function
     * @param int $priority Priority (default: 10)
     * @param int $accepted_args Number of accepted arguments (default: 1)
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function addFilter(string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1): void
    {
        if (function_exists('add_filter')) {
            add_filter($hook, $callback, $priority, $accepted_args);
        }
    }
}