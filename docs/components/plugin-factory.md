# Plugin Factory Component

> **Bootstrap WordPress plugins with pre-configured container and service providers**

The PluginFactory is responsible for creating and configuring Plugin instances. It handles metadata extraction, environment detection, and initial setup, providing a consistent way to bootstrap plugins.

## 🎯 Purpose

The PluginFactory simplifies plugin initialization by:
- **Automatic Configuration** - Extracts plugin metadata automatically
- **Environment Detection** - Adapts to development/production/testing
- **Service Provider Registration** - Sets up providers in correct order
- **Consistent Bootstrap** - Same initialization pattern for all plugins

## 🚀 Basic Usage

### Simple Plugin Creation

```php
use Furgo\Sitechips\Core\Plugin\PluginFactory;

// Minimal setup
$plugin = PluginFactory::create(__FILE__);

// With configuration
$plugin = PluginFactory::create(__FILE__, [
    'api.key' => 'your-api-key',
    'cache.enabled' => true,
    'features.pro' => has_pro_license(),
]);

// With service providers
$plugin = PluginFactory::create(__FILE__, $config, [
    CoreServiceProvider::class,
    AdminServiceProvider::class,
    ApiServiceProvider::class,
]);
```

### Plugin Bootstrapping Pattern

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Version: 1.0.0
 * Text Domain: my-awesome-plugin
 */

// Guard against direct access
defined('ABSPATH') || exit;

// Autoloader check
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Run composer install!</p></div>';
    });
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Create plugin
$plugin = PluginFactory::create(__FILE__, [], [
    MyPlugin\Providers\CoreProvider::class,
    MyPlugin\Providers\AdminProvider::class,
]);

// Boot when WordPress is ready
add_action('plugins_loaded', fn() => $plugin->boot(), 5);
```

## 🔧 Configuration Options

### Automatic Definitions

The factory automatically sets these definitions:

```php
// Plugin identification
'plugin.slug'        => 'my-awesome-plugin'      // From directory name
'plugin.file'        => '/path/to/my-plugin.php' // Main file path
'plugin.path'        => '/path/to/my-plugin/'    // With trailing slash
'plugin.url'         => 'http://site.com/wp-content/plugins/my-plugin/'
'plugin.basename'    => 'my-plugin/my-plugin.php'

// Plugin metadata (from header)
'plugin.name'        => 'My Awesome Plugin'
'plugin.version'     => '1.0.0'
'plugin.description' => 'Plugin description'
'plugin.author'      => 'Your Name'
'plugin.text_domain' => 'my-awesome-plugin'

// Environment
'debug'              => WP_DEBUG              // Debug mode
'environment'        => 'production'          // production|development|staging|testing

// Common paths
'path.assets'        => '/path/to/my-plugin/assets/'
'path.templates'     => '/path/to/my-plugin/templates/'
'path.languages'     => '/path/to/my-plugin/languages/'
'url.assets'         => 'http://site.com/.../my-plugin/assets/'

// Cache configuration
'cache.path'         => WP_CONTENT_DIR . '/cache/my-plugin/'

// Service references
'providers'          => []  // Registered ServiceProvider instances
```

### Custom Configuration

Add your own configuration:

```php
$plugin = PluginFactory::create(__FILE__, [
    // API Configuration
    'api.endpoint' => 'https://api.example.com/v2',
    'api.key' => get_option('my_plugin_api_key'),
    'api.timeout' => 30,
    
    // Feature flags
    'features.import' => true,
    'features.export' => true,
    'features.sync' => defined('MY_PLUGIN_PRO'),
    
    // Custom paths
    'path.imports' => WP_CONTENT_DIR . '/my-plugin-imports/',
    'path.logs' => WP_CONTENT_DIR . '/my-plugin-logs/',
    
    // Service configuration
    'cache.driver' => 'redis',
    'cache.ttl' => HOUR_IN_SECONDS,
    
    // Cross-plugin events
    'events.global_prefix' => 'mycompany',
]);
```

## 🏗️ Service Provider Registration

### How Providers are Registered

```php
$providers = [
    CoreServiceProvider::class,
    DatabaseServiceProvider::class,
    AdminServiceProvider::class,
];

$plugin = PluginFactory::create(__FILE__, $config, $providers);

// What happens internally:
// 1. Each provider class is instantiated with the container
// 2. provider->register() is called immediately
// 3. Providers are stored in container as 'providers'
// 4. provider->boot() will be called when plugin->boot() runs
```

### Provider Registration Order

Order matters! Register providers based on dependencies:

```php
$providers = [
    // 1. Core services first (logger, events, etc.)
    CoreServiceProvider::class,
    
    // 2. Data layer (database, repositories)
    DatabaseServiceProvider::class,
    
    // 3. Business logic
    ImportServiceProvider::class,
    ExportServiceProvider::class,
    
    // 4. Presentation layer
    AdminServiceProvider::class,
    FrontendServiceProvider::class,
    
    // 5. Integrations last
    WooCommerceServiceProvider::class,
];
```

### Conditional Providers

```php
$providers = [
    CoreServiceProvider::class,
];

// Add admin provider only in admin
if (is_admin()) {
    $providers[] = AdminServiceProvider::class;
}

// Add integration only if available
if (class_exists('WooCommerce')) {
    $providers[] = WooCommerceServiceProvider::class;
}

// Add debug provider in development
if (WP_DEBUG) {
    $providers[] = DebugServiceProvider::class;
}

$plugin = PluginFactory::create(__FILE__, $config, $providers);
```

## 🧪 Testing Mode

### Creating Test Instances

```php
use Furgo\Sitechips\Core\Plugin\PluginFactory;

class MyPluginTest extends TestCase
{
    private Plugin $plugin;
    
    protected function setUp(): void
    {
        // Test mode with defaults
        $this->plugin = PluginFactory::createForTesting();
        
        // With custom test configuration
        $this->plugin = PluginFactory::createForTesting(
            '/tmp/test-plugin.php',
            [
                'api.endpoint' => 'https://test-api.example.com',
                'cache.driver' => 'array', // In-memory cache
                'plugin.name' => 'Test Plugin',
                'plugin.version' => '0.0.1',
            ],
            [
                TestServiceProvider::class,
            ]
        );
    }
}
```

### Test Mode Features

In testing mode, the factory:
- Sets `environment` to `'testing'`
- Sets `debug` to `true`
- Disables cache compilation
- Uses `/tmp/test-plugin.php` as default file
- Provides minimal metadata defaults

## 🎨 Advanced Patterns

### Factory Method Pattern

Create your own factory for specific needs:

```php
class MyPluginFactory extends PluginFactory
{
    public static function createWithDatabase(string $pluginFile): Plugin
    {
        // Custom database configuration
        $dbConfig = [
            'db.host' => DB_HOST,
            'db.name' => DB_NAME,
            'db.user' => DB_USER,
            'db.password' => DB_PASSWORD,
            'db.prefix' => $GLOBALS['wpdb']->prefix . 'myplugin_',
        ];
        
        return parent::create($pluginFile, $dbConfig, [
            DatabaseServiceProvider::class,
            MigrationServiceProvider::class,
        ]);
    }
    
    public static function createProVersion(string $pluginFile): Plugin
    {
        $config = [
            'features.pro' => true,
            'license.key' => get_option('myplugin_license_key'),
        ];
        
        $providers = [
            CoreServiceProvider::class,
            ProFeaturesServiceProvider::class,
            LicensingServiceProvider::class,
        ];
        
        return parent::create($pluginFile, $config, $providers);
    }
}
```

### Environment-Specific Creation

```php
$config = [];
$providers = [CoreServiceProvider::class];

// Environment-specific configuration
switch (wp_get_environment_type()) {
    case 'development':
        $config['debug'] = true;
        $config['cache.driver'] = 'file';
        $providers[] = DevelopmentServiceProvider::class;
        break;
        
    case 'staging':
        $config['debug'] = true;
        $config['cache.driver'] = 'redis';
        $providers[] = StagingServiceProvider::class;
        break;
        
    case 'production':
        $config['debug'] = false;
        $config['cache.driver'] = 'redis';
        $config['cache.ttl'] = DAY_IN_SECONDS;
        $providers[] = ProductionServiceProvider::class;
        break;
}

$plugin = PluginFactory::create(__FILE__, $config, $providers);
```

## 🔍 How It Works Internally

### Metadata Extraction

```php
// The factory extracts plugin headers
$pluginData = get_plugin_data($pluginFile);

// Falls back to parsing if WordPress function not available
if (!function_exists('get_plugin_data')) {
    // Parses plugin file header manually
    // Extracts: Plugin Name, Version, Text Domain, etc.
}
```

### Environment Detection

```php
// 1. Check for test environment
if (defined('SITECHIPS_TESTS')) {
    return 'testing';
}

// 2. Use WordPress environment type
if (function_exists('wp_get_environment_type')) {
    return wp_get_environment_type();
}

// 3. Fallback based on debug mode
return WP_DEBUG ? 'development' : 'production';
```

### Container Creation

```php
// Simplified internal process
protected static function create($pluginFile, $config, $providers)
{
    // 1. Create base definitions
    $definitions = self::createBaseDefinitions($pluginFile);
    
    // 2. Merge with user config
    $definitions = array_merge($definitions, $config);
    
    // 3. Create container
    $container = new Container($definitions);
    
    // 4. Register providers
    self::registerServiceProviders($container, $providers);
    
    // 5. Create plugin instance
    return new Plugin($container, $pluginFile);
}
```

## 💡 Best Practices

### DO's ✅

```php
// Use __FILE__ from main plugin file
$plugin = PluginFactory::create(__FILE__);

// Keep configuration external
$config = require __DIR__ . '/config/plugin.php';
$plugin = PluginFactory::create(__FILE__, $config);

// Use environment variables
$plugin = PluginFactory::create(__FILE__, [
    'api.key' => $_ENV['MY_PLUGIN_API_KEY'] ?? '',
    'api.url' => $_ENV['MY_PLUGIN_API_URL'] ?? 'https://api.example.com',
]);

// Validate before creating
if (!MyPlugin::meetsRequirements()) {
    add_action('admin_notices', [MyPlugin::class, 'showRequirementsNotice']);
    return;
}
$plugin = PluginFactory::create(__FILE__);
```

### DON'Ts ❌

```php
// Don't use relative paths
$plugin = PluginFactory::create('my-plugin.php'); // ❌

// Don't create multiple instances
$plugin1 = PluginFactory::create(__FILE__);
$plugin2 = PluginFactory::create(__FILE__); // ❌ Duplicate!

// Don't boot immediately
$plugin = PluginFactory::create(__FILE__);
$plugin->boot(); // ❌ Too early, WordPress not ready!

// Don't store sensitive data in config
$plugin = PluginFactory::create(__FILE__, [
    'api.secret' => 'hardcoded-secret-key', // ❌ Security risk!
]);
```

## 🐛 Common Issues

### Plugin Headers Not Found

```php
// Ensure proper plugin header format
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 */

// Factory needs these headers to extract metadata
```

### Container Compilation Issues

```php
// If compilation fails in production
$plugin = PluginFactory::create(__FILE__, [
    'cache.path' => WP_CONTENT_DIR . '/cache/my-plugin/', // Ensure writable
]);

// Or disable compilation
$container = new Container($definitions, false); // Pass to factory somehow
```

### Provider Not Found

```php
// Check namespace and autoloading
namespace MyPlugin\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;

class MyProvider extends ServiceProvider { }

// In composer.json
"autoload": {
    "psr-4": {
        "MyPlugin\\": "src/"
    }
}
```

## 📚 API Reference

### Main Methods

```php
// Standard creation
public static function create(
    string $pluginFile,
    array $config = [],
    array $providers = []
): Plugin

// Testing creation
public static function createForTesting(
    string $pluginFile = '/tmp/test-plugin.php',
    array $config = [],
    array $providers = []
): Plugin

// Internal methods (protected)
protected static function createBaseDefinitions(string $pluginFile): array
protected static function registerServiceProviders(Container $container, array $providers): void
protected static function extractPluginSlug(string $pluginFile): string
protected static function getPluginPath(string $pluginFile): string
protected static function getPluginUrl(string $pluginFile): string
protected static function getPluginBasename(string $pluginFile): string
protected static function getPluginData(string $pluginFile): array
protected static function detectEnvironment(): string
protected static function isDebugMode(): bool
```

## 🔗 Related Components

- [Plugin](plugin.md) - The instance created by the factory
- [Container](container.md) - Configured by the factory
- [Service Provider](service-provider.md) - Registered by the factory

---

Continue to [**Plugin Class**](plugin.md) →