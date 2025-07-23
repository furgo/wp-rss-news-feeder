# Service Providers Guide

> **Master the Service Provider pattern for modular, reusable WordPress plugin architecture**

This guide provides in-depth knowledge about creating effective Service Providers, organizing complex features, and building reusable components that can be shared across projects.

## 🎯 Understanding Service Providers

Service Providers are the organizational backbone of Sitechips Core plugins. They:
- **Organize** related services into logical groups
- **Separate** service definition from WordPress integration
- **Enable** modular, feature-based architecture
- **Promote** code reuse across projects

### The Two-Phase Lifecycle

```
1. Registration Phase (register method)
   ↓
   - Define services in container
   - Set up dependencies
   - No WordPress functions yet
   ↓
2. Boot Phase (boot method)
   ↓
   - WordPress is fully loaded
   - Register hooks and filters
   - Initialize features
```

## 📦 Basic Service Provider

### Minimal Example

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;
use MyPlugin\Services\EmailService;
use MyPlugin\Services\TemplateEngine;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Simple service binding
        $this->bind('email.service', EmailService::class);
        
        // Shared instance (singleton)
        $this->shared('email.templates', TemplateEngine::class);
        
        // Factory with dependencies
        $this->bind('email.mailer', function($container) {
            return new Mailer(
                $container->get('email.config'),
                $container->get('logger')
            );
        });
    }
    
    /**
     * Boot services
     */
    public function boot(): void
    {
        // Register WordPress hooks
        $this->addAction('wp_mail', [$this, 'logEmail'], 999);
        $this->addFilter('wp_mail_from', [$this, 'setFromAddress']);
    }
    
    public function logEmail(array $args): void
    {
        $this->container->get('logger')->info('Email sent', $args);
    }
    
    public function setFromAddress(string $email): string
    {
        return $this->container->get('email.config.from_address');
    }
}
```

## 🏗️ Provider Patterns

### 1. Feature Provider Pattern

Group all services for a complete feature:

```php
class EcommerceServiceProvider extends ServiceProvider
{
    /**
     * All services for e-commerce functionality
     */
    public function register(): void
    {
        // Core services
        $this->registerCoreServices();
        
        // Repositories
        $this->registerRepositories();
        
        // Business services
        $this->registerBusinessServices();
        
        // API services
        $this->registerApiServices();
    }
    
    private function registerCoreServices(): void
    {
        $this->shared('ecommerce.cart', Cart::class);
        $this->shared('ecommerce.session', SessionManager::class);
        $this->shared('ecommerce.currency', CurrencyConverter::class);
    }
    
    private function registerRepositories(): void
    {
        $this->shared('repository.product', function($container) {
            return new ProductRepository(
                $container->get('wpdb'),
                $container->get('cache')
            );
        });
        
        $this->shared('repository.order', function($container) {
            return new OrderRepository(
                $container->get('wpdb'),
                $container->get('events')
            );
        });
    }
    
    private function registerBusinessServices(): void
    {
        $this->shared('ecommerce.checkout', function($container) {
            return new CheckoutService(
                $container->get('ecommerce.cart'),
                $container->get('repository.order'),
                $container->get('payment.gateway'),
                $container->get('events')
            );
        });
        
        $this->shared('ecommerce.inventory', function($container) {
            return new InventoryService(
                $container->get('repository.product'),
                $container->get('events'),
                $container->get('logger')
            );
        });
    }
    
    public function boot(): void
    {
        // Custom post types
        $this->addAction('init', [$this, 'registerPostTypes']);
        
        // AJAX handlers
        $this->addAction('wp_ajax_add_to_cart', [$this, 'handleAddToCart']);
        $this->addAction('wp_ajax_nopriv_add_to_cart', [$this, 'handleAddToCart']);
        
        // Shortcodes
        add_shortcode('product_list', [$this, 'renderProductList']);
        add_shortcode('shopping_cart', [$this, 'renderCart']);
        
        // REST API
        $this->addAction('rest_api_init', [$this, 'registerApiRoutes']);
    }
}
```

### 2. Integration Provider Pattern

For third-party service integrations:

```php
class StripeServiceProvider extends ServiceProvider
{
    /**
     * Check if integration should be loaded
     */
    private function isEnabled(): bool
    {
        return !empty($this->container->get('stripe.api_key'));
    }
    
    public function register(): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        
        // Stripe client
        $this->shared('stripe.client', function($container) {
            \Stripe\Stripe::setApiKey($container->get('stripe.api_key'));
            return new \Stripe\StripeClient($container->get('stripe.api_key'));
        });
        
        // Payment gateway implementation
        $this->shared('payment.gateway.stripe', function($container) {
            return new StripeGateway(
                $container->get('stripe.client'),
                $container->get('stripe.webhook_secret'),
                $container->get('logger')
            );
        });
        
        // Webhook handler
        $this->shared('stripe.webhook', function($container) {
            return new StripeWebhookHandler(
                $container->get('stripe.client'),
                $container->get('repository.order'),
                $container->get('events')
            );
        });
    }
    
    public function boot(): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        
        // Register payment gateway
        $this->addFilter('payment_gateways', function($gateways) {
            $gateways['stripe'] = $this->container->get('payment.gateway.stripe');
            return $gateways;
        });
        
        // Webhook endpoint
        $this->addAction('rest_api_init', function() {
            register_rest_route('payments/v1', '/stripe/webhook', [
                'methods' => 'POST',
                'callback' => [$this->container->get('stripe.webhook'), 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
        
        // Admin settings
        $this->addAction('admin_init', [$this, 'registerSettings']);
    }
}
```

### 3. Conditional Provider Pattern

Load providers based on conditions:

```php
class PremiumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Check license
        if (!$this->hasValidLicense()) {
            return;
        }
        
        // Premium services
        $this->shared('premium.analytics', AdvancedAnalytics::class);
        $this->shared('premium.export', BulkExporter::class);
        $this->shared('premium.ai', AiContentGenerator::class);
    }
    
    public function boot(): void
    {
        if (!$this->hasValidLicense()) {
            $this->showLicenseNotice();
            return;
        }
        
        // Premium features
        $this->addAction('admin_menu', [$this, 'addPremiumMenus']);
        $this->addFilter('feature_list', [$this, 'addPremiumFeatures']);
    }
    
    private function hasValidLicense(): bool
    {
        $license = $this->container->get('license.key');
        $validator = $this->container->get('license.validator');
        
        return $validator->isValid($license);
    }
    
    private function showLicenseNotice(): void
    {
        $this->addAction('admin_notices', function() {
            echo '<div class="notice notice-info">';
            echo '<p>Premium features require a valid license. ';
            echo '<a href="' . admin_url('admin.php?page=plugin-license') . '">Enter License</a></p>';
            echo '</div>';
        });
    }
}
```

### 4. Environment-Specific Provider

Different behavior per environment:

```php
class DevelopmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Development-only services
        $this->shared('dev.profiler', QueryProfiler::class);
        $this->shared('dev.debugbar', DebugBar::class);
        $this->shared('dev.faker', DataGenerator::class);
    }
    
    public function boot(): void
    {
        // Only in development
        if ($this->container->get('environment') !== 'development') {
            return;
        }
        
        // Query profiling
        $this->addFilter('query', [$this->container->get('dev.profiler'), 'profile']);
        
        // Debug bar
        $this->addAction('wp_footer', [$this->container->get('dev.debugbar'), 'render']);
        
        // Test data generator
        $this->addAction('admin_menu', function() {
            add_management_page(
                'Generate Test Data',
                'Test Data',
                'manage_options',
                'generate-test-data',
                [$this->container->get('dev.faker'), 'renderUI']
            );
        });
        
        // Disable caching
        $this->addFilter('cache_enabled', '__return_false');
    }
}
```

## 🔄 Cross-Provider Communication

### Using Events

```php
class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('order.service', OrderService::class);
    }
    
    public function boot(): void
    {
        // Listen to events from other providers
        $this->addAction('payment.completed', [$this, 'onPaymentCompleted']);
        $this->addAction('inventory.low_stock', [$this, 'onLowStock']);
    }
    
    public function onPaymentCompleted($payment): void
    {
        $orderService = $this->container->get('order.service');
        $order = $orderService->findByPayment($payment);
        
        // Update order status
        $orderService->markAsPaid($order);
        
        // Dispatch event for other providers
        $this->container->get('events')->dispatch('order.paid', $order);
    }
}

class ShippingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // React to order events
        $this->addAction('my-plugin.order.paid', [$this, 'createShippingLabel']);
    }
    
    public function createShippingLabel($plugin, $order): void
    {
        $shipping = $plugin->get('shipping.service');
        $shipping->generateLabel($order);
    }
}
```

### Shared Interfaces

```php
// Define interface in Core
interface DataSourceInterface
{
    public function import(array $config): ImportResult;
    public function validate(array $data): bool;
}

// Implement in different providers
class CsvServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bind('import.csv', CsvImporter::class);
        
        // Register with import manager
        $this->addAlias('import.sources.csv', 'import.csv');
    }
}

class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bind('import.api', ApiImporter::class);
        $this->addAlias('import.sources.api', 'import.api');
    }
}

class ImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('import.manager', function($container) {
            $manager = new ImportManager();
            
            // Collect all registered sources
            foreach ($container->getTagged('import.sources') as $source) {
                $manager->addSource($source);
            }
            
            return $manager;
        });
    }
}
```

## 🏭 Advanced Techniques

### 1. Lazy Provider Registration

```php
class HeavyServiceProvider extends ServiceProvider
{
    /**
     * Services that should be registered lazily
     */
    protected array $lazy = [
        'heavy.image_processor',
        'heavy.pdf_generator',
        'heavy.video_encoder',
    ];
    
    public function register(): void
    {
        // Register factories that create services on-demand
        $this->bind('heavy.image_processor', function() {
            require_once $this->container->get('plugin.path') . 'lib/ImageProcessor.php';
            return new ImageProcessor();
        });
        
        $this->bind('heavy.pdf_generator', function() {
            require_once $this->container->get('plugin.path') . 'lib/PdfGenerator.php';
            return new PdfGenerator();
        });
    }
}
```

### 2. Provider Discovery

```php
class ProviderDiscovery
{
    public static function discover(string $directory): array
    {
        $providers = [];
        $files = glob($directory . '/*ServiceProvider.php');
        
        foreach ($files as $file) {
            $class = self::getClassFromFile($file);
            if (is_subclass_of($class, ServiceProvider::class)) {
                $providers[] = $class;
            }
        }
        
        return $providers;
    }
    
    private static function getClassFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        if (preg_match('/namespace\s+(.+?);.*class\s+(\w+)/s', $content, $matches)) {
            return $matches[1] . '\\' . $matches[2];
        }
        return null;
    }
}

// In PluginFactory
$providers = ProviderDiscovery::discover(__DIR__ . '/src/Providers');
$plugin = PluginFactory::create(__FILE__, $config, $providers);
```

### 3. Provider Priorities

```php
abstract class PrioritizedServiceProvider extends ServiceProvider
{
    /**
     * Provider priority (lower = earlier)
     */
    protected int $priority = 10;
    
    public function getPriority(): int
    {
        return $this->priority;
    }
}

// Sort providers by priority
usort($providers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
```

### 4. Provider Caching

```php
class CachedServiceProvider extends ServiceProvider
{
    protected array $cached = [];
    
    public function register(): void
    {
        $cacheKey = 'provider_' . static::class;
        $cached = get_transient($cacheKey);
        
        if ($cached === false) {
            // Expensive registration
            $this->discoverServices();
            $this->analyzeCode();
            
            // Cache for next time
            set_transient($cacheKey, $this->cached, DAY_IN_SECONDS);
        } else {
            $this->cached = $cached;
        }
        
        // Register cached services
        foreach ($this->cached as $id => $class) {
            $this->bind($id, $class);
        }
    }
}
```

## 🧪 Testing Service Providers

### Unit Testing

```php
namespace MyPlugin\Tests\Unit\Providers;

use MyPlugin\Providers\EmailServiceProvider;
use Furgo\Sitechips\Core\Container\Container;
use PHPUnit\Framework\TestCase;

class EmailServiceProviderTest extends TestCase
{
    private Container $container;
    private EmailServiceProvider $provider;
    
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->provider = new EmailServiceProvider($this->container);
    }
    
    public function testRegisterServices(): void
    {
        // Register services
        $this->provider->register();
        
        // Assert services are registered
        $this->assertTrue($this->container->has('email.service'));
        $this->assertTrue($this->container->has('email.templates'));
        $this->assertTrue($this->container->has('email.mailer'));
    }
    
    public function testServiceCreation(): void
    {
        // Register services
        $this->provider->register();
        
        // Get service
        $emailService = $this->container->get('email.service');
        
        // Assert correct type
        $this->assertInstanceOf(EmailService::class, $emailService);
    }
    
    public function testBootRegistersHooks(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('add_action');
        $this->mockWordPressFunction('add_filter');
        
        // Boot provider
        $this->provider->boot();
        
        // Assert hooks were registered
        $this->assertActionAdded('wp_mail', [$this->provider, 'logEmail'], 999);
        $this->assertFilterAdded('wp_mail_from', [$this->provider, 'setFromAddress']);
    }
}
```

### Integration Testing

```php
class EmailProviderIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create plugin with provider
        $this->plugin = PluginFactory::createForTesting('/tmp/test.php', [], [
            EmailServiceProvider::class,
        ]);
        
        $this->plugin->boot();
    }
    
    public function testEmailLogging(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with('Email sent', $this->anything());
        
        $this->plugin->getContainer()->set('logger', $logger);
        
        // Act - trigger WordPress mail
        wp_mail('test@example.com', 'Test', 'Message');
        
        // Assert - handled by mock expectation
    }
}
```

## 📚 Reusable Provider Library

### Creating a Provider Package

```json
{
    "name": "mycompany/wordpress-search-provider",
    "type": "library",
    "description": "Advanced search provider for WordPress plugins",
    "require": {
        "php": ">=8.1",
        "furgo/sitechips-core": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "MyCompany\\WordPress\\Search\\": "src/"
        }
    }
}
```

```php
namespace MyCompany\WordPress\Search;

use Furgo\Sitechips\Core\Container\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('search.engine', ElasticsearchEngine::class);
        $this->shared('search.indexer', SearchIndexer::class);
        $this->shared('search.query', QueryBuilder::class);
    }
    
    public function boot(): void
    {
        // Replace WordPress search
        $this->addFilter('posts_search', [$this, 'enhanceSearch'], 10, 2);
        
        // Index content
        $this->addAction('save_post', [$this, 'indexPost'], 10, 3);
        
        // Admin UI
        if (is_admin()) {
            $this->addAction('admin_menu', [$this, 'addSearchSettings']);
        }
    }
}
```

### Using in Multiple Projects

```php
// Project A
$plugin = PluginFactory::create(__FILE__, $config, [
    CoreServiceProvider::class,
    \MyCompany\WordPress\Search\SearchServiceProvider::class,
    ProjectAServiceProvider::class,
]);

// Project B  
$plugin = PluginFactory::create(__FILE__, $config, [
    CoreServiceProvider::class,
    \MyCompany\WordPress\Search\SearchServiceProvider::class,
    ProjectBServiceProvider::class,
]);
```

## 💡 Best Practices

### 1. Single Responsibility

```php
// ❌ Bad: Kitchen sink provider
class EverythingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Email services
        // Payment services  
        // User management
        // Analytics
        // ... 500 lines
    }
}

// ✅ Good: Focused providers
class EmailServiceProvider extends ServiceProvider { }
class PaymentServiceProvider extends ServiceProvider { }
class UserServiceProvider extends ServiceProvider { }
class AnalyticsServiceProvider extends ServiceProvider { }
```

### 2. Defensive Registration

```php
public function register(): void
{
    // Check dependencies
    if (!interface_exists(CacheInterface::class)) {
        throw new \RuntimeException('Cache interface required');
    }
    
    // Check configuration
    if (!$this->container->has('api.key')) {
        $this->container->get('logger')->warning('API key not configured');
        return;
    }
    
    // Proceed with registration
    $this->shared('api.client', ApiClient::class);
}
```

### 3. Clean Boot Methods

```php
public function boot(): void
{
    // Group related hooks
    $this->registerAdminHooks();
    $this->registerPublicHooks();
    $this->registerApiEndpoints();
    $this->scheduleBackgroundJobs();
}

private function registerAdminHooks(): void
{
    if (!is_admin()) {
        return;
    }
    
    $this->addAction('admin_menu', [$this, 'addMenuPages']);
    $this->addAction('admin_init', [$this, 'registerSettings']);
    $this->addAction('admin_enqueue_scripts', [$this, 'enqueueAssets']);
}
```

### 4. Configuration Driven

```php
class ConfigurableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->container->get('feature.config');
        
        foreach ($config['modules'] as $module => $settings) {
            if ($settings['enabled']) {
                $this->registerModule($module, $settings);
            }
        }
    }
    
    private function registerModule(string $module, array $settings): void
    {
        $class = $settings['class'] ?? $this->guessModuleClass($module);
        $this->shared("module.$module", $class);
    }
}
```

## 🏁 Summary

Service Providers are powerful tools for organizing WordPress plugins:

- **Modular Architecture** - Each feature in its own provider
- **Separation of Concerns** - Registration vs boot phases
- **Reusability** - Share providers across projects
- **Testability** - Easy to test in isolation
- **Flexibility** - Conditional loading and configuration

Key takeaways:
1. Keep providers focused on a single feature
2. Use register() for service definition only
3. Use boot() for WordPress integration
4. Leverage events for cross-provider communication
5. Create reusable provider packages

Next steps:
- Implement comprehensive [Testing](testing.md)
- Master [Event System](events.md) for provider communication
- Explore the [Cookbook](../cookbook/README.md) for more patterns

---

Continue to [**Testing Guide**](testing.md) →