<?php
/**
 * Base Plugin Class
 *
 * Core plugin class that provides the foundation for WordPress plugin development
 * with dependency injection, service providers, and modern PHP patterns.
 * Handles service container access, provider bootstrapping, and event dispatching.
 *
 * ## Usage Example:
 * ```php
 * // Create plugin instance via factory
 * $plugin = PluginFactory::create(__FILE__, $config, $providers);
 *
 * // Boot the plugin (usually in 'plugins_loaded' hook)
 * add_action('plugins_loaded', fn() => $plugin->boot());
 *
 * // Access services
 * $logger = $plugin->get('logger');
 * $service = $plugin->get('my.service');
 *
 * // Dispatch events
 * $plugin->dispatch('import.starting', $data);
 *
 * // Log messages
 * $plugin->log('Import completed');
 * $plugin->logError('Critical error occurred');
 * ```
 *
 * @package     Furgo\Sitechips\Core\Plugin
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Plugin;

use Furgo\Sitechips\Core\Container\Container;
use Furgo\Sitechips\Core\Container\ContainerException;
use Furgo\Sitechips\Core\Container\ContainerNotFoundException;
use Furgo\Sitechips\Core\Contracts\Bootable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Exception;
use Throwable;

/**
 * Base Plugin Class
 *
 * Provides container access, service provider management, event dispatching,
 * and logging capabilities for WordPress plugins.
 *
 * @since 1.0.0
 */
class Plugin implements Bootable
{
    /**
     * Dependency injection container
     *
     * @var Container
     */
    private Container $container;

    /**
     * Main plugin file path
     *
     * @var string
     */
    private string $pluginFile;

    /**
     * Whether the plugin has been booted
     *
     * @var bool
     */
    private bool $booted = false;

    /**
     * Create new plugin instance
     *
     * @param Container $container Dependency injection container
     * @param string $pluginFile Main plugin file path
     *
     * @since 1.0.0
     */
    public function __construct(Container $container, string $pluginFile)
    {
        $this->container = $container;
        $this->pluginFile = $pluginFile;
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     *
     * @return mixed Service instance
     *
     * @throws NotFoundExceptionInterface No entry was found for this identifier
     * @throws ContainerExceptionInterface Error while retrieving the entry
     *
     * @since 1.0.0
     */
    public function get(string $id): mixed
    {
        try {
            return $this->container->get($id);
        } catch (NotFoundExceptionInterface $e) {
            throw new ContainerNotFoundException(
                "Service '$id' not found in plugin container",
                0,
                $e
            );
        } catch (Exception $e) {
            throw new ContainerException(
                "Error retrieving service '$id' from plugin container: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if container has a service
     *
     * @param string $id Service identifier
     *
     * @return bool True if service exists, false otherwise
     *
     * @since 1.0.0
     */
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Call a method with dependency injection
     *
     * @param callable|array<object|string, string> $callback Method to call
     * @param array<string, mixed> $parameters Additional parameters
     *
     * @return mixed Return value of the called method
     *
     * @throws ContainerExceptionInterface Error while calling method
     *
     * @since 1.0.0
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        try {
            return $this->container->call($callback, $parameters);
        } catch (Exception $e) {
            throw new ContainerException(
                'Failed to call method with dependency injection: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create an instance with dependency injection
     *
     * @param string $className Class name to instantiate
     * @param array<string, mixed> $parameters Additional constructor parameters
     *
     * @return object Instance of the class
     *
     * @throws NotFoundExceptionInterface Class not found
     * @throws ContainerExceptionInterface Error while creating instance
     *
     * @since 1.0.0
     */
    public function make(string $className, array $parameters = []): object
    {
        try {
            return $this->container->make($className, $parameters);
        } catch (NotFoundExceptionInterface $e) {
            throw new ContainerNotFoundException(
                "Class '$className' not found",
                0,
                $e
            );
        } catch (Exception $e) {
            throw new ContainerException(
                "Error creating instance of '$className': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Boot the plugin
     *
     * Boots all registered service providers. Should be called after
     * WordPress is fully loaded (e.g., on 'plugins_loaded' hook).
     *
     * The boot process:
     * 1. Boots all registered service providers
     * 2. Marks plugin as booted
     * 3. Dispatches 'booted' event for other components to hook into
     *
     * @return void
     *
     * @throws ContainerExceptionInterface Error while booting providers
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        try {
            // Boot service providers if available
            if ($this->has('providers')) {
                $providers = $this->get('providers');

                foreach ($providers as $provider) {
                    if ($provider instanceof Bootable && !$provider->isBooted()) {
                        $provider->boot();
                        $provider->markAsBooted();
                    }
                }
            }

            $this->booted = true;

            // Dispatch booted event for components that need to run after full boot
            $this->dispatch('booted');

        } catch (Exception $e) {
            throw new ContainerException(
                'Failed to boot plugin: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if plugin has been booted
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
     * Dispatch an event through WordPress action system
     *
     * Events are dispatched with plugin-specific prefix: {plugin_slug}.{event}
     * Optionally dispatches global events if 'events.global_prefix' is configured.
     *
     * @param string $event Event name
     * @param mixed ...$args Event arguments
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function dispatch(string $event, mixed ...$args): void
    {
        if (!function_exists('do_action')) {
            return;
        }

        try {
            $pluginSlug = $this->get('plugin.slug');

            // Always dispatch plugin-specific event
            do_action("{$pluginSlug}.{$event}", $this, ...$args);

            // Optionally dispatch global event for cross-plugin communication
            if ($this->has('events.global_prefix')) {
                $globalPrefix = $this->get('events.global_prefix');
                do_action("{$globalPrefix}.{$event}", $this, ...$args);
            }
        } catch (Exception $e) {
            // Event dispatching should not break plugin execution
            $this->logError('Failed to dispatch event: ' . $e->getMessage());
        }
    }

    /**
     * Log a message
     *
     * Logs messages through registered logger service or falls back to error_log
     * in debug mode. Available log levels: debug, info, warning, error.
     *
     * @param string $message Message to log
     * @param string $level Log level (default: info)
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function log(string $message, string $level = 'info'): void
    {
        try {
            // Try to use registered logger service
            if ($this->has('logger')) {
                $logger = $this->get('logger');

                if (is_object($logger) && method_exists($logger, 'log')) {
                    $logger->log($level, $message);
                    return;
                }
            }
        } catch (Exception $e) {
            // Logger failed, use fallback
        }

        // Fallback to error_log
        try {
            $debug = $this->get('debug');
            $pluginSlug = $this->get('plugin.slug');

            // Log errors always, other levels only in debug mode
            if ($debug || $level === 'error') {
                $prefix = sprintf('[%s] %s:', $pluginSlug, strtoupper($level));
                error_log("$prefix $message");
            }
        } catch (Exception $e) {
            // Ultimate fallback for critical errors
            error_log("[Sitechips Plugin] ERROR: $message");
        }
    }

    /**
     * Log an error message
     *
     * Error messages are always logged, regardless of debug mode.
     *
     * @param string $message Error message
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function logError(string $message): void
    {
        $this->log($message, 'error');
    }

    /**
     * Get the main plugin file path
     *
     * @return string Plugin file path
     *
     * @since 1.0.0
     */
    public function getPluginFile(): string
    {
        return $this->pluginFile;
    }

    /**
     * Get the dependency injection container
     *
     * Direct container access should be avoided in favor of get() method.
     * This method is primarily for advanced use cases and testing.
     *
     * @return Container Container instance
     *
     * @since 1.0.0
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}