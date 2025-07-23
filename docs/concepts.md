# Core Concepts

> **Understanding the fundamental patterns and principles behind Sitechips Core**

This guide explains the core concepts that make Sitechips Core powerful and how they work together to create maintainable WordPress plugins.

## 🎯 Dependency Injection (DI)

### What is Dependency Injection?

Dependency Injection is a pattern where objects receive their dependencies rather than creating them internally. This makes code more testable, flexible, and maintainable.

### The Problem Without DI

```php
// ❌ Traditional WordPress approach - tightly coupled
class EmailService {
    private $logger;
    private $mailer;
    
    public function __construct() {
        global $wpdb;  // Global dependency
        $this->logger = new FileLogger('/var/log/email.log');  // Hard-coded
        $this->mailer = new PHPMailer();  // Direct instantiation
    }
    
    public function send($to, $subject, $body) {
        // How do we test this without sending real emails?
        // How do we switch to a different logger?
        // How do we mock the database?
    }
}
```

### The Solution With DI

```php
// ✅ With Dependency Injection - loosely coupled
class EmailService {
    public function __construct(
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private Database $db
    ) {
        // Dependencies are injected, not created
    }
    
    public function send(string $to, string $subject, string $body): bool {
        $this->logger->info("Sending email to {$to}");
        
        try {
            $result = $this->mailer->send($to, $subject, $body);
            $this->db->logEmail($to, $subject, $result);
            return true;
        } catch (Exception $e) {
            $this->logger->error("Email failed: " . $e->getMessage());
            return false;
        }
    }
}
```

### Benefits of DI

1. **Testability** - Easy to mock dependencies
2. **Flexibility** - Swap implementations without changing code
3. **Clarity** - Dependencies are explicit
4. **Reusability** - Services can be used in different contexts

### Container-Based DI

Sitechips Core uses a PSR-11 container to manage dependencies:

```php
// Register services
$container->set(LoggerInterface::class, WordPressLogger::class);
$container->set(MailerInterface::class, function() {
    return new WPMailer(get_option('smtp_settings'));
});

// Container automatically injects dependencies
$emailService = $container->get(EmailService::class);
// Container creates: new EmailService($logger, $mailer, $db)
```

## 🔌 Service Provider Pattern

### What are Service Providers?

Service Providers are classes that tell the container how to build your services. They organize related services and handle WordPress integration.

### Anatomy of a Service Provider

```php
class PaymentServiceProvider extends ServiceProvider {
    /**
     * Register phase - Define services
     * Called early, WordPress not fully loaded
     */
    public function register(): void {
        // Register payment gateway
        $this->container->set('payment.gateway', function($container) {
            $config = $container->get('payment.config');
            return new StripeGateway($config['api_key']);
        });
        
        // Register payment processor
        $this->container->set('payment.processor', function($container) {
            return new PaymentProcessor(
                $container->get('payment.gateway'),
                $container->get('logger')
            );
        });
    }
    
    /**
     * Boot phase - WordPress integration
     * Called after all providers registered, WordPress ready
     */
    public function boot(): void {
        // Register hooks
        $this->addAction('woocommerce_checkout_process', [$this, 'processPayment']);
        $this->addFilter('woocommerce_payment_gateways', [$this, 'registerGateway']);
        
        // Register REST routes
        add_action('rest_api_init', function() {
            $processor = $this->container->get('payment.processor');
            register_rest_route('myplugin/v1', '/payments', [
                'methods' => 'POST',
                'callback' => [$processor, 'handleWebhook']
            ]);
        });
    }
}
```

### Why Two Phases?

1. **Register Phase**:
    - Define what services exist
    - Set up dependencies
    - No WordPress functions used
    - Can be tested in isolation

2. **Boot Phase**:
    - WordPress is fully loaded
    - Register hooks and filters
    - Set up integrations
    - Initialize features

### Service Provider Best Practices

```php
class FeatureServiceProvider extends ServiceProvider {
    // Group related services
    private const SERVICES = [
        'feature.repository' => Repository::class,
        'feature.validator' => Validator::class,
        'feature.api' => ApiClient::class,
    ];
    
    public function register(): void {
        // Bulk registration
        foreach (self::SERVICES as $id => $class) {
            $this->container->set($id, autowire($class));
        }
        
        // Complex service with configuration
        $this->container->set('feature.importer', function($c) {
            return new Importer(
                $c->get('feature.repository'),
                $c->get('feature.validator'),
                $c->get('logger'),
                $c->get('config.import')
            );
        });
    }
}
```

## 📡 Event-Driven Architecture

### Understanding Events

Events allow different parts of your plugin to communicate without direct dependencies. Sitechips Core integrates with WordPress's action system while providing additional features.

### Plugin-Scoped Events

```php
// Dispatch an event
$plugin->dispatch('import.started', $importJob);

// Listen to events - automatically prefixed with plugin slug
add_action('my-plugin.import.started', function($plugin, $importJob) {
    $logger = $plugin->get('logger');
    $logger->info('Import started: ' . $importJob->getName());
});
```

### Cross-Plugin Events

```php
// Configure global prefix for cross-plugin communication
$plugin = PluginFactory::create(__FILE__, [
    'events.global_prefix' => 'mycompany'
]);

// Dispatch events that other plugins can listen to
$plugin->dispatch('order.completed', $order);
// Fires: 'my-plugin.order.completed' AND 'mycompany.order.completed'

// Other plugins can listen
add_action('mycompany.order.completed', function($plugin, $order) {
    // React to orders from any MyCompany plugin
});
```

### Event Patterns

#### 1. **Lifecycle Events**

```php
// Core events dispatched by the framework
'booted'        // Plugin fully initialized
'activating'    // Plugin being activated
'deactivating'  // Plugin being deactivated

// Usage
add_action('my-plugin.booted', function($plugin) {
    $plugin->get('cache')->warmup();
});
```

#### 2. **Custom Business Events**

```php
class OrderService {
    public function completeOrder(Order $order): void {
        // Business logic
        $order->markAsComplete();
        $this->repository->save($order);
        
        // Dispatch event for other systems
        $this->plugin->dispatch('order.completed', $order);
    }
}

// Multiple listeners can react
add_action('my-plugin.order.completed', [$inventory, 'updateStock']);
add_action('my-plugin.order.completed', [$email, 'sendConfirmation']);
add_action('my-plugin.order.completed', [$analytics, 'trackSale']);
```

#### 3. **Filter Events**

```php
// Apply filters to modify data
$price = $plugin->filter('product.price', $basePrice, $product);

// Listeners can modify the value
add_filter('my-plugin.product.price', function($price, $plugin, $product) {
    if ($product->isOnSale()) {
        return $price * 0.8; // 20% discount
    }
    return $price;
}, 10, 3);
```

## 🏭 Service Locator Pattern

### When to Use Service Locator

While Dependency Injection is preferred, the Service Locator pattern is useful for:
- WordPress hook callbacks
- Template functions
- Legacy code integration
- Quick prototyping

### Implementation

```php
// Define your service locator
class MyPlugin extends AbstractServiceLocator {
    protected static function setupPlugin(): Plugin {
        return PluginFactory::create(
            dirname(__DIR__) . '/my-plugin.php',
            ['debug' => WP_DEBUG],
            [
                CoreServiceProvider::class,
                FeatureServiceProvider::class
            ]
        );
    }
}
```

### Usage Patterns

```php
// In templates
<?php $products = MyPlugin::get('product.repository')->findAll(); ?>

// In WordPress hooks
add_action('init', function() {
    MyPlugin::get('feature.initializer')->init();
});

// In shortcodes
add_shortcode('my_products', function($atts) {
    $renderer = MyPlugin::get('product.renderer');
    return $renderer->renderList($atts);
});

// Quick access to plugin info
$version = MyPlugin::version();
$path = MyPlugin::path();
$url = MyPlugin::url();
```

### Service Locator Best Practices

1. **Use sparingly** - Prefer dependency injection
2. **Never in services** - Only in WordPress integration points
3. **Document usage** - Make it clear why it's needed
4. **Consider alternatives** - Can you use DI instead?

## 🧩 Bringing It All Together

### Example: Building a Feature

Let's see how all concepts work together in a real feature:

```php
// 1. Define the service provider
class ImportServiceProvider extends ServiceProvider {
    public function register(): void {
        // Register with DI container
        $this->container->set('import.reader', CsvReader::class);
        $this->container->set('import.validator', ImportValidator::class);
        $this->container->set('import.processor', function($c) {
            return new ImportProcessor(
                $c->get('import.reader'),
                $c->get('import.validator'),
                $c->get('product.repository'),
                $c->get('logger')
            );
        });
    }
    
    public function boot(): void {
        // WordPress integration
        add_action('admin_menu', [$this, 'addImportPage']);
        add_action('wp_ajax_process_import', [$this, 'handleImport']);
    }
    
    public function handleImport(): void {
        // Use the service
        $processor = $this->container->get('import.processor');
        
        // Dispatch event
        $this->container->get('plugin')
            ->dispatch('import.started', $_FILES['import_file']);
        
        // Process with DI-managed service
        $result = $processor->process($_FILES['import_file']);
        
        // Dispatch completion event
        $this->container->get('plugin')
            ->dispatch('import.completed', $result);
        
        wp_send_json_success($result);
    }
}

// 2. Listen to events
add_action('my-plugin.import.completed', function($plugin, $result) {
    if ($result->hasErrors()) {
        $plugin->get('notifier')->notifyAdmins(
            'Import completed with errors',
            $result->getErrors()
        );
    }
});

// 3. Access via Service Locator when needed
add_action('admin_notices', function() {
    if (MyPlugin::has('import.last_result')) {
        $result = MyPlugin::get('import.last_result');
        if ($result->hasErrors()) {
            echo '<div class="notice notice-warning">...</div>';
        }
    }
});
```

## 🎓 Key Takeaways

1. **Dependency Injection** makes your code testable and maintainable
2. **Service Providers** organize services and WordPress integration
3. **Events** enable loose coupling between components
4. **Service Locator** provides convenient access when DI isn't practical
5. **All patterns work together** to create clean, professional plugins

---

Ready to explore the components? Continue to [**Components Overview**](components/README.md) →