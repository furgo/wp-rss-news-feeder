<?php
/**
 * Settings Service Provider
 *
 * Registers settings management services and admin settings page.
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
use Furgo\Sitechips\Core\Services\Settings\SettingsManager;
use Furgo\Sitechips\Core\Services\Settings\SettingsPage;

/**
 * Settings Service Provider Class
 *
 * @since 2.0.0
 */
class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register settings services
     *
     * @return void
     */
    public function register(): void
    {
        $optionName = $this->container->get('config.plugin.option_name');
        $settings = new SettingsManager($optionName);

        // Store settings manager first
        $this->container->set('settings.manager', $settings);

        // Settings page will be created in boot phase
        $this->container->set('settings.page', null);
    }

    /**
     * Boot settings services
     *
     * @return void
     */
    public function boot(): void
    {
        // Only setup in admin
        if (!is_admin()) {
            return;
        }

        // Defer settings setup to init hook when translations are ready
        $this->addAction('init', function() {
            $this->setupSettings();
            $this->setupSettingsPage();

            // Register settings page
            $this->container->get('settings.page')->register();
        }, 5);

        // Log settings changes
        if ($this->container->get('config.development.log_settings_changes')) {
            $optionName = $this->container->get('config.plugin.option_name');
            add_action('rss-news-feeder' . $optionName, function($old_value, $value) {
                $this->container->get('logger')->info('Settings updated', [
                    'rss-news-feeder' => array_keys(array_diff_assoc($value, $old_value))
                ]);
            }, 10, 2);
        }
    }

    /**
     * Setup settings sections and fields
     *
     * Called during boot phase when translations are available.
     *
     * @return void
     */
    private function setupSettings(): void
    {
        $configPath = $this->container->get('plugin.path') . $this->container->get('config.paths.settings_config');

        if (!file_exists($configPath)) {
            throw new \RuntimeException('Settings configuration file not found');
        }

        $config = require $configPath;
        $settings = $this->container->get('settings.manager');
        $textDomain = $this->container->get('plugin.text_domain');

        foreach ($config['sections'] as $sectionId => $section) {
            $settings->addSection(
                $sectionId,
                __($section['title'], $textDomain),
                isset($section['description'])
                    ? function() use ($section, $textDomain) {
                    echo '<p>' . esc_html__($section['description'], $textDomain) . '</p>';
                }
                    : null,
                $section['priority'] ?? 10
            );

            foreach ($section['fields'] as $fieldId => $field) {
                // Übersetze Labels
                $field['title'] = __($field['title'], $textDomain);
                if (isset($field['description'])) {
                    $field['description'] = __($field['description'], $textDomain);
                }
                if (isset($field['label'])) {
                    $field['label'] = __($field['label'], $textDomain);
                }
                if (isset($field['options'])) {
                    foreach ($field['options'] as $key => $label) {
                        $field['options'][$key] = __($label, $textDomain);
                    }
                }

                $settings->addField($fieldId, $field['title'], $sectionId, $field['type'], $field);
            }
        }
    }

    /**
     * Setup settings page
     *
     * Creates the settings page instance with translated strings.
     *
     * @return void
     */
    private function setupSettingsPage(): void
    {
        $pageSlug = $this->container->get('config.wordpress.settings_page_slug');

        $settingsPage = new SettingsPage(
            $this->container->get('settings.manager'),
            [
                'page_title' => __('Sitechips Boilerplate Settings', 'rss-news-feeder'),
                'menu_title' => __('Sitechips', 'rss-news-feeder'),
                'capability' => 'manage_options',
                'menu_slug'  => $pageSlug,
                'position'   => 'settings'
            ]
        );

        $this->container->set('settings.page', $settingsPage);
    }
}