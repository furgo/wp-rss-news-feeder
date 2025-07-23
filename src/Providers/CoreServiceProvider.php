<?php
/**
 * Core Service Provider
 *
 * Registers essential core services for the Sitechips Boilerplate plugin.
 * Follows minimalist principles while providing complete functionality.
 *
 * @package     Furgo\RssNewsFeeder\Providers
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       2.0.0
 */

declare(strict_types=1);

namespace Furgo\RssNewsFeeder\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;
use Furgo\Sitechips\Core\Services\EventManager;
use Furgo\Sitechips\Core\Services\AssetManager;
use Furgo\Sitechips\Core\Services\NullLogger;
use Furgo\Sitechips\Core\Services\WordPressLogger;
use Psr\Log\LoggerInterface;

/**
 * Core Service Provider Class
 *
 * Registers fundamental services required by most WordPress plugins:
 * - Logger (PSR-3 compliant)
 * - Event Manager (WordPress hooks integration)
 * - Asset Manager (CSS/JS management)
 *
 * @since 2.0.0
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register core services into the container
     *
     * Services registered here are available throughout the plugin lifecycle.
     * Registration happens early, before WordPress is fully loaded.
     *
     * @return void
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Plugin configuration
        $this->loadPluginConfig();

        // PSR-3 Logger
        $this->registerLogger();

        // Event Manager
        $this->registerEventManager();

        // Asset Manager
        $this->registerAssetManager();
    }

    /**
     * Boot core services
     *
     * Initializes services that require WordPress to be fully loaded.
     * Sets up admin interface if in admin context.
     *
     * @return void
     *
     * @since 2.0.0
     */
    public function boot(): void
    {
        // Load text domain
        $this->addAction('init', function() {
            load_plugin_textdomain(
                $this->container->get('plugin.text_domain'),
                false,
                dirname($this->container->get('plugin.basename')) . '/languages'
            );
        }, 1);

        // Only add admin features in admin context
        if (is_admin()) {
            //$this->addAction('rss-news-feeder', [$this, 'registerAdminMenu']);
        }

        // Log plugin boot if in debug mode
        if ($this->container->get('debug')) {
            $this->container->get('logger')->info('Plugin booted', [
                'version' => $this->container->get('plugin.version'),
                'environment' => $this->container->get('environment')
            ]);
        }
    }

    /**
     * Register PSR-3 Logger service
     *
     * Uses WordPressLogger in debug mode, NullLogger in production.
     * Can be overridden by registering a custom logger before this provider.
     *
     * @return void
     */
    private function registerLogger(): void
    {
        if ($this->container->has('logger')) {
            return;
        }

        $logger = $this->container->get('debug')
            ? new WordPressLogger(
                $this->container->get('plugin.slug'),
                'debug',
                true,
                true
            )
            : new NullLogger();

        $this->container->set('logger', $logger);
        $this->alias(LoggerInterface::class, 'logger');
    }

    /**
     * Register Event Manager service
     *
     * Provides internal event system with WordPress hooks integration.
     *
     * @return void
     */
    private function registerEventManager(): void
    {
        $eventManager = new EventManager(
            $this->container->get('plugin.slug'),
            !($this->container->has('testing') && $this->container->get('testing'))
        );
        $this->container->set('events', $eventManager);
    }

    /**
     * Register Asset Manager service
     *
     * Manages CSS and JavaScript assets registration and enqueuing.
     *
     * @return void
     */
    private function registerAssetManager(): void
    {
        $assetManager = new AssetManager(
            $this->container->get('plugin.url'),
            $this->container->get('plugin.version')
        );
        $this->container->set('assets', $assetManager);
    }

    /**
     * Load plugin configuration
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function loadPluginConfig(): void
    {
        $configPath = $this->container->get('plugin.path') . 'config/plugin.php';

        if (file_exists($configPath)) {
            $config = require $configPath;

            // Flach im Container speichern
            foreach ($config as $group => $values) {
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        $this->container->set("config.{$group}.{$key}", $value);
                    }
                } else {
                    $this->container->set("config.{$group}", $values);
                }
            }
        }
    }
}