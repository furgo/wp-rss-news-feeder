# Testing Guide

> **Write comprehensive tests for your WordPress plugins using modern testing practices**

This guide covers unit testing, integration testing, and testing strategies for WordPress plugins built with Sitechips Core. Learn how to write maintainable tests that give you confidence in your code.

## 🎯 Testing Philosophy

### Why Test WordPress Plugins?

- **Confidence** - Deploy without fear of breaking things
- **Refactoring** - Change code safely with test coverage
- **Documentation** - Tests show how code should be used
- **Regression Prevention** - Bugs don't come back
- **Team Collaboration** - Tests ensure consistency

### Testing Pyramid

```
         /\        Integration Tests (20%)
        /  \       - Plugin with WordPress
       /    \      - Database interactions
      /      \     - API endpoints
     /        \    
    /          \   Unit Tests (70%)
   /            \  - Services
  /              \ - Repositories
 /                \- Business logic
/==================\
    E2E Tests (10%)
    - User workflows
    - Browser testing
```

## 🛠️ Test Environment Setup

### Directory Structure

```
my-plugin/
├── tests/
│   ├── bootstrap.php           # Test bootstrap file
│   ├── TestCase.php           # Base test class
│   ├── Fixtures/              # Test data files
│   │   ├── properties.csv
│   │   ├── import.xml
│   │   └── mock-api-response.json
│   ├── Unit/                  # Unit tests
│   │   ├── Services/
│   │   ├── Repositories/
│   │   └── Models/
│   ├── Integration/           # Integration tests
│   │   ├── Api/
│   │   ├── Import/
│   │   └── Admin/
│   └── Traits/               # Reusable test traits
│       ├── MocksWordPress.php
│       ├── CreatesTestData.php
│       └── AssertsEvents.php
├── phpunit.xml               # PHPUnit configuration
└── .phpunit.result.cache     # PHPUnit cache (gitignored)
```

### PHPUnit Configuration

Create `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit 
    bootstrap="tests/bootstrap.php"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    processIsolation="false"
    stopOnFailure="false"
    verbose="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/Libs</directory>
            <directory>vendor</directory>
        </exclude>
    </coverage>

    <php>
        <const name="SITECHIPS_TESTS" value="true"/>
        <env name="WP_ENVIRONMENT_TYPE" value="testing"/>
    </php>
</phpunit>
```

### Test Bootstrap

Create `tests/bootstrap.php`:

```php
<?php
declare(strict_types=1);

// Define test environment
define('SITECHIPS_TESTS', true);
define('WP_DEBUG', true);

// Load composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mock WordPress functions
require_once __DIR__ . '/wordpress-stubs.php';

// Load test traits
require_once __DIR__ . '/Traits/MocksWordPress.php';
require_once __DIR__ . '/Traits/CreatesTestData.php';
require_once __DIR__ . '/Traits/AssertsEvents.php';

// Create uploads directory for tests
$uploads = sys_get_temp_dir() . '/wordpress-uploads';
if (!is_dir($uploads)) {
    mkdir($uploads, 0777, true);
}
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/wordpress');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
```

### Base Test Case

Create `tests/TestCase.php`:

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Furgo\Sitechips\Core\Plugin\PluginFactory;
use Furgo\Sitechips\Core\Plugin\Plugin;
use Mockery;

abstract class TestCase extends PHPUnitTestCase
{
    use Traits\MocksWordPress;
    use Traits\CreatesTestData;
    use Traits\AssertsEvents;
    
    protected ?Plugin $plugin = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset WordPress globals
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['wpdb'] = $this->createMockWpdb();
    }
    
    protected function tearDown(): void
    {
        // Clean up plugin instance
        if ($this->plugin) {
            // Reset any singleton instances
            if (method_exists($this->plugin, 'reset')) {
                $this->plugin->reset();
            }
        }
        
        // Close Mockery
        Mockery::close();
        
        parent::tearDown();
    }
    
    /**
     * Create a test plugin instance
     */
    protected function createPlugin(array $config = [], array $providers = []): Plugin
    {
        $this->plugin = PluginFactory::createForTesting(
            '/tmp/test-plugin.php',
            array_merge([
                'plugin.name' => 'Test Plugin',
                'plugin.version' => '1.0.0',
                'debug' => true,
            ], $config),
            $providers
        );
        
        return $this->plugin;
    }
    
    /**
     * Get service from container
     */
    protected function getService(string $id): mixed
    {
        if (!$this->plugin) {
            throw new \RuntimeException('Plugin not created. Call createPlugin() first.');
        }
        
        return $this->plugin->get($id);
    }
    
    /**
     * Assert exception with message
     */
    protected function assertExceptionMessage(string $message, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
```

## 🧪 Unit Testing

### Testing Services

```php
<?php
namespace MyPlugin\Tests\Unit\Services;

use MyPlugin\Tests\TestCase;
use MyPlugin\Services\PropertyService;
use MyPlugin\Repositories\PropertyRepository;
use MyPlugin\Services\GeocodeService;
use Psr\Log\LoggerInterface;
use Mockery;

class PropertyServiceTest extends TestCase
{
    private PropertyService $service;
    private $repository;
    private $geocoder;
    private $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->repository = Mockery::mock(PropertyRepository::class);
        $this->geocoder = Mockery::mock(GeocodeService::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        
        // Create service with mocked dependencies
        $this->service = new PropertyService(
            $this->repository,
            $this->geocoder,
            $this->logger
        );
    }
    
    public function testCreatePropertySuccess(): void
    {
        // Arrange
        $data = [
            'title' => 'Test Property',
            'price' => 250000,
            'address' => '123 Main St, New York, NY',
        ];
        
        $coordinates = ['lat' => 40.7128, 'lng' => -74.0060];
        
        // Set expectations
        $this->geocoder->shouldReceive('geocode')
            ->once()
            ->with($data['address'])
            ->andReturn($coordinates);
        
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($arg) use ($data, $coordinates) {
                return $arg['title'] === $data['title']
                    && $arg['price'] === $data['price']
                    && $arg['latitude'] === $coordinates['lat']
                    && $arg['longitude'] === $coordinates['lng'];
            }))
            ->andReturn(123);
        
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Property created', ['id' => 123]);
        
        // Act
        $id = $this->service->create($data);
        
        // Assert
        $this->assertEquals(123, $id);
    }
    
    public function testCreatePropertyWithoutGeocoding(): void
    {
        // Arrange
        $data = [
            'title' => 'Test Property',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ];
        
        // Geocoder should not be called when coordinates provided
        $this->geocoder->shouldNotReceive('geocode');
        
        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn(124);
        
        $this->logger->shouldReceive('info')->once();
        
        // Act
        $id = $this->service->create($data);
        
        // Assert
        $this->assertEquals(124, $id);
    }
    
    public function testCreatePropertyValidationError(): void
    {
        // Arrange
        $data = ['price' => -1000]; // Invalid: missing title, negative price
        
        $this->repository->shouldNotReceive('create');
        $this->logger->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Validation failed/'));
        
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title is required');
        
        $this->service->create($data);
    }
    
    /**
     * @dataProvider searchDataProvider
     */
    public function testSearchProperties(array $criteria, array $expected): void
    {
        $this->repository->shouldReceive('search')
            ->once()
            ->with($criteria)
            ->andReturn($expected);
        
        $results = $this->service->search($criteria);
        
        $this->assertEquals($expected, $results);
    }
    
    public function searchDataProvider(): array
    {
        return [
            'empty criteria' => [
                [],
                ['property1', 'property2'],
            ],
            'price range' => [
                ['min_price' => 100000, 'max_price' => 500000],
                ['property3'],
            ],
            'location search' => [
                ['city' => 'New York', 'bedrooms' => 3],
                ['property4', 'property5'],
            ],
        ];
    }
}
```

### Testing Repositories

```php
<?php
namespace MyPlugin\Tests\Unit\Repositories;

use MyPlugin\Tests\TestCase;
use MyPlugin\Repositories\PropertyRepository;
use MyPlugin\Models\Property;

class PropertyRepositoryTest extends TestCase
{
    private PropertyRepository $repository;
    private $wpdb;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock wpdb
        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->properties = 'wp_properties';
        
        $this->repository = new PropertyRepository($this->wpdb);
    }
    
    public function testFindById(): void
    {
        // Arrange
        $propertyData = (object) [
            'id' => 1,
            'title' => 'Test Property',
            'price' => 250000,
        ];
        
        $this->wpdb->shouldReceive('prepare')
            ->once()
            ->with("SELECT * FROM wp_properties WHERE id = %d", 1)
            ->andReturn("SELECT * FROM wp_properties WHERE id = 1");
        
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->with("SELECT * FROM wp_properties WHERE id = 1")
            ->andReturn($propertyData);
        
        // Act
        $property = $this->repository->find(1);
        
        // Assert
        $this->assertInstanceOf(Property::class, $property);
        $this->assertEquals(1, $property->id);
        $this->assertEquals('Test Property', $property->title);
    }
    
    public function testCreateProperty(): void
    {
        // Arrange
        $data = [
            'title' => 'New Property',
            'price' => 300000,
            'bedrooms' => 3,
        ];
        
        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with('wp_properties', Mockery::on(function($arg) use ($data) {
                return $arg['title'] === $data['title']
                    && $arg['price'] === $data['price']
                    && $arg['bedrooms'] === $data['bedrooms']
                    && isset($arg['created_at']);
            }))
            ->andReturn(1);
        
        $this->wpdb->insert_id = 42;
        
        // Act
        $id = $this->repository->create($data);
        
        // Assert
        $this->assertEquals(42, $id);
    }
    
    public function testUpdateProperty(): void
    {
        // Arrange
        $this->wpdb->shouldReceive('update')
            ->once()
            ->with(
                'wp_properties',
                ['price' => 350000, 'updated_at' => Mockery::any()],
                ['id' => 1]
            )
            ->andReturn(1);
        
        // Act
        $result = $this->repository->update(1, ['price' => 350000]);
        
        // Assert
        $this->assertTrue($result);
    }
    
    public function testDeleteProperty(): void
    {
        // Arrange
        $this->wpdb->shouldReceive('delete')
            ->once()
            ->with('wp_properties', ['id' => 1])
            ->andReturn(1);
        
        // Act
        $result = $this->repository->delete(1);
        
        // Assert
        $this->assertTrue($result);
    }
    
    public function testSearchWithCriteria(): void
    {
        // Arrange
        $criteria = [
            'min_price' => 200000,
            'max_price' => 400000,
            'bedrooms' => 3,
        ];
        
        $expectedSql = "SELECT * FROM wp_properties WHERE price >= %d AND price <= %d AND bedrooms = %d";
        
        $this->wpdb->shouldReceive('prepare')
            ->once()
            ->with($expectedSql, 200000, 400000, 3)
            ->andReturn("SELECT * FROM wp_properties WHERE price >= 200000 AND price <= 400000 AND bedrooms = 3");
        
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn([
                (object) ['id' => 1, 'title' => 'Property 1'],
                (object) ['id' => 2, 'title' => 'Property 2'],
            ]);
        
        // Act
        $results = $this->repository->search($criteria);
        
        // Assert
        $this->assertCount(2, $results);
        $this->assertEquals('Property 1', $results[0]->title);
    }
}
```

### Testing Service Providers

```php
<?php
namespace MyPlugin\Tests\Unit\Providers;

use MyPlugin\Tests\TestCase;
use MyPlugin\Providers\ImportServiceProvider;
use MyPlugin\Services\Importers\CsvImporter;
use MyPlugin\Services\Importers\XmlImporter;

class ImportServiceProviderTest extends TestCase
{
    private ImportServiceProvider $provider;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createPlugin();
        $this->provider = new ImportServiceProvider($this->plugin->getContainer());
    }
    
    public function testRegisterServices(): void
    {
        // Act
        $this->provider->register();
        
        // Assert
        $container = $this->plugin->getContainer();
        $this->assertTrue($container->has('import.csv'));
        $this->assertTrue($container->has('import.xml'));
        $this->assertTrue($container->has('import.manager'));
    }
    
    public function testImporterInstances(): void
    {
        // Arrange
        $this->provider->register();
        
        // Act
        $csvImporter = $this->plugin->get('import.csv');
        $xmlImporter = $this->plugin->get('import.xml');
        
        // Assert
        $this->assertInstanceOf(CsvImporter::class, $csvImporter);
        $this->assertInstanceOf(XmlImporter::class, $xmlImporter);
    }
    
    public function testBootRegistersHooks(): void
    {
        // Arrange
        $this->provider->register();
        
        // Mock WordPress functions
        $this->mockWordPressFunction('add_action', function($hook, $callback) {
            $this->recordAction($hook, $callback);
        });
        
        // Act
        $this->provider->boot();
        
        // Assert
        $this->assertActionAdded('wp_ajax_start_import');
        $this->assertActionAdded('import_process_batch');
    }
}
```

## 🔗 Integration Testing

### Testing with WordPress Functions

```php
<?php
namespace MyPlugin\Tests\Integration;

use MyPlugin\Tests\TestCase;
use MyPlugin\PropertyImporter;

class WordPressIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Boot plugin
        PropertyImporter::boot();
    }
    
    public function testShortcodeRegistration(): void
    {
        // Assert shortcode was registered
        $this->assertTrue(shortcode_exists('property_list'));
        
        // Test shortcode output
        $output = do_shortcode('[property_list limit="5"]');
        $this->assertStringContainsString('<div class="property-list">', $output);
    }
    
    public function testCustomPostTypeRegistration(): void
    {
        // Trigger init action
        do_action('init');
        
        // Assert post type exists
        $this->assertTrue(post_type_exists('property'));
        
        // Check post type properties
        $postType = get_post_type_object('property');
        $this->assertEquals('Properties', $postType->labels->name);
        $this->assertTrue($postType->public);
        $this->assertTrue($postType->has_archive);
    }
    
    public function testAdminMenuRegistration(): void
    {
        // Set admin context
        set_current_screen('dashboard');
        $GLOBALS['current_screen']->in_admin = true;
        
        // Trigger admin menu
        do_action('admin_menu');
        
        // Assert menu exists
        global $menu, $submenu;
        
        $menuFound = false;
        foreach ($menu as $item) {
            if ($item[2] === 'property-importer') {
                $menuFound = true;
                break;
            }
        }
        
        $this->assertTrue($menuFound, 'Admin menu not found');
        $this->assertArrayHasKey('property-importer', $submenu);
    }
}
```

### Testing REST API Endpoints

```php
<?php
namespace MyPlugin\Tests\Integration\Api;

use MyPlugin\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Server;

class PropertyApiTest extends TestCase
{
    private WP_REST_Server $server;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create plugin with API provider
        $this->createPlugin([], [
            \MyPlugin\Providers\ApiServiceProvider::class,
        ]);
        
        // Boot plugin
        $this->plugin->boot();
        
        // Initialize REST server
        $this->server = new WP_REST_Server();
        $GLOBALS['wp_rest_server'] = $this->server;
        
        // Register routes
        do_action('rest_api_init');
    }
    
    public function testGetProperties(): void
    {
        // Arrange
        $request = new WP_REST_Request('GET', '/property-importer/v1/properties');
        $request->set_param('per_page', 5);
        
        // Mock repository
        $repository = Mockery::mock(PropertyRepository::class);
        $repository->shouldReceive('paginate')
            ->once()
            ->with(1, 5)
            ->andReturn([
                'items' => [
                    ['id' => 1, 'title' => 'Property 1'],
                    ['id' => 2, 'title' => 'Property 2'],
                ],
                'total' => 2,
                'pages' => 1,
            ]);
        
        $this->plugin->getContainer()->set('repository.property', $repository);
        
        // Act
        $response = $this->server->dispatch($request);
        
        // Assert
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertCount(2, $data['items']);
        $this->assertEquals('Property 1', $data['items'][0]['title']);
    }
    
    public function testCreateProperty(): void
    {
        // Arrange - authenticate user
        wp_set_current_user(1);
        $user = Mockery::mock('WP_User');
        $user->shouldReceive('has_cap')
            ->with('manage_options')
            ->andReturn(true);
        $GLOBALS['current_user'] = $user;
        
        $request = new WP_REST_Request('POST', '/property-importer/v1/properties');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'title' => 'New Property',
            'price' => 300000,
            'bedrooms' => 3,
        ]));
        
        // Mock service
        $service = Mockery::mock(PropertyService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['title'] === 'New Property'
                    && $data['price'] === 300000;
            }))
            ->andReturn(123);
        
        $this->plugin->getContainer()->set('service.property', $service);
        
        // Act
        $response = $this->server->dispatch($request);
        
        // Assert
        $this->assertEquals(201, $response->get_status());
        $this->assertEquals(['id' => 123], $response->get_data());
    }
    
    public function testUnauthorizedAccess(): void
    {
        // Arrange - no user
        wp_set_current_user(0);
        
        $request = new WP_REST_Request('POST', '/property-importer/v1/properties');
        
        // Act
        $response = $this->server->dispatch($request);
        
        // Assert
        $this->assertEquals(401, $response->get_status());
    }
}
```

### Testing Import Functionality

```php
<?php
namespace MyPlugin\Tests\Integration\Import;

use MyPlugin\Tests\TestCase;
use MyPlugin\Services\Importers\CsvImporter;

class CsvImportTest extends TestCase
{
    private CsvImporter $importer;
    private string $fixturesPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fixturesPath = __DIR__ . '/../../Fixtures/';
        
        // Create plugin with import provider
        $this->createPlugin([], [
            \MyPlugin\Providers\ImportServiceProvider::class,
        ]);
        
        $this->plugin->boot();
        $this->importer = $this->plugin->get('import.csv');
    }
    
    public function testImportValidCsv(): void
    {
        // Arrange
        $file = $this->fixturesPath . 'valid-properties.csv';
        
        // Act
        $result = $this->importer->import($file);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(10, $result->getTotalRows());
        $this->assertEquals(10, $result->getSuccessCount());
        $this->assertEquals(0, $result->getErrorCount());
        
        // Verify properties were created
        $repository = $this->plugin->get('repository.property');
        $properties = $repository->findAll();
        $this->assertCount(10, $properties);
    }
    
    public function testImportWithErrors(): void
    {
        // Arrange
        $file = $this->fixturesPath . 'properties-with-errors.csv';
        
        // Act
        $result = $this->importer->import($file);
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(10, $result->getTotalRows());
        $this->assertEquals(7, $result->getSuccessCount());
        $this->assertEquals(3, $result->getErrorCount());
        
        // Check error details
        $errors = $result->getErrors();
        $this->assertCount(3, $errors);
        $this->assertStringContainsString('Invalid price', $errors[0]['message']);
    }
    
    public function testImportProgress(): void
    {
        // Arrange
        $file = $this->fixturesPath . 'large-import.csv'; // 1000 rows
        $progressUpdates = [];
        
        // Listen to progress events
        add_action('property-importer.import.progress', function($plugin, $progress) use (&$progressUpdates) {
            $progressUpdates[] = $progress;
        }, 10, 2);
        
        // Act
        $result = $this->importer->import($file, ['batch_size' => 100]);
        
        // Assert
        $this->assertCount(10, $progressUpdates); // 1000 rows / 100 batch size
        $this->assertEquals(100, $progressUpdates[0]['processed']);
        $this->assertEquals(1000, $progressUpdates[9]['processed']);
    }
}
```

## 🎭 Mocking Strategies

### WordPress Function Mocks

Create `tests/Traits/MocksWordPress.php`:

```php
<?php
namespace MyPlugin\Tests\Traits;

trait MocksWordPress
{
    private array $mockedFunctions = [];
    private array $actionsCalled = [];
    private array $filtersCalled = [];
    
    protected function mockWordPressFunction(string $function, $returnValue = null): void
    {
        if (!function_exists($function)) {
            $this->mockedFunctions[$function] = $returnValue;
            
            if (is_callable($returnValue)) {
                eval("function $function(...\$args) { 
                    return \$GLOBALS['test_case']->callMockedFunction('$function', \$args); 
                }");
            } else {
                eval("function $function(...\$args) { 
                    return \$GLOBALS['test_case']->getMockedReturn('$function'); 
                }");
            }
            
            $GLOBALS['test_case'] = $this;
        }
    }
    
    public function callMockedFunction(string $function, array $args)
    {
        return call_user_func_array($this->mockedFunctions[$function], $args);
    }
    
    public function getMockedReturn(string $function)
    {
        return $this->mockedFunctions[$function];
    }
    
    protected function createMockWpdb()
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->posts = 'wp_posts';
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->options = 'wp_options';
        
        return $wpdb;
    }
    
    protected function recordAction(string $hook, $callback): void
    {
        $this->actionsCalled[] = ['hook' => $hook, 'callback' => $callback];
    }
    
    protected function assertActionAdded(string $hook): void
    {
        $found = false;
        foreach ($this->actionsCalled as $action) {
            if ($action['hook'] === $hook) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, "Action '$hook' was not added");
    }
    
    protected function assertFilterAdded(string $hook): void
    {
        $found = false;
        foreach ($this->filtersCalled as $filter) {
            if ($filter['hook'] === $hook) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, "Filter '$hook' was not added");
    }
}
```

### Test Data Creation

Create `tests/Traits/CreatesTestData.php`:

```php
<?php
namespace MyPlugin\Tests\Traits;

use Faker\Factory as Faker;

trait CreatesTestData
{
    private $faker;
    
    protected function faker()
    {
        if (!$this->faker) {
            $this->faker = Faker::create();
        }
        return $this->faker;
    }
    
    protected function createTestProperty(array $overrides = []): array
    {
        return array_merge([
            'title' => $this->faker()->sentence(3),
            'description' => $this->faker()->paragraph(),
            'price' => $this->faker()->numberBetween(100000, 1000000),
            'bedrooms' => $this->faker()->numberBetween(1, 5),
            'bathrooms' => $this->faker()->numberBetween(1, 3),
            'area_sqft' => $this->faker()->numberBetween(500, 5000),
            'address' => $this->faker()->streetAddress(),
            'city' => $this->faker()->city(),
            'state' => $this->faker()->stateAbbr(),
            'zip' => $this->faker()->postcode(),
        ], $overrides);
    }
    
    protected function createTestUser(array $overrides = []): object
    {
        return (object) array_merge([
            'ID' => $this->faker()->randomNumber(),
            'user_login' => $this->faker()->userName(),
            'user_email' => $this->faker()->email(),
            'display_name' => $this->faker()->name(),
        ], $overrides);
    }
    
    protected function createCsvFile(array $rows, string $filename = 'test.csv'): string
    {
        $path = sys_get_temp_dir() . '/' . $filename;
        $handle = fopen($path, 'w');
        
        // Write headers
        if (!empty($rows)) {
            fputcsv($handle, array_keys($rows[0]));
        }
        
        // Write data
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        return $path;
    }
    
    protected function createTestImage(string $filename = 'test.jpg'): string
    {
        $path = sys_get_temp_dir() . '/' . $filename;
        
        // Create a simple 100x100 image
        $image = imagecreatetruecolor(100, 100);
        $color = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $path);
        imagedestroy($image);
        
        return $path;
    }
}
```

## 🚀 Continuous Integration

### GitHub Actions

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3']
        wordpress-version: ['6.4', '6.5', 'latest']
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mbstring, xml, ctype, iconv, intl, pdo, pdo_mysql, dom, filter, gd, json
        coverage: xdebug
        tools: composer:v2
    
    - name: Validate composer.json
      run: composer validate --strict
    
    - name: Get Composer Cache Directory
      id: composer-cache
      run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT
    
    - name: Cache Composer dependencies
      uses: actions/cache@v3
      with:
        path: ${{ steps.composer-cache.outputs.dir }}
        key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
        restore-keys: ${{ runner.os }}-composer-
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run PHPUnit tests
      run: vendor/bin/phpunit --coverage-clover coverage.xml
    
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
        flags: unittests
        name: codecov-umbrella
    
    - name: Run PHPStan
      run: vendor/bin/phpstan analyse
    
    - name: Run PHPCS
      run: vendor/bin/phpcs
```

### Local Testing Script

Create `bin/test`:

```bash
#!/usr/bin/env bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🧪 Running Plugin Tests..."

# Run PHPUnit
echo -e "${YELLOW}Running unit tests...${NC}"
vendor/bin/phpunit --testsuite=Unit
UNIT_RESULT=$?

echo -e "${YELLOW}Running integration tests...${NC}"
vendor/bin/phpunit --testsuite=Integration
INTEGRATION_RESULT=$?

# Run static analysis
echo -e "${YELLOW}Running PHPStan...${NC}"
vendor/bin/phpstan analyse
PHPSTAN_RESULT=$?

# Run coding standards
echo -e "${YELLOW}Running PHPCS...${NC}"
vendor/bin/phpcs
PHPCS_RESULT=$?

# Summary
echo ""
echo "📊 Test Summary:"
echo "================"

if [ $UNIT_RESULT -eq 0 ]; then
    echo -e "✅ ${GREEN}Unit Tests: PASSED${NC}"
else
    echo -e "❌ ${RED}Unit Tests: FAILED${NC}"
fi

if [ $INTEGRATION_RESULT -eq 0 ]; then
    echo -e "✅ ${GREEN}Integration Tests: PASSED${NC}"
else
    echo -e "❌ ${RED}Integration Tests: FAILED${NC}"
fi

if [ $PHPSTAN_RESULT -eq 0 ]; then
    echo -e "✅ ${GREEN}PHPStan: PASSED${NC}"
else
    echo -e "❌ ${RED}PHPStan: FAILED${NC}"
fi

if [ $PHPCS_RESULT -eq 0 ]; then
    echo -e "✅ ${GREEN}PHPCS: PASSED${NC}"
else
    echo -e "❌ ${RED}PHPCS: FAILED${NC}"
fi

# Exit with error if any test failed
if [ $UNIT_RESULT -ne 0 ] || [ $INTEGRATION_RESULT -ne 0 ] || [ $PHPSTAN_RESULT -ne 0 ] || [ $PHPCS_RESULT -ne 0 ]; then
    exit 1
fi

echo -e "${GREEN}All tests passed! 🎉${NC}"
```

## 💡 Best Practices

### 1. Test Organization

```php
// ✅ Good: Clear test structure
tests/
├── Unit/
│   ├── Services/        # Business logic
│   ├── Repositories/    # Data access
│   └── Models/         # Domain models
└── Integration/
    ├── Api/            # REST endpoints
    ├── Admin/          # Admin functionality
    └── Import/         # Import processes

// ❌ Bad: Flat structure
tests/
├── PropertyTest.php
├── ImportTest.php
└── ApiTest.php
```

### 2. Test Naming

```php
// ✅ Good: Descriptive test names
public function testCreatePropertyWithValidDataReturnsId(): void
public function testImportCsvWithInvalidHeadersThrowsException(): void
public function testApiRequiresAuthenticationForPostRequests(): void

// ❌ Bad: Vague names
public function testCreate(): void
public function testImport(): void
public function testApi(): void
```

### 3. Test Independence

```php
// ✅ Good: Each test is independent
public function testOne(): void
{
    $service = new Service();
    $result = $service->doSomething();
    $this->assertTrue($result);
}

// ❌ Bad: Tests depend on each other
private static $sharedService;

public function testOne(): void
{
    self::$sharedService = new Service();
    self::$sharedService->initialize();
}

public function testTwo(): void
{
    // Depends on testOne running first!
    $result = self::$sharedService->doSomething();
}
```

### 4. Mock Boundaries

```php
// ✅ Good: Mock external dependencies
public function testServiceWithMockedRepository(): void
{
    $repository = Mockery::mock(RepositoryInterface::class);
    $service = new Service($repository);
}

// ❌ Bad: Mock the system under test
public function testService(): void
{
    $service = Mockery::mock(Service::class); // Don't mock what you're testing!
}
```

### 5. Assertions

```php
// ✅ Good: Specific assertions
$this->assertEquals(200, $response->getStatus());
$this->assertCount(5, $items);
$this->assertInstanceOf(Property::class, $result);
$this->assertStringContainsString('error', $message);

// ❌ Bad: Generic assertions
$this->assertTrue($response->getStatus() == 200);
$this->assertTrue(count($items) == 5);
$this->assertNotNull($result);
```

## 🏁 Summary

Comprehensive testing ensures your WordPress plugin is reliable and maintainable:

- **Unit Tests** - Fast, focused tests for individual components
- **Integration Tests** - Verify components work together
- **Mocking** - Isolate code under test
- **Test Data** - Consistent, realistic test scenarios
- **CI/CD** - Automated testing on every change

Key takeaways:
1. Test early and often
2. Aim for 80%+ code coverage
3. Mock external dependencies
4. Keep tests fast and independent
5. Use CI/CD for consistency

Next steps:
- Master the [Settings API](settings.md)
- Implement [Event-driven architecture](events.md)
- Explore more patterns in the [Cookbook](../cookbook/README.md)

---

Continue to [**Settings API Guide**](settings.md) →