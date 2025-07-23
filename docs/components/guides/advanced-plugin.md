# Advanced Plugin Guide

> **Build a professional WordPress plugin with Service Providers and modular architecture**

This guide demonstrates building a complex WordPress plugin using Sitechips Core's full architecture. We'll create a **Property Import System** that imports real estate listings from multiple sources.

## 🎯 When to Use This Approach

The advanced approach is ideal for:
- ✅ Large plugins (> 1000 lines of code)
- ✅ Team development projects
- ✅ Plugins with multiple features
- ✅ Extensible/modular architecture
- ✅ Enterprise applications

Key benefits over simple approach:
- **Modular** - Features in separate providers
- **Scalable** - Easy to add new features
- **Testable** - Better separation of concerns
- **Reusable** - Share providers between projects
- **Maintainable** - Clear structure and organization

## 📁 Advanced Project Structure

```
property-importer/
├── property-importer.php       # Main plugin file
├── composer.json              # Dependencies + Strauss
├── .strauss.json             # Dependency isolation config
├── Makefile                  # Development commands
│
├── config/                   # Configuration files
│   ├── plugin.php           # Plugin configuration
│   ├── imports.php          # Import sources config
│   └── database.php         # Database settings
│
├── lib/                     # Sitechips Core Framework
│
├── src/
│   ├── Libs/               # Strauss-isolated dependencies
│   │
│   ├── PropertyImporter.php # Service Locator
│   │
│   ├── Providers/          # Service Providers
│   │   ├── CoreServiceProvider.php
│   │   ├── DatabaseServiceProvider.php
│   │   ├── ImportServiceProvider.php
│   │   ├── AdminServiceProvider.php
│   │   ├── ApiServiceProvider.php
│   │   └── SchedulerServiceProvider.php
│   │
│   ├── Models/             # Domain models
│   │   ├── Property.php
│   │   ├── PropertyMeta.php
│   │   └── ImportJob.php
│   │
│   ├── Repositories/       # Data access
│   │   ├── PropertyRepository.php
│   │   ├── ImportJobRepository.php
│   │   └── Contracts/
│   │       └── RepositoryInterface.php
│   │
│   ├── Services/           # Business logic
│   │   ├── Importers/
│   │   │   ├── AbstractImporter.php
│   │   │   ├── XmlImporter.php
│   │   │   ├── CsvImporter.php
│   │   │   └── ApiImporter.php
│   │   ├── PropertyService.php
│   │   ├── GeocodeService.php
│   │   └── NotificationService.php
│   │
│   ├── Http/              # Controllers & API
│   │   ├── Controllers/
│   │   │   ├── AdminController.php
│   │   │   └── ImportController.php
│   │   └── Api/
│   │       ├── PropertyEndpoint.php
│   │       └── ImportEndpoint.php
│   │
│   └── Views/             # Admin UI components
│       ├── AdminPage.php
│       ├── ImportSettings.php
│       └── Components/
│           ├── PropertyTable.php
│           └── ImportProgress.php
│
├── templates/             # View templates
├── assets/               # CSS/JS files
├── languages/            # Translations
│
└── tests/               # Comprehensive tests
    ├── Unit/
    ├── Integration/
    └── Fixtures/
```

## 🚀 Step 1: Project Setup

### composer.json with Strauss

```json
{
    "name": "mycompany/property-importer",
    "description": "Professional property import system for WordPress",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.1",
        "guzzlehttp/guzzle": "^7.5",
        "league/csv": "^9.0",
        "nesbot/carbon": "^2.0",
        "monolog/monolog": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "mockery/mockery": "^1.6",
        "fakerphp/faker": "^1.21"
    },
    "autoload": {
        "psr-4": {
            "Furgo\\Sitechips\\Core\\": "lib/",
            "PropertyImporter\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "PropertyImporter\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "strauss": "vendor/bin/strauss",
        "post-install-cmd": ["@strauss"],
        "post-update-cmd": ["@strauss"],
        "test": "vendor/bin/phpunit",
        "test:coverage": "vendor/bin/phpunit --coverage-html coverage"
    }
}
```

### .strauss.json for Dependency Isolation

```json
{
    "target_directory": "src/Libs",
    "namespace_prefix": "PropertyImporter\\Libs\\",
    "classmap_prefix": "PropertyImporter_",
    "packages": [
        "guzzlehttp/guzzle",
        "league/csv",
        "nesbot/carbon",
        "monolog/monolog",
        "psr/log",
        "psr/http-message"
    ],
    "exclude_from_prefix": {
        "namespaces": ["Furgo\\Sitechips\\Core\\"]
    },
    "delete_vendor_packages": true
}
```

## 🏗️ Step 2: Service Locator

Create `src/PropertyImporter.php`:

```php
<?php
declare(strict_types=1);

namespace PropertyImporter;

use Furgo\Sitechips\Core\Plugin\AbstractServiceLocator;
use Furgo\Sitechips\Core\Plugin\Plugin;
use Furgo\Sitechips\Core\Plugin\PluginFactory;

/**
 * Property Importer Service Locator
 * 
 * Provides static access to plugin services for WordPress integration.
 */
class PropertyImporter extends AbstractServiceLocator
{
    /**
     * Setup plugin with configuration and providers
     */
    protected static function setupPlugin(): Plugin
    {
        $config = require dirname(__DIR__) . '/config/plugin.php';
        
        return PluginFactory::create(
            dirname(__DIR__) . '/property-importer.php',
            $config,
            [
                // Core functionality
                Providers\CoreServiceProvider::class,
                Providers\DatabaseServiceProvider::class,
                
                // Features
                Providers\ImportServiceProvider::class,
                Providers\SchedulerServiceProvider::class,
                Providers\ApiServiceProvider::class,
                
                // UI
                Providers\AdminServiceProvider::class,
            ]
        );
    }
    
    /**
     * Quick access to property repository
     */
    public static function properties(): Repositories\PropertyRepository
    {
        return static::get('repository.property');
    }
    
    /**
     * Quick access to import service
     */
    public static function importer(): Services\Importers\ImportManager
    {
        return static::get('import.manager');
    }
}
```

## 📦 Step 3: Core Service Provider

Create `src/Providers/CoreServiceProvider.php`:

```php
<?php
declare(strict_types=1);

namespace PropertyImporter\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;
use Furgo\Sitechips\Core\Services\EventManager;
use PropertyImporter\Libs\Monolog\Logger;
use PropertyImporter\Libs\Monolog\Handler\StreamHandler;
use PropertyImporter\Services\GeocodeService;
use PropertyImporter\Services\NotificationService;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register core services
     */
    public function register(): void
    {
        $this->registerLogger();
        $this->registerEventManager();
        $this->registerCoreServices();
    }
    
    /**
     * Boot core functionality
     */
    public function boot(): void
    {
        // Load text domain
        $this->addAction('init', [$this, 'loadTextDomain']);
        
        // Register activation/deactivation handlers
        register_activation_hook(
            $this->container->get('plugin.file'),
            [$this, 'onActivation']
        );
        
        register_deactivation_hook(
            $this->container->get('plugin.file'),
            [$this, 'onDeactivation']
        );
    }
    
    private function registerLogger(): void
    {
        $this->shared('logger', function($container) {
            $logger = new Logger('property-importer');
            
            // Log to custom file
            $logPath = $container->get('path.logs') . 'importer.log';
            $logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
            
            // Also log errors to WordPress debug.log
            if ($container->get('debug')) {
                $logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));
            }
            
            return $logger;
        });
        
        // PSR-3 interface binding
        $this->alias(\Psr\Log\LoggerInterface::class, 'logger');
    }
    
    private function registerEventManager(): void
    {
        $this->shared('events', function($container) {
            return new EventManager(
                $container->get('plugin.slug'),
                true // WordPress integration
            );
        });
    }
    
    private function registerCoreServices(): void
    {
        // Geocoding service
        $this->shared('service.geocode', function($container) {
            return new GeocodeService(
                $container->get('http.client'),
                $container->get('cache'),
                $container->get('logger')
            );
        });
        
        // Notification service
        $this->shared('service.notification', function($container) {
            return new NotificationService(
                $container->get('events'),
                $container->get('logger'),
                $container->get('config.notifications')
            );
        });
        
        // HTTP client (Guzzle)
        $this->shared('http.client', function($container) {
            return new \PropertyImporter\Libs\GuzzleHttp\Client([
                'timeout' => $container->get('http.timeout', 30),
                'verify' => $container->get('http.verify_ssl', true),
            ]);
        });
        
        // Cache instance
        $this->shared('cache', function($container) {
            // Use WordPress transients for caching
            return new \PropertyImporter\Services\TransientCache(
                $container->get('cache.prefix', 'pi_')
            );
        });
    }
    
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'property-importer',
            false,
            dirname($this->container->get('plugin.basename')) . '/languages'
        );
    }
    
    public function onActivation(): void
    {
        $this->container->get('plugin')->dispatch('activating');
        
        // Create necessary directories
        $this->createDirectories();
        
        // Set default options
        $this->setDefaultOptions();
        
        $this->container->get('plugin')->dispatch('activated');
    }
    
    public function onDeactivation(): void
    {
        $this->container->get('plugin')->dispatch('deactivating');
        
        // Clear scheduled tasks
        wp_clear_scheduled_hook('property_importer_daily_sync');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        $this->container->get('plugin')->dispatch('deactivated');
    }
    
    private function createDirectories(): void
    {
        $directories = [
            $this->container->get('path.logs'),
            $this->container->get('path.imports'),
            $this->container->get('path.temp'),
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    private function setDefaultOptions(): void
    {
        add_option('property_importer_settings', [
            'import_batch_size' => 50,
            'geocode_enabled' => true,
            'notification_email' => get_option('admin_email'),
        ]);
    }
}
```

## 💾 Step 4: Database Provider

Create `src/Providers/DatabaseServiceProvider.php`:

```php
<?php
declare(strict_types=1);

namespace PropertyImporter\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;
use PropertyImporter\Repositories\PropertyRepository;
use PropertyImporter\Repositories\ImportJobRepository;
use PropertyImporter\Services\PropertyService;

class DatabaseServiceProvider extends ServiceProvider
{
    private array $tables = [
        'properties' => 'properties',
        'property_meta' => 'property_meta',
        'import_jobs' => 'import_jobs',
        'import_logs' => 'import_logs',
    ];
    
    public function register(): void
    {
        $this->registerTableNames();
        $this->registerRepositories();
        $this->registerServices();
    }
    
    public function boot(): void
    {
        // Create tables on activation
        add_action('property-importer.activating', [$this, 'createTables']);
        
        // Register custom post type
        $this->addAction('init', [$this, 'registerPropertyPostType']);
    }
    
    private function registerTableNames(): void
    {
        global $wpdb;
        
        foreach ($this->tables as $key => $table) {
            $this->container->set(
                "table.$key",
                $wpdb->prefix . 'pi_' . $table
            );
        }
    }
    
    private function registerRepositories(): void
    {
        $this->shared('repository.property', function($container) {
            global $wpdb;
            
            return new PropertyRepository(
                $wpdb,
                $container->get('table.properties'),
                $container->get('table.property_meta'),
                $container->get('logger')
            );
        });
        
        $this->shared('repository.import_job', function($container) {
            global $wpdb;
            
            return new ImportJobRepository(
                $wpdb,
                $container->get('table.import_jobs'),
                $container->get('table.import_logs')
            );
        });
    }
    
    private function registerServices(): void
    {
        $this->shared('service.property', function($container) {
            return new PropertyService(
                $container->get('repository.property'),
                $container->get('service.geocode'),
                $container->get('events'),
                $container->get('logger')
            );
        });
    }
    
    public function createTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset = $wpdb->get_charset_collate();
        
        // Properties table
        $sql = "CREATE TABLE {$this->container->get('table.properties')} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reference_id varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            price decimal(10,2),
            property_type varchar(50),
            bedrooms int,
            bathrooms int,
            area_sqft int,
            address text,
            city varchar(100),
            state varchar(100),
            zip varchar(20),
            country varchar(2) DEFAULT 'US',
            latitude decimal(10,7),
            longitude decimal(10,7),
            status varchar(20) DEFAULT 'active',
            import_source varchar(50),
            import_job_id bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY reference_id (reference_id),
            KEY status (status),
            KEY import_source (import_source),
            KEY city_state (city, state),
            KEY property_type (property_type)
        ) $charset;";
        
        dbDelta($sql);
        
        // Property meta table
        $sql = "CREATE TABLE {$this->container->get('table.property_meta')} (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255),
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY property_id (property_id),
            KEY meta_key (meta_key)
        ) $charset;";
        
        dbDelta($sql);
        
        // Import jobs table
        $sql = "CREATE TABLE {$this->container->get('table.import_jobs')} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            total_items int DEFAULT 0,
            processed_items int DEFAULT 0,
            success_items int DEFAULT 0,
            error_items int DEFAULT 0,
            started_at datetime,
            completed_at datetime,
            configuration longtext,
            error_log longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY source (source)
        ) $charset;";
        
        dbDelta($sql);
        
        $this->container->get('logger')->info('Database tables created successfully');
    }
    
    public function registerPropertyPostType(): void
    {
        register_post_type('property', [
            'labels' => [
                'name' => __('Properties', 'property-importer'),
                'singular_name' => __('Property', 'property-importer'),
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-admin-home',
            'rewrite' => ['slug' => 'properties'],
        ]);
    }
}
```

## 📥 Step 5: Import System

Create `src/Providers/ImportServiceProvider.php`:

```php
<?php
declare(strict_types=1);

namespace PropertyImporter\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;
use PropertyImporter\Services\Importers\ImportManager;
use PropertyImporter\Services\Importers\XmlImporter;
use PropertyImporter\Services\Importers\CsvImporter;
use PropertyImporter\Services\Importers\ApiImporter;

class ImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Import manager
        $this->shared('import.manager', function($container) {
            $manager = new ImportManager(
                $container->get('repository.import_job'),
                $container->get('events'),
                $container->get('logger')
            );
            
            // Register importers
            $manager->registerImporter('xml', $container->get('import.xml'));
            $manager->registerImporter('csv', $container->get('import.csv'));
            $manager->registerImporter('api', $container->get('import.api'));
            
            return $manager;
        });
        
        // XML Importer
        $this->shared('import.xml', function($container) {
            return new XmlImporter(
                $container->get('service.property'),
                $container->get('logger')
            );
        });
        
        // CSV Importer
        $this->shared('import.csv', function($container) {
            return new CsvImporter(
                $container->get('service.property'),
                $container->get('logger')
            );
        });
        
        // API Importer
        $this->shared('import.api', function($container) {
            return new ApiImporter(
                $container->get('service.property'),
                $container->get('http.client'),
                $container->get('logger')
            );
        });
    }
    
    public function boot(): void
    {
        // AJAX handlers for imports
        $this->addAction('wp_ajax_pi_start_import', [$this, 'handleStartImport']);
        $this->addAction('wp_ajax_pi_import_status', [$this, 'handleImportStatus']);
        
        // Background processing
        $this->addAction('property_importer_process_batch', [$this, 'processBatch']);
        
        // CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('property import', [$this, 'cliImport']);
        }
    }
    
    public function handleStartImport(): void
    {
        check_ajax_referer('property_import', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $source = sanitize_text_field($_POST['source'] ?? '');
        $file = $_FILES['import_file'] ?? null;
        
        try {
            $manager = $this->container->get('import.manager');
            $jobId = $manager->startImport($source, [
                'file' => $file,
                'batch_size' => 50,
            ]);
            
            // Schedule background processing
            wp_schedule_single_event(time(), 'property_importer_process_batch', [$jobId]);
            
            wp_send_json_success(['job_id' => $jobId]);
            
        } catch (\Exception $e) {
            $this->container->get('logger')->error('Import failed: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function processBatch(int $jobId): void
    {
        $manager = $this->container->get('import.manager');
        
        try {
            $result = $manager->processBatch($jobId, 50);
            
            if (!$result['completed']) {
                // Schedule next batch
                wp_schedule_single_event(
                    time() + 1,
                    'property_importer_process_batch',
                    [$jobId]
                );
            } else {
                // Import completed
                $this->container->get('service.notification')
                    ->sendImportComplete($jobId);
            }
            
        } catch (\Exception $e) {
            $this->container->get('logger')->error(
                'Batch processing failed',
                ['job_id' => $jobId, 'error' => $e->getMessage()]
            );
        }
    }
}
```

## 🎨 Step 6: Admin Interface

Create `src/Providers/AdminServiceProvider.php`:

```php
<?php
declare(strict_types=1);

namespace PropertyImporter\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;
use Furgo\Sitechips\Core\Services\AssetManager;
use PropertyImporter\Http\Controllers\AdminController;
use PropertyImporter\Http\Controllers\ImportController;
use PropertyImporter\Views\AdminPage;
use PropertyImporter\Views\ImportSettings;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin controllers
        $this->shared('controller.admin', function($container) {
            return new AdminController(
                $container->get('repository.property'),
                $container->get('repository.import_job'),
                $container->get('views.admin_page')
            );
        });
        
        $this->shared('controller.import', function($container) {
            return new ImportController(
                $container->get('import.manager'),
                $container->get('views.import_settings')
            );
        });
        
        // Views
        $this->shared('views.admin_page', function($container) {
            return new AdminPage($container->get('plugin'));
        });
        
        $this->shared('views.import_settings', function($container) {
            return new ImportSettings($container->get('plugin'));
        });
        
        // Asset manager
        $this->shared('assets.admin', function($container) {
            return new AssetManager(
                $container->get('plugin.url'),
                $container->get('plugin.version')
            );
        });
    }
    
    public function boot(): void
    {
        if (!is_admin()) {
            return;
        }
        
        $this->addAction('admin_menu', [$this, 'registerAdminMenu']);
        $this->addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        $this->addAction('admin_notices', [$this, 'displayAdminNotices']);
        
        // Dashboard widget
        $this->addAction('wp_dashboard_setup', [$this, 'registerDashboardWidget']);
    }
    
    public function registerAdminMenu(): void
    {
        // Main menu
        add_menu_page(
            __('Property Importer', 'property-importer'),
            __('Properties', 'property-importer'),
            'manage_options',
            'property-importer',
            [$this->container->get('controller.admin'), 'index'],
            'dashicons-admin-home',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'property-importer',
            __('Import Properties', 'property-importer'),
            __('Import', 'property-importer'),
            'manage_options',
            'property-import',
            [$this->container->get('controller.import'), 'index']
        );
        
        add_submenu_page(
            'property-importer',
            __('Import History', 'property-importer'),
            __('History', 'property-importer'),
            'manage_options',
            'property-import-history',
            [$this->container->get('controller.import'), 'history']
        );
        
        add_submenu_page(
            'property-importer',
            __('Settings', 'property-importer'),
            __('Settings', 'property-importer'),
            'manage_options',
            'property-importer-settings',
            [$this->container->get('controller.admin'), 'settings']
        );
    }
    
    public function enqueueAdminAssets(string $hook): void
    {
        // Only on our pages
        if (!str_contains($hook, 'property-importer') && !str_contains($hook, 'property-import')) {
            return;
        }
        
        $assets = $this->container->get('assets.admin');
        
        // Core assets
        $assets->addScript('property-admin', 'assets/js/admin.js', ['jquery', 'wp-api'])
               ->localizeScript('property-admin', 'PropertyImporter', [
                   'ajaxUrl' => admin_url('admin-ajax.php'),
                   'apiUrl' => rest_url('property-importer/v1'),
                   'nonce' => wp_create_nonce('property_import'),
                   'strings' => [
                       'importing' => __('Importing...', 'property-importer'),
                       'completed' => __('Import completed!', 'property-importer'),
                       'error' => __('Import failed', 'property-importer'),
                   ]
               ]);
        
        $assets->addStyle('property-admin', 'assets/css/admin.css');
        
        // Page-specific assets
        if ($hook === 'properties_page_property-import') {
            $assets->addScript('property-import', 'assets/js/import.js', ['property-admin'])
                   ->addStyle('property-import', 'assets/css/import.css');
        }
        
        $assets->enqueue();
    }
    
    public function registerDashboardWidget(): void
    {
        wp_add_dashboard_widget(
            'property_importer_stats',
            __('Property Import Stats', 'property-importer'),
            [$this, 'renderDashboardWidget']
        );
    }
    
    public function renderDashboardWidget(): void
    {
        $repository = $this->container->get('repository.property');
        $stats = $repository->getStats();
        
        echo '<div class="property-stats">';
        echo '<p><strong>' . __('Total Properties:', 'property-importer') . '</strong> ' . $stats['total'] . '</p>';
        echo '<p><strong>' . __('Last Import:', 'property-importer') . '</strong> ' . $stats['last_import'] . '</p>';
        echo '<p><strong>' . __('Active Listings:', 'property-importer') . '</strong> ' . $stats['active'] . '</p>';
        echo '</div>';
        
        echo '<p class="property-actions">';
        echo '<a href="' . admin_url('admin.php?page=property-import') . '" class="button">';
        echo __('Import Properties', 'property-importer');
        echo '</a>';
        echo '</p>';
    }
}
```

## 🌐 Step 7: REST API

Create `src/Providers/ApiServiceProvider.php`:

```php
<?php
declare(strict_types=1);

namespace PropertyImporter\Providers;

use Furgo\Sitechips\Core\Container\ServiceProvider;
use PropertyImporter\Http\Api\PropertyEndpoint;
use PropertyImporter\Http\Api\ImportEndpoint;

class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('api.property', function($container) {
            return new PropertyEndpoint(
                $container->get('repository.property'),
                $container->get('service.property')
            );
        });
        
        $this->shared('api.import', function($container) {
            return new ImportEndpoint(
                $container->get('import.manager'),
                $container->get('repository.import_job')
            );
        });
    }
    
    public function boot(): void
    {
        $this->addAction('rest_api_init', [$this, 'registerEndpoints']);
    }
    
    public function registerEndpoints(): void
    {
        $namespace = 'property-importer/v1';
        
        // Property endpoints
        register_rest_route($namespace, '/properties', [
            [
                'methods' => 'GET',
                'callback' => [$this->container->get('api.property'), 'index'],
                'permission_callback' => '__return_true',
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'default' => 10,
                        'sanitize_callback' => 'absint',
                    ],
                    'search' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this->container->get('api.property'), 'create'],
                'permission_callback' => [$this, 'canManageProperties'],
            ],
        ]);
        
        register_rest_route($namespace, '/properties/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this->container->get('api.property'), 'show'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this->container->get('api.property'), 'update'],
                'permission_callback' => [$this, 'canManageProperties'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this->container->get('api.property'), 'delete'],
                'permission_callback' => [$this, 'canManageProperties'],
            ],
        ]);
        
        // Import endpoints
        register_rest_route($namespace, '/import/start', [
            'methods' => 'POST',
            'callback' => [$this->container->get('api.import'), 'start'],
            'permission_callback' => [$this, 'canManageProperties'],
        ]);
        
        register_rest_route($namespace, '/import/status/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this->container->get('api.import'), 'status'],
            'permission_callback' => [$this, 'canManageProperties'],
        ]);
    }
    
    public function canManageProperties(): bool
    {
        return current_user_can('manage_options');
    }
}
```

## 🧪 Step 8: Comprehensive Testing

Create `tests/Unit/Services/PropertyServiceTest.php`:

```php
<?php
namespace PropertyImporter\Tests\Unit\Services;

use PropertyImporter\Services\PropertyService;
use PropertyImporter\Repositories\PropertyRepository;
use PropertyImporter\Services\GeocodeService;
use Furgo\Sitechips\Core\Services\EventManager;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class PropertyServiceTest extends TestCase
{
    private PropertyService $service;
    private $repository;
    private $geocoder;
    private $events;
    private $logger;
    
    protected function setUp(): void
    {
        $this->repository = Mockery::mock(PropertyRepository::class);
        $this->geocoder = Mockery::mock(GeocodeService::class);
        $this->events = Mockery::mock(EventManager::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        
        $this->service = new PropertyService(
            $this->repository,
            $this->geocoder,
            $this->events,
            $this->logger
        );
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
    }
    
    public function testCreatePropertyWithGeocoding(): void
    {
        $propertyData = [
            'title' => 'Test Property',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
        ];
        
        $coordinates = ['lat' => 40.7128, 'lng' => -74.0060];
        
        // Expectations
        $this->geocoder->shouldReceive('geocode')
            ->once()
            ->with('123 Main St, New York, NY 10001')
            ->andReturn($coordinates);
        
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) use ($coordinates) {
                return $data['latitude'] === $coordinates['lat']
                    && $data['longitude'] === $coordinates['lng'];
            }))
            ->andReturn(123);
        
        $this->events->shouldReceive('dispatch')
            ->once()
            ->with('property.created', Mockery::any());
        
        $this->logger->shouldReceive('info')
            ->once()
            ->with('Property created', ['id' => 123]);
        
        // Act
        $id = $this->service->create($propertyData);
        
        // Assert
        $this->assertEquals(123, $id);
    }
}
```

Create `tests/Integration/ImportTest.php`:

```php
<?php
namespace PropertyImporter\Tests\Integration;

use PropertyImporter\PropertyImporter;
use PropertyImporter\Tests\TestCase;

class ImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset plugin
        PropertyImporter::reset();
        
        // Boot plugin
        PropertyImporter::boot();
    }
    
    public function testCsvImport(): void
    {
        $csvContent = <<<CSV
reference_id,title,price,bedrooms,bathrooms,address,city,state,zip
PROP001,"Beautiful Home",350000,3,2,"123 Main St","New York","NY","10001"
PROP002,"Luxury Condo",550000,2,2,"456 Park Ave","New York","NY","10002"
CSV;
        
        $file = $this->createTempFile($csvContent, 'properties.csv');
        
        $importer = PropertyImporter::importer();
        $jobId = $importer->startImport('csv', ['file' => $file]);
        
        // Process synchronously for testing
        $result = $importer->processAll($jobId);
        
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['success']);
        $this->assertEquals(0, $result['errors']);
        
        // Verify properties were created
        $repository = PropertyImporter::properties();
        $properties = $repository->findAll();
        
        $this->assertCount(2, $properties);
        $this->assertEquals('Beautiful Home', $properties[0]->title);
    }
    
    private function createTempFile(string $content, string $name): string
    {
        $path = sys_get_temp_dir() . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }
}
```

## 🚀 Step 9: Main Plugin File

Create `property-importer.php`:

```php
<?php
/**
 * Plugin Name: Property Importer Pro
 * Plugin URI: https://example.com/property-importer
 * Description: Professional property import system with multiple source support
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Your Company
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: property-importer
 * Domain Path: /languages
 */

declare(strict_types=1);

use PropertyImporter\PropertyImporter;

// Prevent direct access
defined('ABSPATH') || exit;

// Check requirements
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Property Importer:</strong> This plugin requires PHP 8.1 or higher.';
        echo '</p></div>';
    });
    return;
}

// Autoloader check
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Property Importer:</strong> Dependencies missing. Run <code>composer install</code>.';
        echo '</p></div>';
    });
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Initialize plugin
add_action('plugins_loaded', function(): void {
    try {
        PropertyImporter::boot();
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-error"><p><strong>Property Importer Error:</strong> %s</p></div>',
                esc_html($e->getMessage())
            );
        });
        
        // Log error
        if (function_exists('error_log')) {
            error_log('Property Importer: ' . $e->getMessage());
        }
    }
}, 5);

// Cleanup on uninstall
register_uninstall_hook(__FILE__, function() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }
    
    // Clean up options
    delete_option('property_importer_settings');
    
    // Optionally clean up data
    if (get_option('property_importer_delete_data_on_uninstall')) {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'pi_properties',
            $wpdb->prefix . 'pi_property_meta',
            $wpdb->prefix . 'pi_import_jobs',
            $wpdb->prefix . 'pi_import_logs',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
});
```

## 📋 Configuration Files

Create `config/plugin.php`:

```php
<?php
return [
    // Paths
    'path.imports' => WP_CONTENT_DIR . '/uploads/property-imports/',
    'path.logs' => WP_CONTENT_DIR . '/property-importer-logs/',
    'path.temp' => sys_get_temp_dir() . '/property-importer/',
    
    // Import settings
    'import.batch_size' => 50,
    'import.timeout' => 300,
    'import.memory_limit' => '256M',
    
    // API settings
    'api.rate_limit' => 100,
    'api.cache_ttl' => 3600,
    
    // Geocoding
    'geocode.provider' => 'openstreetmap',
    'geocode.cache_ttl' => 86400 * 30, // 30 days
    
    // Notifications
    'notifications.admin_email' => get_option('admin_email'),
    'notifications.slack_webhook' => $_ENV['SLACK_WEBHOOK'] ?? null,
    
    // HTTP settings
    'http.timeout' => 30,
    'http.verify_ssl' => true,
    
    // Cache settings
    'cache.prefix' => 'pi_',
    'cache.default_ttl' => 3600,
];
```

## 💡 Advanced Patterns

### Feature Flags

```php
// In config
'features' => [
    'geocoding' => true,
    'image_processing' => true,
    'ai_descriptions' => false,
    'bulk_export' => true,
],

// In ServiceProvider
public function register(): void
{
    if ($this->container->get('features.ai_descriptions')) {
        $this->shared('service.ai', AiService::class);
    }
}
```

### Multi-tenancy Support

```php
class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->shared('tenant.manager', function($container) {
            return new TenantManager(
                $container->get('repository.tenant'),
                $container->get('cache')
            );
        });
    }
    
    public function boot(): void
    {
        // Scope queries by tenant
        $this->addFilter('property_query_args', function($args) {
            $tenant = $this->container->get('tenant.manager')->current();
            if ($tenant) {
                $args['meta_query'][] = [
                    'key' => 'tenant_id',
                    'value' => $tenant->id,
                ];
            }
            return $args;
        });
    }
}
```

## 🎯 Best Practices

### 1. Service Provider Organization
- One provider per feature/domain
- Keep providers focused and small
- Use descriptive names

### 2. Dependency Management
- Always use Strauss for external packages
- Keep WordPress dependencies minimal
- Document required PHP extensions

### 3. Error Handling
- Log all errors with context
- Provide user-friendly messages
- Implement retry mechanisms

### 4. Performance
- Use caching strategically
- Implement background processing
- Optimize database queries

### 5. Security
- Validate all inputs
- Use nonces for forms
- Implement capability checks
- Sanitize outputs

## 🏁 Conclusion

You've built a professional WordPress plugin using:
- ✅ **Service Providers** for modular architecture
- ✅ **Strauss** for dependency isolation
- ✅ **Repository Pattern** for data access
- ✅ **Service Layer** for business logic
- ✅ **REST API** for modern integration
- ✅ **Background Processing** for performance
- ✅ **Comprehensive Testing** for quality

This architecture scales to handle:
- Large codebases (10,000+ lines)
- Multiple developers
- Complex business requirements
- High-traffic sites

Next steps:
- Explore [Service Provider](service-providers.md) patterns
- Implement comprehensive [Testing](testing.md)
- Add cross-plugin [Events](events.md)
- Study the [Cookbook](../cookbook/README.md) for more patterns

---

Continue to [**Service Providers Guide**](service-providers.md) →