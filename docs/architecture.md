# Sitechips Core Architecture

> **Understanding the framework's design and component interaction**

This document provides a comprehensive overview of the Sitechips Core architecture, explaining how components work together to create a modern WordPress plugin development experience.

## 🏗️ High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           WordPress Environment                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────┐        Your Plugin         ┌─────────────────┐   │
│  │  Plugin Entry   │ ───────────────────────────▶│ Service Locator │   │
│  │  (my-plugin.php)│                             │ (AbstractService│   │
│  └────────┬────────┘                             │  Locator)       │   │
│           │                                       └─────────────────┘   │
│           │ uses                                           │            │
│           ▼                                                │ creates    │
│  ┌─────────────────┐     creates      ┌─────────────────┐ ▼            │
│  │ PluginFactory   │ ────────────────▶│     Plugin      │◀─────────   │
│  └─────────────────┘                  └────────┬────────┘              │
│           │                                     │                       │
│           │ configures                          │ manages               │
│           ▼                                     ▼                       │
│  ┌─────────────────┐    registers     ┌─────────────────┐              │
│  │   Container     │◀──────────────────│ServiceProviders │              │
│  │  (PSR-11 DI)   │                   └─────────────────┘              │
│  └────────┬────────┘                                                   │
│           │                                                             │
│           │ contains                                                    │
│           ▼                                                             │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                          Services                                │  │
│  ├─────────────────────────────────────────────────────────────────┤  │
│  │ • EventManager    • Logger (Null/WordPress)   • AssetManager    │  │
│  │ • SettingsManager • SettingsPage              • FieldRenderer   │  │
│  │ • Your Custom Services...                                       │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

## 📦 Component Layers

### 1. **Entry Layer** - Plugin Bootstrap

The entry point where WordPress meets Sitechips Core:

```php
// my-plugin.php
$plugin = PluginFactory::create(__FILE__, $config, $providers);
add_action('plugins_loaded', fn() => $plugin->boot());
```

### 2. **Factory Layer** - Plugin Construction

**PluginFactory** orchestrates the creation process:
- Extracts plugin metadata
- Creates and configures the Container
- Registers Service Providers
- Returns configured Plugin instance

### 3. **Container Layer** - Dependency Management

**Container** (PSR-11 compliant) manages services:
- Service registration and resolution
- Dependency injection
- Auto-wiring capabilities
- WordPress-optimized performance

### 4. **Provider Layer** - Service Organization

**ServiceProviders** organize related functionality:
- `register()` - Define services (early phase)
- `boot()` - WordPress integration (late phase)
- Modular architecture support

### 5. **Service Layer** - Core Functionality

Built-in and custom services provide features:
- Event management
- Logging
- Settings handling
- Asset management
- Your business logic

## 🔄 Data Flow

### Plugin Initialization Flow

```
1. WordPress loads plugin file
   ↓
2. PluginFactory::create() called
   ├── Extract plugin metadata
   ├── Create Container with base definitions
   ├── Register ServiceProviders
   └── Return Plugin instance
   ↓
3. WordPress fires 'plugins_loaded'
   ↓
4. Plugin::boot() called
   ├── Boot all ServiceProviders
   ├── Register WordPress hooks
   └── Dispatch 'booted' event
   ↓
5. Plugin ready for use
```

### Service Resolution Flow

```
1. Code requests service: $plugin->get('logger')
   ↓
2. Container checks for service
   ├── Found: Return instance
   └── Not found: Check auto-wiring
       ├── Can create: Instantiate with dependencies
       └── Cannot create: Throw NotFoundException
```

## 🏛️ Architectural Patterns

### Dependency Injection Container

The framework uses **Constructor Injection** pattern:

```php
class OrderService {
    public function __construct(
        private Database $db,
        private Logger $logger,
        private EventManager $events
    ) {}
}

// Container automatically injects dependencies
$orderService = $container->get(OrderService::class);
```

### Service Provider Pattern

Organizes services into logical groups:

```php
class EcommerceServiceProvider extends ServiceProvider {
    public function register(): void {
        // Register services
        $this->container->set('cart', CartService::class);
        $this->container->set('checkout', CheckoutService::class);
        $this->container->set('payment', PaymentGateway::class);
    }
    
    public function boot(): void {
        // WordPress integration
        $this->addAction('init', [$this, 'registerPostTypes']);
        $this->addAction('rest_api_init', [$this, 'registerEndpoints']);
    }
}
```

### Service Locator Pattern (Optional)

For static access when needed:

```php
class MyPlugin extends AbstractServiceLocator {
    protected static function setupPlugin(): Plugin {
        return PluginFactory::create(__FILE__, $config, $providers);
    }
}

// Use anywhere
MyPlugin::get('cart')->addItem($product);
```

### Event-Driven Architecture

Loose coupling through events:

```php
// Dispatch events
$plugin->dispatch('order.placed', $order);

// Listen from anywhere
add_action('my-plugin.order.placed', function($plugin, $order) {
    $plugin->get('inventory')->update($order);
    $plugin->get('email')->sendConfirmation($order);
});
```

## 🔧 Dependency Isolation with Strauss

### The Problem

```
Without Strauss:
├── plugin-a/
│   └── vendor/guzzle (v6.0)
├── plugin-b/
│   └── vendor/guzzle (v7.0)
└── 💥 Class redeclaration error!
```

### The Solution

```
With Strauss:
├── plugin-a/
│   └── src/Libs/PluginA/Guzzle (v6.0)
├── plugin-b/
│   └── src/Libs/PluginB/Guzzle (v7.0)
└── ✅ No conflicts!
```

### How It Works

1. **Composer Install**: Downloads packages to `vendor/`
2. **Strauss Process**:
    - Copies packages to `src/Libs/`
    - Rewrites namespaces
    - Updates autoloader
3. **Runtime**: Each plugin uses its isolated dependencies

## 📁 Directory Structure

```
my-plugin/
├── my-plugin.php              # Entry point
├── composer.json              # Dependencies
├── .strauss.json             # Isolation config
│
├── lib/                      # Sitechips Core Framework
│   ├── Container/           # DI Container
│   ├── Plugin/              # Plugin management
│   ├── Services/            # Core services
│   └── Contracts/           # Interfaces
│
├── src/                     # Your plugin code
│   ├── Libs/               # Isolated dependencies (Strauss)
│   ├── Providers/          # Service providers
│   ├── Services/           # Your services
│   ├── Controllers/        # Request handlers
│   └── Models/             # Data models
│
├── tests/                   # Test suite
│   ├── Unit/               # Unit tests
│   └── Integration/        # Integration tests
│
└── vendor/                  # Composer packages (not distributed)
```

## 🔌 WordPress Integration Points

### Hooks System

Sitechips Core enhances but doesn't replace WordPress hooks:

```php
class MyServiceProvider extends ServiceProvider {
    public function boot(): void {
        // Traditional WordPress hooks
        add_action('init', [$this, 'onInit']);
        add_filter('the_content', [$this, 'filterContent']);
        
        // With container integration
        add_action('save_post', function($postId) {
            $this->container->get('cache')->clear("post_$postId");
        });
    }
}
```

### Database Integration

```php
class PostRepository {
    public function __construct(private \wpdb $wpdb) {}
    
    public function findPublished(): array {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->posts} 
             WHERE post_status = 'publish'"
        );
    }
}
```

### Settings API Enhancement

```php
$settings = new SettingsManager('my_plugin_options');
$settings->addSection('general', 'General Settings')
         ->addField('api_key', 'API Key', 'general')
         ->register();
```

## 🚀 Performance Considerations

### Container Compilation

In production mode:
- Container definitions are compiled
- Faster service resolution
- Reduced memory usage

```php
// Automatic in production (WP_DEBUG = false)
$container = new Container($definitions, true); // compiled mode
```

### Lazy Loading

Services are created on-demand:
```php
// Service not created until first use
$logger = $plugin->get('logger'); // Created here
```

### WordPress Native

The framework doesn't replace WordPress systems:
- Uses native hooks system
- Works with existing APIs
- No custom routing or request handling

## 🔐 Security Architecture

### Dependency Isolation
- Each plugin has isolated dependencies
- No shared global state
- Reduced attack surface

### Type Safety
- PHP 8.1+ strict types
- Container ensures type correctness
- Reduced runtime errors

### WordPress Standards
- Follows WordPress security practices
- Uses WordPress APIs for data handling
- Compatible with security plugins

## 📚 Key Concepts Summary

1. **Separation of Concerns** - Each component has a single responsibility
2. **Dependency Inversion** - Depend on abstractions, not concretions
3. **Modular Architecture** - Features organized in Service Providers
4. **WordPress Native** - Enhances rather than replaces WordPress
5. **Developer Experience** - Modern tools in WordPress context

---

Ready to explore individual components? Continue to [**Core Concepts**](core-concepts.md) →