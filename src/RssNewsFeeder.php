<?php
/**
 * RssNewsFeeder - Service Locator
 *
 * Central service locator for the RssNewsFeeder plugin.
 * Provides static access to all plugin services and functionality.
 *
 * @package     Furgo\RssNewsFeeder
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       2.0.0
 */

declare(strict_types=1);

namespace Furgo\RssNewsFeeder;

use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;
use Furgo\Sitechips\Core\Plugin\AbstractServiceLocator;
use Furgo\RssNewsFeeder\Providers\CoreServiceProvider;
use Furgo\RssNewsFeeder\Providers\SettingsServiceProvider;
use Furgo\RssNewsFeeder\Providers\FrontendServiceProvider;

/**
 * RssNewsFeeder Service Locator
 *
 * Central access point for all plugin services using Service Locator pattern.
 * Handles plugin lifecycle including activation, deactivation and uninstall.
 *
 * @since 2.0.0
 */
class RssNewsFeeder extends AbstractServiceLocator
{
    /**
     * Plugin lifecycle events with log messages
     *
     * @var array<string, array{message: string, level: string}>
     */
    private const LIFECYCLE_EVENTS = [
        'activating'    => ['message' => 'Plugin activation started', 'level' => 'info'],
        'activated'     => ['message' => 'Plugin activated successfully', 'level' => 'info'],
        'deactivating'  => ['message' => 'Plugin deactivation started', 'level' => 'info'],
        'deactivated'   => ['message' => 'Plugin deactivated', 'level' => 'info'],
        'uninstalling'  => ['message' => 'Plugin uninstall started', 'level' => 'warning'],
        'uninstalled'   => ['message' => 'Plugin uninstalled', 'level' => 'warning']
    ];

    /**
     * Activation callback
     *
     * Called when plugin is being activated.
     * Sets up default options and creates necessary resources.
     *
     * @return void
     */
    public static function activate(): void
    {
        try {
            static::dispatch('activating');

            // Load default settings from config
            $defaults = static::getDefaultSettings();

            // Create default options if they don't exist
            $optionName = static::get('config.plugin.option_name');
            if (false === get_option($optionName)) {
                add_option($optionName, $defaults);
            }

            // Create cache directory
            $cacheDir = static::get('cache.path');
            if (!file_exists($cacheDir)) {
                wp_mkdir_p($cacheDir);
            }

            // Schedule cron events
            if (static::get('config.features.enable_cron')) {
                $cronHook = static::get('config.wordpress.cron_hook');
                if (!wp_next_scheduled($cronHook)) {
                    wp_schedule_event(time(), 'daily', $cronHook);
                }
            }

            static::dispatch('activated');

        } catch (\Exception $e) {
            // Don't throw during activation to prevent breaking the site
            error_log('RssNewsFeeder activation error: ' . $e->getMessage());
        }
    }

    /**
     * Deactivation callback
     *
     * Called when plugin is being deactivated.
     * Cleans up scheduled tasks and temporary data.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        try {
            static::dispatch('deactivating');

            // Clear scheduled tasks
            $cronHook = static::get('config.wordpress.cron_hook');
            wp_clear_scheduled_hook($cronHook);

            // Clear transients
            if (static::get('config.features.enable_transient_cleanup')) {
                global $wpdb;
                $transientPrefix = static::get('config.wordpress.transient_prefix');
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                        '_transient_' . $transientPrefix . '%'
                    )
                );
            }

            static::dispatch('deactivated');

        } catch (\Exception $e) {
            error_log('RssNewsFeeder deactivation error: ' . $e->getMessage());
        }
    }

    /**
     * Uninstall callback
     *
     * Called when plugin is being uninstalled.
     * Removes all plugin data including options and custom tables.
     *
     * @return void
     */
    public static function uninstall(): void
    {
        try {
            static::dispatch('uninstalling');

            // Remove plugin options
            delete_option(static::get('config.plugin.option_name'));
            delete_option(static::get('config.plugin.version_option'));

            // Remove custom tables if any
            // global $wpdb;
            // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}rss_news_feeder_data");

            // Remove uploaded files/directories
            $uploadDir = wp_upload_dir();
            $pluginDir = $uploadDir['basedir'] . static::get('config.paths.upload_dir');
            if (is_dir($pluginDir)) {
                static::recursiveRemoveDirectory($pluginDir);
            }

            static::dispatch('uninstalled');

        } catch (\Exception $e) {
            error_log('RssNewsFeeder uninstall error: ' . $e->getMessage());
        }
    }

    /**
     * Create the plugin instance
     *
     * Sets up the plugin with configuration and service providers.
     *
     * @return Plugin
     * @throws \RuntimeException If plugin creation fails
     */
    protected static function setupPlugin(): Plugin
    {
        $pluginFile = dirname(__DIR__) . '/rss-news-feeder.php';

        try {
            // Load plugin configuration
            $pluginConfig = require dirname(__DIR__) . '/config/plugin.php';

            // Core configuration
            $config = [
                'plugin.text_domain' => $pluginConfig['plugin']['text_domain'],
                'cache.path' => $pluginConfig['paths']['cache_dir'],
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
            ];

            // Service providers
            $providers = [
                CoreServiceProvider::class,
                SettingsServiceProvider::class,
                FrontendServiceProvider::class,
            ];

            $plugin = PluginFactory::create($pluginFile, $config, $providers);

            // Setup lifecycle event listeners
            static::setupEventListeners($plugin);

            return $plugin;

        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create plugin: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Setup event listeners for plugin lifecycle
     *
     * Registers logging for all lifecycle events.
     *
     * @param Plugin $plugin
     * @return void
     */
    private static function setupEventListeners(Plugin $plugin): void
    {
        if (!$plugin->get('config.development.log_lifecycle_events')) {
            return;
        }

        $pluginSlug = $plugin->get('plugin.slug');

        foreach (self::LIFECYCLE_EVENTS as $event => $config) {
            add_action(
                "{$pluginSlug}.{$event}",
                fn() => $plugin->log($config['message'], $config['level'])
            );
        }
    }

    /**
     * Get default settings from config file
     *
     * Loads default settings from config/admin-settings-page.php
     * and extracts only the default values.
     *
     * @return array<string, mixed>
     */
    private static function getDefaultSettings(): array
    {
        $configFile = dirname(__DIR__) . '/' . static::get('config.paths.settings_config');

        if (!file_exists($configFile)) {
            // Fallback defaults if config doesn't exist
            return [
                'api_key' => '',
                'enable_feature' => false,
                'items_per_page' => 10,
                'debug_mode' => 'off',
                'custom_message' => 'Hello World from RssNewsFeeder!'
            ];
        }

        $config = require $configFile;
        $defaults = [];

        // Extract defaults from config structure
        foreach ($config['sections'] as $section) {
            foreach ($section['fields'] as $fieldId => $field) {
                if (isset($field['default'])) {
                    $defaults[$fieldId] = $field['default'];
                }
            }
        }

        return $defaults;
    }

    /**
     * Recursively remove a directory
     *
     * Helper method to remove directories during uninstall.
     *
     * @param string $dir Directory path to remove
     * @return void
     */
    private static function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? static::recursiveRemoveDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}