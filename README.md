# Sitechips Boilerplate

A modular WordPress plugin framework for professional plugin development with modern PHP standards and comprehensive test coverage.

## Features

- 🏗️ **Modular Architecture** - Core + Features system
- 🔄 **Dependency Injection** - Container for clean dependencies
- 📡 **Event System** - Loosely coupled communication
- 🧪 **Unit Tests** - PHPUnit with WordPress stubs
- 📏 **Code Quality** - PHPStan, PHPCS, WordPress Standards
- 📦 **Composer Integration** - Modern PHP dependency management
- 🎯 **WordPress Compliant** - Hooks, standards, best practices

## Requirements

- **PHP:** 8.1 or higher
- **WordPress:** 6.4 or higher
- **Composer:** For dependency management

## Installation

### Development Environment

```bash
# Clone repository
git clone https://github.com/furgo/sitechips-boilerplate.git
cd sitechips-boilerplate

# Install dependencies (with Strauss)
composer install

# Activate in WordPress admin panel
```

## Development

```bash
# Show help
make help

# Run plugin tests
make test-plugin

# Check code quality  
make quality-plugin
```

## Framework Development

```bash
# All tests (Core + Plugin)
make test-all

# Core tests only
make test-core
```

## Documentation

For detailed developer documentation see: [docs/README.md](docs/README.md)

## License

GPL v2 or later