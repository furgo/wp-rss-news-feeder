# Plugin Class Component

> **The central hub providing service access, event dispatching, and lifecycle management**

The Plugin class is the main controller of your WordPress plugin. It provides access to the container, manages the plugin lifecycle, dispatches events, and offers convenient methods for common tasks.

## 🎯 Purpose

The Plugin class serves as:
- **Service Gateway** - Access point for all services
- **Event Dispatcher** - Handles plugin events
- **Lifecycle Manager** - Manages boot process
- **Logger Interface** - Convenient logging methods
- **Container Wrapper** - Simplified container access

## 🚀 Basic Usage

### Accessing Services

```php
// Get the plugin instance (created by PluginFactory)
$plugin = PluginFactory::create(__FILE__);

// Access services
$logger = $plugin->get('logger');
$cache = $plugin->get('cache');
$repository = $plugin->get('user.repository');

// Check if service exists
if ($plugin->has('premium.features')) {
    $premium = $plugin->get('premium.features');
    $premium->activate();
}

// Get with error handling
try {
    $service = $plugin->get('my.service');
} catch (ContainerNotFoundException $e) {
    $plugin->logError('Service not found: ' . $e->getMessage());
}
```

### Creating Instances

```php
// Create instance with dependency injection
$controller = $plugin->make(ImportController::class);

// With constructor parameters
$validator = $plugin->make(Validator::class, [
    'rules' => ['email' => 'required|email']
]);

// Call method with dependency injection
$result = $plugin->call([$controller, 'import'], [
    'file' => $_FILES['import_file']
]);
```

## 🔄 Plugin Lifecycle

### Boot Process

```php
// Main plugin file
$plugin = PluginFactory::create(__FILE__, $config, $providers);

// Boot when WordPress is ready
add_action('plugins_loaded', function() use ($plugin) {
    $plugin->boot();
}, 5);

// What happens during boot:
// 1. Check if already booted (prevent double boot)
// 2. Boot all registered ServiceProviders
// 3. Mark plugin as booted
// 4. Dispatch 'booted' event
```

### Lifecycle Hooks

```php
// Listen to plugin lifecycle events
add_action('my-plugin.booted', function($plugin) {
    // Plugin is fully initialized
    $plugin->get('cache')->warmUp();
    $plugin->get('cron')->scheduleJobs();
});

// Custom lifecycle events
register_activation_hook(__FILE__, function() use ($plugin) {
    $plugin->dispatch('activating');
    // Activation logic...
    $plugin->dispatch('activated');
});

register_deactivation_hook(__FILE__, function() use ($plugin) {
    $plugin->dispatch('deactivating');
    // Cleanup...
    $plugin->dispatch('deactivated');
});
```

## 📡 Event System

### Dispatching Events

```php
// Basic event dispatch
$plugin->dispatch('import.started');

// With data
$plugin->dispatch('user.created', $user);

// Multiple arguments
$plugin->dispatch('order.processed', $order, $payment, $shipping);

// Events are automatically prefixed with plugin slug
// 'user.created' becomes 'my-plugin.user.created'
```

### Global Events

```php
// Configure global prefix in factory
$plugin = PluginFactory::create(__FILE__, [
    'events.global_prefix' => 'acme_company'
]);

// Now events fire twice:
$plugin->dispatch('order.completed', $order);
// Fires: 'my-plugin.order.completed' (plugin-specific)
// Fires: 'acme_company.order.completed' (global)

// Other ACME plugins can listen
add_action('acme_company.order.completed', function($plugin, $order) {
    // React to orders from any ACME plugin
});
```

### Event Patterns

```php
// Progress events
$plugin->dispatch('import.progress', ['current' => 50, 'total' => 100]);

// Error events
try {
    $importer->import($file);
} catch (Exception $e) {
    $plugin->dispatch('import.failed', $e);
    throw $e;
}

// State change events
$plugin->dispatch('status.changed', $oldStatus, $newStatus);

// Batch events
$plugin->dispatch('batch.started', $batchId);
foreach ($items as $item) {
    $plugin->dispatch('item.processed', $item);
}
$plugin->dispatch('batch.completed', $batchId, $results);
```

## 📝 Logging

### Basic Logging

```php
// Log with default level (info)
$plugin->log('Import process started');

// Log with specific level
$plugin->log('Debug information', 'debug');
$plugin->log('User logged in', 'info');
$plugin->log('API rate limit reached', 'warning');
$plugin->log('Database connection failed', 'error');

// Convenience method for errors
$plugin->logError('Critical failure in payment processing');
```

### Logging Implementation

The Plugin class uses registered logger or falls back to error_log:

```php
// If logger service exists
if ($plugin->has('logger')) {
    $logger = $plugin->get('logger');
    $logger->log($level, $message);
}

// Fallback to error_log
if ($debug || $level === 'error') {
    error_log("[{$pluginSlug}] {$level}: {$message}");
}
```

### Structured Logging

```php
// Register PSR-3 logger
$container->set('logger', function() {
    return new WordPressLogger('my-plugin');
});

// Use context for structured data
$logger = $plugin->get('logger');
$logger->info('User action', [
    'user_id' => $userId,
    'action' => 'purchase',
    'amount' => 99.99
]);
```

## 🎨 Usage Patterns

### Service Resolution Pattern

```php
class OrderController
{
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }
    
    public function createOrder(array $data): Order
    {
        // Resolve services as needed
        $validator = $this->plugin->get('order.validator');
        $repository = $this->plugin->get('order.repository');
        $events = $this->plugin->get('events');
        
        // Validate
        if (!$validator->validate($data)) {
            throw new ValidationException($validator->errors());
        }
        
        // Create order
        $order = $repository->create($data);
        
        // Dispatch event
        $this->plugin->dispatch('order.created', $order);
        
        return $order;
    }
}
```

### Lazy Service Pattern

```php
class ExpensiveService
{
    private ?ApiClient $client = null;
    private Plugin $plugin;
    
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }
    
    private function getClient(): ApiClient
    {
        // Lazy load expensive service
        if ($this->client === null) {
            $this->client = $this->plugin->get('api.client');
        }
        return $this->client;
    }
    
    public function fetchData(): array
    {
        return $this->getClient()->request('/data');
    }
}
```

### Plugin-Aware Services

```php
interface PluginAwareInterface
{
    public function setPlugin(Plugin $plugin): void;
}

class PluginAwareService implements PluginAwareInterface
{
    private Plugin $plugin;
    
    public function setPlugin(Plugin $plugin): void
    {
        $this->plugin = $plugin;
    }
    
    public function doSomething(): void
    {
        $this->plugin->log('Doing something');
        $this->plugin->dispatch('something.done');
    }
}

// In ServiceProvider
$this->bind('aware.service', function($container) {
    $service = new PluginAwareService();
    $service->setPlugin($container->get('plugin'));
    return $service;
});
```

## 🔍 Plugin Information

### Accessing Plugin Metadata

```php
// Get plugin file path
$pluginFile = $plugin->getPluginFile();

// Access container directly (use sparingly)
$container = $plugin->getContainer();

// Check if booted
if ($plugin->isBooted()) {
    // Safe to use all services
}

// Common metadata via container
$version = $plugin->get('plugin.version');
$name = $plugin->get('plugin.name');
$slug = $plugin->get('plugin.slug');
$textDomain = $plugin->get('plugin.text_domain');
```

### Environment Information

```php
// Check debug mode
if ($plugin->get('debug')) {
    $plugin->log('Debug mode enabled');
}

// Check environment
$environment = $plugin->get('environment');
switch ($environment) {
    case 'production':
        // Production optimizations
        break;
    case 'development':
        // Development features
        break;
    case 'testing':
        // Test mode
        break;
}
```

## 🧪 Testing with Plugin

### Unit Testing

```php
use Furgo\Sitechips\Core\Plugin\PluginFactory;

class PluginTest extends TestCase
{
    private Plugin $plugin;
    
    protected function setUp(): void
    {
        $this->plugin = PluginFactory::createForTesting('/tmp/test.php', [
            'test.mode' => true,
            'cache.driver' => 'array',
        ]);
    }
    
    public function testServiceResolution(): void
    {
        $this->plugin->getContainer()->set('test.service', TestService::class);
        
        $service = $this->plugin->get('test.service');
        $this->assertInstanceOf(TestService::class, $service);
    }
    
    public function testEventDispatching(): void
    {
        $called = false;
        add_action('test-plugin.test_event', function() use (&$called) {
            $called = true;
        });
        
        $this->plugin->dispatch('test_event');
        $this->assertTrue($called);
    }
}
```

### Mocking the Plugin

```php
class ServiceTest extends TestCase
{
    public function testServiceWithPlugin(): void
    {
        // Create mock plugin
        $plugin = $this->createMock(Plugin::class);
        
        // Configure mock
        $plugin->method('get')
               ->willReturnMap([
                   ['logger', new NullLogger()],
                   ['cache', new ArrayCache()],
               ]);
        
        $plugin->expects($this->once())
               ->method('dispatch')
               ->with('task.completed');
        
        // Test service
        $service = new MyService($plugin);
        $service->doTask();
    }
}
```

## 💡 Best Practices

### DO's ✅

```php
// Use type hints
public function __construct(private Plugin $plugin) {}

// Handle missing services gracefully
if ($plugin->has('optional.service')) {
    $service = $plugin->get('optional.service');
}

// Use events for decoupling
$plugin->dispatch('data.imported', $count);

// Log important operations
$plugin->log('Starting maintenance mode');

// Boot at the right time
add_action('plugins_loaded', [$plugin, 'boot']);
```

### DON'Ts ❌

```php
// Don't access container directly in services
$container = $plugin->getContainer(); // ❌ Breaks abstraction

// Don't boot multiple times
$plugin->boot();
$plugin->boot(); // ❌ Second call is ignored but wasteful

// Don't use plugin as service locator everywhere
class BadService {
    public function __construct(private Plugin $plugin) {
        // ❌ Should inject specific dependencies
    }
}

// Don't dispatch events in constructors
public function __construct(Plugin $plugin) {
    $plugin->dispatch('initialized'); // ❌ Too early
}
```

## 🐛 Common Issues

### Service Not Found

```php
// Problem: Service not registered
try {
    $service = $plugin->get('missing.service');
} catch (ContainerNotFoundException $e) {
    // Handle gracefully
    $plugin->logError('Required service not found: missing.service');
    return;
}
```

### Events Not Firing

```php
// Ensure WordPress is loaded
if (!function_exists('do_action')) {
    // Too early, WordPress not loaded
    return;
}

// Check event name format
$plugin->dispatch('my-event'); // Becomes: 'plugin-slug.my-event'
```

### Boot Issues

```php
// Ensure single boot
if ($plugin->isBooted()) {
    return; // Already booted
}

// Boot after WordPress loads
add_action('plugins_loaded', fn() => $plugin->boot());
```

## 📚 API Reference

### Public Methods

```php
// Service resolution
public function get(string $id): mixed
public function has(string $id): bool
public function call(callable|array $callback, array $parameters = []): mixed
public function make(string $className, array $parameters = []): object

// Lifecycle
public function boot(): void
public function isBooted(): bool

// Events
public function dispatch(string $event, mixed ...$args): void

// Logging
public function log(string $message, string $level = 'info'): void
public function logError(string $message): void

// Plugin info
public function getPluginFile(): string
public function getContainer(): Container
```

### Container Access Methods

These methods proxy to the container:
- `get()` - Retrieve service
- `has()` - Check service exists
- `call()` - Call with dependency injection
- `make()` - Create instance with dependencies

## 🔗 Related Components

- [Plugin Factory](plugin-factory.md) - Creates Plugin instances
- [Container](container.md) - Manages services
- [Service Provider](service-provider.md) - Uses Plugin in boot phase
- [Service Locator](service-locator.md) - Static access to Plugin

---

Continue to [**Service Locator**](service-locator.md) →