<?php
/**
 * Plugin Factory
 *
 * Factory for creating WordPress plugin instances with pre-configured
 * container and service providers. Provides the foundation for
 * WordPress plugin development with dependency injection.
 *
 * ## Usage Example:
 * ```php
 * // Standard plugin creation
 * $plugin = PluginFactory::create(__FILE__, [
 *     'cache.enabled' => true,
 *     'api.endpoint' => 'https://api.example.com'
 * ], [
 *     CoreServiceProvider::class,
 *     DatabaseServiceProvider::class,
 *     ImportServiceProvider::class
 * ]);
 *
 * // Testing environment
 * $plugin = PluginFactory::createForTesting('/tmp/test.php', [
 *     'plugin.name' => 'Test Plugin'
 * ]);
 *
 * // Boot the plugin
 * add_action('plugins_loaded', fn() => $plugin->boot());
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
use Furgo\Sitechips\Core\Container\ServiceProvider;
use Exception;
use InvalidArgumentException;

/**
 * Plugin Factory Class
 *
 * Creates plugin instances with pre-configured dependency injection container
 * and service providers. Handles the bootstrap process for WordPress plugins.
 *
 * @since 1.0.0
 */
class PluginFactory
{
    /**
     * Create a new plugin instance
     *
     * @param string $pluginFile Main plugin file path
     * @param array<string, mixed> $config Additional plugin configuration
     * @param array<class-string> $providers Service provider class names
     *
     * @return Plugin Configured plugin instance
     *
     * @throws InvalidArgumentException If plugin file is invalid
     * @throws ContainerException If container creation fails
     * @throws Exception If service provider registration fails
     *
     * @since 1.0.0
     */
    public static function create(
        string $pluginFile,
        array $config = [],
        array $providers = []
    ): Plugin {
        // Validate plugin file
        if (!file_exists($pluginFile)) {
            throw new InvalidArgumentException(
                "Plugin file not found: $pluginFile"
            );
        }

        // Create container with base definitions and user config
        $definitions = array_merge(
            self::createBaseDefinitions($pluginFile),
            $config
        );

        $container = new Container($definitions);

        // Register service providers
        self::registerServiceProviders($container, $providers);

        // Create and return plugin instance
        return new Plugin($container, $pluginFile);
    }

    /**
     * Create a plugin instance for testing
     *
     * Creates a plugin with test-friendly defaults and minimal WordPress dependencies.
     *
     * @param string $pluginFile Main plugin file path (default: /tmp/test-plugin.php)
     * @param array<string, mixed> $config Additional plugin configuration
     * @param array<class-string> $providers Service provider class names
     *
     * @return Plugin Configured plugin instance for testing
     *
     * @throws ContainerException If container creation fails
     * @throws Exception If service provider registration fails
     *
     * @since 1.0.0
     */
    public static function createForTesting(
        string $pluginFile = '/tmp/test-plugin.php',
        array $config = [],
        array $providers = []
    ): Plugin {
        // Merge test-specific configuration
        $testConfig = array_merge([
            'environment' => 'testing',
            'debug' => true,
            'cache.enabled' => false,
            'plugin.name' => 'Test Plugin',
            'plugin.version' => '1.0.0',
            'plugin.text_domain' => 'test-plugin',
        ], $config);

        // Create plugin without file validation
        $definitions = array_merge(
            self::createBaseDefinitions($pluginFile),
            $testConfig
        );

        $container = new Container($definitions);
        self::registerServiceProviders($container, $providers);

        return new Plugin($container, $pluginFile);
    }

    /**
     * Create base service definitions
     *
     * Creates the required and recommended service definitions for the plugin.
     * Handles WordPress function availability gracefully with fallbacks.
     *
     * @param string $pluginFile Main plugin file path
     *
     * @return array<string, mixed> Base service definitions
     *
     * @since 1.0.0
     */
    protected static function createBaseDefinitions(string $pluginFile): array
    {
        // Extract basic plugin information
        $pluginSlug = self::extractPluginSlug($pluginFile);
        $pluginPath = self::getPluginPath($pluginFile);
        $pluginUrl = self::getPluginUrl($pluginFile);
        $pluginBasename = self::getPluginBasename($pluginFile);

        // Get plugin metadata if available
        $pluginData = self::getPluginData($pluginFile);

        return [
            // Required definitions
            'plugin.slug' => $pluginSlug,

            // Recommended definitions
            'debug' => self::isDebugMode(),
            'providers' => [], // Will be populated by registerServiceProviders

            // Plugin file information
            'plugin.file' => $pluginFile,
            'plugin.path' => $pluginPath,
            'plugin.url' => $pluginUrl,
            'plugin.basename' => $pluginBasename,

            // Plugin metadata
            'plugin.name' => $pluginData['Name'] ?? $pluginSlug,
            'plugin.version' => $pluginData['Version'] ?? '1.0.0',
            'plugin.description' => $pluginData['Description'] ?? '',
            'plugin.author' => $pluginData['Author'] ?? '',
            'plugin.text_domain' => $pluginData['TextDomain'] ?? $pluginSlug,

            // Environment
            'environment' => self::detectEnvironment(),

            // Paths
            'path.assets' => $pluginPath . 'assets/',
            'path.templates' => $pluginPath . 'templates/',
            'path.languages' => $pluginPath . 'languages/',

            // URLs
            'url.assets' => $pluginUrl . 'assets/',

            // Optional: Global events prefix (null by default)
            'events.global_prefix' => null,
        ];
    }

    /**
     * Register service providers in the container
     *
     * @param Container $container Container instance
     * @param array<class-string> $providers Service provider class names
     *
     * @return void
     *
     * @throws InvalidArgumentException If provider class not found or not a ServiceProvider
     * @throws Exception If provider instantiation or registration fails
     *
     * @since 1.0.0
     */
    protected static function registerServiceProviders(Container $container, array $providers): void
    {
        $providerInstances = [];

        foreach ($providers as $providerClass) {
            // Validate provider class exists
            if (!class_exists($providerClass)) {
                throw new InvalidArgumentException(
                    "Service provider class not found: $providerClass"
                );
            }

            try {
                // Create provider instance
                $provider = new $providerClass($container);

                // Validate it's a ServiceProvider
                if (!$provider instanceof ServiceProvider) {
                    throw new InvalidArgumentException(
                        "Class $providerClass must extend ServiceProvider"
                    );
                }

                // Register the provider
                $provider->register();
                $provider->markAsRegistered();

                $providerInstances[] = $provider;

            } catch (InvalidArgumentException $e) {
                // Re-throw InvalidArgumentException as-is for validation errors
                throw $e;
            } catch (Exception $e) {
                // Wrap other exceptions with context
                throw new Exception(
                    "Failed to register service provider '$providerClass': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        // Store provider instances in container for boot phase
        $container->set('providers', $providerInstances);
    }

    /**
     * Extract plugin slug from file path
     *
     * @param string $pluginFile Plugin file path
     *
     * @return string Plugin slug
     *
     * @since 1.0.0
     */
    protected static function extractPluginSlug(string $pluginFile): string
    {
        // Get directory name as slug
        $pluginDir = basename(dirname($pluginFile));

        // Sanitize for use as slug
        return strtolower(str_replace(' ', '-', $pluginDir));
    }

    /**
     * Get plugin directory path with fallback
     *
     * @param string $pluginFile Plugin file path
     *
     * @return string Plugin directory path with trailing slash
     *
     * @since 1.0.0
     */
    protected static function getPluginPath(string $pluginFile): string
    {
        if (function_exists('plugin_dir_path')) {
            return plugin_dir_path($pluginFile);
        }

        // Fallback for testing environments
        return self::trailingslashit(dirname($pluginFile));
    }

    /**
     * Get plugin URL with fallback
     *
     * @param string $pluginFile Plugin file path
     *
     * @return string Plugin URL with trailing slash
     *
     * @since 1.0.0
     */
    protected static function getPluginUrl(string $pluginFile): string
    {
        if (function_exists('plugin_dir_url')) {
            return plugin_dir_url($pluginFile);
        }

        // Fallback for testing environments
        $pluginPath = dirname($pluginFile);

        // Try to construct URL from known WordPress structure
        if (defined('WP_CONTENT_URL') && defined('WP_CONTENT_DIR')) {
            $contentDir = WP_CONTENT_DIR;
            if (strpos($pluginPath, $contentDir) === 0) {
                $relativePath = substr($pluginPath, strlen($contentDir));
                return self::trailingslashit(WP_CONTENT_URL . $relativePath);
            }
        }

        // Ultimate fallback
        $pluginSlug = basename($pluginPath);
        return "http://example.com/wp-content/plugins/$pluginSlug/";
    }

    /**
     * Get plugin basename with fallback
     *
     * @param string $pluginFile Plugin file path
     *
     * @return string Plugin basename (e.g., 'my-plugin/my-plugin.php')
     *
     * @since 1.0.0
     */
    protected static function getPluginBasename(string $pluginFile): string
    {
        if (function_exists('plugin_basename')) {
            return plugin_basename($pluginFile);
        }

        // Fallback for testing environments
        $pluginDir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '/wp-content/plugins';

        if (strpos($pluginFile, $pluginDir) === 0) {
            return ltrim(substr($pluginFile, strlen($pluginDir)), '/\\');
        }

        // Ultimate fallback
        return basename(dirname($pluginFile)) . '/' . basename($pluginFile);
    }

    /**
     * Get plugin metadata with fallback
     *
     * @param string $pluginFile Plugin file path
     *
     * @return array<string, string> Plugin headers or empty array
     *
     * @since 1.0.0
     */
    protected static function getPluginData(string $pluginFile): array
    {
        if (function_exists('get_plugin_data')) {
            return get_plugin_data($pluginFile, false, false);
        }

        // Fallback: Try to parse plugin header manually
        if (!file_exists($pluginFile)) {
            return [];
        }

        $pluginData = [];
        $fileData = file_get_contents($pluginFile, false, null, 0, 8192);

        if ($fileData === false) {
            return [];
        }

        // Basic header parsing
        $headers = [
            'Name' => 'Plugin Name',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author',
            'TextDomain' => 'Text Domain',
        ];

        foreach ($headers as $key => $header) {
            if (preg_match('/^[\s\*]*' . $header . ':(.*)$/mi', $fileData, $matches)) {
                $pluginData[$key] = trim($matches[1]);
            }
        }

        return $pluginData;
    }

    /**
     * Detect current environment
     *
     * @return string Environment name (testing|development|staging|production)
     *
     * @since 1.0.0
     */
    protected static function detectEnvironment(): string
    {
        // Check for test environment
        if (defined('SITECHIPS_TESTS') || defined('SITECHIPS_CORE_TESTS')) {
            return 'testing';
        }

        // Use WordPress environment if available
        if (function_exists('wp_get_environment_type')) {
            return wp_get_environment_type();
        }

        // Fallback detection
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }

        return 'production';
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool True if debug mode is active
     *
     * @since 1.0.0
     */
    protected static function isDebugMode(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Add trailing slash to path
     *
     * @param string $path Path to add slash to
     *
     * @return string Path with trailing slash
     *
     * @since 1.0.0
     */
    protected static function trailingslashit(string $path): string
    {
        if (function_exists('trailingslashit')) {
            return trailingslashit($path);
        }

        return rtrim($path, '/\\') . '/';
    }
}