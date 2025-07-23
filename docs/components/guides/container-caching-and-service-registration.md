# Container Caching and Service Registration

## Overview

This document explains the constraints and best practices for service registration in Sitechips Core when using PHP-DI's container compilation/caching feature.

## The Problem

When PHP-DI's container compilation is enabled (production mode), you cannot register **definitions** (factories, closures, references) at runtime. Only **raw values** (instantiated objects, strings, integers) can be set after the container is built.

### Error Example
```
LogicException: You cannot set a definition at runtime on a container that has caching enabled. 
Doing so would risk caching the definition for the next execution, where it might be different.
```

## Technical Background

### What are Definitions?

PHP-DI definitions include:
- Factory functions: `function($container) { return new Service(); }`
- References: `DI\get('other.service')`
- Autowired classes: `DI\autowire(Service::class)`

### Why the Restriction?

1. **Compilation**: PHP-DI compiles the container into optimized PHP code
2. **Caching**: This compiled code is cached for performance
3. **Immutability**: Runtime definitions would change the container structure, invalidating the cache
4. **Performance**: The whole point of compilation is to avoid runtime resolution

### When is Caching Enabled?

In Sitechips Core:
```php
// Container.php constructor
if ($enableCompilation === null) {
    $enableCompilation = !defined('WP_DEBUG') || !WP_DEBUG;
}
```

- **Production** (WP_DEBUG = false): Caching enabled
- **Development** (WP_DEBUG = true): Caching disabled

## Solutions

### Solution 1: Direct Instantiation (Recommended) ✅

Register services as instantiated objects instead of factories:

```php
// ❌ BAD - Factory definition
$this->shared('logger', function($container) {
    return new Logger($container->get('config'));
});

// ✅ GOOD - Direct instantiation
$logger = new Logger($this->container->get('config'));
$this->container->set('logger', $logger);
```

**Pros:**
- Works with caching enabled
- Explicit and clear
- Production-ready

**Cons:**
- No lazy loading
- Services created even if not used

### Solution 2: Disable Caching ❌

Force caching off in PluginFactory:
```php
$container = new Container($definitions, false);
```

**Pros:**
- Can use factories and lazy loading
- More flexible

**Cons:**
- Loses performance benefits
- Not recommended for production

### Solution 3: Pre-compile Definitions ⚠️

Add all definitions before building the container:
```php
$definitions = [
    'logger' => function($c) { return new Logger(); },
    // ... all other services
];
$container = new Container($definitions);
```

**Pros:**
- Works with caching
- Maintains lazy loading

**Cons:**
- Breaks ServiceProvider pattern
- Less modular architecture

## Best Practices for ServiceProviders

### 1. Always Use Direct Instantiation in register()

```php
public function register(): void
{
    // Create services directly
    $service = new MyService(
        $this->container->get('dependency1'),
        $this->container->get('dependency2')
    );
    
    $this->container->set('my.service', $service);
}
```

### 2. Handle Optional Dependencies

```php
private function registerLogger(): void
{
    // Check if already registered
    if ($this->container->has('logger')) {
        return;
    }
    
    // Create based on environment
    $logger = $this->container->get('debug')
        ? new WordPressLogger($this->container->get('plugin.slug'))
        : new NullLogger();
    
    $this->container->set('logger', $logger);
}
```

### 3. Complex Services with Dependencies

```php
public function register(): void
{
    // Settings Manager with configuration
    $settings = new SettingsManager('my_plugin_settings');
    
    // Configure the service
    $settings->addSection('general', 'General Settings');
    $settings->addField('api_key', 'API Key', 'general');
    
    // Register configured service
    $this->container->set('settings', $settings);
    
    // Service depending on another
    $page = new SettingsPage(
        $settings,  // Direct reference, not container->get()
        ['menu_title' => 'My Plugin']
    );
    
    $this->container->set('settings.page', $page);
}
```

### 4. The alias() Method Issue

The `alias()` method requires special handling:

```php
// Container.php - Correct implementation
public function alias(string $alias, string $target): void
{
    // Get the actual service instance
    if (!$this->has($target)) {
        throw new ContainerNotFoundException(
            "Cannot create alias '$alias': target service '$target' not found"
        );
    }
    
    $service = $this->get($target);
    $this->container->set($alias, $service);
}
```

## Testing Considerations

### 1. Test Both Modes

```php
/**
 * @dataProvider compilationModeProvider
 */
public function testServiceRegistration(bool $enableCompilation): void
{
    $container = new Container(['cache.path' => $this->tempDir], $enableCompilation);
    // ... test logic
}

public function compilationModeProvider(): array
{
    return [
        'development mode' => [false],
        'production mode' => [true],  // This catches caching issues
    ];
}
```

### 2. Clear Cache Between Tests

```php
protected function setUp(): void
{
    parent::setUp();
    $this->clearCompiledContainer();
}
```

## Framework Design Decision

For Sitechips Core, we chose **Solution 1 (Direct Instantiation)** because:

1. **Production Ready**: Works with performance optimizations enabled
2. **Predictable**: No surprises between development and production
3. **WordPress Compatible**: Aligns with WordPress's eager-loading philosophy
4. **Simple**: Easy to understand and debug
5. **Testable**: Same behavior in tests and production

## Migration Guide

If converting from factory-based registration:

```php
// Before
$this->shared('service', function($c) {
    return new Service($c->get('dep1'), $c->get('dep2'));
});

// After
$service = new Service(
    $this->container->get('dep1'),
    $this->container->get('dep2')
);
$this->container->set('service', $service);
```

## Summary

- **Always use direct instantiation** in ServiceProvider register() methods
- **Never use factories/closures** when registering services at runtime
- **Test with caching enabled** to catch production issues early
- **Document why** services are instantiated directly (reference this doc)

This approach ensures consistent behavior across development, testing, and production environments while maintaining the performance benefits of container compilation.