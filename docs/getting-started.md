# Getting Started with Sitechips Core

> **From zero to modern WordPress plugin in 5 minutes**

This guide will help you create your first WordPress plugin using Sitechips Core. We'll start simple and gradually introduce more advanced features.

## 📋 Prerequisites

Before you begin, ensure you have:

- **PHP 8.1+** installed (`php -v`)
- **Composer** installed (`composer --version`)
- **WordPress 6.4+** installation (local or staging)
- Basic knowledge of PHP and WordPress plugin development

## 🚀 Quick Start

### 1. Create Your Plugin

```bash
# Create plugin directory
mkdir my-awesome-plugin
cd my-awesome-plugin

# Initialize composer
composer init --name="mycompany/my-awesome-plugin" --type=wordpress-plugin

# Install Sitechips Core (when available publicly)
# For now, copy the framework files from the boilerplate
```

### 2. Create Plugin Structure

```
my-awesome-plugin/
├── my-awesome-plugin.php    # Main plugin file
├── composer.json            
├── .gitignore
├── lib/                     # Sitechips Core (copy from boilerplate)
└── src/                     # Your plugin code
```

### 3. Main Plugin File

Create `my-awesome-plugin.php`:

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Description: A modern WordPress plugin built with Sitechips Core
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: my-awesome-plugin
 */

declare(strict_types=1);

// Prevent direct access
defined('ABSPATH') || exit;

// Composer autoloader
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Please run <code>composer install</code> in the plugin directory.</p></div>';
    });
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Create and boot plugin
use Furgo\Sitechips\Core\Plugin\PluginFactory;

$plugin = PluginFactory::create(__FILE__);

add_action('plugins_loaded', function() use ($plugin) {
    $plugin->boot();
});
```

### 4. Configure Composer

Edit `composer.json`:

```json
{
    "name": "mycompany/my-awesome-plugin",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "Furgo\\Sitechips\\Core\\": "lib/",
            "MyCompany\\MyAwesomePlugin\\": "src/"
        }
    }
}
```

### 5. Install Dependencies

```bash
composer install
```

### 6. Activate Your Plugin

1. Move your plugin folder to `wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Activate "My Awesome Plugin"

🎉 **Congratulations!** You now have a working plugin with Sitechips Core.

## 🏗️ Building Your First Feature

Let's add a simple feature to demonstrate the framework.

### Example: Hello World Admin Page

#### 1. Create a Service Provider

Create `src/Providers/AdminServiceProvider.php`:

```php
<?php
declare(strict_types=1);

namespace MyCompany\MyAwesomePlugin\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register services that other providers might need
    }

    public function boot(): void
    {
        // Add WordPress hooks
        $this->addAction('admin_menu', [$this, 'registerAdminMenu']);
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            'My Awesome Plugin',           // Page title
            'My Awesome Plugin',           // Menu title
            'manage_options',              // Capability
            'my-awesome-plugin',           // Menu slug
            [$this, 'renderAdminPage'],    // Callback
            'dashicons-superhero',         // Icon
            30                             // Position
        );
    }

    public function renderAdminPage(): void
    {
        $version = $this->container->get('plugin.version');
        ?>
        <div class="wrap">
            <h1>My Awesome Plugin</h1>
            <p>Welcome to version <?php echo esc_html($version); ?>!</p>
        </div>
        <?php
    }
}
```

#### 2. Register the Provider

Update your main plugin file:

```php
$plugin = PluginFactory::create(__FILE__, [], [
    MyCompany\MyAwesomePlugin\Providers\AdminServiceProvider::class
]);
```

## 🎨 Different Approaches

### Approach 1: Simple (No Service Providers)

For small plugins, you can work directly with the plugin instance:

```php
$plugin = PluginFactory::create(__FILE__, [
    // Configuration
    'api.url' => 'https://api.example.com',
    'cache.ttl' => 3600,
    
    // Register services inline
    'my.service' => function() {
        return new MyService();
    }
]);

// Use services directly
add_action('init', function() use ($plugin) {
    $service = $plugin->get('my.service');
    $service->doSomething();
});
```

### Approach 2: Service Locator Pattern

For medium complexity, use the Service Locator pattern:

```php
// src/MyPlugin.php
namespace MyCompany\MyAwesomePlugin;

use Furgo\Sitechips\Core\Plugin\AbstractServiceLocator;
use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;

class MyPlugin extends AbstractServiceLocator
{
    protected static function setupPlugin(): Plugin
    {
        return PluginFactory::create(
            dirname(__DIR__) . '/my-awesome-plugin.php',
            [
                'api.key' => get_option('my_plugin_api_key'),
                'features.pro' => defined('MY_PLUGIN_PRO'),
            ],
            [
                Providers\CoreServiceProvider::class,
                Providers\AdminServiceProvider::class,
            ]
        );
    }
}
```

Then use it anywhere:

```php
use MyCompany\MyAwesomePlugin\MyPlugin;

// Static access from anywhere
$logger = MyPlugin::get('logger');
$version = MyPlugin::version();

// In WordPress hooks
add_action('init', function() {
    MyPlugin::get('importer')->run();
});
```

### Approach 3: Full Architecture (Large Plugins)

For complex plugins, use the full architecture with multiple providers:

```php
$plugin = PluginFactory::create(__FILE__, $config, [
    // Core functionality
    Providers\CoreServiceProvider::class,
    Providers\DatabaseServiceProvider::class,
    
    // Features
    Providers\AdminServiceProvider::class,
    Providers\ApiServiceProvider::class,
    Providers\ImportServiceProvider::class,
    
    // Integrations
    Providers\WooCommerceServiceProvider::class,
]);
```

## 🧪 Adding Your First Test

Create `tests/Unit/MyFirstTest.php`:

```php
<?php
namespace MyCompany\MyAwesomePlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Furgo\Sitechips\Core\Plugin\PluginFactory;

class MyFirstTest extends TestCase
{
    public function testPluginCreation(): void
    {
        $plugin = PluginFactory::createForTesting('/tmp/test.php', [
            'plugin.name' => 'Test Plugin'
        ]);
        
        $this->assertEquals('Test Plugin', $plugin->get('plugin.name'));
        $this->assertTrue($plugin->get('debug')); // Always true in tests
    }
}
```

Run tests:
```bash
vendor/bin/phpunit tests/
```

## 📦 Using Composer Packages Safely

One of Sitechips Core's killer features is safe Composer usage via Strauss:

### 1. Install Any Package

```bash
composer require guzzlehttp/guzzle
```

### 2. Configure Strauss

Create `.strauss.json`:

```json
{
    "target_directory": "src/Libs",
    "namespace_prefix": "MyCompany\\MyAwesomePlugin\\Libs\\",
    "packages": [
        "guzzlehttp/guzzle",
        "psr/http-message"
    ],
    "exclude_from_prefix": {
        "namespaces": ["Furgo\\Sitechips\\Core\\"]
    }
}
```

### 3. Use Without Fear

```php
use MyCompany\MyAwesomePlugin\Libs\GuzzleHttp\Client;

class ApiService
{
    private Client $client;
    
    public function __construct()
    {
        // Your Guzzle instance, isolated from other plugins!
        $this->client = new Client(['base_uri' => 'https://api.example.com']);
    }
}
```

## 🎯 Next Steps

Now that you have a working plugin:

1. **Explore Components** - Learn about [Container](components/container.md), [Service Providers](components/service-provider.md), and more
2. **Add Features** - Check the [Cookbook](cookbook/README.md) for common patterns
3. **Write Tests** - See the [Testing Guide](guides/testing.md)
4. **Use Services** - Explore built-in [Services](components/services.md)

## 💡 Tips for Success

### Do's ✅
- Start simple, add complexity as needed
- Use type hints everywhere
- Write tests for your features
- Follow PSR standards
- Use dependency injection

### Don'ts ❌
- Don't use global variables
- Don't prefix everything (use namespaces)
- Don't fear Composer packages
- Don't skip tests
- Don't bypass the container

## 🆘 Getting Help

- **Documentation**: You're reading it!
- **Examples**: Check the boilerplate plugin
- **Tests**: Read the framework tests for examples
- **Issues**: GitHub issues for bugs/features

---

Ready to dive deeper? Continue to [**Architecture Overview**](architecture.md) →