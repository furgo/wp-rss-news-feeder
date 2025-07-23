# Sitechips Core Framework Documentation

> **Modern WordPress Plugin Development with PHP Best Practices**

Welcome to the Sitechips Core Framework documentation. This framework brings modern PHP development practices to WordPress plugin development, including Dependency Injection, Service Providers, and isolated dependencies through Strauss.

## 📚 Table of Contents

### Getting Started
- [**Motivation**](motivation.md) - Why Sitechips Core? Problems it solves
- [**Getting Started**](getting-started.md) - Quick start guide and installation
- [**Architecture Overview**](architecture.md) - System design and components

### Core Concepts
- [**Core Concepts**](core-concepts.md) - DI, Service Providers, Events
- [**Components Overview**](components/README.md) - Framework components
    - [Container](components/container.md) - Dependency Injection Container
    - [Service Provider](components/service-provider.md) - Service organization
    - [Plugin Factory](components/plugin-factory.md) - Plugin bootstrapping
    - [Plugin Class](components/plugin.md) - Main plugin functionality
    - [Service Locator](components/service-locator.md) - Static service access
    - [Core Services](components/services.md) - Built-in services

### Development Guides
- [**Development Guides**](guides/README.md) - Step-by-step tutorials
    - [Simple Plugin](guides/simple-plugin.md) - Without Service Providers
    - [Advanced Plugin](guides/advanced-plugin.md) - With full architecture
    - [Service Providers](guides/service-providers.md) - Creating providers
    - [Testing](guides/testing.md) - Unit and integration testing
    - [Settings API](guides/settings.md) - WordPress settings integration
    - [Event System](guides/events.md) - Event-driven development

### Recipes & Examples
- [**Cookbook**](cookbook/README.md) - Common tasks and solutions
- [**Migration Guide**](migration.md) - Migrating legacy plugins
- [**API Reference**](api-reference.md) - Complete API documentation
- [**Troubleshooting**](troubleshooting.md) - Common issues and solutions

### Contributing
- [**Contributing**](contributing.md) - How to contribute to the framework

---

## 🚀 Quick Start

```php
<?php
// my-plugin.php
use Furgo\Sitechips\Core\Plugin\PluginFactory;

$plugin = PluginFactory::create(__FILE__, [
    'cache.enabled' => true,
    'api.endpoint' => 'https://api.example.com'
]);

add_action('plugins_loaded', fn() => $plugin->boot());
```

That's it! You now have a modern WordPress plugin with:
- ✅ **Dependency Injection Container**
- ✅ **Service Provider Architecture**
- ✅ **Event System**
- ✅ **PSR-3 Logging**
- ✅ **Settings Management**
- ✅ **Asset Management**

## 🎯 Key Features

### Modern PHP in WordPress
- **Composer Integration** - Finally use Composer packages in WordPress without conflicts!
- **Strauss Isolation** - Dependencies are isolated per plugin, preventing version conflicts
- **PSR Standards** - PSR-4 autoloading, PSR-11 container, PSR-3 logging
- **PHP 8.1+** - Modern PHP features like strict types, attributes, and more

### Professional Architecture
- **Dependency Injection** - No more global variables or singletons
- **Service Providers** - Organize code into logical, reusable modules
- **Event-Driven** - Decouple components with events
- **Testable** - Built with testing in mind from the ground up

### WordPress Integration
- **Hooks Compatible** - Works seamlessly with WordPress hooks
- **Settings API** - Enhanced settings management
- **Admin Integration** - Easy admin page creation
- **Asset Management** - Modern asset handling

## 📖 Tests as Documentation

The framework includes comprehensive test coverage. Tests serve as living documentation:

```bash
# View test examples
lib/tests/Unit/Container/ContainerTest.php
lib/tests/Unit/Plugin/PluginFactoryTest.php
```

Tests demonstrate:
- How to use each component
- Expected behavior
- Edge cases and error handling
- Best practices

## 🏗️ Framework vs. Plugin Development

This documentation covers two use cases:

1. **Framework Development** - Contributing to Sitechips Core itself
2. **Plugin Development** - Using Sitechips Core for your plugins

Most developers will focus on plugin development. Framework sections are clearly marked.

## 💡 Philosophy

Sitechips Core believes in:
- **Progressive Enhancement** - Start simple, add complexity as needed
- **WordPress Native** - Enhance, don't replace WordPress
- **Developer Experience** - Make the right thing the easy thing
- **Production Ready** - Performance and stability for real-world use

---

Ready to build modern WordPress plugins? Start with [**Why Sitechips Core?**](motivation.md) →