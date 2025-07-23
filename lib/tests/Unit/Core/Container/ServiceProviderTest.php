<?php
/**
 * Service Provider Unit Tests
 *
 * Comprehensive tests for the ServiceProvider base class.
 * Tests service registration, WordPress integration, dependency injection,
 * and the provider lifecycle (register/boot phases).
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Container
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Container;

use Furgo\Sitechips\Core\Container\Container;
use Furgo\Sitechips\Core\Container\ServiceProvider;
use Furgo\Sitechips\Core\Contracts\ServiceProviderInterface;
use Furgo\Sitechips\Core\Contracts\Bootable;
use Furgo\Sitechips\Core\Tests\TestCase;
use Furgo\Sitechips\Core\Container\ContainerException;
use Furgo\Sitechips\Core\Container\ContainerNotFoundException;

/**
 * Service Provider Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Container\ServiceProvider
 */
class ServiceProviderTest extends TestCase
{
    /**
     * Container instance
     *
     * @var Container
     */
    private Container $container;

    /**
     * Test service provider
     *
     * @var ConcreteServiceProvider
     */
    private ConcreteServiceProvider $provider;

    /**
     * Temporary cache directory for compilation tests
     *
     * @var string
     */
    private string $tempCacheDir;

    /**
     * Set up each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container([], false);
        $this->provider = new ConcreteServiceProvider($this->container);

        // Create temporary cache directory for compilation tests
        $this->tempCacheDir = sys_get_temp_dir() . '/sitechips-test-cache-' . uniqid();
        if (!is_dir($this->tempCacheDir)) {
            mkdir($this->tempCacheDir, 0777, true);
        }
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temporary cache directory
        if (is_dir($this->tempCacheDir)) {
            $this->recursiveRemoveDirectory($this->tempCacheDir);
        }
    }

    /**
     * Recursively remove directory
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveRemoveDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Provides test cases for both compilation modes
     *
     * @return array<string, array<bool>>
     */
    public function compilationModeProvider(): array
    {
        return [
            'development mode' => [false],
            'production mode' => [true],
        ];
    }

    // ========================================================================
    // Basic Tests
    // ========================================================================

    /**
     * @group basic
     *
     * Tests that ServiceProvider implements required interfaces.
     * These interfaces ensure providers can be registered and booted properly.
     */
    public function testImplementsRequiredInterfaces(): void
    {
        $this->assertInstanceOf(ServiceProviderInterface::class, $this->provider);
        $this->assertInstanceOf(Bootable::class, $this->provider);
    }

    /**
     * @group basic
     *
     * Tests provider construction and initial state.
     * Provider should store container reference and not be registered/booted.
     */
    public function testConstructionAndInitialState(): void
    {
        $provider = new ConcreteServiceProvider($this->container);

        $this->assertFalse($provider->isRegistered());
        $this->assertFalse($provider->isBooted());
    }

    /**
     * @group basic
     *
     * Tests the abstract register method is enforced.
     * Concrete providers must implement register() to define services.
     */
    public function testRegisterMethodMustBeImplemented(): void
    {
        $provider = new MinimalServiceProvider($this->container);

        // Should not throw - minimal implementation exists
        $provider->register();

        $this->assertTrue(method_exists($provider, 'register'));
    }

    /**
     * @group basic
     *
     * Tests marking provider as registered.
     * Used by the framework to track provider lifecycle.
     */
    public function testMarkAsRegistered(): void
    {
        $this->assertFalse($this->provider->isRegistered());

        $this->provider->markAsRegistered();

        $this->assertTrue($this->provider->isRegistered());
    }

    /**
     * @group basic
     *
     * Tests marking provider as booted.
     * Used by the framework to track provider lifecycle.
     */
    public function testMarkAsBooted(): void
    {
        $this->assertFalse($this->provider->isBooted());

        $this->provider->markAsBooted();

        $this->assertTrue($this->provider->isBooted());
    }

    // ========================================================================
    // Service Registration Tests
    // ========================================================================

    /**
     * @group registration
     *
     * Tests bind() method for service registration.
     * Services registered this way are resolved each time (factory pattern).
     */
    public function testBindService(): void
    {
        $this->provider->publicBind('test.service', function () {
            return new SPTestService('bound');
        });

        $this->assertTrue($this->container->has('test.service'));

        $service = $this->container->get('test.service');
        $this->assertInstanceOf(SPTestService::class, $service);
        $this->assertEquals('bound', $service->getValue());
    }

    /**
     * @group registration
     *
     * Tests bind() with null concrete parameter.
     * Should use abstract as concrete (class name string for auto-wiring).
     */
    public function testBindServiceWithNullConcrete(): void
    {
        // When concrete is null, the abstract is stored as-is (class name string)
        $this->provider->publicBind(SPTestService::class);

        $this->assertTrue($this->container->has(SPTestService::class));

        // PHP-DI will auto-wire when we request the service
        $service = $this->container->get(SPTestService::class);
        $this->assertInstanceOf(SPTestService::class, $service);
        $this->assertEquals('default', $service->getValue()); // Uses default constructor value
    }

    /**
     * @group registration
     *
     * Tests shared() method for singleton service registration.
     * In PHP-DI, services are shared by default, so this is an alias for bind().
     */
    public function testSharedService(): void
    {
        $this->provider->publicShared('test.shared', function () {
            static $counter = 0;
            return new SPTestService('shared-' . ++$counter);
        });

        $service1 = $this->container->get('test.shared');
        $service2 = $this->container->get('test.shared');

        $this->assertSame($service1, $service2);
        $this->assertEquals('shared-1', $service1->getValue());
    }

    /**
     * @group registration
     *
     * Tests alias() method for service aliasing.
     * Allows referencing the same service by different names.
     */
    public function testServiceAlias(): void
    {
        $this->container->set('original.service', new SPTestService('original'));
        $this->provider->publicAlias('alias.service', 'original.service');

        $this->assertTrue($this->container->has('alias.service'));

        $original = $this->container->get('original.service');
        $alias = $this->container->get('alias.service');

        $this->assertSame($original, $alias);
    }

    /**
     * @group registration
     *
     * Tests registerServices() for bulk service registration.
     * Convenient for registering multiple services at once.
     */
    public function testRegisterServices(): void
    {
        $services = [
            'service.one' => new SPTestService('one'),
            'service.two' => function () {
                return new SPTestService('two');
            },
            SPTestService::class => function () {
                return new SPTestService('class');
            }
        ];

        $this->provider->publicRegisterServices($services);

        $this->assertTrue($this->container->has('service.one'));
        $this->assertTrue($this->container->has('service.two'));
        $this->assertTrue($this->container->has(SPTestService::class));

        $this->assertEquals('one', $this->container->get('service.one')->getValue());
        $this->assertEquals('two', $this->container->get('service.two')->getValue());
        $this->assertEquals('class', $this->container->get(SPTestService::class)->getValue());
    }

    /**
     * @group registration
     *
     * Tests registerServices() with shared = false parameter.
     * Although PHP-DI shares by default, the API is maintained for compatibility.
     */
    public function testRegisterServicesNonShared(): void
    {
        $services = [
            'service.factory' => function () {
                return new SPTestService('factory');
            }
        ];

        $this->provider->publicRegisterServices($services, false);

        $this->assertTrue($this->container->has('service.factory'));

        // Even with shared = false, PHP-DI shares by default
        $instance1 = $this->container->get('service.factory');
        $instance2 = $this->container->get('service.factory');
        $this->assertSame($instance1, $instance2);
    }

    /**
     * @group registration
     *
     * Tests registerAliases() for bulk alias registration.
     * Convenient for creating multiple aliases at once.
     */
    public function testRegisterAliases(): void
    {
        // Set up original services
        $this->container->set('db.connection', new SPTestService('database'));
        $this->container->set('cache.store', new SPTestService('cache'));

        $aliases = [
            'database' => 'db.connection',
            'db' => 'db.connection',
            'cache' => 'cache.store'
        ];

        $this->provider->publicRegisterAliases($aliases);

        // Verify all aliases work
        $this->assertTrue($this->container->has('database'));
        $this->assertTrue($this->container->has('db'));
        $this->assertTrue($this->container->has('cache'));

        // Verify they reference the same instances
        $this->assertSame(
            $this->container->get('db.connection'),
            $this->container->get('database')
        );
        $this->assertSame(
            $this->container->get('db.connection'),
            $this->container->get('db')
        );
    }

    /**
     * @group registration
     *
     * Tests complex service registration with dependencies.
     * Shows how providers can register services that depend on each other.
     */
    public function testComplexServiceRegistration(): void
    {
        $provider = new ComplexServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->has('config.database'));
        $this->assertTrue($this->container->has('service.database'));
        $this->assertTrue($this->container->has('repository.user'));

        $repository = $this->container->get('repository.user');
        $this->assertInstanceOf(SPUserRepository::class, $repository);
        $this->assertEquals('localhost', $repository->getDatabase()->getHost());
    }

    // ========================================================================
    // Compilation Tests
    // ========================================================================

    /**
     * @group compilation
     * @dataProvider compilationModeProvider
     *
     * Tests that ServiceProviders work correctly with compiled containers.
     * Ensures that service registration works in both development and production modes.
     */
    public function testServiceProviderWithCompiledContainer(bool $enableCompilation): void
    {
        $container = new Container(['cache.path' => $this->tempCacheDir], $enableCompilation);
        $provider = new TestServiceProvider($container);

        // register() sollte in beiden Modi funktionieren
        $provider->register();

        // Services sollten verfügbar sein
        $this->assertTrue($container->has('test.service'));
        $this->assertInstanceOf(TestService::class, $container->get('test.service'));
    }

    /**
     * @group compilation
     * @dataProvider compilationModeProvider
     *
     * Tests that bind() method works with compiled containers when using
     * direct instantiation instead of factories.
     */
    public function testBindWithCompiledContainer(bool $enableCompilation): void
    {
        $container = new Container(['cache.path' => $this->tempCacheDir], $enableCompilation);
        $provider = new ConcreteServiceProvider($container);

        // bind() mit direkter Instanz sollte funktionieren
        $service = new SPTestService('bound');
        $provider->publicBind('test.service', $service);

        $this->assertSame($service, $container->get('test.service'));
    }

    /**
     * @group compilation
     * @group best-practices
     *
     * Tests and documents the required pattern for production-compatible service providers.
     * Shows the correct way to register services that works with container compilation.
     *
     * @see Container Caching and Service Registration - Technical Documentation.md
     */
    public function testProductionCompatibleServiceRegistration(): void
    {
        $container = new Container(['cache.path' => $this->tempCacheDir], true); // Production mode
        $provider = new ProductionCompatibleProvider($container);

        // Dies sollte funktionieren - direkte Instanziierung
        $provider->register();

        $this->assertTrue($container->has('logger'));
        $this->assertTrue($container->has('repository'));
        $this->assertInstanceOf(SPLogger::class, $container->get('logger'));
        $this->assertInstanceOf(SPRepository::class, $container->get('repository'));
    }

    // ========================================================================
    // WordPress Integration Tests
    // ========================================================================

    /**
     * @group wordpress
     *
     * Tests addAction() helper for WordPress action registration.
     * Should work with the WordPress stubs in our test environment.
     */
    public function testAddAction(): void
    {
        $called = false;
        $callback = function () use (&$called) {
            $called = true;
        };

        $this->provider->publicAddAction('init', $callback);

        // WordPress stub accepts the call without error
        $this->assertTrue(true);
    }

    /**
     * @group wordpress
     *
     * Tests addAction() with priority and accepted args.
     * WordPress allows customizing hook priority and argument count.
     */
    public function testAddActionWithPriorityAndArgs(): void
    {
        $callback = function ($arg1, $arg2) {
            return [$arg1, $arg2];
        };

        $this->provider->publicAddAction('custom_hook', $callback, 20, 2);

        // WordPress stub accepts the call without error
        $this->assertTrue(true);
    }

    /**
     * @group wordpress
     *
     * Tests addFilter() helper for WordPress filter registration.
     * Filters modify data as it passes through WordPress.
     */
    public function testAddFilter(): void
    {
        $callback = function ($content) {
            return $content . ' filtered';
        };

        $this->provider->publicAddFilter('the_content', $callback);

        // WordPress stub accepts the call without error
        $this->assertTrue(true);
    }

    /**
     * @group wordpress
     *
     * Tests addFilter() with priority and accepted args.
     * Like actions, filters can have custom priority and argument count.
     */
    public function testAddFilterWithPriorityAndArgs(): void
    {
        $callback = function ($value, $id, $context) {
            return "$value-$id-$context";
        };

        $this->provider->publicAddFilter('custom_filter', $callback, 15, 3);

        // WordPress stub accepts the call without error
        $this->assertTrue(true);
    }

    /**
     * @group wordpress
     *
     * Tests WordPress integration in a real provider scenario.
     * Shows how providers typically register hooks in the boot() method.
     */
    public function testWordPressIntegrationInProvider(): void
    {
        $provider = new WordPressServiceProvider($this->container);

        $provider->register();
        $provider->boot();

        // Verify services were registered
        $this->assertTrue($this->container->has('wp.service'));
        $this->assertTrue($this->container->has('wp.admin'));

        // Boot method registers WordPress hooks
        $this->assertTrue($provider->hooksRegistered());
    }

    // ========================================================================
    // Dependency Injection Tests
    // ========================================================================

    /**
     * @group dependency-injection
     *
     * Tests call() method for invoking functions with dependency injection.
     * PHP-DI resolves parameters based on type hints.
     */
    public function testCallWithDependencyInjection(): void
    {
        $this->container->set(SPTestService::class, new SPTestService('injected'));

        $result = $this->provider->publicCall(function (SPTestService $service, string $suffix) {
            return $service->getValue() . '-' . $suffix;
        }, ['suffix' => 'called']);

        $this->assertEquals('injected-called', $result);
    }

    /**
     * @group dependency-injection
     *
     * Tests call() with array callable (object method).
     * Common pattern for calling controller methods with DI.
     */
    public function testCallWithArrayCallable(): void
    {
        $controller = new SPTestController();

        $result = $this->provider->publicCall(
            [$controller, 'action'],
            ['param' => 'test']
        );

        $this->assertEquals('Action: test', $result);
    }

    /**
     * @group dependency-injection
     *
     * Tests make() method for creating instances with dependency injection.
     * Unlike get(), make() always creates new instances.
     */
    public function testMakeWithDependencyInjection(): void
    {
        $instance = $this->provider->publicMake(SPTestService::class, [
            'value' => 'created'
        ]);

        $this->assertInstanceOf(SPTestService::class, $instance);
        $this->assertEquals('created', $instance->getValue());
    }

    /**
     * @group dependency-injection
     *
     * Tests make() with complex dependencies.
     * Shows how PHP-DI resolves nested dependencies automatically.
     */
    public function testMakeWithComplexDependencies(): void
    {
        $this->container->set('config.db', ['host' => 'localhost']);

        $instance = $this->provider->publicMake(SPComplexService::class, [
            'name' => 'test-service'
        ]);

        $this->assertInstanceOf(SPComplexService::class, $instance);
        $this->assertEquals('test-service', $instance->getName());
        $this->assertIsArray($instance->getConfig());
    }

    // ========================================================================
    // Boot Process Tests
    // ========================================================================

    /**
     * @group boot
     *
     * Tests basic boot() method.
     * Default implementation does nothing, but can be overridden.
     */
    public function testBasicBoot(): void
    {
        $provider = new MinimalServiceProvider($this->container);

        // Should not throw
        $provider->boot();

        $this->assertTrue(true);
    }

    /**
     * @group boot
     *
     * Tests boot() in a provider that registers WordPress hooks.
     * Common pattern: register() defines services, boot() integrates with WordPress.
     */
    public function testBootWithWordPressIntegration(): void
    {
        $provider = new BootableServiceProvider($this->container);

        $this->assertFalse($provider->isWordPressIntegrated());

        $provider->register();
        $provider->boot();

        $this->assertTrue($provider->isWordPressIntegrated());
    }

    /**
     * @group boot
     *
     * Tests the provider lifecycle: construct -> register -> boot.
     * This is the order enforced by the framework.
     */
    public function testProviderLifecycle(): void
    {
        $provider = new LifecycleTrackingProvider($this->container);

        $this->assertEquals(['constructed'], $provider->getLifecycleEvents());

        $provider->register();
        $provider->markAsRegistered();
        $this->assertEquals(['constructed', 'registered'], $provider->getLifecycleEvents());

        $provider->boot();
        $provider->markAsBooted();
        $this->assertEquals(['constructed', 'registered', 'booted'], $provider->getLifecycleEvents());
    }

    // ========================================================================
    // Edge Cases and Error Handling
    // ========================================================================

    /**
     * @group edge-cases
     *
     * Tests provider with no services to register.
     * Valid use case for providers that only add WordPress hooks.
     */
    public function testProviderWithNoServices(): void
    {
        $provider = new HookOnlyServiceProvider($this->container);

        $provider->register(); // Does nothing
        $provider->boot();     // Only registers hooks

        // No services registered
        $this->assertFalse($this->container->has('any.service'));

        // But hooks were registered
        $this->assertTrue($provider->hooksRegistered());
    }

    /**
     * @group edge-cases
     *
     * Tests that providers can access container in register phase.
     * Sometimes needed for conditional registration based on existing services.
     */
    public function testAccessContainerDuringRegister(): void
    {
        // Pre-register a service
        $this->container->set('existing.service', new SPTestService('existing'));

        $provider = new ConditionalServiceProvider($this->container);
        $provider->register();

        // Provider should have registered additional service
        $this->assertTrue($this->container->has('conditional.service'));
        $this->assertEquals(
            'depends-on-existing',
            $this->container->get('conditional.service')->getValue()
        );
    }

    /**
     * @group edge-cases
     *
     * Tests that marking as registered/booted is idempotent.
     * Multiple calls should not cause issues.
     */
    public function testMarkingIdempotency(): void
    {
        $this->provider->markAsRegistered();
        $this->provider->markAsRegistered();
        $this->provider->markAsRegistered();

        $this->assertTrue($this->provider->isRegistered());

        $this->provider->markAsBooted();
        $this->provider->markAsBooted();
        $this->provider->markAsBooted();

        $this->assertTrue($this->provider->isBooted());
    }

    /**
     * @group edge-cases
     *
     * Tests provider inheritance chain.
     * Providers can extend other providers to share functionality.
     */
    public function testProviderInheritance(): void
    {
        $provider = new ExtendedServiceProvider($this->container);
        $provider->register();

        // Should have services from both base and extended
        $this->assertTrue($this->container->has('base.service'));
        $this->assertTrue($this->container->has('extended.service'));
    }
}

// ========================================================================
// Test Helper Classes
// ========================================================================

/**
 * Concrete service provider for testing protected methods
 */
class ConcreteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Empty implementation for testing
    }

    // Public wrappers for protected methods
    public function publicBind(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete);
    }

    public function publicShared(string $abstract, mixed $concrete = null): void
    {
        $this->shared($abstract, $concrete);
    }

    public function publicAlias(string $alias, string $abstract): void
    {
        $this->alias($alias, $abstract);
    }

    public function publicRegisterServices(array $services, bool $shared = true): void
    {
        $this->registerServices($services, $shared);
    }

    public function publicRegisterAliases(array $aliases): void
    {
        $this->registerAliases($aliases);
    }

    public function publicAddAction(string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $this->addAction($hook, $callback, $priority, $accepted_args);
    }

    public function publicAddFilter(string $hook, callable|array $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $this->addFilter($hook, $callback, $priority, $accepted_args);
    }

    public function publicCall(callable|array $callback, array $parameters = []): mixed
    {
        return $this->call($callback, $parameters);
    }

    public function publicMake(string $className, array $parameters = []): object
    {
        return $this->make($className, $parameters);
    }
}

/**
 * Minimal service provider implementation
 */
class MinimalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Minimal implementation
    }
}

/**
 * Complex service provider with dependencies
 */
class ComplexServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bind('config.database', [
            'host' => 'localhost',
            'port' => 3306
        ]);

        $this->shared('service.database', function () {
            return new SPDatabaseService($this->container->get('config.database'));
        });

        $this->shared('repository.user', function () {
            return new SPUserRepository($this->container->get('service.database'));
        });
    }
}

/**
 * WordPress integration service provider
 */
class WordPressServiceProvider extends ServiceProvider
{
    private bool $hooksRegistered = false;

    public function register(): void
    {
        $this->shared('wp.service', function () {
            return new SPTestService('wordpress');
        });

        $this->shared('wp.admin', function () {
            return new SPTestService('admin');
        });
    }

    public function boot(): void
    {
        $this->addAction('init', [$this, 'onInit']);
        $this->addFilter('the_content', [$this, 'filterContent']);
        $this->hooksRegistered = true;
    }

    public function onInit(): void
    {
        // WordPress init handler
    }

    public function filterContent(string $content): string
    {
        return $content;
    }

    public function hooksRegistered(): bool
    {
        return $this->hooksRegistered;
    }
}

/**
 * Bootable service provider
 */
class BootableServiceProvider extends ServiceProvider
{
    private bool $wordpressIntegrated = false;

    public function register(): void
    {
        // Use closure instead of class string
        $this->bind('bootable.service', function() {
            return new SPTestService('bootable');
        });
    }

    public function boot(): void
    {
        $this->addAction('init', function () {
            // Init logic
        });
        $this->wordpressIntegrated = true;
    }

    public function isWordPressIntegrated(): bool
    {
        return $this->wordpressIntegrated;
    }
}

/**
 * Lifecycle tracking provider
 */
class LifecycleTrackingProvider extends ServiceProvider
{
    private array $events = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->events[] = 'constructed';
    }

    public function register(): void
    {
        $this->events[] = 'registered';
    }

    public function boot(): void
    {
        $this->events[] = 'booted';
    }

    public function getLifecycleEvents(): array
    {
        return $this->events;
    }
}

/**
 * Hook-only service provider (no services)
 */
class HookOnlyServiceProvider extends ServiceProvider
{
    private bool $hooksRegistered = false;

    public function register(): void
    {
        // No services to register
    }

    public function boot(): void
    {
        $this->addAction('wp_head', [$this, 'addMeta']);
        $this->addFilter('body_class', [$this, 'addBodyClass']);
        $this->hooksRegistered = true;
    }

    public function addMeta(): void
    {
        echo '<meta name="test" content="value">';
    }

    public function addBodyClass(array $classes): array
    {
        $classes[] = 'custom-class';
        return $classes;
    }

    public function hooksRegistered(): bool
    {
        return $this->hooksRegistered;
    }
}

/**
 * Conditional service provider
 */
class ConditionalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->container->has('existing.service')) {
            $this->bind('conditional.service', function () {
                return new SPTestService('depends-on-existing');
            });
        }
    }
}

/**
 * Base service provider for inheritance testing
 */
class BaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('base.service', function () {
            return new SPTestService('base');
        });
    }
}

/**
 * Extended service provider
 */
class ExtendedServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        parent::register(); // Register base services

        $this->shared('extended.service', function () {
            return new SPTestService('extended');
        });
    }
}

/**
 * Test service provider for compilation tests
 */
class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Direct instantiation for production compatibility
        $service = new TestService('test');
        $this->container->set('test.service', $service);
    }
}

/**
 * Example of a production-compatible service provider
 */
class ProductionCompatibleProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ RICHTIG - Direkte Instanziierung
        $logLevel = $this->container->has('config.log_level')
            ? $this->container->get('config.log_level')
            : 'info';
        $logger = new SPLogger($logLevel);
        $this->container->set('logger', $logger);

        // ✅ RICHTIG - Service mit aufgelösten Dependencies
        $database = $this->container->has('database')
            ? $this->container->get('database')
            : new SPMockDatabase();

        $repository = new SPRepository($database, $logger);
        $this->container->set('repository', $repository);
    }
}

// ========================================================================
// Test Helper Classes for Services
// ========================================================================

/**
 * Simple test service
 */
class SPTestService
{
    private string $value;

    public function __construct(string $value = 'default')
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

/**
 * Test service for compilation tests
 */
class TestService
{
    private string $name;

    public function __construct(string $name = 'default')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

/**
 * Test controller for callable testing
 */
class SPTestController
{
    public function action(string $param): string
    {
        return 'Action: ' . $param;
    }
}

/**
 * Complex service with dependencies
 */
class SPComplexService
{
    private string $name;
    private array $config;

    public function __construct(string $name, array $config = [])
    {
        $this->name = $name;
        $this->config = $config;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}

/**
 * Database service for testing
 */
class SPDatabaseService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getHost(): string
    {
        return $this->config['host'] ?? 'localhost';
    }
}

/**
 * User repository for testing
 */
class SPUserRepository
{
    private SPDatabaseService $database;

    public function __construct(SPDatabaseService $database)
    {
        $this->database = $database;
    }

    public function getDatabase(): SPDatabaseService
    {
        return $this->database;
    }
}

/**
 * Test logger for production compatibility tests
 */
class SPLogger
{
    private string $level;

    public function __construct(string $level = 'info')
    {
        $this->level = $level;
    }

    public function log(string $message): void
    {
        // Simple test implementation
        error_log("[{$this->level}] {$message}");
    }

    public function getLevel(): string
    {
        return $this->level;
    }
}

/**
 * Test repository for production compatibility tests
 */
class SPRepository
{
    private object $database;
    private SPLogger $logger;

    public function __construct(object $database, SPLogger $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    public function getDatabase(): object
    {
        return $this->database;
    }

    public function getLogger(): SPLogger
    {
        return $this->logger;
    }
}

/**
 * Mock database for production compatibility tests
 */
class SPMockDatabase
{
    public function query(string $sql): array
    {
        // Mock implementation
        return [];
    }
}