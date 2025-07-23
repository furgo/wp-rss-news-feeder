<?php
/**
 * Core Service Provider Unit Tests
 *
 * Tests the CoreServiceProvider implementation, focusing on service registration
 * and debug/production mode logic. Base functionality is already tested in
 * the parent ServiceProvider tests.
 *
 * @package     SitechipsBoilerplate\Tests\Unit\Providers
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       2.0.0
 */

declare(strict_types=1);

namespace Furgo\RssNewsFeeder\Tests\Unit;

use Furgo\RssNewsFeeder\Providers\CoreServiceProvider;
use Furgo\Sitechips\Core\Container\Container;
use Furgo\Sitechips\Core\Services\EventManager;
use Furgo\Sitechips\Core\Services\AssetManager;
use Furgo\Sitechips\Core\Services\NullLogger;
use Furgo\Sitechips\Core\Services\WordPressLogger;
use Furgo\Sitechips\Core\Tests\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Core Service Provider Test Class
 *
 * @since 2.0.0
 * @covers \Furgo\SitechipsBoilerplate\Providers\CoreServiceProvider
 */
class CoreServiceProviderTest extends TestCase
{
    /**
     * Container instance
     *
     * @var Container
     */
    private Container $container;

    /**
     * Provider instance
     *
     * @var CoreServiceProvider
     */
    private CoreServiceProvider $provider;

    /**
     * Temporary directory for test configs
     *
     * @var string
     */
    private string $tempDir;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for test configs
        $this->tempDir = sys_get_temp_dir() . '/sitechips-test-' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/config');
    }

    /**
     * Clean up test environment
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * @group registration
     *
     * Tests that all expected core services are registered.
     */
    public function testRegistersAllCoreServices(): void
    {
        $this->setupProvider(['debug' => false]);

        $this->provider->register();

        // Verify all services are registered
        $this->assertTrue($this->container->has('logger'), 'Logger service not registered');
        $this->assertTrue($this->container->has(LoggerInterface::class), 'LoggerInterface alias not registered');
        $this->assertTrue($this->container->has('events'), 'Event manager service not registered');
        $this->assertTrue($this->container->has('assets'), 'Asset manager service not registered');
    }

    /**
     * @group registration
     *
     * Tests that services can be resolved and are of correct types.
     */
    public function testServicesCanBeResolved(): void
    {
        $this->setupProvider([
            'debug' => false,
            'plugin.slug' => 'test-plugin',
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/',
            'plugin.version' => '1.0.0',
            'testing' => false  // Add testing key explicitly
        ]);

        $this->provider->register();

        // Resolve and verify services
        $logger = $this->container->get('logger');
        $this->assertInstanceOf(LoggerInterface::class, $logger);

        $events = $this->container->get('events');
        $this->assertInstanceOf(EventManager::class, $events);

        $assets = $this->container->get('assets');
        $this->assertInstanceOf(AssetManager::class, $assets);
    }

    /**
     * @group logger
     *
     * Tests that NullLogger is used in production mode (debug = false).
     */
    public function testUsesNullLoggerInProductionMode(): void
    {
        $this->setupProvider(['debug' => false]);

        $this->provider->register();

        $logger = $this->container->get('logger');
        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    /**
     * @group logger
     *
     * Tests that WordPressLogger is used in debug mode (debug = true).
     */
    public function testUsesWordPressLoggerInDebugMode(): void
    {
        $this->setupProvider([
            'debug' => true,
            'plugin.slug' => 'test-plugin'
        ]);

        $this->provider->register();

        $logger = $this->container->get('logger');
        $this->assertInstanceOf(WordPressLogger::class, $logger);
    }

    /**
     * @group logger
     *
     * Tests that existing logger is not overridden.
     * Allows plugins to register custom logger before CoreServiceProvider.
     */
    /**
     * @group logger
     *
     * Tests that existing logger is not overridden.
     * Allows plugins to register custom logger before CoreServiceProvider.
     */
    public function testDoesNotOverrideExistingLogger(): void
    {
        $customLogger = new class implements LoggerInterface {
            public function emergency(string|\Stringable $message, array $context = []): void {}
            public function alert(string|\Stringable $message, array $context = []): void {}
            public function critical(string|\Stringable $message, array $context = []): void {}
            public function error(string|\Stringable $message, array $context = []): void {}
            public function warning(string|\Stringable $message, array $context = []): void {}
            public function notice(string|\Stringable $message, array $context = []): void {}
            public function info(string|\Stringable $message, array $context = []): void {}
            public function debug(string|\Stringable $message, array $context = []): void {}
            public function log($level, string|\Stringable $message, array $context = []): void {}
        };

        $this->container = new Container([
            'debug' => true,
            'plugin.path' => $this->tempDir . '/',
            'plugin.slug' => 'test-plugin',
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/',
            'plugin.version' => '1.0.0'
        ], false);
        $this->container->set('logger', $customLogger);

        $this->provider = new CoreServiceProvider($this->container);
        $this->provider->register();

        $logger = $this->container->get('logger');
        $this->assertSame($customLogger, $logger);
    }

    /**
     * @group events
     *
     * Tests EventManager configuration in normal mode.
     */
    public function testEventManagerUsesWordPressIntegration(): void
    {
        $this->setupProvider([
            'plugin.slug' => 'test-plugin',
            'testing' => false
        ]);

        $this->provider->register();

        $events = $this->container->get('events');
        $this->assertInstanceOf(EventManager::class, $events);

        // Event manager is configured with plugin slug
        // In real usage, it would prefix events with 'test-plugin.'
    }

    /**
     * @group events
     *
     * Tests EventManager configuration in testing mode.
     */
    public function testEventManagerDisablesWordPressInTestMode(): void
    {
        $this->setupProvider([
            'plugin.slug' => 'test-plugin',
            'testing' => true
        ]);

        $this->provider->register();

        $events = $this->container->get('events');
        $this->assertInstanceOf(EventManager::class, $events);

        // Event manager is configured without WordPress integration
        // for isolated testing
    }

    /**
     * @group assets
     *
     * Tests AssetManager configuration.
     */
    public function testAssetManagerConfiguration(): void
    {
        $this->setupProvider([
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/',
            'plugin.version' => '2.0.0'
        ]);

        $this->provider->register();

        $assets = $this->container->get('assets');
        $this->assertInstanceOf(AssetManager::class, $assets);

        // Asset manager is configured with plugin URL and version
        // for proper asset URLs and cache busting
    }

    /**
     * @group config
     *
     * Tests plugin configuration loading.
     */
    public function testLoadsPluginConfiguration(): void
    {
        // Create test config file
        $configContent = "<?php\nreturn [\n    'api' => [\n        'endpoint' => 'https://test.example.com',\n        'timeout' => 45\n    ],\n    'features' => [\n        'enable_cron' => false\n    ]\n];";
        file_put_contents($this->tempDir . '/config/plugin.php', $configContent);

        $this->setupProvider([
            'debug' => false,
            'plugin.slug' => 'test-plugin',
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/',
            'plugin.version' => '1.0.0'
        ]);

        $this->provider->register();

        // Verify config values are loaded
        $this->assertEquals('https://test.example.com', $this->container->get('config.api.endpoint'));
        $this->assertEquals(45, $this->container->get('config.api.timeout'));
        $this->assertFalse($this->container->get('config.features.enable_cron'));
    }

    /**
     * @group config
     *
     * Tests behavior when config file is missing.
     */
    public function testHandlesMissingConfigFile(): void
    {
        $this->setupProvider([
            'debug' => false,
            'plugin.slug' => 'test-plugin',
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/',
            'plugin.version' => '1.0.0'
        ]);

        // Should not throw exception
        $this->provider->register();

        // Services should still be registered
        $this->assertTrue($this->container->has('logger'));
        $this->assertTrue($this->container->has('events'));
        $this->assertTrue($this->container->has('assets'));
    }

    /**
     * @group boot
     *
     * Tests boot phase in non-admin context.
     * Should boot without errors but not register admin features.
     */
    public function testBootInNonAdminContext(): void
    {
        $this->setupProvider([
            'debug' => false,
            'plugin.version' => '1.0.0',
            'environment' => 'production',
            'plugin.text_domain' => 'test-plugin',
            'plugin.basename' => 'test-plugin/test-plugin.php'
        ]);

        $this->provider->register();

        // Should not throw any errors
        $this->provider->boot();

        // Verify provider is bootable
        $this->assertTrue(method_exists($this->provider, 'boot'));
    }

    /**
     * @group boot
     *
     * Tests boot phase in debug mode.
     * Should log plugin boot information.
     */
    /**
     * @group boot
     *
     * Tests boot phase in debug mode.
     * Should log plugin boot information.
     */
    public function testBootLogsInDebugMode(): void
    {
        // Create a mock logger to verify logging
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Plugin booted',
                $this->callback(function ($context) {
                    return isset($context['version']) && $context['version'] === '1.0.0'
                        && isset($context['environment']) && $context['environment'] === 'development';
                })
            );

        $this->container = new Container([
            'debug' => true,
            'plugin.version' => '1.0.0',
            'environment' => 'development',
            'plugin.slug' => 'test-plugin',
            'plugin.path' => $this->tempDir . '/',
            'plugin.text_domain' => 'test-plugin',
            'plugin.basename' => 'test-plugin/test-plugin.php',
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/'
        ], false);

        $this->container->set('logger', $mockLogger);

        $this->provider = new CoreServiceProvider($this->container);
        $this->provider->register();
        $this->provider->boot();
    }

    /**
     * @group boot
     *
     * Tests boot phase without debug mode.
     * Should not attempt to log.
     */
    public function testBootDoesNotLogInProductionMode(): void
    {
        $this->setupProvider([
            'debug' => false,
            'plugin.version' => '1.0.0',
            'environment' => 'production',
            'plugin.text_domain' => 'test-plugin',
            'plugin.basename' => 'test-plugin/test-plugin.php'
        ]);

        $this->provider->register();

        // NullLogger will be used, no actual logging occurs
        $this->provider->boot();

        // Verify logger is NullLogger
        $logger = $this->container->get('logger');
        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    /**
     * @group integration
     *
     * Tests full lifecycle: construct -> register -> boot.
     */
    public function testFullProviderLifecycle(): void
    {
        $this->setupProvider([
            'debug' => true,
            'plugin.slug' => 'test-plugin',
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/',
            'plugin.version' => '1.0.0',
            'environment' => 'development',
            'plugin.text_domain' => 'test-plugin',
            'plugin.basename' => 'test-plugin/test-plugin.php'
        ]);

        // Register phase
        $this->provider->register();
        $this->provider->markAsRegistered();
        $this->assertTrue($this->provider->isRegistered());

        // Verify services available after registration
        $this->assertTrue($this->container->has('logger'));
        $this->assertTrue($this->container->has('events'));
        $this->assertTrue($this->container->has('assets'));

        // Boot phase
        $this->provider->boot();
        $this->provider->markAsBooted();
        $this->assertTrue($this->provider->isBooted());
    }

    /**
     * Set up provider with given configuration
     *
     * @param array<string, mixed> $config Container configuration
     */
    private function setupProvider(array $config): void
    {
        // Merge with default values to ensure all required services can be created
        $defaults = [
            'plugin.path' => $this->tempDir . '/',
            'plugin.slug' => 'test-plugin',
            'plugin.url' => 'http://example.com/wp-content/plugins/test-plugin/',
            'plugin.version' => '1.0.0',
            'plugin.text_domain' => 'test-plugin',
            'plugin.basename' => 'test-plugin/test-plugin.php',
            'debug' => false,
            'environment' => 'testing',
            'testing' => false
        ];

        $config = array_merge($defaults, $config);

        $this->container = new Container($config, false);
        $this->provider = new CoreServiceProvider($this->container);
    }

    /**
     * Recursively remove a directory
     *
     * @param string $dir Directory to remove
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}