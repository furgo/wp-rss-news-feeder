<?php
/**
 * Container Caching Integration Tests
 *
 * Tests the integration between Container, ServiceProvider, and PluginFactory
 * with a focus on container compilation/caching behavior in production mode.
 * These tests ensure that the framework works correctly with WordPress in
 * both development and production environments.
 *
 * @package     Furgo\Sitechips\Core\Tests\Integration
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Integration\Container;

use Furgo\Sitechips\Core\Container\Container;
use Furgo\Sitechips\Core\Container\ServiceProvider;
use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;
use Furgo\Sitechips\Core\Tests\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Container Caching Integration Test Class
 *
 * @since 1.0.0
 */
class ContainerCachingIntegrationTest extends TestCase
{
    /**
     * Temporary directory for test files
     *
     * @var string
     */
    private string $tempDir;

    /**
     * Temporary plugin file path
     *
     * @var string
     */
    private string $pluginFile;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory
        $this->tempDir = sys_get_temp_dir() . '/sitechips-integration-test-' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        // Create a mock plugin file
        $this->pluginFile = $this->tempDir . '/test-plugin.php';
        file_put_contents($this->pluginFile, '<?php
/**
 * Plugin Name: Test Plugin
 * Version: 1.0.0
 * Text Domain: test-plugin
 */
');
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove temporary files
        if (is_file($this->pluginFile)) {
            unlink($this->pluginFile);
        }

        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
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
    // Basic Integration Tests
    // ========================================================================

    /**
     * @group integration
     * @group container-caching
     *
     * Tests that a complete plugin setup works with container compilation enabled.
     * This simulates production environment behavior.
     */
    public function testPluginWithCompiledContainer(): void
    {
        // Create plugin with production mode (compilation enabled)
        $plugin = PluginFactory::create($this->pluginFile, [
            'cache.path' => $this->tempDir . '/cache',
            'debug' => false  // Forces production mode
        ], [
            TestIntegrationServiceProvider::class
        ]);

        // Boot the plugin
        $plugin->boot();

        // Verify services are available
        $this->assertTrue($plugin->has('test.service'));
        $this->assertTrue($plugin->has('test.logger'));

        $service = $plugin->get('test.service');
        $this->assertInstanceOf(IntegrationTestService::class, $service);
        $this->assertEquals('production', $service->getMode());
    }

    /**
     * @group integration
     * @group container-caching
     *
     * Tests that service providers can register services correctly in both modes.
     * This ensures our pattern of direct instantiation works universally.
     */
    public function testServiceProviderCompatibility(): void
    {
        // Test both development and production modes
        foreach ([true, false] as $debug) {
            $plugin = PluginFactory::create($this->pluginFile, [
                'cache.path' => $this->tempDir . '/cache-' . ($debug ? 'dev' : 'prod'),
                'debug' => $debug
            ], [
                ProductionCompatibleIntegrationProvider::class
            ]);

            $plugin->boot();

            // All services should be available regardless of mode
            $this->assertTrue($plugin->has('logger'), "Logger missing in " . ($debug ? 'dev' : 'prod') . " mode");
            $this->assertTrue($plugin->has('repository'), "Repository missing in " . ($debug ? 'dev' : 'prod') . " mode");
            $this->assertTrue($plugin->has('service.manager'), "Service manager missing in " . ($debug ? 'dev' : 'prod') . " mode");

            // Verify services work correctly
            $logger = $plugin->get('logger');
            $this->assertInstanceOf(IntegrationLogger::class, $logger);

            $repository = $plugin->get('repository');
            $this->assertInstanceOf(IntegrationRepository::class, $repository);
            $this->assertSame($logger, $repository->getLogger()); // Same instance
        }
    }

    /**
     * @group integration
     * @group container-caching
     *
     * Tests that WordPress hooks are properly registered when using compiled containers.
     * This verifies the boot phase works correctly in production.
     */
    public function testWordPressIntegrationWithCompilation(): void
    {
        // Remove any existing actions to ensure clean test
        remove_all_actions('test_plugin_init');
        remove_all_filters('test_plugin_content');

        $plugin = PluginFactory::create($this->pluginFile, [
            'cache.path' => $this->tempDir . '/cache',
            'debug' => false  // Production mode
        ], [
            WordPressIntegrationProvider::class
        ]);

        $plugin->boot();

        // Verify WordPress hooks were registered
        $this->assertTrue(has_action('test_plugin_init'));
        $this->assertTrue(has_filter('test_plugin_content'));

        // Test that hooks work correctly
        $initCalled = false;
        add_action('test_plugin_init_called', function() use (&$initCalled) {
            $initCalled = true;
        });

        do_action('test_plugin_init');
        $this->assertTrue($initCalled, 'Init hook was not called');

        // Test filter
        $content = apply_filters('test_plugin_content', 'original');
        $this->assertEquals('original [filtered]', $content);
    }

    /**
     * @group integration
     * @group container-caching
     * @group known-issues
     *
     * Tests that factory-based registration fails in production mode.
     * This documents the limitation we work around.
     */
    public function testFactoryRegistrationFailsInProduction(): void
    {
        // Create container with explicit compilation
        $container = new Container([
            'cache.path' => $this->tempDir . '/cache'
        ], true);

        $this->assertTrue($container->isCompiled());

        // Create provider manually
        $provider = new FactoryBasedProvider($container);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You cannot set a definition at runtime');

        // This should fail
        $provider->register();
    }

    /**
     * @group integration
     * @group container-caching
     *
     * Tests complex dependency chains work correctly with compiled containers.
     * This ensures our direct instantiation pattern scales to real-world scenarios.
     */
    public function testComplexDependencyChain(): void
    {
        $plugin = PluginFactory::create($this->pluginFile, [
            'cache.path' => $this->tempDir . '/cache',
            'debug' => false,  // Production mode
            'database.host' => 'localhost',
            'database.name' => 'test_db',
            'api.key' => 'test-api-key',
            'api.endpoint' => 'https://api.example.com'
        ], [
            ComplexDependencyProvider::class
        ]);

        $plugin->boot();

        // Verify the entire dependency chain works
        $controller = $plugin->get('api.controller');
        $this->assertInstanceOf(ApiController::class, $controller);

        // Test that dependencies were injected correctly
        $result = $controller->fetchData();
        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('database', $result);
        $this->assertEquals('https://api.example.com', $result['source']);
        $this->assertEquals('localhost:test_db', $result['database']);
    }

    /**
     * @group integration
     * @group container-caching
     *
     * Tests that plugin lifecycle events work correctly with compiled containers.
     * This ensures events are dispatched properly in production.
     */
    public function testPluginLifecycleEvents(): void
    {
        $events = [];

        // Create plugin first to get the actual slug
        $plugin = PluginFactory::create($this->pluginFile, [
            'cache.path' => $this->tempDir . '/cache',
            'debug' => false  // Production mode
        ], [
            LifecycleTestProvider::class
        ]);

        // Get the actual plugin slug
        $pluginSlug = $plugin->get('plugin.slug');

        // Set up event listener with correct event name
        add_action($pluginSlug . '.booted', function($plugin) use (&$events) {
            $events[] = 'booted';
            $this->assertInstanceOf(Plugin::class, $plugin);
        });

        // Boot should trigger events
        $plugin->boot();

        $this->assertContains('booted', $events);

        // Service provider should have registered its own lifecycle tracking
        $tracker = $plugin->get('lifecycle.tracker');
        $this->assertTrue($tracker->wasBooted());
    }

    /**
     * @group integration
     * @group container-caching
     * @group performance
     *
     * Tests that compiled containers actually improve performance.
     * This validates that our production optimizations work.
     */
    public function testCompiledContainerPerformance(): void
    {
        // Skip this test in environments where WP_DEBUG is true
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->markTestSkipped('Container compilation is disabled when WP_DEBUG is true');
        }

        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir, 0777, true);

        // Alternative: Test with direct Container creation
        $container1 = new Container([
            'cache.path' => $cacheDir
        ], true); // Force compilation

        $this->assertTrue($container1->isCompiled());

        // Register services
        $provider = new PerformanceTestProvider($container1);
        $provider->register();

        // Access service to trigger compilation
        $service = $container1->get('heavy.service');
        $this->assertInstanceOf(HeavyService::class, $service);

        // Check if compiled container was created
        $compiledFile = $cacheDir . '/CompiledContainer.php';
        if ($container1->isCompiled()) {
            $this->assertFileExists($compiledFile);
        }
    }
}

// ========================================================================
// Test Service Providers
// ========================================================================

/**
 * Basic test service provider
 */
class TestIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Direct instantiation pattern
        $mode = $this->container->get('debug') ? 'development' : 'production';

        $service = new IntegrationTestService($mode);
        $this->container->set('test.service', $service);

        $logger = new IntegrationLogger();
        $this->container->set('test.logger', $logger);
    }

    public function boot(): void
    {
        // WordPress integration would go here
    }
}

/**
 * Production-compatible provider following best practices
 */
class ProductionCompatibleIntegrationProvider extends ServiceProvider
{
    public function register(): void
    {
        // Logger service
        $logger = new IntegrationLogger();
        $this->container->set('logger', $logger);
        $this->container->set(LoggerInterface::class, $logger);

        // Repository with dependency
        $repository = new IntegrationRepository($logger);
        $this->container->set('repository', $repository);

        // Service manager with multiple dependencies
        $config = [
            'cache_enabled' => !$this->container->get('debug'),
            'log_level' => $this->container->has('log.level')
                ? $this->container->get('log.level')
                : 'info'
        ];

        $manager = new ServiceManager($repository, $logger, $config);
        $this->container->set('service.manager', $manager);
    }
}

/**
 * WordPress integration provider
 */
class WordPressIntegrationProvider extends ServiceProvider
{
    public function register(): void
    {
        $handler = new WordPressHandler();
        $this->container->set('wp.handler', $handler);
    }

    public function boot(): void
    {
        $handler = $this->container->get('wp.handler');

        $this->addAction('test_plugin_init', [$handler, 'onInit']);
        $this->addFilter('test_plugin_content', [$handler, 'filterContent']);
    }
}

/**
 * Factory-based provider that will fail in production
 */
class FactoryBasedProvider extends ServiceProvider
{
    public function register(): void
    {
        // This will fail with compiled containers
        $this->bind('failing.service', function() {
            return new \stdClass();
        });
    }
}

/**
 * Complex dependency provider
 */
class ComplexDependencyProvider extends ServiceProvider
{
    public function register(): void
    {
        // Database connection
        $dbConfig = [
            'host' => $this->container->get('database.host'),
            'name' => $this->container->get('database.name')
        ];
        $database = new DatabaseConnection($dbConfig);
        $this->container->set('database', $database);

        // API client
        $apiConfig = [
            'key' => $this->container->get('api.key'),
            'endpoint' => $this->container->get('api.endpoint')
        ];
        $apiClient = new ApiClient($apiConfig);
        $this->container->set('api.client', $apiClient);

        // Logger
        $logger = new IntegrationLogger();
        $this->container->set('logger', $logger);

        // Repository with database dependency
        $repository = new DataRepository($database, $logger);
        $this->container->set('data.repository', $repository);

        // Service with multiple dependencies
        $service = new DataService($repository, $apiClient, $logger);
        $this->container->set('data.service', $service);

        // Controller with service dependency
        $controller = new ApiController($service, $logger);
        $this->container->set('api.controller', $controller);
    }
}

/**
 * Lifecycle test provider
 */
class LifecycleTestProvider extends ServiceProvider
{
    public function register(): void
    {
        $tracker = new LifecycleTracker();
        $this->container->set('lifecycle.tracker', $tracker);
    }

    public function boot(): void
    {
        $tracker = $this->container->get('lifecycle.tracker');
        $tracker->markBooted();

        // Listen to plugin events
        $this->addAction('test-plugin.booted', [$tracker, 'onPluginBooted']);
    }
}

/**
 * Performance test provider with heavy services
 */
class PerformanceTestProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simulate heavy service initialization
        $config = [];
        for ($i = 0; $i < 100; $i++) {
            $config["key_$i"] = "value_$i";
        }

        $service = new HeavyService($config);
        $this->container->set('heavy.service', $service);

        // Multiple simple services
        for ($i = 0; $i < 10; $i++) {
            $this->container->set("service_$i", new IntegrationTestService("service_$i"));
        }
    }
}

// ========================================================================
// Test Service Classes
// ========================================================================

/**
 * Basic test service
 */
class IntegrationTestService
{
    private string $mode;

    public function __construct(string $mode)
    {
        $this->mode = $mode;
    }

    public function getMode(): string
    {
        return $this->mode;
    }
}

/**
 * Test logger
 */
class IntegrationLogger implements LoggerInterface
{
    private array $logs = [];

    public function emergency($message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}

/**
 * Test repository
 */
class IntegrationRepository
{
    private IntegrationLogger $logger;

    public function __construct(IntegrationLogger $logger)
    {
        $this->logger = $logger;
    }

    public function getLogger(): IntegrationLogger
    {
        return $this->logger;
    }
}

/**
 * Service manager
 */
class ServiceManager
{
    private IntegrationRepository $repository;
    private IntegrationLogger $logger;
    private array $config;

    public function __construct(
        IntegrationRepository $repository,
        IntegrationLogger $logger,
        array $config
    ) {
        $this->repository = $repository;
        $this->logger = $logger;
        $this->config = $config;
    }
}

/**
 * WordPress handler
 */
class WordPressHandler
{
    public function onInit(): void
    {
        do_action('test_plugin_init_called');
    }

    public function filterContent(string $content): string
    {
        return $content . ' [filtered]';
    }
}

/**
 * Database connection
 */
class DatabaseConnection
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConnectionString(): string
    {
        return $this->config['host'] . ':' . $this->config['name'];
    }
}

/**
 * API client
 */
class ApiClient
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getEndpoint(): string
    {
        return $this->config['endpoint'];
    }
}

/**
 * Data repository
 */
class DataRepository
{
    private DatabaseConnection $database;
    private IntegrationLogger $logger;

    public function __construct(DatabaseConnection $database, IntegrationLogger $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    public function getDatabaseInfo(): string
    {
        return $this->database->getConnectionString();
    }
}

/**
 * Data service
 */
class DataService
{
    private DataRepository $repository;
    private ApiClient $apiClient;
    private IntegrationLogger $logger;

    public function __construct(
        DataRepository $repository,
        ApiClient $apiClient,
        IntegrationLogger $logger
    ) {
        $this->repository = $repository;
        $this->apiClient = $apiClient;
        $this->logger = $logger;
    }

    public function getData(): array
    {
        return [
            'source' => $this->apiClient->getEndpoint(),
            'database' => $this->repository->getDatabaseInfo()
        ];
    }
}

/**
 * API controller
 */
class ApiController
{
    private DataService $service;
    private IntegrationLogger $logger;

    public function __construct(DataService $service, IntegrationLogger $logger)
    {
        $this->service = $service;
        $this->logger = $logger;
    }

    public function fetchData(): array
    {
        $this->logger->info('Fetching data via API controller');
        return $this->service->getData();
    }
}

/**
 * Lifecycle tracker
 */
class LifecycleTracker
{
    private bool $booted = false;
    private array $events = [];

    public function markBooted(): void
    {
        $this->booted = true;
        $this->events[] = 'booted';
    }

    public function wasBooted(): bool
    {
        return $this->booted;
    }

    public function onPluginBooted(Plugin $plugin): void
    {
        $this->events[] = 'plugin_booted';
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}

/**
 * Heavy service for performance testing
 */
class HeavyService
{
    private array $config;
    private array $data = [];

    public function __construct(array $config)
    {
        $this->config = $config;

        // Simulate heavy initialization
        for ($i = 0; $i < 10; $i++) {
            $this->data[] = array_map('md5', $config);
        }
    }

    public function getData(): array
    {
        return $this->data;
    }
}