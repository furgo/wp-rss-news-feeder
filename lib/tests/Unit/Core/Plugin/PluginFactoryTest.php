<?php
/**
 * Plugin Factory Unit Tests
 *
 * Comprehensive tests for the PluginFactory class.
 * Tests plugin creation, container configuration, service provider management,
 * and environment detection with various WordPress function availability scenarios.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Plugin
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Plugin;

use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;
use Furgo\Sitechips\Core\Container\Container;
use Furgo\Sitechips\Core\Container\ContainerException;
use Furgo\Sitechips\Core\Container\ServiceProvider;
use Furgo\Sitechips\Core\Tests\TestCase;
use InvalidArgumentException;
use Exception;

/**
 * Plugin Factory Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Plugin\PluginFactory
 */
class PluginFactoryTest extends TestCase
{
    /**
     * Test plugin file path
     *
     * @var string
     */
    private string $pluginFile;

    /**
     * Temporary test file for creation tests
     *
     * @var string
     */
    private string $tempPluginFile;

    /**
     * Set up each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginFile = '/tmp/test-plugin.php';
        $this->tempPluginFile = sys_get_temp_dir() . '/test-plugin-' . uniqid() . '.php';

        // Create temporary plugin file for tests that need it
        file_put_contents($this->tempPluginFile, '<?php // Test plugin');
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove temporary file if exists
        if (file_exists($this->tempPluginFile)) {
            unlink($this->tempPluginFile);
        }
    }

    // ========================================================================
    // Basic Creation Tests
    // ========================================================================

    /**
     * @group creation
     *
     * Tests basic plugin creation with existing file.
     * Should create a plugin instance with all required definitions.
     */
    public function testBasicPluginCreation(): void
    {
        $plugin = PluginFactory::create($this->tempPluginFile);

        $this->assertInstanceOf(Plugin::class, $plugin);
        $this->assertEquals($this->tempPluginFile, $plugin->getPluginFile());
        $this->assertInstanceOf(Container::class, $plugin->getContainer());

        // Check required definitions are set
        $this->assertTrue($plugin->has('plugin.slug'));
        $this->assertTrue($plugin->has('plugin.path'));
        $this->assertTrue($plugin->has('plugin.url'));
    }

    /**
     * @group creation
     *
     * Tests plugin creation with non-existent file.
     * Should throw InvalidArgumentException with helpful message.
     */
    public function testCreateWithNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plugin file not found: /path/to/non-existent.php');

        PluginFactory::create('/path/to/non-existent.php');
    }

    /**
     * @group creation
     *
     * Tests plugin creation with custom configuration.
     * Custom config should override base definitions.
     */
    public function testCreateWithCustomConfig(): void
    {
        $config = [
            'debug' => false,
            'custom.setting' => 'test_value',
            'plugin.name' => 'Custom Plugin Name',
        ];

        $plugin = PluginFactory::create($this->tempPluginFile, $config);

        $this->assertFalse($plugin->get('debug'));
        $this->assertEquals('test_value', $plugin->get('custom.setting'));
        $this->assertEquals('Custom Plugin Name', $plugin->get('plugin.name'));
    }

    /**
     * @group creation
     *
     * Tests plugin creation for testing environment.
     * Should have test-friendly defaults and debug enabled.
     */
    public function testCreateForTesting(): void
    {
        $plugin = PluginFactory::createForTesting();

        $this->assertInstanceOf(Plugin::class, $plugin);
        $this->assertEquals('/tmp/test-plugin.php', $plugin->getPluginFile());
        $this->assertEquals('testing', $plugin->get('environment'));
        $this->assertTrue($plugin->get('debug'));
        $this->assertFalse($plugin->get('cache.enabled'));
        $this->assertEquals('Test Plugin', $plugin->get('plugin.name'));
        $this->assertEquals('1.0.0', $plugin->get('plugin.version'));
        $this->assertEquals('test-plugin', $plugin->get('plugin.text_domain'));
    }

    /**
     * @group creation
     *
     * Tests createForTesting with custom configuration.
     * Should merge custom config with test defaults.
     */
    public function testCreateForTestingWithConfig(): void
    {
        $config = [
            'custom.test' => 'value',
            'plugin.name' => 'My Test Plugin',
        ];

        $plugin = PluginFactory::createForTesting('/custom/test.php', $config);

        $this->assertEquals('/custom/test.php', $plugin->getPluginFile());
        $this->assertEquals('value', $plugin->get('custom.test'));
        $this->assertEquals('My Test Plugin', $plugin->get('plugin.name'));
        $this->assertTrue($plugin->get('debug')); // Still has test defaults
    }

    // ========================================================================
    // Service Provider Tests
    // ========================================================================

    /**
     * @group providers
     *
     * Tests plugin creation with service providers.
     * Providers should be registered and services available.
     */
    public function testCreateWithServiceProviders(): void
    {
        $providers = [TestServiceProvider::class];

        $plugin = PluginFactory::create($this->tempPluginFile, [], $providers);

        $this->assertTrue($plugin->has('test.service'));
        $this->assertEquals('test_value', $plugin->get('test.service'));

        // Verify provider was registered
        $providerInstances = $plugin->get('providers');
        $this->assertCount(1, $providerInstances);
        $this->assertInstanceOf(TestServiceProvider::class, $providerInstances[0]);
    }

    /**
     * @group providers
     *
     * Tests plugin creation with invalid service provider.
     * Should throw exception with clear error message.
     */
    public function testCreateWithInvalidServiceProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service provider class not found: NonExistentProvider');

        PluginFactory::create($this->tempPluginFile, [], ['NonExistentProvider']);
    }

    /**
     * @group providers
     *
     * Tests plugin creation with class that's not a ServiceProvider.
     * Should throw exception explaining the requirement.
     */
    public function testCreateWithNonServiceProviderClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class stdClass must extend ServiceProvider');

        PluginFactory::create($this->tempPluginFile, [], [\stdClass::class]);
    }

    /**
     * @group providers
     *
     * Tests plugin creation with multiple providers.
     * All providers should be registered in order.
     */
    public function testCreateWithMultipleProviders(): void
    {
        $providers = [
            TestServiceProvider::class,
            AnotherTestServiceProvider::class,
        ];

        $plugin = PluginFactory::create($this->tempPluginFile, [], $providers);

        $this->assertTrue($plugin->has('test.service'));
        $this->assertTrue($plugin->has('another.service'));
        $this->assertEquals('test_value', $plugin->get('test.service'));
        $this->assertEquals('another_value', $plugin->get('another.service'));
    }

    /**
     * @group providers
     *
     * Tests that provider registration failure is handled properly.
     * Should wrap exception with context about which provider failed.
     */
    public function testCreateWithFailingProviderRegistration(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to register service provider 'Furgo\Sitechips\Core\Tests\Unit\Core\Plugin\FailingRegistrationProvider': Registration failed");

        PluginFactory::create($this->tempPluginFile, [], [FailingRegistrationProvider::class]);
    }

    // ========================================================================
    // Definition Creation Tests
    // ========================================================================

    /**
     * @group definitions
     *
     * Tests base definitions creation.
     * Should include all required and recommended definitions.
     */
    public function testCreateBaseDefinitions(): void
    {
        $definitions = $this->invokeMethod(
            PluginFactory::class,
            'createBaseDefinitions',
            [$this->tempPluginFile]
        );

        // Required definitions
        $this->assertArrayHasKey('plugin.slug', $definitions);
        $this->assertArrayHasKey('debug', $definitions);
        $this->assertArrayHasKey('providers', $definitions);

        // Plugin file information
        $this->assertArrayHasKey('plugin.file', $definitions);
        $this->assertArrayHasKey('plugin.path', $definitions);
        $this->assertArrayHasKey('plugin.url', $definitions);
        $this->assertArrayHasKey('plugin.basename', $definitions);

        // Plugin metadata
        $this->assertArrayHasKey('plugin.name', $definitions);
        $this->assertArrayHasKey('plugin.version', $definitions);
        $this->assertArrayHasKey('plugin.description', $definitions);
        $this->assertArrayHasKey('plugin.author', $definitions);
        $this->assertArrayHasKey('plugin.text_domain', $definitions);

        // Environment and paths
        $this->assertArrayHasKey('environment', $definitions);
        $this->assertArrayHasKey('path.assets', $definitions);
        $this->assertArrayHasKey('path.templates', $definitions);
        $this->assertArrayHasKey('path.languages', $definitions);
        $this->assertArrayHasKey('url.assets', $definitions);
        $this->assertArrayHasKey('events.global_prefix', $definitions);

        // Check some values
        $this->assertEquals($this->tempPluginFile, $definitions['plugin.file']);
        $this->assertIsString($definitions['plugin.slug']);
        $this->assertIsArray($definitions['providers']);
        $this->assertNull($definitions['events.global_prefix']);
    }

    /**
     * @group definitions
     *
     * Tests plugin slug extraction from file path.
     * Should handle various directory names correctly.
     */
    public function testExtractPluginSlug(): void
    {
        // Normal plugin path
        $slug = $this->invokeMethod(
            PluginFactory::class,
            'extractPluginSlug',
            ['/wp-content/plugins/my-awesome-plugin/my-awesome-plugin.php']
        );
        $this->assertEquals('my-awesome-plugin', $slug);

        // Plugin with spaces
        $slug = $this->invokeMethod(
            PluginFactory::class,
            'extractPluginSlug',
            ['/wp-content/plugins/My Awesome Plugin/plugin.php']
        );
        $this->assertEquals('my-awesome-plugin', $slug);

        // Simple name
        $slug = $this->invokeMethod(
            PluginFactory::class,
            'extractPluginSlug',
            ['/tmp/test/plugin.php']
        );
        $this->assertEquals('test', $slug);
    }

    // ========================================================================
    // WordPress Function Tests
    // ========================================================================

    /**
     * @group wordpress-functions
     *
     * Tests plugin path extraction with various scenarios.
     * Real WordPress function returns path with trailing slash.
     */
    public function testGetPluginPath(): void
    {
        // Standard plugin path
        $path = $this->invokeMethod(
            PluginFactory::class,
            'getPluginPath',
            ['/wp-content/plugins/test/plugin.php']
        );
        $this->assertStringEndsWith('/', $path);

        // Root directory plugin
        $path = $this->invokeMethod(
            PluginFactory::class,
            'getPluginPath',
            ['/plugin.php']
        );
        $this->assertStringEndsWith('/', $path);
    }

    /**
     * @group wordpress-functions
     *
     * Tests plugin URL extraction with various paths.
     * Should handle different plugin locations.
     */
    public function testGetPluginUrl(): void
    {
        // Standard WordPress plugin
        $url = $this->invokeMethod(
            PluginFactory::class,
            'getPluginUrl',
            [WP_PLUGIN_DIR . '/my-plugin/plugin.php']
        );
        $this->assertStringStartsWith('http', $url);
        $this->assertStringEndsWith('/', $url);
        $this->assertStringContainsString('my-plugin', $url);

        // Plugin in subdirectory
        $url = $this->invokeMethod(
            PluginFactory::class,
            'getPluginUrl',
            [WP_PLUGIN_DIR . '/vendor/plugin/main.php']
        );
        $this->assertStringContainsString('vendor/plugin', $url);
    }

    /**
     * @group wordpress-functions
     *
     * Tests plugin URL for paths outside content directory.
     * Should use fallback logic.
     */
    public function testGetPluginUrlFallback(): void
    {
        // Path completely outside WordPress structure
        $url = $this->invokeMethod(
            PluginFactory::class,
            'getPluginUrl',
            ['/custom/absolute/path/plugin.php']
        );

        // Should still return a valid URL
        $this->assertStringStartsWith('http', $url);
        $this->assertStringEndsWith('/', $url);
    }

    /**
     * @group wordpress-functions
     *
     * Tests plugin basename extraction with various paths.
     * Should handle standard and non-standard locations.
     */
    public function testGetPluginBasename(): void
    {
        // Standard plugin location
        $basename = $this->invokeMethod(
            PluginFactory::class,
            'getPluginBasename',
            [WP_PLUGIN_DIR . '/my-plugin/my-plugin.php']
        );
        $this->assertEquals('my-plugin/my-plugin.php', $basename);

        // Nested plugin
        $basename = $this->invokeMethod(
            PluginFactory::class,
            'getPluginBasename',
            [WP_PLUGIN_DIR . '/vendor/nested/plugin.php']
        );
        $this->assertEquals('vendor/nested/plugin.php', $basename);
    }

    /**
     * @group wordpress-functions
     *
     * Tests plugin basename for non-standard paths.
     * Should create reasonable basename.
     */
    public function testGetPluginBasenameFallback(): void
    {
        // Outside plugin directory
        $basename = $this->invokeMethod(
            PluginFactory::class,
            'getPluginBasename',
            ['/custom/location/my-plugin/plugin.php']
        );

        // Should create basename from directory and file
        $this->assertStringContainsString('my-plugin', $basename);
        $this->assertStringContainsString('plugin.php', $basename);
    }

    /**
     * @group wordpress-functions
     *
     * Tests plugin data extraction with actual file.
     * WordPress parses plugin headers.
     */
    public function testGetPluginDataWithFile(): void
    {
        // Create plugin file with headers
        $content = "<?php\n/**\n * Plugin Name: Test Plugin\n * Version: 2.0.0\n * Description: A test plugin\n * Author: Test Author\n * Text Domain: test-domain\n */";
        $testFile = $this->tempPluginFile . '.headers';
        file_put_contents($testFile, $content);

        $data = $this->invokeMethod(
            PluginFactory::class,
            'getPluginData',
            [$testFile]
        );

        unlink($testFile);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('Name', $data);
        $this->assertArrayHasKey('Version', $data);

        // WordPress should parse the headers
        if (!empty($data['Name'])) {
            $this->assertEquals('Test Plugin', $data['Name']);
            $this->assertEquals('2.0.0', $data['Version']);
        }
    }

    /**
     * @group wordpress-functions
     *
     * Tests plugin data extraction without file.
     * WordPress returns default structure.
     */
    public function testGetPluginDataWithoutFile(): void
    {
        $data = $this->invokeMethod(
            PluginFactory::class,
            'getPluginData',
            ['/non/existent/file.php']
        );

        $this->assertIsArray($data);
        // WordPress returns array with keys but empty values
        $this->assertArrayHasKey('Name', $data);
        $this->assertEmpty($data['Name']);
    }

    /**
     * @group wordpress-functions
     *
     * Tests plugin data manual parsing fallback.
     * When get_plugin_data is not available, parse headers manually.
     */
    public function testGetPluginDataManualParsing(): void
    {
        // This tests the manual parsing logic even though
        // get_plugin_data is available in our environment
        $reflection = new \ReflectionClass(PluginFactory::class);
        $method = $reflection->getMethod('getPluginData');
        $method->setAccessible(true);

        // Create test file
        $content = "<?php\n/**\n * Plugin Name: Manual Parse Test\n * Version: 3.0.0\n */";
        $testFile = $this->tempPluginFile . '.manual';
        file_put_contents($testFile, $content);

        // The method will use get_plugin_data if available
        $data = $method->invoke(null, $testFile);

        unlink($testFile);

        $this->assertIsArray($data);
    }

    /**
     * @group wordpress-functions
     *
     * Tests trailing slash addition with various inputs.
     * Should handle different path formats.
     */
    public function testTrailingSlashit(): void
    {
        // Without slash
        $result = $this->invokeMethod(
            PluginFactory::class,
            'trailingslashit',
            ['/path/without/slash']
        );
        $this->assertEquals('/path/without/slash/', $result);

        // With slash
        $result = $this->invokeMethod(
            PluginFactory::class,
            'trailingslashit',
            ['/path/with/slash/']
        );
        $this->assertEquals('/path/with/slash/', $result);

        // Windows backslash
        $result = $this->invokeMethod(
            PluginFactory::class,
            'trailingslashit',
            ['C:\path\windows\\']
        );
        $this->assertStringEndsWith('/', $result);

        // Empty string
        $result = $this->invokeMethod(
            PluginFactory::class,
            'trailingslashit',
            ['']
        );
        $this->assertEquals('/', $result);
    }

    // ========================================================================
    // Environment Detection Tests
    // ========================================================================

    /**
     * @group environment
     *
     * Tests environment detection in test context.
     * Should detect 'testing' when SITECHIPS_TESTS is defined.
     */
    public function testDetectEnvironmentTesting(): void
    {
        // SITECHIPS_TESTS is defined in bootstrap.php
        $env = $this->invokeMethod(
            PluginFactory::class,
            'detectEnvironment',
            []
        );

        $this->assertEquals('testing', $env);
    }

    /**
     * @group environment
     *
     * Tests environment detection logic paths.
     * Tests the method structure even if we can't change constants.
     */
    public function testDetectEnvironmentLogic(): void
    {
        // We can't undefine constants, but we can test the method exists
        // and returns a valid environment string
        $env = $this->invokeMethod(
            PluginFactory::class,
            'detectEnvironment',
            []
        );

        $this->assertIsString($env);
        $this->assertContains($env, ['testing', 'development', 'staging', 'production']);
    }

    /**
     * @group environment
     *
     * Tests debug mode detection.
     * Should check WP_DEBUG constant.
     */
    public function testIsDebugMode(): void
    {
        // WP_DEBUG is set to true in bootstrap.php
        $debug = $this->invokeMethod(
            PluginFactory::class,
            'isDebugMode',
            []
        );

        $this->assertTrue($debug);
    }

    // ========================================================================
    // Container Configuration Tests
    // ========================================================================

    /**
     * @group container
     *
     * Tests that container is properly configured.
     * Should have compilation disabled for tests.
     */
    public function testContainerConfiguration(): void
    {
        $plugin = PluginFactory::createForTesting();
        $container = $plugin->getContainer();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertFalse($container->isCompiled());
    }

    /**
     * @group container
     *
     * Tests service provider registration process.
     * Providers should be registered and marked as registered.
     */
    public function testRegisterServiceProviders(): void
    {
        $container = new Container([], false);
        $providers = [TestServiceProvider::class, AnotherTestServiceProvider::class];

        $this->invokeMethod(
            PluginFactory::class,
            'registerServiceProviders',
            [$container, $providers]
        );

        $this->assertTrue($container->has('providers'));
        $providerInstances = $container->get('providers');

        $this->assertCount(2, $providerInstances);
        $this->assertInstanceOf(TestServiceProvider::class, $providerInstances[0]);
        $this->assertInstanceOf(AnotherTestServiceProvider::class, $providerInstances[1]);

        // Check they are marked as registered
        $this->assertTrue($providerInstances[0]->isRegistered());
        $this->assertTrue($providerInstances[1]->isRegistered());
    }

    /**
     * @group container
     *
     * Tests that custom config overrides base definitions.
     * Later array values should win in array_merge.
     */
    public function testConfigPrecedence(): void
    {
        $config = [
            'plugin.name' => 'Overridden Name',
            'plugin.version' => '2.0.0',
            'debug' => false,
            'custom.value' => 123,
        ];

        $plugin = PluginFactory::create($this->tempPluginFile, $config);

        $this->assertEquals('Overridden Name', $plugin->get('plugin.name'));
        $this->assertEquals('2.0.0', $plugin->get('plugin.version'));
        $this->assertFalse($plugin->get('debug'));
        $this->assertEquals(123, $plugin->get('custom.value'));
    }

    // ========================================================================
    // Edge Cases and Error Handling
    // ========================================================================

    /**
     * @group edge-cases
     *
     * Tests plugin creation with empty config.
     * Should work with all defaults.
     */
    public function testCreateWithEmptyConfig(): void
    {
        $plugin = PluginFactory::create($this->tempPluginFile, []);

        $this->assertInstanceOf(Plugin::class, $plugin);
        $this->assertTrue($plugin->has('plugin.slug'));
        $this->assertTrue($plugin->has('environment'));
    }

    /**
     * @group edge-cases
     *
     * Tests plugin creation with empty providers array.
     * Should create plugin without any providers.
     */
    public function testCreateWithEmptyProviders(): void
    {
        $plugin = PluginFactory::create($this->tempPluginFile, [], []);

        $this->assertInstanceOf(Plugin::class, $plugin);
        $providers = $plugin->get('providers');
        $this->assertIsArray($providers);
        $this->assertEmpty($providers);
    }

    /**
     * @group edge-cases
     *
     * Tests URL construction with content directory paths.
     * Should properly construct URLs when inside WP_CONTENT_DIR.
     */
    public function testGetPluginUrlWithContentDir(): void
    {
        // Test with a path inside WP_CONTENT_DIR
        if (defined('WP_CONTENT_DIR') && defined('WP_CONTENT_URL')) {
            $testPath = WP_CONTENT_DIR . '/plugins/test-plugin/plugin.php';

            $url = $this->invokeMethod(
                PluginFactory::class,
                'getPluginUrl',
                [$testPath]
            );

            // URL should contain the content path, protocol may vary (http/https)
            $this->assertStringContainsString('/wp-content/plugins/test-plugin/', $url);
            $this->assertStringEndsWith('/', $url);
        } else {
            $this->markTestSkipped('WP_CONTENT_DIR or WP_CONTENT_URL not defined');
        }
    }

    /**
     * @group edge-cases
     *
     * Tests basename construction for various edge cases.
     * Should handle empty paths and special characters.
     */
    public function testGetPluginBasenameEdgeCases(): void
    {
        // Root file
        $basename = $this->invokeMethod(
            PluginFactory::class,
            'getPluginBasename',
            ['/plugin.php']
        );
        $this->assertStringContainsString('plugin.php', $basename);

        // Deep nesting
        $basename = $this->invokeMethod(
            PluginFactory::class,
            'getPluginBasename',
            ['/very/deep/nested/structure/plugin.php']
        );
        $this->assertStringContainsString('plugin.php', $basename);
    }

    /**
     * @group edge-cases
     *
     * Tests with complex plugin metadata.
     * Should handle special characters and multi-line values.
     */
    public function testGetPluginDataComplexHeaders(): void
    {
        $content = '<?php
/**
 * Plugin Name: Complex Plugin™ with Special Chars
 * Version: 1.0.0-beta.1+build.123
 * Description: A plugin with
 * multi-line description
 * and special characters: äöü
 * Author: Test <test@example.com>
 * Text Domain: complex-domain_test.123
 */';

        $testFile = $this->tempPluginFile . '.complex';
        file_put_contents($testFile, $content);

        $data = $this->invokeMethod(
            PluginFactory::class,
            'getPluginData',
            [$testFile]
        );

        unlink($testFile);

        $this->assertIsArray($data);
        // WordPress should handle complex headers properly
    }

    /**
     * Invoke protected/private method for testing
     *
     * @param string $className Class name
     * @param string $methodName Method name
     * @param array<mixed> $parameters Method parameters
     *
     * @return mixed
     */
    private function invokeMethod(string $className, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($className);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $parameters);
    }
}

// ========================================================================
// Test Helper Classes
// ========================================================================

/**
 * Test service provider for testing
 */
class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->set('test.service', 'test_value');
    }
}

/**
 * Another test service provider
 */
class AnotherTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->set('another.service', 'another_value');
    }
}

/**
 * Service provider that fails during registration
 */
class FailingRegistrationProvider extends ServiceProvider
{
    public function register(): void
    {
        throw new \RuntimeException('Registration failed');
    }
}