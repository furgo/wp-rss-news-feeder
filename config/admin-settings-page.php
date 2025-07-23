<?php
/**
 * Configuration for admin settings page
 *
 * Defines user-editable settings for the admin interface.
 * Note: Translation keys are used here, actual translations are applied when loading.
 *
 * @package     Furgo\SitechipsBoilerplate
 * @since       2.0.0
 */

return [
    'option_name' => 'rss_news_feeder_settings',

    'sections' => [
        'general' => [
            'title' => 'General Settings',
            'description' => 'Configure the basic plugin settings.',
            'priority' => 10,
            'fields' => [
                'api_key' => [
                    'title' => 'API Key',
                    'type' => 'text',
                    'description' => 'Enter your API key for external services.',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'enable_feature' => [
                    'title' => 'Enable Feature',
                    'type' => 'checkbox',
                    'label' => 'Enable the special feature',
                    'description' => 'Check to enable the awesome boilerplate feature.',
                    'default' => false
                ],
                'items_per_page' => [
                    'title' => 'Items per Page',
                    'type' => 'number',
                    'description' => 'Number of items to display per page.',
                    'default' => 10,
                    'min' => 5,
                    'max' => 100,
                    'step' => 5
                ]
            ]
        ],
        'advanced' => [
            'title' => 'Advanced Settings',
            'description' => 'Advanced configuration options.',
            'priority' => 20,
            'fields' => [
                'debug_mode' => [
                    'title' => 'Debug Mode',
                    'type' => 'select',
                    'description' => 'Select the debug level.',
                    'options' => [
                        'off' => 'Off',
                        'error' => 'Errors Only',
                        'all' => 'All Messages'
                    ],
                    'default' => 'off'
                ],
                'custom_message' => [
                    'title' => 'Custom Message',
                    'type' => 'textarea',
                    'description' => 'Enter a custom message to display.',
                    'default' => 'Hello World from SitechipsBoilerplate!',
                    'rows' => 3,
                    'cols' => 50
                ]
            ]
        ]
    ],

    'page' => [
        'page_title' => 'SitechipsBoilerplate Settings',
        'menu_title' => 'Sitechips',
        'capability' => 'manage_options',
        'menu_slug' => 'rss-news-feeder',
        'position' => 'settings'
    ]
];