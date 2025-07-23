<?php
/**
 * Container Unit Tests
 *
 * Tests for the PHP-DI Container wrapper.
 * Tests PSR-11 compliance, WordPress optimizations, and wrapper functionality.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Container
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\SitechipsBoilerplate\Tests\Unit\Core\Container;

use Furgo\Sitechips\Core\Container\Container;
use Furgo\Sitechips\Core\Container\ContainerException;
use Furgo\Sitechips\Core\Container\ContainerNotFoundException;
use Furgo\Sitechips\Core\Tests\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Container Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Container\Container
 */
class ContainerTest extends TestCase
{
    /**
     * Container instance for testing
     *
     * @var Container
     */
    private Container $container;

    /**
     * Temporary cache directory for tests
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

        // Create temporary cache directory for tests
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

    // ========================================================================
    // Basic Container Tests
    // ========================================================================

    /**
     * @group basic
     *
     * Tests that container can be created with initial service definitions.
     * These definitions should be immediately available via has() and get().
     */
    public function testContainerCreationWithDefinitions(): void
    {
        $definitions = [
            'test.value' => 'hello world',
            'test.number' => 42,
        ];

        $container = new Container($definitions, false);

        $this->assertTrue($container->has('test.value'));
        $this->assertTrue($container->has('test.number'));
        $this->assertEquals('hello world', $container->get('test.value'));
        $this->assertEquals(42, $container->get('test.number'));
    }

    /**
     * @group basic
     *
     * Tests that container creation with enableCompilation = null uses WP_DEBUG to determine mode
     */
    public function testContainerCreationWithNullCompilation(): void
    {
        // WP_DEBUG is true in test environment, so compilation should be disabled
        $container = new Container(['cache.path' => $this->tempCacheDir], null);

        // With WP_DEBUG = true, compilation should be disabled
        $this->assertFalse($container->isCompiled());
    }

    /**
     * @group basic
     *
     * Tests access to the internal PHP-DI container instance.
     * This is an escape hatch for accessing PHP-DI features not exposed by our wrapper.
     * Should be used sparingly in production code.
     */
    public function testInternalContainerAccess(): void
    {
        $internalContainer = $this->container->getInternalContainer();

        $this->assertInstanceOf(\DI\Container::class, $internalContainer);
    }

    // ========================================================================
    // Service Registration Tests
    // ========================================================================

    /**
     * @group registration
     *
     * Tests basic service registration using set().
     * Services registered this way should be retrievable via get() and return the same instance.
     */
    public function testServiceRegistration(): void
    {
        $service = new \stdClass();
        $service->message = 'test';

        $this->container->set('test.service', $service);

        $this->assertTrue($this->container->has('test.service'));
        $this->assertSame($service, $this->container->get('test.service'));
    }

    /**
     * @group registration
     *
     * Tests set() method with various types of values
     */
    public function testSetWithVariousTypes(): void
    {
        // Scalar values
        $this->container->set('string', 'test string');
        $this->container->set('int', 42);
        $this->container->set('float', 3.14);
        $this->container->set('bool', true);
        $this->container->set('null', null);

        // Arrays and objects
        $this->container->set('array', ['a', 'b', 'c']);
        $this->container->set('object', new \stdClass());

        // Closures
        $this->container->set('closure', function() { return 'closure result'; });

        // Class names for auto-wiring (stores as string, not instance)
        $this->container->set('class', SimpleTestClass::class);

        // Verify all can be retrieved
        $this->assertEquals('test string', $this->container->get('string'));
        $this->assertEquals(42, $this->container->get('int'));
        $this->assertEquals(3.14, $this->container->get('float'));
        $this->assertTrue($this->container->get('bool'));
        $this->assertNull($this->container->get('null'));
        $this->assertEquals(['a', 'b', 'c'], $this->container->get('array'));
        $this->assertInstanceOf(\stdClass::class, $this->container->get('object'));
        $this->assertEquals('closure result', $this->container->get('closure'));
        $this->assertEquals(SimpleTestClass::class, $this->container->get('class')); // String comparison, not instance
    }

    /**
     * @group registration
     *
     * Tests that set() throws exception when container operation fails
     */
    public function testSetThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Cannot set definition for 'invalid.key'");

        // Create a mock internal container that throws on set()
        $mockContainer = $this->createMock(\DI\Container::class);
        $mockContainer->method('set')
            ->willThrowException(new \Exception('Internal error'));

        // Use reflection to replace internal container
        $reflection = new \ReflectionClass($this->container);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($this->container, $mockContainer);

        $this->container->set('invalid.key', 'value');
    }

    /**
     * @group registration
     *
     * Tests shared() method which is an alias for set().
     * In PHP-DI all services are shared (singleton) by default.
     */
    public function testSharedServiceRegistration(): void
    {
        $this->container->shared('test.shared', function () {
            return new \stdClass();
        });

        $instance1 = $this->container->get('test.shared');
        $instance2 = $this->container->get('test.shared');

        $this->assertSame($instance1, $instance2);
    }

    /**
     * @group registration
     *
     * Tests service aliasing functionality.
     * Allows referencing the same service by different names.
     */
    public function testServiceAliasing(): void
    {
        $service = new \stdClass();
        $this->container->set('original.service', $service);
        $this->container->alias('alias.service', 'original.service');

        $this->assertTrue($this->container->has('alias.service'));
        $this->assertSame($service, $this->container->get('alias.service'));
    }

    /**
     * @group registration
     *
     * Tests closure-based service definitions.
     * The closure receives the container as parameter for dependency resolution.
     */
    public function testClosureBasedService(): void
    {
        $this->container->set('test.closure', function (ContainerInterface $container) {
            $obj = new \stdClass();
            $obj->called = true;
            $obj->container = $container;
            return $obj;
        });

        $service = $this->container->get('test.closure');

        $this->assertInstanceOf(\stdClass::class, $service);
        $this->assertTrue($service->called);
        $this->assertSame($this->container->getInternalContainer(), $service->container);
    }

    /**
     * @group registration
     *
     * Tests that runtime registration works regardless of compilation setting.
     * PHP-DI allows set() even on compiled containers (unlike some other DI containers).
     */
    public function testRuntimeRegistration(): void
    {
        foreach ([true, false] as $enableCompilation) {
            $container = new Container(['initial' => 'value'], $enableCompilation);

            $container->get('initial'); // Use container
            $container->set('dynamic', 'runtime value');

            $this->assertEquals('runtime value', $container->get('dynamic'));
        }
    }

    // ========================================================================
    // Service Resolution Tests
    // ========================================================================

    /**
     * @group resolution
     *
     * Tests that get() returns singleton instances.
     * Multiple calls to get() for the same service return the same instance.
     */
    public function testGetReturnsSingleton(): void
    {
        $this->container->set('factory.service', function () {
            static $counter = 0;
            $obj = new \stdClass();
            $obj->id = ++$counter;
            return $obj;
        });

        $instance1 = $this->container->get('factory.service');
        $instance2 = $this->container->get('factory.service');

        $this->assertSame($instance1, $instance2);
        $this->assertEquals(1, $instance1->id);
    }

    /**
     * @group resolution
     *
     * Tests make() method which always creates new instances.
     * Unlike get(), make() does not return singletons.
     */
    public function testMakeCreatesNewInstances(): void
    {
        $instance1 = $this->container->make(FactoryTestClass::class);
        $instance2 = $this->container->make(FactoryTestClass::class);

        $this->assertNotSame($instance1, $instance2);
        $this->assertInstanceOf(FactoryTestClass::class, $instance1);
        $this->assertInstanceOf(FactoryTestClass::class, $instance2);
    }

    /**
     * @group resolution
     *
     * Tests make() with constructor parameters.
     * Parameters passed to make() override auto-wired dependencies.
     */
    public function testMakeMethodWithParameters(): void
    {
        $instance = $this->container->make(ParameterizedTestClass::class, [
            'message' => 'custom message'
        ]);

        $this->assertInstanceOf(ParameterizedTestClass::class, $instance);
        $this->assertEquals('custom message', $instance->getMessage());
    }

    /**
     * @group resolution
     *
     * Tests make() with complex dependencies and parameters
     */
    public function testMakeWithComplexDependencies(): void
    {
        // Register a dependency
        $this->container->set(SimpleTestClass::class, new SimpleTestClass());

        // Make with partial parameters
        $instance = $this->container->make(ComplexParameterizedClass::class, [
            'customValue' => 'test123'
        ]);

        $this->assertInstanceOf(ComplexParameterizedClass::class, $instance);
        $this->assertInstanceOf(SimpleTestClass::class, $instance->getDependency());
        $this->assertEquals('test123', $instance->getCustomValue());
    }

    /**
     * @group resolution
     *
     * Tests make() throws proper exception for invalid class
     */
    public function testMakeThrowsNotFoundExceptionForInvalidClass(): void
    {
        $this->expectException(ContainerNotFoundException::class);
        $this->expectExceptionMessage("Class 'NonExistentClass' not found");

        $this->container->make('NonExistentClass');
    }

    /**
     * @group resolution
     *
     * Tests make() handles general exceptions properly
     */
    public function testMakeThrowsContainerExceptionForOtherErrors(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Error creating instance of");

        // Try to make a class with unresolvable dependencies
        $this->container->make(UnresolvableDependencyClass::class);
    }

    /**
     * @group resolution
     *
     * Tests call() method for dependency injection in callables.
     * PHP-DI analyzes the callable's parameters and injects dependencies automatically.
     * Useful for controller actions, event handlers, or any callable needing DI.
     */
    public function testCallMethod(): void
    {
        $this->container->set(SimpleTestClass::class, new SimpleTestClass());

        $result = $this->container->call(function (SimpleTestClass $service) {
            return $service->getValue() . ' called';
        });

        $this->assertEquals('simple called', $result);
    }

    /**
     * @group resolution
     *
     * Tests call() method with array callable
     */
    public function testCallMethodWithArrayCallable(): void
    {
        $object = new CallableTestClass();

        $result = $this->container->call([$object, 'testMethod'], [
            'param' => 'test value'
        ]);

        $this->assertEquals('Method called with: test value', $result);
    }

    /**
     * @group resolution
     *
     * Tests complex service resolution with nested dependencies.
     * Shows how to build a dependency graph: Config -> Database -> Repository.
     */
    public function testComplexDependencyResolution(): void
    {
        $this->container->set('config.database', [
            'host' => 'localhost',
            'port' => 3306
        ]);

        $this->container->set(DatabaseService::class, function ($container) {
            return new DatabaseService($container->get('config.database'));
        });

        $this->container->set(UserRepository::class, function ($container) {
            return new UserRepository($container->get(DatabaseService::class));
        });

        $userRepo = $this->container->get(UserRepository::class);

        $this->assertInstanceOf(UserRepository::class, $userRepo);
        $this->assertInstanceOf(DatabaseService::class, $userRepo->getDatabase());
    }

    // ========================================================================
    // Auto-wiring Tests
    // ========================================================================

    /**
     * @group autowiring
     *
     * Tests auto-wiring for classes without dependencies.
     * PHP-DI can instantiate simple classes without explicit registration.
     */
    public function testAutoWiringSimpleClass(): void
    {
        $instance = $this->container->get(SimpleTestClass::class);

        $this->assertInstanceOf(SimpleTestClass::class, $instance);
        $this->assertEquals('simple', $instance->getValue());
    }

    /**
     * @group autowiring
     *
     * Tests auto-wiring with dependencies.
     * PHP-DI uses reflection to detect constructor dependencies and inject them.
     * ComplexTestClass is not registered but PHP-DI creates it by injecting SimpleTestClass.
     */
    public function testAutoWiringWithDependencies(): void
    {
        $this->container->set(SimpleTestClass::class, new SimpleTestClass());
        $instance = $this->container->get(ComplexTestClass::class);

        $this->assertInstanceOf(ComplexTestClass::class, $instance);
        $this->assertInstanceOf(SimpleTestClass::class, $instance->getDependency());
    }

    // ========================================================================
    // Compilation Tests
    // ========================================================================

    /**
     * @group compilation
     *
     * Tests that compilation can be controlled via constructor parameter.
     * true = production mode with compilation, false = development mode.
     */
    public function testCompilationControl(): void
    {
        $withCompilation = new Container([
            'cache.path' => $this->tempCacheDir
        ], true);
        $withoutCompilation = new Container([], false);

        $this->assertTrue($withCompilation->isCompiled());
        $this->assertFalse($withoutCompilation->isCompiled());
    }

    /**
     * @group compilation
     *
     * Tests that compilation actually creates cache files.
     * When compilation is enabled, PHP-DI generates CompiledContainer.php for performance.
     */
    public function testCompilationCreatesCache(): void
    {
        $container = new Container([
            'test' => 'value',
            'cache.path' => $this->tempCacheDir
        ], true);

        $this->assertTrue($container->isCompiled());
        $this->assertFileExists($this->tempCacheDir . '/CompiledContainer.php');
    }

    /**
     * @group compilation
     *
     * Tests compilation without cache path
     */
    public function testCompilationWithoutCachePath(): void
    {
        // Enable compilation but don't provide cache.path
        $container = new Container([], true);

        // Should fall back to not compiled
        $this->assertFalse($container->isCompiled());
    }

    /**
     * @group compilation
     *
     * Tests compilation with non-writable cache directory
     */
    public function testCompilationWithNonWritableCache(): void
    {
        // Create non-writable directory
        $readOnlyDir = $this->tempCacheDir . '/readonly';
        mkdir($readOnlyDir, 0444, true);

        $container = new Container([
            'cache.path' => $readOnlyDir
        ], true);

        // Should fall back to not compiled
        $this->assertFalse($container->isCompiled());

        // Clean up
        chmod($readOnlyDir, 0777);
    }

    /**
     * @group compilation
     *
     * Tests that container creates cache directory if it doesn't exist
     */
    public function testContainerCreatesCacheDirectory(): void
    {
        $newCacheDir = $this->tempCacheDir . '/new-cache-dir';
        $this->assertDirectoryDoesNotExist($newCacheDir);

        $container = new Container([
            'cache.path' => $newCacheDir
        ], true);

        $this->assertDirectoryExists($newCacheDir);
        $this->assertDirectoryExists($newCacheDir . '/proxies');
    }

    /**
     * @group compilation
     *
     * Tests development configuration with APCu not available
     */
    public function testDevelopmentConfigurationWithoutAPCu(): void
    {
        // Most test environments don't have APCu enabled
        // This test ensures development mode works without it
        $container = new Container([], false);

        $this->assertFalse($container->isCompiled());

        // Container should work fine without APCu
        $container->set('test', 'value');
        $this->assertEquals('value', $container->get('test'));
    }

    /**
     * @group compilation
     * @requires function apcu_enabled
     *
     * Documentation test for compiled container behavior.
     * This test is skipped but documents expected behavior with compilation.
     */
    public function testCompiledContainerDocumentation(): void
    {
        $this->markTestSkipped(
            'Compiled container behavior: set() works even after compilation in PHP-DI. ' .
            'This differs from other DI containers that freeze after compilation.'
        );
    }

    /**
     * @group compilation
     * @dataProvider compilationModeProvider
     *
     * Tests that alias() works correctly in both development and production modes.
     * This test specifically addresses the issue where aliasing might fail with
     * compiled containers due to incorrect implementation.
     */
    public function testAliasWithCompiledContainer(bool $enableCompilation): void
    {
        $container = new Container(['cache.path' => $this->tempCacheDir], $enableCompilation);

        // Erst Service setzen
        $service = new \stdClass();
        $service->value = 'test';
        $container->set('original.service', $service);

        // Dann Alias erstellen
        $container->alias('alias.service', 'original.service');

        // Beide sollten dasselbe Objekt zurückgeben
        $this->assertSame($container->get('original.service'), $container->get('alias.service'));
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
    // Exception Tests
    // ========================================================================

    /**
     * @group exceptions
     *
     * Tests that accessing non-existent services throws proper exception.
     * Should throw our wrapped ContainerNotFoundException, not PHP-DI's exception.
     */
    public function testServiceNotFoundThrowsException(): void
    {
        $this->expectException(ContainerNotFoundException::class);
        $this->expectExceptionMessage("Service 'non.existent' not found in container");

        $this->container->get('non.existent');
    }

    /**
     * @group exceptions
     *
     * Tests that get() properly wraps general exceptions
     */
    public function testGetWrapsGeneralExceptions(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Error retrieving service 'broken.factory'");

        $this->container->set('broken.factory', function() {
            throw new \RuntimeException('Factory error');
        });

        $this->container->get('broken.factory');
    }

    /**
     * @group exceptions
     *
     * Tests that circular dependencies are caught and wrapped properly.
     *
     * Important: set() itself rarely throws exceptions - it only stores definitions.
     * Exceptions typically occur during resolution with get(), not during registration.
     * This test demonstrates that circular dependencies are detected when resolving,
     * not when defining the services.
     */
    public function testCircularDependencyThrowsException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Error retrieving service 'a': Circular dependency detected");

        // Set up circular dependency - no exception here
        $this->container->set('a', \DI\get('b'));
        $this->container->set('b', \DI\get('a'));

        // Exception happens when trying to resolve, not when setting
        $this->container->get('a');
    }

    // ========================================================================
    // Private Method Tests (via public API)
    // ========================================================================

    /**
     * @group internals
     *
     * Tests getCacheDirectory behavior through compilation
     */
    public function testGetCacheDirectoryBehavior(): void
    {
        // Test 1: No WP_CONTENT_DIR defined in some test environments
        // Container should handle this gracefully

        // Test 2: With cache.path but directory doesn't exist
        $nonExistentDir = $this->tempCacheDir . '/will-be-created';
        $container = new Container([
            'cache.path' => $nonExistentDir
        ], true);

        // Directory should be created
        $this->assertDirectoryExists($nonExistentDir);

        // Test 3: Without cache.path
        $containerNoCache = new Container([], true);
        $this->assertFalse($containerNoCache->isCompiled());
    }

    /**
     * @group internals
     *
     * Tests that wp_mkdir_p fallback works
     */
    public function testCacheDirectoryCreationFallback(): void
    {
        // Test directory creation without wp_mkdir_p
        $testDir = $this->tempCacheDir . '/deep/nested/structure';

        $container = new Container([
            'cache.path' => $testDir
        ], true);

        // Should create nested directories
        $this->assertDirectoryExists($testDir);
        $this->assertDirectoryExists($testDir . '/proxies');
    }
}

// ========================================================================
// Test Helper Classes
// ========================================================================

class SimpleTestClass
{
    public function getValue(): string
    {
        return 'simple';
    }
}

class ComplexTestClass
{
    private SimpleTestClass $dependency;

    public function __construct(SimpleTestClass $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getDependency(): SimpleTestClass
    {
        return $this->dependency;
    }
}

class ParameterizedTestClass
{
    private string $message;

    public function __construct(string $message = 'default')
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

class ComplexParameterizedClass
{
    private SimpleTestClass $dependency;
    private string $customValue;

    public function __construct(SimpleTestClass $dependency, string $customValue)
    {
        $this->dependency = $dependency;
        $this->customValue = $customValue;
    }

    public function getDependency(): SimpleTestClass
    {
        return $this->dependency;
    }

    public function getCustomValue(): string
    {
        return $this->customValue;
    }
}

class UnresolvableDependencyClass
{
    public function __construct(NonExistentDependency $dep)
    {
        // This will fail because NonExistentDependency doesn't exist
    }
}

class CallableTestClass
{
    public function testMethod(string $param): string
    {
        return "Method called with: " . $param;
    }
}

class DatabaseService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}

class UserRepository
{
    private DatabaseService $database;

    public function __construct(DatabaseService $database)
    {
        $this->database = $database;
    }

    public function getDatabase(): DatabaseService
    {
        return $this->database;
    }
}

class FactoryTestClass
{
    public function __construct()
    {
        // Simple test class for factory testing
    }
}