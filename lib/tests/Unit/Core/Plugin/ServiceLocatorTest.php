<?php
/**
 * Abstract Service Locator Unit Tests
 *
 * Comprehensive tests for the AbstractServiceLocator class.
 * Tests singleton pattern implementation, service location, plugin information access,
 * and delegation to the underlying Plugin instance.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Plugin
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Plugin;

use Furgo\Sitechips\Core\Plugin\AbstractServiceLocator;
use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;
use Furgo\Sitechips\Core\Container\ContainerException;
use Furgo\Sitechips\Core\Container\ContainerNotFoundException;
use Furgo\Sitechips\Core\Tests\TestCase;
use LogicException;

/**
 * Abstract Service Locator Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Plugin\AbstractServiceLocator
 */
class AbstractServiceLocatorTest extends TestCase
{
    /**
     * Set up each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Reset any existing instances before each test
        TestServiceLocator::reset();
        FailingServiceLocator::reset();
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        // Ensure clean state after each test
        TestServiceLocator::reset();
        FailingServiceLocator::reset();
    }

    // ========================================================================
    // Singleton Pattern Tests
    // ========================================================================

    /**
     * @group singleton
     *
     * Tests that instance() returns the same instance on multiple calls.
     * This is the core of the singleton pattern implementation.
     */
    public function testSingletonInstance(): void
    {
        $instance1 = TestServiceLocator::instance();
        $instance2 = TestServiceLocator::instance();

        $this->assertInstanceOf(Plugin::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    /**
     * @group singleton
     *
     * Tests that reset() clears the singleton instance.
     * After reset, a new instance should be created.
     */
    public function testResetClearsSingletonInstance(): void
    {
        $instance1 = TestServiceLocator::instance();
        TestServiceLocator::reset();
        $instance2 = TestServiceLocator::instance();

        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * @group singleton
     *
     * Tests that constructor is private and cannot be called directly.
     * Singleton pattern requires preventing direct instantiation.
     */
    public function testCannotInstantiateDirectly(): void
    {
        $reflection = new \ReflectionClass(TestServiceLocator::class);
        $constructor = $reflection->getConstructor();

        $this->assertTrue($constructor->isPrivate());
    }

    /**
     * @group singleton
     *
     * Tests that clone is private and throws exception.
     * Singleton pattern must prevent cloning.
     */
    public function testCannotClone(): void
    {
        $reflection = new \ReflectionClass(TestServiceLocator::class);
        $cloneMethod = $reflection->getMethod('__clone');

        $this->assertTrue($cloneMethod->isPrivate());
    }

    /**
     * @group singleton
     *
     * Tests that __wakeup throws exception to prevent unserialization.
     * Singleton pattern must prevent unserialization.
     */
    public function testCannotUnserialize(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot unserialize singleton');

        // Create a serialized representation of a service locator
        // We need to bypass the private constructor for this test
        $reflection = new \ReflectionClass(TestServiceLocator::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $serialized = serialize($instance);

        // Attempting to unserialize should trigger __wakeup and throw exception
        unserialize($serialized);
    }

    // ========================================================================
    // Service Location Tests
    // ========================================================================

    /**
     * @group service-location
     *
     * Tests get() method for service retrieval.
     * Should delegate to the plugin instance's get() method.
     */
    public function testGetService(): void
    {
        $service = TestServiceLocator::get('test.service');

        $this->assertEquals('test-value', $service);
    }

    /**
     * @group service-location
     *
     * Tests get() with non-existent service.
     * Should throw ContainerNotFoundException.
     */
    public function testGetNonExistentService(): void
    {
        $this->expectException(ContainerNotFoundException::class);
        $this->expectExceptionMessage("Service 'non.existent' not found");

        TestServiceLocator::get('non.existent');
    }

    /**
     * @group service-location
     *
     * Tests has() method for service existence check.
     * Should return true for existing services, false for non-existent.
     */
    public function testHasService(): void
    {
        $this->assertTrue(TestServiceLocator::has('test.service'));
        $this->assertTrue(TestServiceLocator::has('plugin.name'));
        $this->assertFalse(TestServiceLocator::has('non.existent'));
    }

    /**
     * @group service-location
     *
     * Tests that get() and has() use the same plugin instance.
     * Important for consistency in service location.
     */
    public function testServiceLocationConsistency(): void
    {
        // Pre-check service exists
        $this->assertTrue(TestServiceLocator::has('test.service'));

        // Get service
        $service = TestServiceLocator::get('test.service');
        $this->assertEquals('test-value', $service);

        // Should still exist after get()
        $this->assertTrue(TestServiceLocator::has('test.service'));
    }

    // ========================================================================
    // Plugin Information Tests
    // ========================================================================

    /**
     * @group plugin-info
     *
     * Tests version() method returns plugin version.
     * Should delegate to plugin's version configuration.
     */
    public function testGetVersion(): void
    {
        $version = TestServiceLocator::version();

        $this->assertEquals('1.0.0', $version);
    }

    /**
     * @group plugin-info
     *
     * Tests path() method returns plugin directory path.
     * Should include trailing slash as per WordPress convention.
     */
    public function testGetPath(): void
    {
        $path = TestServiceLocator::path();

        $this->assertStringEndsWith('/', $path);
        $this->assertStringContainsString('/tmp/', $path);
    }

    /**
     * @group plugin-info
     *
     * Tests url() method returns plugin URL.
     * Should include trailing slash as per WordPress convention.
     */
    public function testGetUrl(): void
    {
        $url = TestServiceLocator::url();

        $this->assertStringStartsWith('http', $url);
        $this->assertStringEndsWith('/', $url);
    }

    /**
     * @group plugin-info
     *
     * Tests basename() method returns plugin basename.
     * Format should be 'directory/file.php'.
     */
    public function testGetBasename(): void
    {
        $basename = TestServiceLocator::basename();

        $this->assertStringContainsString('/', $basename);
        $this->assertStringEndsWith('.php', $basename);
    }

    /**
     * @group plugin-info
     *
     * Tests name() method returns plugin display name.
     * Should return human-readable plugin name.
     */
    public function testGetName(): void
    {
        $name = TestServiceLocator::name();

        $this->assertEquals('Test Plugin', $name);
    }

    /**
     * @group plugin-info
     *
     * Tests textDomain() method returns plugin text domain.
     * Used for internationalization in WordPress.
     */
    public function testGetTextDomain(): void
    {
        $textDomain = TestServiceLocator::textDomain();

        $this->assertEquals('test-plugin', $textDomain);
    }

    /**
     * @group plugin-info
     *
     * Tests environment() method returns current environment.
     * Should return 'testing' in test environment.
     */
    public function testGetEnvironment(): void
    {
        $environment = TestServiceLocator::environment();

        $this->assertEquals('testing', $environment);
    }

    /**
     * @group plugin-info
     *
     * Tests isDebug() method returns debug status.
     * Should return true in test environment.
     */
    public function testIsDebug(): void
    {
        $isDebug = TestServiceLocator::isDebug();

        $this->assertTrue($isDebug);
    }

    // ========================================================================
    // Plugin Lifecycle Tests
    // ========================================================================

    /**
     * @group lifecycle
     *
     * Tests isBooted() before and after boot.
     * Plugin should not be booted initially.
     */
    public function testIsBooted(): void
    {
        $this->assertFalse(TestServiceLocator::isBooted());

        TestServiceLocator::boot();

        $this->assertTrue(TestServiceLocator::isBooted());
    }

    /**
     * @group lifecycle
     *
     * Tests boot() method boots the plugin.
     * Should delegate to plugin's boot method.
     */
    public function testBoot(): void
    {
        $this->assertFalse(TestServiceLocator::isBooted());

        TestServiceLocator::boot();

        $this->assertTrue(TestServiceLocator::isBooted());

        // Boot again should be idempotent
        TestServiceLocator::boot();
        $this->assertTrue(TestServiceLocator::isBooted());
    }

    /**
     * @group lifecycle
     *
     * Tests that plugin is created lazily on first access.
     * Instance should not exist until first method call.
     */
    public function testLazyPluginCreation(): void
    {
        // Reset to ensure clean state
        TestServiceLocator::reset();

        // We can't directly check if instance is null without accessing it
        // (which would create it). Instead, we verify the behavior:

        // Create a new test locator that counts setup calls
        CountingServiceLocator::reset();
        CountingServiceLocator::$setupCallCount = 0;

        // No setup should have been called yet
        $this->assertEquals(0, CountingServiceLocator::$setupCallCount);

        // First access should trigger setup
        CountingServiceLocator::has('test.service');
        $this->assertEquals(1, CountingServiceLocator::$setupCallCount);

        // Subsequent accesses should not trigger setup again
        CountingServiceLocator::get('test.service');
        CountingServiceLocator::version();
        $this->assertEquals(1, CountingServiceLocator::$setupCallCount);
    }

    // ========================================================================
    // Dependency Injection Tests
    // ========================================================================

    /**
     * @group dependency-injection
     *
     * Tests call() method delegates to plugin.
     * Should support dependency injection in callables.
     */
    public function testCallWithDependencyInjection(): void
    {
        $result = TestServiceLocator::call(function (string $value) {
            return strtoupper($value);
        }, ['value' => 'test']);

        $this->assertEquals('TEST', $result);
    }

    /**
     * @group dependency-injection
     *
     * Tests call() with array callable.
     * Should support object methods as callables.
     */
    public function testCallWithArrayCallable(): void
    {
        $object = new CallableTestHelper();

        $result = TestServiceLocator::call([$object, 'process'], [
            'input' => 'test-data'
        ]);

        $this->assertEquals('Processed: test-data', $result);
    }

    /**
     * @group dependency-injection
     *
     * Tests make() method creates instances.
     * Should support dependency injection in constructors.
     */
    public function testMakeWithDependencyInjection(): void
    {
        $instance = TestServiceLocator::make(ServiceWithConstructor::class, [
            'value' => 'injected'
        ]);

        $this->assertInstanceOf(ServiceWithConstructor::class, $instance);
        $this->assertEquals('injected', $instance->getValue());
    }

    /**
     * @group dependency-injection
     *
     * Tests make() with invalid class.
     * Should throw ContainerNotFoundException.
     */
    public function testMakeWithInvalidClass(): void
    {
        $this->expectException(ContainerNotFoundException::class);
        $this->expectExceptionMessage("Class 'NonExistentClass' not found");

        TestServiceLocator::make('NonExistentClass');
    }

    // ========================================================================
    // Error Handling Tests
    // ========================================================================

    /**
     * @group error-handling
     *
     * Tests behavior when setupPlugin() throws exception.
     * All methods should propagate the exception.
     */
    public function testSetupPluginFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plugin setup failed');

        FailingServiceLocator::instance();
    }

    /**
     * @group error-handling
     *
     * Tests that failed setup doesn't cache broken instance.
     * Each attempt should try setup again.
     */
    public function testFailedSetupDoesNotCacheInstance(): void
    {
        try {
            FailingServiceLocator::instance();
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Second attempt should also fail (not cached)
        $this->expectException(\RuntimeException::class);
        FailingServiceLocator::instance();
    }

    /**
     * @group error-handling
     *
     * Tests get() when plugin has broken service.
     * Should propagate container exceptions.
     */
    public function testGetWithBrokenService(): void
    {
        TestServiceLocator::instance(); // Ensure instance exists

        // Add a broken service
        $plugin = TestServiceLocator::instance();
        $plugin->getContainer()->set('broken.service', function() {
            throw new \RuntimeException('Service creation failed');
        });

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Service creation failed');

        TestServiceLocator::get('broken.service');
    }

    // ========================================================================
    // Integration Tests
    // ========================================================================

    /**
     * @group integration
     *
     * Tests complete workflow: setup -> service location -> boot.
     * Verifies all components work together correctly.
     */
    public function testCompleteWorkflow(): void
    {
        // Service location
        $this->assertTrue(TestServiceLocator::has('test.service'));
        $service = TestServiceLocator::get('test.service');
        $this->assertEquals('test-value', $service);

        // Plugin info
        $this->assertEquals('Test Plugin', TestServiceLocator::name());
        $this->assertEquals('1.0.0', TestServiceLocator::version());

        // Boot
        $this->assertFalse(TestServiceLocator::isBooted());
        TestServiceLocator::boot();
        $this->assertTrue(TestServiceLocator::isBooted());

        // DI helpers
        $result = TestServiceLocator::call(fn($x) => $x * 2, ['x' => 5]);
        $this->assertEquals(10, $result);
    }

    /**
     * @group integration
     *
     * Tests multiple service locator implementations.
     * Each should maintain its own singleton instance.
     */
    public function testMultipleServiceLocators(): void
    {
        $test1 = TestServiceLocator::instance();
        $test2 = AnotherServiceLocator::instance();

        $this->assertNotSame($test1, $test2);

        // Each has its own services
        $this->assertEquals('test-value', TestServiceLocator::get('test.service'));
        $this->assertEquals('another-value', AnotherServiceLocator::get('another.service'));
    }

    /**
     * @group integration
     *
     * Tests that static calls maintain proper context.
     * Each service locator class should maintain its own state.
     */
    public function testStaticContextIsolation(): void
    {
        // Boot one locator
        TestServiceLocator::boot();
        $this->assertTrue(TestServiceLocator::isBooted());

        // Other locator should not be booted
        $this->assertFalse(AnotherServiceLocator::isBooted());

        // Boot the other
        AnotherServiceLocator::boot();
        $this->assertTrue(AnotherServiceLocator::isBooted());
    }
}

// ========================================================================
// Test Helper Classes
// ========================================================================

/**
 * Concrete service locator for testing
 */
class TestServiceLocator extends AbstractServiceLocator
{
    protected static function setupPlugin(): Plugin
    {
        return PluginFactory::createForTesting('/tmp/test-plugin.php', [
            'test.service' => 'test-value',
            'plugin.name' => 'Test Plugin',
            'plugin.version' => '1.0.0',
            'plugin.text_domain' => 'test-plugin'
        ]);
    }
}

/**
 * Another service locator for isolation testing
 */
class AnotherServiceLocator extends AbstractServiceLocator
{
    protected static function setupPlugin(): Plugin
    {
        return PluginFactory::createForTesting('/tmp/another-plugin.php', [
            'another.service' => 'another-value',
            'plugin.name' => 'Another Plugin'
        ]);
    }
}

/**
 * Service locator that fails during setup
 */
class FailingServiceLocator extends AbstractServiceLocator
{
    protected static function setupPlugin(): Plugin
    {
        throw new \RuntimeException('Plugin setup failed');
    }
}

/**
 * Helper class for callable testing
 */
class CallableTestHelper
{
    public function process(string $input): string
    {
        return 'Processed: ' . $input;
    }
}

/**
 * Service with constructor for make() testing
 */
class ServiceWithConstructor
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
 * Service locator that counts setup calls for testing
 */
class CountingServiceLocator extends AbstractServiceLocator
{
    public static int $setupCallCount = 0;

    protected static function setupPlugin(): Plugin
    {
        self::$setupCallCount++;
        return PluginFactory::createForTesting('/tmp/counting-plugin.php', [
            'test.service' => 'counted'
        ]);
    }
}