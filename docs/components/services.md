# Core Services

> **Built-in services that handle common WordPress plugin needs**

Sitechips Core provides several ready-to-use services that solve common plugin development challenges. These services are optional but designed to work seamlessly with the framework.

## 📋 Available Services

### Event Management
- [**EventManager**](#eventmanager) - Internal event system with WordPress integration

### Logging
- [**NullLogger**](#nulllogger) - PSR-3 logger that discards messages
- [**WordPressLogger**](#wordpresslogger) - PSR-3 logger using WordPress debug log

### Asset Management
- [**AssetManager**](#assetmanager) - JavaScript and CSS asset registration

### Settings Management
- [**SettingsManager**](#settingsmanager) - WordPress Settings API wrapper
- [**SettingsPage**](#settingspage) - Admin settings page creation
- [**FieldRenderer**](#fieldrenderer) - Form field rendering

---

## 📡 EventManager

The EventManager provides an event system that integrates with WordPress hooks while offering additional features like event namespacing and priority management.

### Basic Usage

```php
use Furgo\Sitechips\Core\Services\EventManager;

// Create event manager
$events = new EventManager('my-plugin');

// Listen to events
$events->listen('user.created', function($user) {
    echo "New user: {$user->name}";
});

// Dispatch events
$user = new User(['name' => 'John']);
$events->dispatch('user.created', $user);
```

### WordPress Integration

```php
// Enable WordPress integration (default)
$events = new EventManager('my-plugin', true);

// Events are also fired as WordPress actions
$events->dispatch('order.completed', $order);
// Also fires: do_action('my-plugin.order.completed', $order)

// WordPress hooks can listen
add_action('my-plugin.order.completed', function($order) {
    // React to event
});
```

### Event Filtering

```php
// Register filter listeners
$events->listen('product.price', function($price, $product) {
    if ($product->isOnSale()) {
        return $price * 0.9; // 10% discount
    }
    return $price;
});

// Apply filters
$price = 100;
$finalPrice = $events->filter('product.price', $price, $product);
// $finalPrice = 90 (if on sale)
```

### Advanced Features

```php
// Priority ordering
$events->listen('init', [$this, 'firstHandler'], 5);  // Runs first
$events->listen('init', [$this, 'secondHandler'], 10); // Runs second

// Remove listeners
$events->forget('user.created', $callback);

// Check for listeners
if ($events->hasListeners('user.created')) {
    // Event has handlers
}

// Clear all listeners
$events->clear('user.created'); // Clear specific event
$events->clear(); // Clear all events
```

### Service Provider Registration

```php
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('events', function($container) {
            $pluginSlug = $container->get('plugin.slug');
            $useWordPress = !$container->get('testing', false);
            
            return new EventManager($pluginSlug, $useWordPress);
        });
    }
}
```

---

## 📝 Logging Services

### NullLogger

A no-operation logger that silently discards all messages. Useful for testing or when logging should be disabled.

```php
use Furgo\Sitechips\Core\Services\NullLogger;

$logger = new NullLogger();

// All these calls do nothing
$logger->debug('Debug message');
$logger->info('Info message');
$logger->error('Error message');
$logger->log('custom', 'Custom level message');
```

### WordPressLogger

PSR-3 compliant logger that writes to WordPress debug log with configurable formatting.

```php
use Furgo\Sitechips\Core\Services\WordPressLogger;

// Basic usage
$logger = new WordPressLogger('my-plugin');
$logger->info('Plugin initialized');
$logger->error('API request failed');

// With configuration
$logger = new WordPressLogger(
    'my-plugin',
    LogLevel::WARNING,  // Minimum level
    true,              // Include timestamp
    true               // Include context
);

// With context
$logger->info('User action', [
    'user_id' => 123,
    'action' => 'login',
    'ip' => $_SERVER['REMOTE_ADDR']
]);
// Logs: [2024-01-15 10:30:45] [my-plugin] INFO: User action Context: {"user_id":123,"action":"login","ip":"192.168.1.1"}

// With placeholders
$logger->info('User {username} logged in from {ip}', [
    'username' => 'john_doe',
    'ip' => '192.168.1.1'
]);
// Logs: [my-plugin] INFO: User john_doe logged in from 192.168.1.1

// Exception logging
try {
    $api->request();
} catch (Exception $e) {
    $logger->error('API request failed', ['exception' => $e]);
}
```

### Logger Registration

```php
class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('logger', function($container) {
            if ($container->get('logging.enabled', true)) {
                return new WordPressLogger(
                    $container->get('plugin.slug'),
                    $container->get('logging.level', 'debug')
                );
            }
            
            return new NullLogger();
        });
        
        // PSR-3 alias
        $this->alias(LoggerInterface::class, 'logger');
    }
}
```

---

## 🎨 AssetManager

Manages registration and enqueuing of JavaScript and CSS assets with automatic URL resolution.

### Basic Usage

```php
use Furgo\Sitechips\Core\Services\AssetManager;

// Create asset manager
$assets = new AssetManager($pluginUrl, $pluginVersion);

// Add scripts
$assets->addScript('my-admin', 'assets/js/admin.js', ['jquery'])
       ->addScript('my-api', 'assets/js/api.js', ['wp-api-request']);

// Add styles
$assets->addStyle('my-admin', 'assets/css/admin.css')
       ->addStyle('my-editor', 'assets/css/editor.css', ['wp-edit-blocks']);

// Enqueue all registered assets
$assets->enqueue();
```

### Script Configuration

```php
// With all options
$assets->addScript(
    'my-script',              // Handle
    'assets/js/script.js',    // Source (relative or absolute)
    ['jquery', 'lodash'],     // Dependencies
    false                     // In footer (default: true)
);

// Script localization
$assets->addScript('my-ajax', 'assets/js/ajax.js')
       ->localizeScript('my-ajax', 'MyAjax', [
           'ajaxUrl' => admin_url('admin-ajax.php'),
           'nonce' => wp_create_nonce('my-ajax-nonce'),
           'strings' => [
               'loading' => __('Loading...', 'my-plugin'),
               'error' => __('An error occurred', 'my-plugin')
           ]
       ]);

// Inline scripts
$assets->addScript('my-app', 'assets/js/app.js')
       ->addInlineScript('my-app', 'MyApp.init(' . json_encode($config) . ');');
```

### Style Configuration

```php
// With media query
$assets->addStyle(
    'my-print',              // Handle
    'assets/css/print.css',  // Source
    [],                      // Dependencies
    'print'                  // Media query
);

// Inline styles
$assets->addStyle('my-theme', 'assets/css/theme.css')
       ->addInlineStyle('my-theme', '.primary-color { color: ' . $primaryColor . '; }');
```

### Conditional Loading

```php
class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
    }
    
    public function enqueueAdminAssets(string $hook): void
    {
        $assets = $this->container->get('assets.admin');
        
        // Load only on specific pages
        if ($hook === 'toplevel_page_my-plugin') {
            $assets->addScript('my-settings', 'assets/js/settings.js')
                   ->addStyle('my-settings', 'assets/css/settings.css')
                   ->enqueue();
        }
    }
    
    public function enqueueFrontendAssets(): void
    {
        $assets = $this->container->get('assets.frontend');
        
        // Conditional loading
        if (is_singular('product')) {
            $assets->addScript('product-gallery', 'assets/js/gallery.js')
                   ->enqueue();
        }
    }
}
```

### Service Registration

```php
public function register(): void
{
    // Admin assets
    $this->shared('assets.admin', function($container) {
        return new AssetManager(
            $container->get('plugin.url'),
            $container->get('plugin.version')
        );
    });
    
    // Frontend assets
    $this->shared('assets.frontend', function($container) {
        return new AssetManager(
            $container->get('plugin.url'),
            $container->get('plugin.version')
        );
    });
}
```

---

## ⚙️ Settings Management

### SettingsManager

Core functionality for managing WordPress plugin settings using the Settings API.

```php
use Furgo\Sitechips\Core\Services\Settings\SettingsManager;

// Create settings manager
$settings = new SettingsManager('my_plugin_settings');

// Add sections and fields
$settings->addSection('general', 'General Settings')
         ->addField('api_key', 'API Key', 'general', 'text', [
             'description' => 'Enter your API key',
             'required' => true
         ])
         ->addField('enable_debug', 'Enable Debug Mode', 'general', 'checkbox', [
             'label' => 'Enable debug logging',
             'default' => false
         ]);

// Register with WordPress
$settings->register();

// Get values
$apiKey = $settings->getValue('api_key');
$debugEnabled = $settings->getValue('enable_debug', false);
```

### Field Types

```php
// Text input
$settings->addField('name', 'Name', 'section', 'text');

// Email input
$settings->addField('email', 'Email', 'section', 'email', [
    'description' => 'Your email address',
    'required' => true
]);

// Number input
$settings->addField('timeout', 'Timeout', 'section', 'number', [
    'min' => 1,
    'max' => 300,
    'step' => 1,
    'default' => 30
]);

// Textarea
$settings->addField('description', 'Description', 'section', 'textarea', [
    'rows' => 5,
    'cols' => 50
]);

// Select dropdown
$settings->addField('country', 'Country', 'section', 'select', [
    'options' => [
        'us' => 'United States',
        'uk' => 'United Kingdom',
        'de' => 'Germany'
    ],
    'default' => 'us'
]);

// Radio buttons
$settings->addField('plan', 'Plan', 'section', 'radio', [
    'options' => [
        'free' => 'Free',
        'pro' => 'Professional',
        'enterprise' => 'Enterprise'
    ]
]);

// Checkbox
$settings->addField('newsletter', 'Newsletter', 'section', 'checkbox', [
    'label' => 'Subscribe to newsletter'
]);

// Color picker
$settings->addField('theme_color', 'Theme Color', 'section', 'color', [
    'default' => '#0073aa'
]);
```

### Validation and Sanitization

```php
// Custom sanitization
$settings->addField('api_key', 'API Key', 'general', 'text', [
    'sanitize_callback' => function($value) {
        // Remove spaces and validate format
        $value = preg_replace('/\s+/', '', $value);
        if (!preg_match('/^[A-Z0-9]{32}$/', $value)) {
            add_settings_error('my_plugin_settings', 'invalid_api_key', 'Invalid API key format');
            return get_option('my_plugin_settings')['api_key'] ?? '';
        }
        return $value;
    }
]);

// Custom validation
$settings->addField('port', 'Port', 'network', 'number', [
    'validate_callback' => function($value) {
        return $value >= 1 && $value <= 65535;
    }
]);
```

### SettingsPage

Creates WordPress admin settings pages with configurable menu position.

```php
use Furgo\Sitechips\Core\Services\Settings\SettingsPage;

// Create settings page
$page = new SettingsPage($settings, [
    'page_title' => 'My Plugin Settings',
    'menu_title' => 'My Plugin',
    'capability' => 'manage_options',
    'menu_slug' => 'my-plugin-settings',
    'position' => 'settings'  // options: toplevel, settings, tools, users, plugins, theme
]);

// Register the page
$page->register();

// For top-level menu
$page = new SettingsPage($settings, [
    'page_title' => 'My Plugin',
    'menu_title' => 'My Plugin',
    'capability' => 'manage_options',
    'menu_slug' => 'my-plugin',
    'position' => 'toplevel',
    'icon_url' => 'dashicons-admin-generic',
    'menu_position' => 30
]);
```

### Complete Settings Example

```php
class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('settings.manager', function($container) {
            $settings = new SettingsManager('my_plugin_settings');
            
            // API Settings
            $settings->addSection('api', 'API Configuration', function() {
                echo '<p>Configure your API connection.</p>';
            });
            
            $settings->addField('api_endpoint', 'API Endpoint', 'api', 'url', [
                'default' => 'https://api.example.com',
                'description' => 'The API endpoint URL'
            ]);
            
            $settings->addField('api_key', 'API Key', 'api', 'password', [
                'description' => 'Your secret API key'
            ]);
            
            // Display Settings
            $settings->addSection('display', 'Display Options');
            
            $settings->addField('items_per_page', 'Items Per Page', 'display', 'number', [
                'min' => 10,
                'max' => 100,
                'step' => 10,
                'default' => 20
            ]);
            
            $settings->addField('theme', 'Theme', 'display', 'select', [
                'options' => [
                    'light' => 'Light',
                    'dark' => 'Dark',
                    'auto' => 'Auto'
                ],
                'default' => 'light'
            ]);
            
            return $settings;
        });
        
        $this->shared('settings.page', function($container) {
            return new SettingsPage(
                $container->get('settings.manager'),
                [
                    'page_title' => 'My Plugin Settings',
                    'menu_title' => 'My Plugin',
                    'capability' => 'manage_options',
                    'menu_slug' => 'my-plugin',
                    'position' => 'settings'
                ]
            );
        });
    }
    
    public function boot(): void
    {
        $this->container->get('settings.manager')->register();
        $this->container->get('settings.page')->register();
    }
}
```

### FieldRenderer

The FieldRenderer handles the actual rendering of form fields. It's used internally by SettingsManager but can be used directly for custom forms.

```php
use Furgo\Sitechips\Core\Services\Settings\FieldRenderer;

$renderer = new FieldRenderer('my_plugin_options');

// Render a field
$field = [
    'id' => 'email',
    'type' => 'email',
    'required' => true,
    'description' => 'Your email address'
];

$renderer->render($field, $currentValue);
```

---

## 🎯 Using Services Together

### Complete Example

```php
class MyPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Event Manager
        $this->shared('events', function($container) {
            return new EventManager($container->get('plugin.slug'));
        });
        
        // Logger
        $this->shared('logger', function($container) {
            if ($container->get('debug')) {
                return new WordPressLogger($container->get('plugin.slug'));
            }
            return new NullLogger();
        });
        
        // Asset Manager
        $this->shared('assets', function($container) {
            return new AssetManager(
                $container->get('plugin.url'),
                $container->get('plugin.version')
            );
        });
        
        // Settings
        $this->shared('settings', function($container) {
            $settings = new SettingsManager('my_plugin_settings');
            // Configure settings...
            return $settings;
        });
    }
    
    public function boot(): void
    {
        // Use services together
        $logger = $this->container->get('logger');
        $events = $this->container->get('events');
        
        // Log when settings are saved
        $events->listen('settings.saved', function($settings) use ($logger) {
            $logger->info('Settings updated', ['settings' => $settings]);
        });
        
        // Enqueue assets based on settings
        add_action('wp_enqueue_scripts', function() {
            $settings = $this->container->get('settings');
            $assets = $this->container->get('assets');
            
            if ($settings->getValue('enable_frontend_features')) {
                $assets->addScript('my-frontend', 'assets/js/frontend.js')
                       ->addStyle('my-frontend', 'assets/css/frontend.css')
                       ->enqueue();
            }
        });
    }
}
```

## 💡 Best Practices

1. **Register services as singletons** - Most services should be shared instances
2. **Use interfaces** - Register against interfaces for flexibility
3. **Configure via container** - Pass configuration through container definitions
4. **Lazy initialization** - Services are created only when needed
5. **Combine services** - Services work best when used together

---

Continue to the [**Development Guides**](../guides/README.md) →