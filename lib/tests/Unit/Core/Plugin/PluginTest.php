<?php
/**
 * Plugin Unit Tests
 *
 * Comprehensive tests for the Plugin class.
 * Tests plugin lifecycle, container integration, event dispatching,
 * logging capabilities, and service provider management.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Plugin
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Plugin;

use Furgo\Sitechips\Core\Contracts\Bootable;
use Furgo\Sitechips\Core\Container\Container;
use Furgo\Sitechips\Core\Container\ContainerException;
use Furgo\Sitechips\Core\Container\ContainerNotFoundException;
use Furgo\Sitechips\Core\Container\ServiceProvider;
use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;
use Furgo\Sitechips\Core\Tests\TestCase;

/**
 * Plugin Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Plugin\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * Container instance
     *
     * @var Container
     */
    private Container $container;

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Test plugin file path
     *
     * @var string
     */
    private string $pluginFile;

    /**
     * Set up each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = PluginFactory::createForTesting('/tmp/test-plugin.php', [
            'plugin.slug' => 'test-plugin',
            'plugin.url' => 'http://example.com/wp-content/plugins/test/',
            'plugin.basename' => 'test-plugin/test-plugin.php'
        ]);

        $this->container = $this->plugin->getContainer();
        $this->pluginFile = $this->plugin->getPluginFile();
    }

    // ========================================================================
    // Basic Plugin Tests
    // ========================================================================

    /**
     * @group basic
     *
     * Tests that setUp properly initializes the test environment.
     * This is a sanity check to ensure our test fixtures are working.
     */
    public function testSetUpRuns(): void
    {
        $this->assertInstanceOf(Plugin::class, $this->plugin);
        $this->assertInstanceOf(Container::class, $this->container);
        $this->assertEquals('/tmp/test-plugin.php', $this->pluginFile);
    }

    /**
     * @group basic
     *
     * Tests that Plugin implements the Bootable interface.
     * This interface ensures the plugin can be properly bootstrapped.
     */
    public function testImplementsBootableInterface(): void
    {
        $this->assertInstanceOf(Bootable::class, $this->plugin);
    }

    /**
     * @group basic
     *
     * Tests plugin construction and initial state.
     * After construction, the plugin should not be booted and should
     * contain references to the container and plugin file.
     */
    public function testConstruction(): void
    {
        $this->assertSame($this->container, $this->plugin->getContainer());
        $this->assertEquals($this->pluginFile, $this->plugin->getPluginFile());
        $this->assertFalse($this->plugin->isBooted());
    }

    /**
     * @group basic
     *
     * Tests that plugin metadata is properly set by the factory.
     * These values are extracted from the plugin file and environment.
     */
    public function testPluginMetadata(): void
    {
        $this->assertEquals($this->pluginFile, $this->plugin->getPluginFile());
        $this->assertEquals('/tmp/', $this->plugin->get('plugin.path'));
        $this->assertEquals('http://example.com/wp-content/plugins/test/', $this->plugin->get('plugin.url'));
        $this->assertEquals('test-plugin/test-plugin.php', $this->plugin->get('plugin.basename'));
        $this->assertEquals('Test Plugin', $this->plugin->get('plugin.name'));
        $this->assertEquals('1.0.0', $this->plugin->get('plugin.version'));
        $this->assertEquals('test-plugin', $this->plugin->get('plugin.text_domain'));
    }

    /**
     * @group basic
     *
     * Tests environment detection and debug mode.
     * In testing environment, debug should be true by default.
     */
    public function testEnvironmentDetection(): void
    {
        $this->assertEquals('testing', $this->plugin->get('environment'));
        $this->assertTrue($this->plugin->get('debug')); // createForTesting sets debug=true

        // Test debug mode can be changed
        $this->container->set('debug', false);
        $this->assertFalse($this->plugin->get('debug'));
    }

    // ========================================================================
    // Container Integration Tests
    // ========================================================================

    /**
     * @group container
     *
     * Tests container access methods get() and has().
     * The plugin should delegate these calls to the underlying container.
     */
    public function testContainerAccess(): void
    {
        $testService = new \stdClass();
        $testService->value = 'test';

        $this->container->set('test.service', $testService);

        $this->assertSame($this->container, $this->plugin->getContainer());
        $this->assertSame($testService, $this->plugin->get('test.service'));
        $this->assertTrue($this->plugin->has('test.service'));
        $this->assertFalse($this->plugin->has('non.existent'));
    }

    /**
     * @group container
     *
     * Tests that get() throws proper exception for missing services.
     * Should wrap the container's exception with additional context.
     */
    public function testGetThrowsExceptionForMissingService(): void
    {
        $this->expectException(ContainerNotFoundException::class);
        $this->expectExceptionMessage("Service 'missing.service' not found in plugin container");

        $this->plugin->get('missing.service');
    }

    /**
     * @group container
     *
     * Tests that get() handles general container exceptions properly.
     * Should wrap any container exception with helpful context.
     */
    public function testGetWithContainerException(): void
    {
        $this->container->set('broken.service', \DI\factory(function() {
            throw new \RuntimeException('Factory failed');
        }));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Factory failed');

        $this->plugin->get('broken.service');
    }

    /**
     * @group container
     *
     * Tests the call() method for dependency injection in callables.
     * This allows invoking functions/methods with automatic dependency resolution.
     */
    public function testContainerCall(): void
    {
        $result = $this->plugin->call(function ($value) {
            return $value * 3;
        }, ['value' => 5]);

        $this->assertEquals(15, $result);
    }

    /**
     * @group container
     *
     * Tests the call() method with direct parameters.
     * Parameters passed to call() are used for the callable.
     */
    public function testContainerCallWithParameters(): void
    {
        $result = $this->plugin->call(function ($value) {
            return $value * 3;
        }, ['value' => 5]);

        $this->assertEquals(15, $result);
    }

    /**
     * @group container
     *
     * Tests the call() method with dependency injection.
     * PHP-DI injects services based on type hints automatically.
     */
    public function testContainerCallWithDependencyInjection(): void
    {
        $this->container->set(TestService::class, new TestService('injected'));

        $result = $this->plugin->call(function (TestService $service, $suffix) {
            return $service->getValue() . $suffix;
        }, ['suffix' => '-called']);

        $this->assertEquals('injected-called', $result);
    }

    /**
     * @group container
     *
     * Tests that call() handles exceptions properly.
     * Should wrap exceptions thrown during callable execution.
     */
    public function testCallWithException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Failed to call method with dependency injection');

        $this->plugin->call(function() {
            throw new \RuntimeException('Callable failed');
        });
    }

    /**
     * @group container
     *
     * Tests that call() handles missing parameter exceptions.
     * Should provide helpful error message when parameters are missing.
     */
    public function testCallWithMissingParameter(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Failed to call method with dependency injection');

        // Function requires $required parameter but it's not provided
        $this->plugin->call(function($required) {
            return $required;
        });
    }

    /**
     * @group container
     *
     * Tests the make() method for creating instances with dependency injection.
     * Unlike get(), make() always creates new instances.
     */
    public function testContainerMake(): void
    {
        $instance = $this->plugin->make(TestService::class, ['value' => 'test']);

        $this->assertInstanceOf(TestService::class, $instance);
        $this->assertEquals('test', $instance->getValue());
    }

    /**
     * @group container
     *
     * Tests that make() throws proper exception for invalid classes.
     * Should provide helpful error message when class cannot be instantiated.
     */
    public function testMakeThrowsExceptionForInvalidClass(): void
    {
        $this->expectException(ContainerNotFoundException::class);
        $this->expectExceptionMessage("Class 'NonExistentClass' not found");

        $this->plugin->make('NonExistentClass');
    }

    /**
     * @group container
     *
     * Tests that make() handles general container exceptions properly.
     * Should wrap exceptions that occur during instantiation.
     */
    public function testMakeWithContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Error creating instance of");

        $this->plugin->make(BrokenConstructorService::class);
    }

    /**
     * @group container
     *
     * Tests plugin creation with minimal container configuration.
     * The plugin should handle missing optional services gracefully.
     */
    public function testPluginWithMinimalContainer(): void
    {
        $minimalContainer = new Container(['plugin.slug' => 'minimal'], false);
        $plugin = new Plugin($minimalContainer, '/tmp/minimal.php');

        // Required service works
        $this->assertEquals('minimal', $plugin->get('plugin.slug'));

        // Optional service throws exception
        $this->expectException(ContainerNotFoundException::class);
        $plugin->get('plugin.name');
    }

    // ========================================================================
    // Boot Process Tests
    // ========================================================================

    /**
     * @group boot
     *
     * Tests basic boot process without service providers.
     * After booting, the plugin should be marked as booted.
     */
    public function testBasicBoot(): void
    {
        $this->assertFalse($this->plugin->isBooted());

        $this->plugin->boot();

        $this->assertTrue($this->plugin->isBooted());
    }

    /**
     * @group boot
     *
     * Tests that boot() is idempotent.
     * Calling boot() multiple times should have no additional effect.
     */
    public function testBootIsIdempotent(): void
    {
        $this->plugin->boot();
        $this->assertTrue($this->plugin->isBooted());

        // Boot again - should not throw or change state
        $this->plugin->boot();
        $this->assertTrue($this->plugin->isBooted());
    }

    /**
     * @group boot
     *
     * Tests boot process with service providers.
     * Providers should be booted in order and marked as booted.
     */
    public function testBootWithServiceProviders(): void
    {
        $provider1 = new MockServiceProvider($this->container);
        $provider2 = new MockServiceProvider($this->container);

        $this->container->set('providers', [$provider1, $provider2]);

        $this->assertFalse($provider1->isBooted());
        $this->assertFalse($provider2->isBooted());

        $this->plugin->boot();

        $this->assertTrue($provider1->isBooted());
        $this->assertTrue($provider2->isBooted());
        $this->assertEquals(1, $provider1->bootCount);
        $this->assertEquals(1, $provider2->bootCount);
    }

    /**
     * @group boot
     *
     * Tests that providers are only booted once even with multiple boot() calls.
     * This ensures idempotency at the provider level.
     */
    public function testProvidersBootedOnlyOnce(): void
    {
        $provider = new MockServiceProvider($this->container);
        $this->container->set('providers', [$provider]);

        $this->plugin->boot();
        $this->plugin->boot();
        $this->plugin->boot();

        $this->assertEquals(1, $provider->bootCount);
    }

    /**
     * @group boot
     *
     * Tests that boot fires the 'booted' event.
     * Other components can listen to this event to run after boot completion.
     */
    public function testBootFiresEvent(): void
    {
        $eventFired = false;

        add_action('test-plugin.booted', function($plugin) use (&$eventFired) {
            $eventFired = true;
            $this->assertSame($this->plugin, $plugin);
        });

        $this->plugin->boot();

        $this->assertTrue($eventFired);
    }

    /**
     * @group boot
     *
     * Tests boot with failing service provider.
     * Should throw exception with helpful error message.
     */
    public function testBootWithFailingProvider(): void
    {
        $failingProvider = new FailingServiceProvider($this->container);
        $this->container->set('providers', [$failingProvider]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Failed to boot plugin: Provider boot failed');

        $this->plugin->boot();
    }

    /**
     * @group boot
     *
     * Tests boot with providers that throw during get().
     * Should handle container exceptions during provider retrieval.
     */
    public function testBootWithBrokenProvidersService(): void
    {
        $this->container->set('providers', \DI\factory(function() {
            throw new \RuntimeException('Cannot get providers');
        }));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot get providers');

        $this->plugin->boot();
    }

    // ========================================================================
    // Event Dispatching Tests
    // ========================================================================

    /**
     * @group events
     *
     * Tests basic event dispatching with plugin-specific prefix.
     * Events should be prefixed with the plugin slug.
     */
    public function testEventDispatching(): void
    {
        $eventFired = false;
        $receivedData = null;

        add_action('test-plugin.test-event', function($plugin, $data) use (&$eventFired, &$receivedData) {
            $eventFired = true;
            $receivedData = $data;
        }, 10, 2);

        $this->plugin->dispatch('test-event', 'test-data');

        $this->assertTrue($eventFired);
        $this->assertEquals('test-data', $receivedData);
    }

    /**
     * @group events
     *
     * Tests event dispatching with multiple arguments.
     * All arguments after event name should be passed to listeners.
     */
    public function testEventDispatchingWithMultipleArgs(): void
    {
        $receivedArgs = [];

        add_action('test-plugin.multi-args', function($plugin, $arg1, $arg2, $arg3) use (&$receivedArgs) {
            $receivedArgs = [$arg1, $arg2, $arg3];
        }, 10, 4);

        $this->plugin->dispatch('multi-args', 'first', 'second', ['third']);

        $this->assertEquals(['first', 'second', ['third']], $receivedArgs);
    }

    /**
     * @group events
     *
     * Tests global event dispatching when configured.
     * Global events allow cross-plugin communication.
     */
    public function testGlobalEventDispatching(): void
    {
        $this->container->set('events.global_prefix', 'mycompany');

        $pluginEventFired = false;
        $globalEventFired = false;

        add_action('test-plugin.shared-event', function() use (&$pluginEventFired) {
            $pluginEventFired = true;
        });

        add_action('mycompany.shared-event', function() use (&$globalEventFired) {
            $globalEventFired = true;
        });

        $this->plugin->dispatch('shared-event');

        $this->assertTrue($pluginEventFired);
        $this->assertTrue($globalEventFired);
    }

    /**
     * @group events
     *
     * Tests that event dispatching handles errors gracefully.
     * If dispatching fails, it should log error but not throw exception.
     */
    public function testEventDispatchingErrorHandling(): void
    {
        // Remove plugin.slug to cause error in dispatch
        $minimalContainer = new Container(['debug' => true], false);
        $plugin = new Plugin($minimalContainer, '/tmp/test.php');

        // Should not throw exception
        $plugin->dispatch('test-event');

        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    public function testEventDispatchingWithBrokenSlug(): void
    {
        $this->container->set('plugin.slug', \DI\factory(function() {
            throw new \RuntimeException('Cannot get slug');
        }));

        $logger = new MockLogger();
        $this->container->set('logger', $logger);

        $this->plugin->dispatch('test-event');

        // Prüfe nur ob ein Error geloggt wurde
        $logs = $logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
    }

    // ========================================================================
    // Logging Tests
    // ========================================================================

    /**
     * @group logging
     *
     * Tests error logging with a logger service.
     * When a logger is registered, it should be used instead of error_log.
     */
    public function testErrorLoggingWithLogger(): void
    {
        $logger = new MockLogger();
        $this->container->set('logger', $logger);

        $this->plugin->logError('Test error message');

        $this->assertTrue($logger->hasLog('error', 'Test error message'));
        $this->assertCount(1, $logger->getLogs());
    }

    /**
     * @group logging
     *
     * Tests info logging with a logger service.
     * Info level logs should use the registered logger.
     */
    public function testInfoLoggingWithLogger(): void
    {
        $logger = new MockLogger();
        $this->container->set('logger', $logger);

        $this->plugin->log('Test info message', 'info');

        $this->assertTrue($logger->hasLog('info', 'Test info message'));
    }

    /**
     * @group logging
     *
     * Tests various log levels with logger service.
     * The logger should receive the correct level for each log call.
     */
    public function testVariousLogLevels(): void
    {
        $logger = new MockLogger();
        $this->container->set('logger', $logger);

        $this->plugin->log('Debug message', 'debug');
        $this->plugin->log('Info message', 'info');
        $this->plugin->log('Warning message', 'warning');
        $this->plugin->logError('Error message');

        $this->assertTrue($logger->hasLog('debug', 'Debug message'));
        $this->assertTrue($logger->hasLog('info', 'Info message'));
        $this->assertTrue($logger->hasLog('warning', 'Warning message'));
        $this->assertTrue($logger->hasLog('error', 'Error message'));
        $this->assertCount(4, $logger->getLogs());
    }

    /**
     * @group logging
     *
     * Tests error logging fallback when no logger is registered.
     * Errors should always be logged via error_log, even without debug mode.
     */
    public function testErrorLoggingFallback(): void
    {
        $this->assertFalse($this->container->has('logger'));
        $this->container->set('debug', false); // Disable debug

        // Should not throw exception
        $this->plugin->logError('Test error message');

        // We can't easily test error_log output, but no exception = success
        $this->assertTrue(true);
    }

    /**
     * @group logging
     *
     * Tests info logging behavior without debug mode.
     * Info logs should be ignored when debug is false and no logger is registered.
     */
    public function testInfoLoggingWithoutDebug(): void
    {
        $this->assertFalse($this->container->has('logger'));
        $this->container->set('debug', false);

        // Should not throw exception and should not log
        $this->plugin->log('This should not be logged', 'info');

        // Test passes if no exception
        $this->assertTrue(true);
    }

    /**
     * @group logging
     *
     * Tests logging with invalid logger object.
     * Should fall back to error_log if logger doesn't have log() method.
     */
    public function testLoggingWithInvalidLogger(): void
    {
        $invalidLogger = new \stdClass(); // No log() method
        $this->container->set('logger', $invalidLogger);
        $this->container->set('debug', true);

        // Should fall back to error_log without exception
        $this->plugin->log('Test message', 'info');
        $this->plugin->logError('Test error');

        $this->assertTrue(true);
    }

    /**
     * @group logging
     *
     * Tests logging when logger service throws exception.
     * Should fall back gracefully without breaking.
     */
    public function testLoggingWithFailingLogger(): void
    {
        $failingLogger = new FailingLogger();
        $this->container->set('logger', $failingLogger);
        $this->container->set('debug', true);

        // Should fall back to error_log without throwing
        $this->plugin->log('Test message', 'info');
        $this->plugin->logError('Test error');

        $this->assertTrue(true);
    }

    /**
     * @group logging
     *
     * Tests logging when retrieving logger throws exception.
     * Should handle container exceptions gracefully.
     */
    public function testLoggingWithBrokenLoggerService(): void
    {
        $this->container->set('logger', \DI\factory(function() {
            throw new \RuntimeException('Logger service broken');
        }));
        $this->container->set('debug', true);

        // Should fall back without throwing
        $this->plugin->log('Test message', 'info');
        $this->plugin->logError('Test error');

        $this->assertTrue(true);
    }

    /**
     * @group logging
     *
     * Tests ultimate fallback when even basic services are missing.
     * Should still attempt to log errors without throwing exceptions.
     */
    public function testLoggingUltimateFallback(): void
    {
        $emptyContainer = new Container([], false);
        $plugin = new Plugin($emptyContainer, '/tmp/test.php');

        // Should use ultimate fallback without exception
        $plugin->logError('Critical error');

        $this->assertTrue(true);
    }

    /**
     * @group logging
     *
     * Tests logging when debug service throws exception.
     * Should handle gracefully and still log errors.
     */
    public function testLoggingWithBrokenDebugService(): void
    {
        $this->container->set('debug', \DI\factory(function() {
            throw new \RuntimeException('Debug service broken');
        }));

        // Error should still be logged via ultimate fallback
        $this->plugin->logError('Critical error');

        $this->assertTrue(true);
    }
}

// ========================================================================
// Test Helper Classes
// ========================================================================

/**
 * Simple test service for make() testing
 */
class TestService
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
 * Service with broken constructor for testing
 */
class BrokenConstructorService
{
    public function __construct()
    {
        throw new \RuntimeException('Constructor failed');
    }
}

/**
 * Mock service provider for testing boot process
 */
class MockServiceProvider extends ServiceProvider
{
    public int $bootCount = 0;

    public function register(): void
    {
        // Empty register for testing
    }

    public function boot(): void
    {
        $this->bootCount++;
    }
}

/**
 * Failing service provider for error testing
 */
class FailingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Empty register
    }

    public function boot(): void
    {
        throw new \Exception('Provider boot failed');
    }
}

/**
 * Mock logger for testing logging functionality
 */
class MockLogger
{
    private array $logs = [];

    public function log(string $level, string $message): void
    {
        $this->logs[] = ['level' => $level, 'message' => $message];
    }

    public function hasLog(string $level, string $message): bool
    {
        foreach ($this->logs as $log) {
            if ($log['level'] === $level && $log['message'] === $message) {
                return true;
            }
        }
        return false;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}

/**
 * Failing logger for testing fallback behavior
 */
class FailingLogger
{
    public function log(string $level, string $message): void
    {
        throw new \RuntimeException('Logger failed');
    }
}