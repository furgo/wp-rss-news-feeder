# Service Locator Component

> **Static access to plugin services for WordPress integration points**

The Service Locator pattern provides static access to your plugin instance and its services. While Dependency Injection is preferred, Service Locator is useful for WordPress hooks, templates, and legacy code integration.

## 🎯 Purpose

The Service Locator solves specific WordPress challenges:
- **WordPress Hooks** - Callbacks need access to services
- **Template Files** - No DI container in templates
- **Shortcodes** - Static callbacks need services
- **Legacy Integration** - Working with existing code
- **Global Access** - When DI isn't practical

## 🚀 Basic Implementation

### Creating a Service Locator

```php
namespace MyCompany\MyPlugin;

use Furgo\Sitechips\Core\Plugin\AbstractServiceLocator;
use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;

class MyPlugin extends AbstractServiceLocator
{
    /**
     * Setup the plugin instance
     * This method is called only once (Singleton)
     */
    protected static function setupPlugin(): Plugin
    {
        return PluginFactory::create(
            dirname(__DIR__) . '/my-plugin.php',
            [
                'api.endpoint' => 'https://api.example.com',
                'cache.enabled' => true,
            ],
            [
                Providers\CoreServiceProvider::class,
                Providers\AdminServiceProvider::class,
                Providers\ApiServiceProvider::class,
            ]
        );
    }
}
```

### Using the Service Locator

```php
use MyCompany\MyPlugin\MyPlugin;

// Access services statically from anywhere
$logger = MyPlugin::get('logger');
$repository = MyPlugin::get('user.repository');

// Check if service exists
if (MyPlugin::has('premium.features')) {
    $premium = MyPlugin::get('premium.features');
}

// Get plugin information
$version = MyPlugin::version();
$path = MyPlugin::path();
$url = MyPlugin::url();

// Check plugin state
if (MyPlugin::isBooted()) {
    // Plugin is ready
}
```

## 🎣 WordPress Integration

### In WordPress Hooks

```php
// Traditional approach - problematic
add_action('init', function() {
    global $my_plugin; // ❌ Global variable
    $my_plugin->get('initializer')->init();
});

// With Service Locator - clean
add_action('init', function() {
    MyPlugin::get('initializer')->init();
});

// Direct method reference
add_action('save_post', function($postId) {
    MyPlugin::get('cache')->clear("post_$postId");
});

// Complex hook with multiple services
add_action('woocommerce_order_complete', function($orderId) {
    $order = MyPlugin::get('order.repository')->find($orderId);
    MyPlugin::get('inventory')->update($order);
    MyPlugin::get('email')->sendConfirmation($order);
    MyPlugin::dispatch('order.fulfilled', $order);
});
```

### In Shortcodes

```php
// Register shortcode with Service Locator
add_shortcode('product_list', function($atts) {
    $atts = shortcode_atts([
        'category' => '',
        'limit' => 10,
    ], $atts);
    
    $products = MyPlugin::get('product.repository')
        ->findByCategory($atts['category'], $atts['limit']);
    
    return MyPlugin::get('template.renderer')
        ->render('shortcodes/product-list', ['products' => $products]);
});

// Or use a dedicated shortcode class
add_shortcode('my_form', [MyPlugin::get('shortcode.handler'), 'renderForm']);
```

### In Template Files

```php
<?php
// In theme template file
use MyCompany\MyPlugin\MyPlugin;

$products = MyPlugin::get('product.repository')->findFeatured();
$currency = MyPlugin::get('settings')->get('currency', 'USD');
?>

<div class="featured-products">
    <?php foreach ($products as $product): ?>
        <div class="product">
            <h3><?php echo esc_html($product->name); ?></h3>
            <p class="price">
                <?php echo esc_html($currency . ' ' . $product->price); ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>

<?php if (MyPlugin::isDebug()): ?>
    <pre>Debug: <?php echo MyPlugin::version(); ?></pre>
<?php endif; ?>
```

### In AJAX Handlers

```php
// Register AJAX action
add_action('wp_ajax_my_plugin_search', function() {
    $query = sanitize_text_field($_POST['query'] ?? '');
    
    try {
        $results = MyPlugin::get('search.service')->search($query);
        wp_send_json_success($results);
    } catch (Exception $e) {
        MyPlugin::logError('Search failed: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Search failed']);
    }
});

// Frontend JavaScript can call this
jQuery.post(ajaxurl, {
    action: 'my_plugin_search',
    query: 'search term'
});
```

## 🏗️ Advanced Patterns

### Conditional Service Access

```php
class MyPlugin extends AbstractServiceLocator
{
    /**
     * Get premium service if available
     */
    public static function premium(): ?PremiumService
    {
        if (static::has('premium.service')) {
            return static::get('premium.service');
        }
        return null;
    }
    
    /**
     * Check if running in pro mode
     */
    public static function isPro(): bool
    {
        return static::has('license.manager') 
            && static::get('license.manager')->isValid();
    }
}

// Usage
if (MyPlugin::isPro()) {
    MyPlugin::premium()->enableAdvancedFeatures();
}
```

### Facade Pattern

Create facades for frequently used services:

```php
class MyPlugin extends AbstractServiceLocator
{
    /**
     * Quick access to logger
     */
    public static function log(string $message, string $level = 'info'): void
    {
        static::get('logger')->log($level, $message);
    }
    
    /**
     * Quick access to cache
     */
    public static function cache(): CacheInterface
    {
        return static::get('cache');
    }
    
    /**
     * Quick access to settings
     */
    public static function setting(string $key, mixed $default = null): mixed
    {
        return static::get('settings')->get($key, $default);
    }
}

// Clean usage
MyPlugin::log('Process started');
MyPlugin::cache()->remember('data', fn() => expensive_operation());
$apiKey = MyPlugin::setting('api.key');
```

### Helper Functions

For maximum convenience, create global helper functions:

```php
// In your plugin file or helpers.php

if (!function_exists('my_plugin')) {
    /**
     * Get the plugin instance
     */
    function my_plugin(): Plugin
    {
        return MyPlugin::instance();
    }
}

if (!function_exists('my_plugin_service')) {
    /**
     * Get a service from the container
     */
    function my_plugin_service(string $id): mixed
    {
        return MyPlugin::get($id);
    }
}

// Usage in templates or hooks
$logger = my_plugin_service('logger');
my_plugin()->dispatch('event.name');
```

## 🧪 Testing with Service Locator

### Unit Testing

```php
use MyCompany\MyPlugin\MyPlugin;

class ServiceLocatorTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singleton instance
        MyPlugin::reset();
    }
    
    protected function tearDown(): void
    {
        // Clean up after test
        MyPlugin::reset();
    }
    
    public function testServiceAccess(): void
    {
        // First access creates instance
        $logger = MyPlugin::get('logger');
        $this->assertInstanceOf(LoggerInterface::class, $logger);
        
        // Subsequent calls return same instance
        $plugin1 = MyPlugin::instance();
        $plugin2 = MyPlugin::instance();
        $this->assertSame($plugin1, $plugin2);
    }
    
    public function testStaticMethods(): void
    {
        $this->assertIsString(MyPlugin::version());
        $this->assertIsString(MyPlugin::path());
        $this->assertIsBool(MyPlugin::isDebug());
    }
}
```

### Mocking in Tests

```php
class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        // Create test instance with mocks
        MyPlugin::reset();
        
        // Override the setupPlugin method for testing
        $plugin = PluginFactory::createForTesting('/tmp/test.php');
        $plugin->getContainer()->set('api.client', $this->createMock(ApiClient::class));
        
        // Manually set the test instance
        $reflection = new ReflectionClass(MyPlugin::class);
        $property = $reflection->getProperty('instances');
        $property->setAccessible(true);
        $property->setValue([MyPlugin::class => $plugin]);
    }
}
```

## ⚖️ Service Locator vs Dependency Injection

### When to Use Service Locator

✅ **Good Use Cases:**
```php
// WordPress hooks
add_action('init', fn() => MyPlugin::get('service')->init());

// Template files
<?php $data = MyPlugin::get('repository')->findAll(); ?>

// Shortcodes
add_shortcode('my_shortcode', fn($atts) => MyPlugin::get('renderer')->render($atts));

// Global helper functions
function my_plugin_api() {
    return MyPlugin::get('api.client');
}
```

❌ **Bad Use Cases:**
```php
// In service classes - use DI instead
class BadService {
    public function doWork() {
        $logger = MyPlugin::get('logger'); // ❌ Hidden dependency
    }
}

// Better with DI
class GoodService {
    public function __construct(private LoggerInterface $logger) {} // ✅ Explicit
}
```

### Comparison Table

| Aspect | Dependency Injection | Service Locator |
|--------|---------------------|-----------------|
| Dependencies | Explicit in constructor | Hidden in method |
| Testability | Easy to mock | Harder to mock |
| IDE Support | Full autocomplete | Limited support |
| Coupling | Loose coupling | Tighter coupling |
| Use Case | Service classes | WordPress integration |

## 💡 Best Practices

### DO's ✅

```php
// Use for WordPress integration
add_action('wp_loaded', function() {
    MyPlugin::boot();
});

// Create convenience methods
class MyPlugin extends AbstractServiceLocator {
    public static function api(): ApiClient {
        return static::get('api.client');
    }
}

// Document service availability
/**
 * @method static LoggerInterface get(string $id)
 * @method static bool has(string $id)
 */
class MyPlugin extends AbstractServiceLocator {}

// Handle missing services gracefully
if (MyPlugin::has('optional.feature')) {
    MyPlugin::get('optional.feature')->activate();
}
```

### DON'Ts ❌

```php
// Don't use in service constructors
class Service {
    private $logger;
    
    public function __construct() {
        $this->logger = MyPlugin::get('logger'); // ❌ Hidden dependency
    }
}

// Don't create multiple Service Locators for one plugin
class MyPlugin extends AbstractServiceLocator {}
class AlsoMyPlugin extends AbstractServiceLocator {} // ❌ Confusing

// Don't expose internal implementation
class MyPlugin extends AbstractServiceLocator {
    public static function getContainer() {
        return static::instance()->getContainer(); // ❌ Breaks encapsulation
    }
}
```

## 🔍 Debugging

### Debug Service Resolution

```php
class MyPlugin extends AbstractServiceLocator
{
    public static function debug(): array
    {
        if (!static::isDebug()) {
            return [];
        }
        
        $plugin = static::instance();
        $container = $plugin->getContainer();
        
        return [
            'version' => static::version(),
            'environment' => static::environment(),
            'booted' => static::isBooted(),
            'services' => array_keys($container->getInternalContainer()->getKnownEntryNames()),
        ];
    }
}

// In development
add_action('admin_footer', function() {
    if (MyPlugin::isDebug()) {
        echo '<!-- Plugin Debug: ';
        print_r(MyPlugin::debug());
        echo ' -->';
    }
});
```

## 📚 API Reference

### Available Static Methods

```php
// From AbstractServiceLocator
public static function instance(): Plugin
public static function reset(): void
public static function get(string $serviceId): mixed
public static function has(string $serviceId): bool
public static function version(): string
public static function path(): string
public static function url(): string
public static function basename(): string
public static function name(): string
public static function textDomain(): string
public static function environment(): string
public static function isDebug(): bool
public static function isBooted(): bool
public static function boot(): void
public static function call(callable|array $callback, array $parameters = []): mixed
public static function make(string $className, array $parameters = []): object

// Must be implemented
protected static function setupPlugin(): Plugin
```

## 🔗 Related Components

- [Plugin](plugin.md) - The instance managed by Service Locator
- [Plugin Factory](plugin-factory.md) - Used in setupPlugin()
- [Container](container.md) - Accessed through Service Locator

---

Continue to [**Core Services**](services.md) →