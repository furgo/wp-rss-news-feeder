<?php
/**
 * Plugin Configuration
 *
 * Central configuration file for plugin-wide settings.
 * These values are not user-editable and define the plugin's behavior.
 *
 * @package     Furgo\SitechipsBoilerplate
 * @since       2.0.0
 */

return [
    // Plugin Identity
    'plugin' => [
        'text_domain' => 'rss-news-feeder',
        'option_name' => 'rss_news_feeder_settings',
        'version_option' => 'rss_news_feeder_version',
    ],

    // Paths and URLs
    'paths' => [
        'cache_dir' => WP_CONTENT_DIR . '/cache/rss-news-feeder',
        'upload_dir' => '/rss-news-feeder',  // Relative to wp-content/uploads
        'settings_config' => 'config/admin-settings-page.php',
    ],

    // WordPress Integration
    'wordpress' => [
        'cron_hook' => 'rss_news_feeder_daily_task',
        'transient_prefix' => 'rss_news_feeder_reader_',
        'settings_page_slug' => 'rss-news-feeder',
    ],

    // Shortcodes
    'shortcodes' => [
        'hello' => 'sitechips_hello',
    ],

    // API Configuration (example)
    'api' => [
        'endpoint' => 'https://api.example.com/v2',
        'timeout' => 30,
        'retry_count' => 3,
        'user_agent' => 'RssNewsFeeder/2.0',
    ],

    // Feature Flags
    'features' => [
        'enable_cron' => true,
        'enable_shortcodes' => true,
        'enable_content_filter' => true,
        'enable_transient_cleanup' => true,
    ],

    // Development/Debug
    'development' => [
        'log_lifecycle_events' => true,
        'log_settings_changes' => true,
    ],
];