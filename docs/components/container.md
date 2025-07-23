# Container Component

> **PSR-11 Dependency Injection Container powered by PHP-DI**

The Container is the heart of Sitechips Core, managing service registration and dependency resolution. It provides a WordPress-optimized wrapper around PHP-DI with convenient methods for common use cases.

## 🎯 Purpose

The Container solves fundamental problems in WordPress plugin development:
- **Dependency Management** - No more manual wiring of dependencies
- **Service Location** - Central place to find services
- **Auto-wiring** - Automatic dependency injection
- **Performance** - Compiled container in production

## 🚀 Basic Usage

### Creating a Container

```php
use Furgo\Sitechips\Core\Container\Container;

// Basic container
$container = new Container();

// With initial definitions
$container = new Container([
    'api.key' => 'your-api-key',
    'cache.enabled' => true,
    'logger' => WordPressLogger::class,
]);

// With production compilation disabled (for development)
$container = new Container([], false);
```

### Registering Services

```php
// Register a concrete class
$container->set('logger', WordPressLogger::class);

// Register a factory function
$container->set('mailer', function($container) {
    $config = $container->get('mail.config');
    return new Mailer($config);
});

// Register an instance
$container->set('config', new Configuration('/path/to/config'));

// Register with alias
$container->set('cache', RedisCache::class);
$container->alias('cache.store', 'cache');
```

### Retrieving Services

```php
// Get a service
$logger = $container->get('logger');

// Check if service exists
if ($container->has('cache')) {
    $cache = $container->get('cache');
}

// Get with type safety
try {
    $service = $container->get('my.service');
} catch (ContainerNotFoundException $e) {
    // Service not found
} catch (ContainerException $e) {
    // Error creating service
}
```

## 🔧 Advanced Features

### Auto-wiring

The Container can automatically resolve class dependencies:

```php
// Given these classes
class UserRepository {
    public function __construct(
        private Database $db,
        private CacheInterface $cache
    ) {}
}

class UserService {
    public function __construct(
        private UserRepository $repository,
        private LoggerInterface $logger
    ) {}
}

// Register dependencies
$container->set(Database::class, MySQLDatabase::class);
$container->set(CacheInterface::class, RedisCache::class);
$container->set(LoggerInterface::class, WordPressLogger::class);

// Container automatically injects all dependencies
$userService = $container->make(UserService::class);
```

### Factory Functions

Use factory functions for complex service creation:

```php
$container->set('payment.gateway', function($container) {
    $config = $container->get('payment.config');
    
    switch ($config['provider']) {
        case 'stripe':
            return new StripeGateway($config['stripe_key']);
        case 'paypal':
            return new PayPalGateway($config['paypal_id']);
        default:
            return new TestGateway();
    }
});
```

### Method Injection

Call methods with automatic dependency injection:

```php
class ImportController {
    public function import(
        CsvReader $reader, 
        Validator $validator,
        Repository $repository
    ) {
        // Method dependencies are automatically injected
    }
}

// Call with dependency injection
$controller = new ImportController();
$result = $container->call([$controller, 'import']);
```

### Service Decoration

Enhance existing services:

```php
// Original service
$container->set('logger', FileLogger::class);

// Decorate with additional functionality
$container->set('logger', function($container) {
    $fileLogger = $container->make(FileLogger::class);
    return new BufferedLogger($fileLogger);
});
```

## 🏗️ Configuration Patterns

### Environment-based Configuration

```php
$config = [
    'cache.enabled' => !defined('WP_DEBUG') || !WP_DEBUG,
    'cache.driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
    'api.timeout' => (int) ($_ENV['API_TIMEOUT'] ?? 30),
];

$container = new Container($config);

// Use configuration
$container->set('cache', function($c) {
    if (!$c->get('cache.enabled')) {
        return new NullCache();
    }
    
    return match($c->get('cache.driver')) {
        'redis' => new RedisCache($c->get('redis.connection')),
        'memcached' => new MemcachedCache($c->get('memcached.servers')),
        default => new FileCache($c->get('cache.path')),
    };
});
```

### Service Provider Registration

```php
// ServiceProviders have access to container
class DatabaseServiceProvider extends ServiceProvider {
    public function register(): void {
        // Use convenience methods from parent
        $this->bind('db.connection', function() {
            return new PDO(
                $this->container->get('db.dsn'),
                $this->container->get('db.user'),
                $this->container->get('db.pass')
            );
        });
        
        $this->shared('db.repository', UserRepository::class);
    }
}
```

## 🎭 Production vs Development

### Production Mode (Compiled)

When `WP_DEBUG` is false or compilation is enabled:

```php
// Container automatically enables compilation
$container = new Container($definitions); // Compiled if !WP_DEBUG

// What happens:
// 1. Container definitions are compiled to PHP files
// 2. Faster service resolution
// 3. Better performance
// 4. Cached in wp-content/cache/container/
```

### Development Mode

When `WP_DEBUG` is true or compilation is disabled:

```php
// Force development mode
$container = new Container($definitions, false);

// Benefits:
// 1. No compilation overhead
// 2. Changes reflected immediately
// 3. Better error messages
// 4. Easier debugging
```

## 📊 Performance Considerations

### Lazy Services

Services are created only when needed:

```php
// This doesn't create the logger yet
$container->set('logger', ExpensiveLogger::class);

// Logger is created here (lazy instantiation)
$logger = $container->get('logger');
```

### Shared Instances (Singletons)

By default, services are shared (singleton):

```php
$container->set('logger', WordPressLogger::class);

$logger1 = $container->get('logger');
$logger2 = $container->get('logger');

// Same instance
assert($logger1 === $logger2); // true
```

### Compilation Benefits

In production, the compiled container provides:
- **50-80% faster** service resolution
- **Reduced memory usage**
- **Optimized dependency graphs**
- **Pre-validated service definitions**

## 🧪 Testing with Container

### Test Doubles

```php
class ServiceTest extends TestCase {
    private Container $container;
    
    protected function setUp(): void {
        $this->container = new Container([], false); // No compilation in tests
        
        // Register test doubles
        $this->container->set('logger', function() {
            return $this->createMock(LoggerInterface::class);
        });
        
        $this->container->set('cache', function() {
            return new ArrayCache(); // In-memory cache for tests
        });
    }
    
    public function testService(): void {
        $service = $this->container->make(MyService::class);
        // Test with mocked dependencies
    }
}
```

### Overriding Services

```php
// In tests, override production services
$container->set('emailer', TestEmailer::class); // Won't send real emails
$container->set('payment', TestPaymentGateway::class); // Won't charge cards
```

## 💡 Best Practices

### DO's ✅

```php
// Use interfaces for flexibility
$container->set(LoggerInterface::class, WordPressLogger::class);

// Use factory functions for complex creation
$container->set('complex.service', function($c) {
    return new ComplexService(
        $c->get('dep1'),
        $c->get('dep2'),
        $c->get('config.value')
    );
});

// Group related definitions
$container->set('mail.transport', SmtpTransport::class);
$container->set('mail.from', 'noreply@example.com');
$container->set('mail.mailer', Mailer::class);
```

### DON'Ts ❌

```php
// Don't use container as service locator in services
class BadService {
    public function __construct(private Container $container) {
        // Bad: Makes dependencies implicit
    }
}

// Don't register WordPress hooks in service definitions
$container->set('service', function() {
    $service = new Service();
    add_action('init', [$service, 'init']); // Don't do this!
    return $service;
});

// Don't mutate services after creation
$logger = $container->get('logger');
$logger->setLevel('debug'); // Affects all users of logger!
```

## 📚 API Reference

### Constructor

```php
public function __construct(
    array $definitions = [], 
    ?bool $enableCompilation = null
)
```

### Core Methods

```php
// PSR-11 methods
public function get(string $id): mixed
public function has(string $id): bool

// Registration methods  
public function set(string $id, mixed $value): void
public function shared(string $id, mixed $value): void
public function alias(string $alias, string $target): void

// Advanced methods
public function call(callable|array $callable, array $parameters = []): mixed
public function make(string $className, array $parameters = []): object

// Inspection methods
public function isCompiled(): bool
public function getInternalContainer(): PHPDIContainer
```

## 🔍 Debugging Tips

### Debug Service Resolution

```php
// Check if service exists
if (!$container->has('my.service')) {
    error_log('Service not registered: my.service');
}

// Catch specific exceptions
try {
    $service = $container->get('my.service');
} catch (ContainerNotFoundException $e) {
    error_log('Service not found: ' . $e->getMessage());
} catch (DependencyException $e) {
    error_log('Dependency error: ' . $e->getMessage());
}
```

### List All Services

```php
// In development, inspect registered services
if (WP_DEBUG) {
    $internal = $container->getInternalContainer();
    $definitions = $internal->getKnownEntryNames();
    error_log('Registered services: ' . print_r($definitions, true));
}
```

## 🔗 Related Components

- [Service Provider](service-provider.md) - Organize service registration
- [Plugin Factory](plugin-factory.md) - Creates configured containers
- [Plugin](plugin.md) - Provides container access

---

Continue to [**Service Provider**](service-provider.md) →