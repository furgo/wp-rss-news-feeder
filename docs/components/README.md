# Components Overview

> **The building blocks of Sitechips Core Framework**

This section provides detailed documentation for each component of the Sitechips Core Framework. Each component is designed to solve specific challenges in WordPress plugin development while working seamlessly together.

## 🧩 Core Components

### 📦 [Container](container.md)
**PSR-11 Dependency Injection Container**

The heart of the framework - manages service registration, dependency resolution, and auto-wiring.

```php
$container->set('logger', WordPressLogger::class);
$logger = $container->get('logger');
```

**Key Features:**
- PSR-11 compliant
- Auto-wiring support
- WordPress-optimized performance
- Singleton management

---

### 🔌 [Service Provider](service-provider.md)
**Modular Service Organization**

Organizes related services and handles WordPress integration in a clean, testable way.

```php
class MyServiceProvider extends ServiceProvider {
    public function register(): void { /* Define services */ }
    public function boot(): void { /* WordPress hooks */ }
}
```

**Key Features:**
- Two-phase initialization
- WordPress hook helpers
- Service grouping
- Lazy loading support

---

### 🏭 [Plugin Factory](plugin-factory.md)
**Plugin Instance Creation**

Creates and configures plugin instances with all necessary dependencies and metadata.

```php
$plugin = PluginFactory::create(__FILE__, $config, $providers);
```

**Key Features:**
- Automatic metadata extraction
- Environment detection
- Test mode support
- Configuration merging

---

### 🎯 [Plugin Class](plugin.md)
**Main Plugin Controller**

The central hub that provides access to all services and coordinates plugin functionality.

```php
$service = $plugin->get('my.service');
$plugin->dispatch('event.name', $data);
$plugin->log('Important message');
```

**Key Features:**
- Service resolution
- Event dispatching
- Logging integration
- Lifecycle management

---

### 🔍 [Service Locator](service-locator.md)
**Static Service Access**

Optional pattern for accessing services statically - useful for WordPress integration points.

```php
class MyPlugin extends AbstractServiceLocator {
    // Static access from anywhere
}
MyPlugin::get('service')->doSomething();
```

**Key Features:**
- Singleton pattern
- Static convenience methods
- WordPress-friendly API
- Testing support

---

### 🛠️ [Core Services](services.md)
**Built-in Framework Services**

Ready-to-use services that handle common plugin needs.

#### Available Services:
- **EventManager** - Event dispatching and filtering
- **Logger** - PSR-3 logging (Null/WordPress implementations)
- **AssetManager** - CSS/JS asset management
- **SettingsManager** - WordPress settings API wrapper
- **SettingsPage** - Admin settings page creation
- **FieldRenderer** - Form field rendering

---

## 🏗️ Component Relationships

```
                    ┌─────────────────┐
                    │ PluginFactory   │
                    └────────┬────────┘
                             │ creates
                    ┌────────▼────────┐
                    │     Plugin      │
                    └────────┬────────┘
                             │ uses
                    ┌────────▼────────┐
     ┌──────────────┤   Container     ├──────────────┐
     │              └────────┬────────┘              │
     │ manages               │ contains              │ manages
     ▼                       ▼                       ▼
ServiceProviders        Core Services          Your Services
```

## 🎨 Component Categories

### Infrastructure Components
These provide the foundation:
- **Container** - Dependency management
- **Plugin Factory** - Bootstrap and configuration
- **Plugin** - Runtime coordination

### Organizational Components
These structure your code:
- **Service Provider** - Service grouping and registration
- **Service Locator** - Static access pattern

### Functional Components
These provide features:
- **EventManager** - Communication between components
- **Logger** - Application logging
- **Settings Suite** - Configuration management
- **AssetManager** - Frontend resource handling

## 📝 Usage Patterns

### Basic Plugin Setup
```php
// 1. Create plugin with factory
$plugin = PluginFactory::create(__FILE__);

// 2. Register services directly or via providers
$plugin->getContainer()->set('my.service', MyService::class);

// 3. Boot when WordPress is ready
add_action('plugins_loaded', [$plugin, 'boot']);
```

### With Service Providers
```php
// 1. Create providers for features
class FeatureProvider extends ServiceProvider { ... }

// 2. Register with factory
$plugin = PluginFactory::create(__FILE__, [], [
    CoreProvider::class,
    FeatureProvider::class
]);

// 3. Providers handle registration and booting
```

### With Service Locator
```php
// 1. Create service locator
class MyPlugin extends AbstractServiceLocator {
    protected static function setupPlugin(): Plugin {
        return PluginFactory::create(__FILE__);
    }
}

// 2. Use statically anywhere
MyPlugin::get('service')->method();
```

## 🔧 Choosing Components

### For Small Plugins
- Use **PluginFactory** + **Plugin** directly
- Register services in main file
- Skip Service Providers if not needed

### For Medium Plugins
- Add **Service Providers** for organization
- Use **Core Services** for common tasks
- Consider **Service Locator** for convenience

### For Large Plugins
- Use all components
- Multiple **Service Providers** per feature
- Extensive use of **Core Services**
- Custom services extending framework

## 🚀 Best Practices

1. **Start Simple** - Use only what you need
2. **Follow PSR Standards** - Ensures compatibility
3. **Prefer Dependency Injection** - Over Service Locator
4. **Group Related Services** - In Service Providers
5. **Use Type Hints** - For better IDE support and safety

## 📚 Learning Path

1. Start with [Container](container.md) - Understanding DI is fundamental
2. Learn [Plugin Factory](plugin-factory.md) - How plugins are created
3. Explore [Service Provider](service-provider.md) - Organizing your code
4. Master [Core Services](services.md) - Leverage built-in functionality
5. Consider [Service Locator](service-locator.md) - When appropriate

---

Ready to dive deep? Start with the [**Container**](container.md) component →