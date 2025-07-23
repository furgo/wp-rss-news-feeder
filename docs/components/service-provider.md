# Service Provider Component

> **Organize services and WordPress integration in modular, testable units**

Service Providers are the organizational backbone of Sitechips Core plugins. They group related services together and handle WordPress integration in a clean, structured way.

## 🎯 Purpose

Service Providers solve the organization problem in WordPress plugins:
- **Modular Architecture** - Group related functionality
- **Separation of Concerns** - Service definition vs WordPress integration
- **Testability** - Test services without WordPress
- **Reusability** - Share providers between projects

## 🏗️ Anatomy of a Service Provider

```php
use Furgo\Sitechips\Core\Container\ServiceProvider;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Register services - Early phase
     * Define what services exist and how to create them
     * WordPress is NOT fully loaded yet
     */
    public function register(): void
    {
        // Register services
        $this->bind('email.transport', SmtpTransport::class);
        $this->shared('email.mailer', Mailer::class);
        
        // Register with factory
        $this->bind('email.service', function($container) {
            return new EmailService(
                $container->get('email.mailer'),
                $container->get('email.templates'),
                $container->get('logger')
            );
        });
    }
    
    /**
     * Boot services - Late phase
     * WordPress is fully loaded, register hooks and initialize
     */
    public function boot(): void
    {
        // Register WordPress hooks
        $this->addAction('wp_mail_failed', [$this, 'logFailedEmail']);
        $this->addFilter('wp_mail_from', [$this, 'setFromAddress']);
        
        // Initialize services that need WordPress
        $emailService = $this->container->get('email.service');
        $emailService->loadTemplates();
    }
}
```

## 🔄 The Two-Phase System

### Why Two Phases?

WordPress loads in stages. The two-phase system respects this:

```php
// WordPress loading sequence
1. Plugin files loaded
2. register() called - Define services
3. WordPress init
4. plugins_loaded hook
5. boot() called - WordPress ready
```

### Register Phase Rules

✅ **DO in register():**
```php
public function register(): void
{
    // Define services
    $this->bind('service', ServiceClass::class);
    
    // Use configuration
    $this->bind('api.client', function($c) {
        return new ApiClient($c->get('api.key'));
    });
    
    // Set up aliases
    $this->alias('db', 'database.connection');
}
```

❌ **DON'T in register():**
```php
public function register(): void
{
    // Don't use WordPress functions
    add_action('init', [$this, 'init']); // ❌ Too early!
    
    // Don't access WordPress data
    $user = wp_get_current_user(); // ❌ Not loaded!
    
    // Don't initialize services
    $this->container->get('service')->init(); // ❌ Wait for boot!
}
```

### Boot Phase Rules

✅ **DO in boot():**
```php
public function boot(): void
{
    // Register WordPress hooks
    $this->addAction('init', [$this, 'initialize']);
    $this->addFilter('the_content', [$this, 'filterContent']);
    
    // Access WordPress APIs
    if (current_user_can('manage_options')) {
        $this->registerAdminFeatures();
    }
    
    // Initialize services
    $this->container->get('cron.scheduler')->schedule();
}
```

## 📦 Service Registration Methods

### Basic Registration

```php
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simple binding
        $this->bind('user.repository', UserRepository::class);
        
        // Shared instance (singleton)
        $this->shared('product.repository', ProductRepository::class);
        
        // Concrete instance
        $this->bind('config', new Configuration());
        
        // With interface
        $this->bind(RepositoryInterface::class, UserRepository::class);
    }
}
```

### Factory Registration

```php
public function register(): void
{
    // Complex service with dependencies
    $this->bind('import.service', function($container) {
        $config = $container->get('import.config');
        
        return new ImportService(
            $container->get('csv.reader'),
            $container->get('validator'),
            $container->get('logger'),
            $config['batch_size'] ?? 100
        );
    });
    
    // Conditional service
    $this->bind('cache', function($container) {
        if ($container->get('cache.enabled')) {
            return new RedisCache($container->get('redis.client'));
        }
        return new NullCache();
    });
}
```

### Bulk Registration

```php
public function register(): void
{
    // Register multiple services at once
    $this->registerServices([
        'email.transport' => SmtpTransport::class,
        'email.mailer' => Mailer::class,
        'email.templates' => EmailTemplates::class,
        'email.queue' => EmailQueue::class,
    ]);
    
    // Register multiple aliases
    $this->registerAliases([
        'mail' => 'email.mailer',
        'templates' => 'email.templates',
    ]);
}
```

## 🎣 WordPress Integration

### Hook Registration

```php
public function boot(): void
{
    // Basic action
    $this->addAction('init', [$this, 'onInit']);
    
    // With priority and args
    $this->addAction('save_post', [$this, 'onSavePost'], 20, 3);
    
    // Filter
    $this->addFilter('the_content', [$this, 'filterContent'], 10, 1);
    
    // Using service methods directly
    $service = $this->container->get('content.processor');
    $this->addFilter('the_content', [$service, 'process']);
}
```

### Advanced Hook Patterns

```php
public function boot(): void
{
    // Conditional hooks
    if (is_admin()) {
        $this->addAction('admin_menu', [$this, 'registerAdminMenu']);
        $this->addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }
    
    // Dynamic hooks
    $postTypes = $this->container->get('config.post_types');
    foreach ($postTypes as $postType) {
        $this->addAction("save_post_{$postType}", [$this, 'handleSave']);
    }
    
    // Late hooks with closures
    $this->addAction('wp_loaded', function() {
        $this->container->get('feature.manager')->initialize();
    });
}
```

## 🏛️ Provider Patterns

### Feature Provider

Group all functionality for a feature:

```php
class ShoppingCartProvider extends ServiceProvider
{
    public function register(): void
    {
        // Storage
        $this->bind('cart.storage', SessionCartStorage::class);
        
        // Core services
        $this->shared('cart.manager', CartManager::class);
        $this->bind('cart.calculator', PriceCalculator::class);
        
        // API
        $this->bind('cart.api', CartApiController::class);
    }
    
    public function boot(): void
    {
        // Frontend
        $this->addAction('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        $this->addShortcode('shopping_cart', [$this, 'renderCart']);
        
        // AJAX handlers
        $this->addAction('wp_ajax_add_to_cart', [$this, 'handleAddToCart']);
        $this->addAction('wp_ajax_nopriv_add_to_cart', [$this, 'handleAddToCart']);
        
        // REST API
        $this->addAction('rest_api_init', [$this, 'registerEndpoints']);
    }
    
    private function addShortcode(string $tag, callable $callback): void
    {
        add_shortcode($tag, $callback);
    }
}
```

### Integration Provider

For third-party integrations:

```php
class WooCommerceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Only register if WooCommerce is active
        if (!$this->isWooCommerceActive()) {
            return;
        }
        
        $this->bind('woo.sync', WooCommerceSync::class);
        $this->bind('woo.importer', ProductImporter::class);
    }
    
    public function boot(): void
    {
        if (!$this->isWooCommerceActive()) {
            return;
        }
        
        // WooCommerce specific hooks
        $this->addAction('woocommerce_after_single_product', [$this, 'addCustomSection']);
        $this->addFilter('woocommerce_product_tabs', [$this, 'addProductTab']);
    }
    
    private function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }
}
```

### Admin Provider

Dedicated to admin functionality:

```php
class AdminProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bind('admin.menu', AdminMenuManager::class);
        $this->bind('admin.settings', SettingsController::class);
        $this->bind('admin.dashboard', DashboardWidget::class);
    }
    
    public function boot(): void
    {
        // Only in admin
        if (!is_admin()) {
            return;
        }
        
        $this->addAction('admin_menu', [$this, 'registerMenus']);
        $this->addAction('admin_init', [$this, 'registerSettings']);
        $this->addAction('wp_dashboard_setup', [$this, 'registerWidgets']);
        
        // Admin assets
        $this->addAction('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }
}
```

## 🧪 Testing Service Providers

### Unit Testing Register Phase

```php
class EmailProviderTest extends TestCase
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
        
        // Verify registration
        $this->assertTrue($this->container->has('email.mailer'));
        $this->assertTrue($this->container->has('email.service'));
        
        // Test service creation
        $mailer = $this->container->get('email.mailer');
        $this->assertInstanceOf(Mailer::class, $mailer);
    }
}
```

### Testing with WordPress Stubs

```php
public function testBootPhase(): void
{
    // Register first
    $this->provider->register();
    
    // Mock WordPress functions
    $this->mockWordPressFunction('add_action');
    $this->mockWordPressFunction('add_filter');
    
    // Boot provider
    $this->provider->boot();
    
    // Verify hooks registered
    $this->assertActionAdded('init', [$this->provider, 'onInit']);
    $this->assertFilterAdded('wp_mail_from', [$this->provider, 'setFromAddress']);
}
```

## 💡 Best Practices

### 1. Single Responsibility

Each provider should focus on one feature or integration:

```php
// Good: Focused providers
class PaymentProvider extends ServiceProvider { }
class EmailProvider extends ServiceProvider { }
class SecurityProvider extends ServiceProvider { }

// Bad: Kitchen sink provider
class EverythingProvider extends ServiceProvider { 
    // Does payments, emails, security, and more!
}
```

### 2. Defensive Booting

Check conditions before booting:

```php
public function boot(): void
{
    // Check dependencies
    if (!$this->container->has('required.service')) {
        $this->container->get('logger')->warning('Required service missing');
        return;
    }
    
    // Check environment
    if (!$this->shouldBootInEnvironment()) {
        return;
    }
    
    // Proceed with boot
    $this->registerHooks();
}
```

### 3. Configuration Driven

Use configuration for flexibility:

```php
public function register(): void
{
    $config = $this->container->get('email.config');
    
    // Register transport based on config
    $this->bind('email.transport', function() use ($config) {
        return match($config['driver']) {
            'smtp' => new SmtpTransport($config['smtp']),
            'sendmail' => new SendmailTransport(),
            'mail' => new MailTransport(),
            default => new LogTransport(),
        };
    });
}
```

## 📋 Provider Lifecycle

```
1. Provider instantiated with Container
2. markAsRegistered() called
3. register() method called
4. All providers registered
5. WordPress loads...
6. boot() method called  
7. markAsBooted() called
8. Provider fully active
```

## 🔍 Debugging Providers

### Debug Registration

```php
public function register(): void
{
    if ($this->container->get('debug')) {
        error_log(sprintf(
            'Registering %s provider with %d services',
            static::class,
            count($this->getServices())
        ));
    }
    
    // Registration logic...
}
```

### Track Boot Order

```php
public function boot(): void
{
    $startTime = microtime(true);
    
    // Boot logic...
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $duration = microtime(true) - $startTime;
        error_log(sprintf(
            '%s booted in %.3f seconds',
            static::class,
            $duration
        ));
    }
}
```

## 📚 API Reference

### Available Methods

```php
// From ServiceProvider base class
protected function bind(string $abstract, mixed $concrete = null): void
protected function shared(string $abstract, mixed $concrete = null): void
protected function alias(string $alias, string $abstract): void
protected function registerServices(array $services, bool $shared = true): void
protected function registerAliases(array $aliases): void
protected function call(callable|array $callback, array $parameters = []): mixed
protected function make(string $className, array $parameters = []): object
protected function addAction(string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1): void
protected function addFilter(string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1): void

// From Bootable interface
public function boot(): void

// From ServiceProviderInterface
public function register(): void
public function isRegistered(): bool
public function isBooted(): bool
public function markAsRegistered(): void
public function markAsBooted(): void
```

## 🔗 Related Components

- [Container](container.md) - Where services are registered
- [Plugin Factory](plugin-factory.md) - Registers providers
- [Plugin](plugin.md) - Boots providers

---

Continue to [**Plugin Factory**](plugin-factory.md) →