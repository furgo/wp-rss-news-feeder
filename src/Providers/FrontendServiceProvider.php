<?php
/**
 * Frontend Service Provider
 *
 * Handles frontend output and shortcodes for the plugin.
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

/**
 * Frontend Service Provider Class
 *
 * @since 2.0.0
 */
class FrontendServiceProvider extends ServiceProvider
{
    /**
     * Register frontend services
     *
     * @return void
     */
    public function register(): void
    {
        // Register any frontend-specific services here
        // For this minimal example, we'll just use the boot method
    }

    /**
     * Boot frontend services
     *
     * @return void
     */
    public function boot(): void
    {
        // Register shortcode
        if ($this->container->get('config.features.enable_shortcodes')) {
            $shortcodeName = $this->container->get('config.shortcodes.hello');
            add_shortcode($shortcodeName, [$this, 'renderHelloShortcode']);
        }

        // Add content filter example
        if ($this->container->get('config.features.enable_content_filter')) {
            $this->addFilter('rss-news-feeder', [$this, 'filterContent'], 20);
        }

        // Log frontend initialization
        $this->container->get('logger')->debug('Frontend services initialized');
    }

    /**
     * Render the hello shortcode
     *
     * Usage: [rss_news_feeder_hello show_settings="yes"]
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function renderHelloShortcode($atts): string
    {
        $atts = shortcode_atts([
            'rss-news-feeder' => 'no'
        ], $atts);

        $settings = $this->container->get('settings.manager');
        $customMessage = $settings->getValue('rss-news-feeder', 'Hello World from Sitechips Boilerplate!');

        $output = '<div class="sitechips-boilerplate-hello">';
        $output .= '<h3>' . esc_html($customMessage) . '</h3>';

        if ($atts['rss-news-feeder'] === 'yes') {
            $output .= $this->renderSettingsInfo();
        }

        $output .= '</div>';

        // Log shortcode usage
        $this->container->get('logger')->debug('Hello shortcode rendered', [
            'rss-news-feeder' => $atts['rss-news-feeder']
        ]);

        return $output;
    }

    /**
     * Filter content to add plugin info if feature is enabled
     *
     * @param string $content Post content
     * @return string Modified content
     */
    public function filterContent(string $content): string
    {
        // Only on singular posts/pages
        if (!is_singular()) {
            return $content;
        }

        $settings = $this->container->get('settings.manager');

        // Check if feature is enabled
        if (!$settings->getValue('rss-news-feeder', false)) {
            return $content;
        }

        // Add a notice at the end of content
        $notice = sprintf(
            '<div class="sitechips-boilerplate-notice" style="padding: 10px; background: #f0f0f0; margin: 20px 0; border-left: 4px solid #0073aa;">
                <p><strong>%s</strong></p>
                <p style="font-size: 0.9em; color: #666;">%s</p>
            </div>',
            esc_html__('Sitechips Boilerplate Feature Active!', 'rss-news-feeder'),
            esc_html__('This message appears because the special feature is enabled in settings.', 'rss-news-feeder')
        );

        return $content . $notice;
    }

    /**
     * Render current settings information
     *
     * @return string
     */
    private function renderSettingsInfo(): string
    {
        $settings = $this->container->get('settings.manager');

        $output = '<div class="sitechips-settings-info" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">';
        $output .= '<h4>' . esc_html__('Current Settings:', 'rss-news-feeder') . '</h4>';
        $output .= '<ul style="list-style: disc; margin-left: 20px;">';

        // API Key (masked for security)
        $apiKey = $settings->getValue('rss-news-feeder', '');
        $maskedKey = !empty($apiKey) ? substr($apiKey, 0, 4) . '****' : __('Not set', 'rss-news-feeder');
        $output .= sprintf(
            '<li><strong>%s:</strong> %s</li>',
            esc_html__('API Key', 'rss-news-feeder'),
            esc_html($maskedKey)
        );

        // Feature Status
        $featureEnabled = $settings->getValue('rss-news-feeder', false);
        $output .= sprintf(
            '<li><strong>%s:</strong> %s</li>',
            esc_html__('Special Feature', 'rss-news-feeder'),
            $featureEnabled
                ? '<span style="color: green;">✓ ' . esc_html__('Enabled', 'rss-news-feeder') . '</span>'
                : '<span style="color: red;">✗ ' . esc_html__('Disabled', 'rss-news-feeder') . '</span>'
        );

        // Items per page
        $itemsPerPage = $settings->getValue('rss-news-feeder', 10);
        $output .= sprintf(
            '<li><strong>%s:</strong> %d</li>',
            esc_html__('Items per Page', 'rss-news-feeder'),
            $itemsPerPage
        );

        // Debug Mode
        $debugMode = $settings->getValue('rss-news-feeder', 'off');
        $debugLabels = [
            'off' => __('Off', 'rss-news-feeder'),
            'error' => __('Errors Only', 'rss-news-feeder'),
            'all' => __('All Messages', 'rss-news-feeder')
        ];
        $output .= sprintf(
            '<li><strong>%s:</strong> %s</li>',
            esc_html__('Debug Mode', 'rss-news-feeder'),
            esc_html($debugLabels[$debugMode] ?? $debugMode)
        );

        $output .= '</ul>';
        $output .= '<p style="margin-top: 10px; font-size: 0.9em; color: #666;">';
        $output .= sprintf(
            esc_html__('Plugin Version: %s | Environment: %s', 'rss-news-feeder'),
            esc_html($this->container->get('plugin.version')),
            esc_html($this->container->get('environment'))
        );
        $output .= '</p>';
        $output .= '</div>';

        return $output;
    }
}