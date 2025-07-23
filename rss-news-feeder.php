<?php
/**
 * Plugin Name: RssNewsFeeder
 * Plugin URI: https://github.com/furgo/sitechips-boilerplate
 * Description: RSS Feed Aggregation System with WordPress Backend and JavaScript Frontend
 * Version: 2.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Axel Wüstemann
 * Author URI: wuestemann.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rss-news-feeder
 * Domain Path: /languages
 *
 * @package Furgo\RssNewsFeeder
 * @author  Axel Wüstemann
 * @since   2.0.0
 */

declare(strict_types=1);

// Prevent direct access
defined('ABSPATH') || exit;

// Check for Composer autoloader
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Rss News Feeder:</strong> Composer dependencies missing. Run <code>composer install</code>.';
        echo '</p></div>';
    });
    return;
}

//require_once __DIR__ . '/vendor/autoload.php';

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!defined('RSS_NEWS_FEEDER_LOADED')) {
    define('RSS_NEWS_FEEDER_LOADED', true);
    require_once __DIR__ . '/vendor/autoload.php';
}

use Furgo\RssNewsFeeder\RssNewsFeeder;

// Initialize plugin on 'plugins_loaded' hook
add_action('plugins_loaded', function (): void {
    try {
        RssNewsFeeder::boot();
    } catch (Exception $e) {
        add_action('admin_notices', function () use ($e) {
            printf(
                '<div class="notice notice-error"><p><strong>Rss News Feeder Error:</strong> %s</p></div>',
                esc_html($e->getMessage())
            );
        });
    }
}, 5);

// Register plugin lifecycle hooks
register_activation_hook(__FILE__, [RssNewsFeeder::class, 'activate']);
register_deactivation_hook(__FILE__, [RssNewsFeeder::class, 'deactivate']);
register_uninstall_hook(__FILE__, [RssNewsFeeder::class, 'uninstall']);

// Add settings link to plugin list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    try {
        $pageSlug = RssNewsFeeder::get('config.wordpress.settings_page_slug');
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=' . $pageSlug),
            'Settings'
        );
        array_unshift($links, $settings_link);
    } catch (\Exception $e) {
        // Fallback if config not available
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=rss-news-feeder'),
            'Settings'
        );
        array_unshift($links, $settings_link);
    }
    return $links;
});