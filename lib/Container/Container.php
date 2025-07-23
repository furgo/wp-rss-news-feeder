<?php
/**
 * PSR-11 Container Wrapper for PHP-DI
 *
 * WordPress-optimized container wrapper around PHP-DI with convenience
 * methods for service registration and WordPress-specific optimizations.
 *
 * @package     Furgo\Sitechips\Core
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Container;

use DI\Container as PHPDIContainer;
use DI\ContainerBuilder;
use DI\NotFoundException;
use Exception;
use Furgo\Sitechips\Core\Container\ContainerNotFoundException;
use Furgo\Sitechips\Core\Container\ContainerException;
use Psr\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Container Class - PHP-DI Wrapper
 *
 * Provides a WordPress-optimized PSR-11 container using PHP-DI as the
 * underlying implementation with convenience methods for common use cases.
 *
 * @since 1.0.0
 */
class Container implements ContainerInterface
{
    /**
     * PHP-DI container instance
     *
     * @var PHPDIContainer
     */
    private PHPDIContainer $container;

    /**
     * Whether the container is compiled for production
     *
     * @var bool
     */
    private bool $compiled = false;

    /**
     * Create new container instance
     *
     * @param array<string, mixed> $definitions Initial service definitions
     * @param bool $enableCompilation Whether to enable compilation for production4
     *
     * @throws ContainerException
     *
     * @since 1.0.0
     */
    public function __construct(array $definitions = [], bool $enableCompilation = null)
    {
        $builder = new ContainerBuilder();

        // Cache-Path direkt aus definitions holen
        $cachePath = $definitions['cache.path'] ?? null;

        // Determine compilation setting
        if ($enableCompilation === null) {
            $enableCompilation = !defined('WP_DEBUG') || !WP_DEBUG;
        }

        // WordPress-optimized configuration MIT cache-path
        if ($enableCompilation && $cachePath) {
            $this->configureForProduction($builder, $cachePath);
            $this->compiled = true;
        } else {
            $this->configureForDevelopment($builder);
            $this->compiled = false;
        }

        // Add initial definitions
        if (!empty($definitions)) {
            $builder->addDefinitions($definitions);
        }

        try {
            $this->container = $builder->build();
        } catch (Exception $e) {
            throw new ContainerException('Failed to build container: ' . $e->getMessage(), 0, $e);
        }

        if ($enableCompilation && !$this->getCacheDirectory()) {
            $this->compiled = false;
        }
    }

    /**
     * Finds an entry of the container by its identifier and returns it
     *
     * @param string $id Identifier of the entry to look for
     *
     * @return mixed Entry
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
        } catch (NotFoundException $e) {
            throw new ContainerNotFoundException("Service '$id' not found in container", 0, $e);
        } catch (Exception $e) {
            throw new ContainerException("Error retrieving service '$id': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Returns true if the container can return an entry for the given identifier
     *
     * @param string $id Identifier of the entry to look for
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Register a service in the container
     *
     * @param string $id Service identifier
     * @param mixed $value Service definition (callable, class name, or instance)
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function set(string $id, mixed $value): void
    {
        try {
            $this->container->set($id, $value);
        } catch (\Exception $e) {
            throw new ContainerException(
                "Cannot set definition for '$id': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Register a shared service (singleton)
     *
     * In PHP-DI, services are shared by default, so this is an alias for set().
     * Kept for API compatibility with other container implementations.
     *
     * @param string $id Service identifier
     * @param mixed $value Service definition
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function shared(string $id, mixed $value): void
    {
        $this->set($id, $value);
    }

    /**
     * Register a service alias
     *
     * @param string $alias Alias name
     * @param string $target Original service identifier
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function alias(string $alias, string $target): void
    {
        // Check if target service exists
        if (!$this->has($target)) {
            throw new ContainerNotFoundException(
                "Cannot create alias '$alias': target service '$target' not found"
            );
        }

        // Get the actual service instance (not a definition)
        $service = $this->get($target);

        // Set the alias to the same instance
        $this->container->set($alias, $service);
    }

    /**
     * Call a method or function with dependency injection
     *
     * @param callable|array<object|string, string> $callable Function or method to call
     * @param array<string, mixed> $parameters Additional parameters
     *
     * @return mixed Return value of the called function/method
     *
     * @since 1.0.0
     */
    public function call(callable|array $callable, array $parameters = []): mixed
    {
        return $this->container->call($callable, $parameters);
    }

    /**
     * Create an instance of the given class with dependency injection
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
        } catch (NotFoundException $e) {
            throw new ContainerNotFoundException("Class '$className' not found", 0, $e);
        } catch (Exception $e) {
            throw new ContainerException("Error creating instance of '$className': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if container is compiled for production
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function isCompiled(): bool
    {
        return $this->compiled;
    }

    /**
     * Get the underlying PHP-DI container instance
     *
     * Use this sparingly - prefer using the wrapper methods.
     *
     * @return PHPDIContainer
     *
     * @since 1.0.0
     */
    public function getInternalContainer(): PHPDIContainer
    {
        return $this->container;
    }

    /**
     * Configure container for production use
     *
     * @param ContainerBuilder $builder Container builder
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function configureForProduction(ContainerBuilder $builder, string $cachePath): void
    {
        // Create cache directory if it doesn't exist
        if (!is_dir($cachePath)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($cachePath . '/proxies');
            } else {
                // Fallback for test environments
                @mkdir($cachePath . '/proxies', 0777, true);
            }
        }

        // Enable compilation if cache directory is writable
        if (is_dir($cachePath) && is_writable($cachePath)) {
            $builder->enableCompilation($cachePath);
            $builder->writeProxiesToFile(true, $cachePath . '/proxies');
        }

        // Only enable definition cache if APCu is available
        if (extension_loaded('apcu') && apcu_enabled()) {
            $builder->enableDefinitionCache();
        }
    }

    /**
     * Configure container for development use
     *
     * @param ContainerBuilder $builder Container builder
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function configureForDevelopment(ContainerBuilder $builder): void
    {
        // No compilation in development for faster iteration
        // PHP-DI will use reflection which is slower but allows for changes

        // Only enable definition cache if APCu extension is available
        // This prevents errors in test environments or systems without APCu
        if (extension_loaded('apcu') && apcu_enabled()) {
            $builder->enableDefinitionCache();
        }
        // Without APCu: Container still works, just slightly slower
    }

    /**
     * Get cache directory for compiled container
     *
     * Retrieves the cache directory path from container configuration.
     * Creates the directory structure if it doesn't exist.
     *
     * @return string|null Cache directory path or null if not available
     *
     * @since 1.0.0
     */
    private function getCacheDirectory(): ?string
    {
        if (!defined('WP_CONTENT_DIR')) {
            return null;
        }

        // Get cache path from container definitions
        $cachePath = $this->has('cache.path')
            ? $this->get('cache.path')
            : null;

        if (!$cachePath) {
            return null; // No compilation without cache path
        }

        // Try to create cache directory if it doesn't exist
        if (!is_dir($cachePath)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($cachePath . '/proxies');
            } else {
                // Fallback for test environments
                @mkdir($cachePath . '/proxies', 0777, true);
            }
        }

        return is_dir($cachePath) && is_writable($cachePath) ? $cachePath : null;
    }
}