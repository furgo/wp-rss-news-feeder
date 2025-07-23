<?php
/**
 * Abstract Service Locator
 *
 * Base class for plugin service locators implementing the Service Locator pattern.
 * Provides standard service location methods while requiring subclasses to implement
 * plugin-specific configuration.
 *
 * @package     Furgo\Sitechips\Core\Plugin
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Plugin;

use Furgo\Sitechips\Core\Container\ContainerException;
use Furgo\Sitechips\Core\Container\ContainerNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use LogicException;

/**
 * Abstract Service Locator Class
 *
 * Provides a Service Locator pattern implementation with Singleton plugin instance.
 * Subclasses only need to implement the setupPlugin() method with their specific configuration.
 *
 * @since 1.0.0
 */
abstract class AbstractServiceLocator
{
    /**
     * Plugin instances per class (using late static binding)
     *
     * @var array<class-string, Plugin>
     */
    private static array $instances = [];

    /**
     * Prevent direct instantiation (Singleton pattern)
     *
     * @throws LogicException If attempting to instantiate directly
     */
    private function __construct()
    {
        throw new LogicException('Cannot instantiate singleton');
    }

    /**
     * Prevent cloning (Singleton pattern)
     *
     * @return void
     *
     * @throws LogicException If attempting to clone
     */
    private function __clone(): void
    {
        throw new LogicException('Cannot clone singleton');
    }

    /**
     * Prevent unserialization (Singleton pattern)
     *
     * @return void
     *
     * @throws LogicException If attempting to unserialize
     */
    public function __wakeup(): void
    {
        throw new LogicException('Cannot unserialize singleton');
    }

    /**
     * Get plugin instance
     *
     * @return Plugin
     *
     * @throws ContainerExceptionInterface If plugin setup fails
     */
    public static function instance(): Plugin
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = static::setupPlugin();
        }

        return self::$instances[$class];
    }

    /**
     * Reset plugin instance (for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        $class = static::class;
        unset(self::$instances[$class]);
    }

    /**
     * Locate and get a service (Service Locator Pattern)
     *
     * @param string $serviceId Service identifier
     *
     * @return mixed
     *
     * @throws NotFoundExceptionInterface Service not found
     * @throws ContainerExceptionInterface Error while retrieving service
     */
    public static function get(string $serviceId): mixed
    {
        return static::instance()->get($serviceId);
    }

    /**
     * Check if a service is available
     *
     * @param string $serviceId Service identifier
     *
     * @return bool
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function has(string $serviceId): bool
    {
        return static::instance()->has($serviceId);
    }

    /**
     * Get plugin version
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function version(): string
    {
        return static::instance()->get('plugin.version');
    }

    /**
     * Get plugin path
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function path(): string
    {
        return static::instance()->get('plugin.path');
    }

    /**
     * Get plugin URL
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function url(): string
    {
        return static::instance()->get('plugin.url');
    }

    /**
     * Get plugin basename
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function basename(): string
    {
        return static::instance()->get('plugin.basename');
    }

    /**
     * Get plugin name
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function name(): string
    {
        return static::instance()->get('plugin.name');
    }

    /**
     * Get plugin text domain
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function textDomain(): string
    {
        return static::instance()->get('plugin.text_domain');
    }

    /**
     * Get current environment
     *
     * @return string
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function environment(): string
    {
        return static::instance()->get('environment');
    }

    /**
     * Check if plugin is in debug mode
     *
     * @return bool
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function isDebug(): bool
    {
        return static::instance()->get('debug');
    }

    /**
     * Check if plugin has been booted
     *
     * @return bool
     *
     * @throws ContainerExceptionInterface If instance creation fails
     */
    public static function isBooted(): bool
    {
        return static::instance()->isBooted();
    }

    /**
     * Boot the plugin
     *
     * @return void
     *
     * @throws ContainerExceptionInterface If boot fails
     */
    public static function boot(): void
    {
        static::instance()->boot();
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
     */
    public static function call(callable|array $callback, array $parameters = []): mixed
    {
        return static::instance()->call($callback, $parameters);
    }

    /**
     * Create an instance with dependency injection
     *
     * @param string $className Class name to instantiate
     * @param array<string, mixed> $parameters Additional constructor parameters
     *
     * @return object
     *
     * @throws NotFoundExceptionInterface Class not found
     * @throws ContainerExceptionInterface Error while creating instance
     */
    public static function make(string $className, array $parameters = []): object
    {
        return static::instance()->make($className, $parameters);
    }

// ========================================================================
// Core Service Convenience Methods
// ========================================================================

    /**
     * Dispatch an event through the plugin's event system
     *
     * Falls back to WordPress actions if no event manager is available.
     *
     * @param string $event Event name
     * @param mixed ...$args Event arguments
     *
     * @return void
     *
     * @throws ContainerExceptionInterface If instance creation fails
     *
     * @since 1.0.0
     */
    public static function dispatch(string $event, mixed ...$args): void
    {
        $plugin = static::instance();

        if ($plugin->has('events')) {
            $plugin->get('events')->dispatch($event, ...$args);
        } else {
            // Fallback to WordPress action
            do_action($plugin->get('plugin.slug') . '.' . $event, $plugin, ...$args);
        }
    }

    /**
     * Log a message through the plugin's logger
     *
     * Does nothing if no logger is available.
     *
     * @param string $message Log message
     * @param string $level Log level (debug, info, warning, error)
     *
     * @return void
     *
     * @throws ContainerExceptionInterface If instance creation fails
     *
     * @since 1.0.0
     */
    public static function log(string $message, string $level = 'info'): void
    {
        $plugin = static::instance();

        if ($plugin->has('logger')) {
            $plugin->get('logger')->log($level, $message);
        }
    }

    /**
     * Log an error message
     *
     * Convenience method for error logging.
     *
     * @param string $message Error message
     *
     * @return void
     *
     * @throws ContainerExceptionInterface If instance creation fails
     *
     * @since 1.0.0
     */
    public static function logError(string $message): void
    {
        static::log($message, 'error');
    }

    /**
     * Setup the plugin instance with specific configuration
     *
     * This method must be implemented by subclasses to provide their specific
     * plugin configuration, services, and service providers.
     *
     * @return Plugin Configured plugin instance
     *
     * @throws ContainerExceptionInterface If plugin creation fails
     */
    abstract protected static function setupPlugin(): Plugin;
}