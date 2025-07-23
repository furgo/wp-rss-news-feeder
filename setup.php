#!/usr/bin/env php
<?php
/**
 * Sitechips Boilerplate Setup Script
 *
 * Transforms the Sitechips Boilerplate into a custom WordPress plugin
 * by updating namespaces, plugin headers, and configuration.
 *
 * @package     Furgo\Sitechips\Setup
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Colors for terminal output
const COLOR_RESET = "\033[0m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_CYAN = "\033[36m";
const COLOR_RED = "\033[31m";

/**
 * Main setup class
 */
class PluginSetup
{
    private array $config = [];
    private string $baseDir;
    private string $backupDir;

    public function __construct()
    {
        $this->baseDir = dirname(__FILE__);
    }

    /**
     * Run the setup process
     */
    public function run(): void
    {
        $this->printHeader();
        $this->checkRequirements();
        $this->checkGitStatus();
        $this->createBackup();
        $this->gatherInformation();
        $this->confirmChanges();
        $this->processFiles();
        $this->printNextSteps();
    }

    /**
     * Print welcome header
     */
    private function printHeader(): void
    {
        echo COLOR_CYAN . "\n";
        echo "=========================================\n";
        echo "  Sitechips Boilerplate Setup Script\n";
        echo "=========================================\n";
        echo COLOR_RESET . "\n";
        echo "This script will help you transform the Sitechips Boilerplate\n";
        echo "into your custom WordPress plugin.\n\n";

        echo COLOR_RED . "⚠️  IMPORTANT: This script will modify files in place!\n" . COLOR_RESET;
        echo COLOR_YELLOW . "   Make sure to commit your changes or work on a copy.\n\n" . COLOR_RESET;
    }

    /**
     * Check basic requirements
     */
    private function checkRequirements(): void
    {
        // Check if this is the boilerplate
        if (!file_exists($this->baseDir . '/sitechips-boilerplate.php')) {
            $this->error("This doesn't appear to be the Sitechips Boilerplate directory.");
        }

        // Check if already customized
        if (file_exists($this->baseDir . '/.setup-complete')) {
            $this->error("Setup has already been run on this plugin.");
        }

        echo COLOR_GREEN . "✓ Requirements check passed\n\n" . COLOR_RESET;
    }

    /**
     * Check Git status and warn about uncommitted changes
     */
    private function checkGitStatus(): void
    {
        if (is_dir($this->baseDir . '/.git')) {
            exec('cd ' . escapeshellarg($this->baseDir) . ' && git status --porcelain 2>&1', $output, $returnCode);

            if ($returnCode === 0 && !empty($output)) {
                echo COLOR_YELLOW . "⚠ WARNING: You have uncommitted changes!\n" . COLOR_RESET;
                echo "It's recommended to commit your changes before running this setup.\n\n";
                echo "Continue anyway? (y/n): ";
                $confirm = strtolower(trim(fgets(STDIN)));

                if ($confirm !== 'y' && $confirm !== 'yes') {
                    echo "\nSetup cancelled. Please commit your changes first:\n";
                    echo COLOR_GREEN . "  git add .\n";
                    echo "  git commit -m \"Before plugin customization\"\n" . COLOR_RESET;
                    exit(0);
                }
            }
        } else {
            echo COLOR_YELLOW . "⚠ WARNING: This is not a Git repository!\n" . COLOR_RESET;
            echo "It's highly recommended to use version control.\n\n";
            echo "Continue without Git? (y/n): ";
            $confirm = strtolower(trim(fgets(STDIN)));

            if ($confirm !== 'y' && $confirm !== 'yes') {
                echo "\nSetup cancelled. Initialize Git first:\n";
                echo COLOR_GREEN . "  git init\n";
                echo "  git add .\n";
                echo "  git commit -m \"Initial boilerplate\"\n" . COLOR_RESET;
                exit(0);
            }
        }
    }

    /**
     * Create backup of current state
     */
    private function createBackup(): void
    {
        echo "Creating backup...\n";

        $timestamp = date('Y-m-d_H-i-s');
        $this->backupDir = $this->baseDir . '/.backup-' . $timestamp;

        // Create backup directory
        if (!mkdir($this->backupDir, 0755, true)) {
            $this->error("Failed to create backup directory.");
        }

        // Files to backup
        $filesToBackup = [
            'sitechips-boilerplate.php',
            'src/SitechipsBoilerplate.php',
            'composer.json',
            '.strauss.json',
            'config/plugin.php',
            'config/admin-settings-page.php'
        ];

        // Add all provider files
        $providerFiles = glob($this->baseDir . '/src/Providers/*.php');
        foreach ($providerFiles as $file) {
            $filesToBackup[] = str_replace($this->baseDir . '/', '', $file);
        }

        // Create backup
        foreach ($filesToBackup as $file) {
            $source = $this->baseDir . '/' . $file;
            if (file_exists($source)) {
                $dest = $this->backupDir . '/' . $file;
                $destDir = dirname($dest);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($source, $dest);
            }
        }

        // Save current config for rollback
        file_put_contents($this->backupDir . '/setup-config.json', json_encode([
            'timestamp' => $timestamp,
            'original_files' => $filesToBackup
        ], JSON_PRETTY_PRINT));

        echo COLOR_GREEN . "✓ Backup created in " . basename($this->backupDir) . "\n\n" . COLOR_RESET;
    }

    /**
     * Gather information from user
     */
    private function gatherInformation(): void
    {
        echo COLOR_YELLOW . "Please provide the following information:\n" . COLOR_RESET;
        echo "Press Enter to use the [default] value.\n\n";

        // Plugin name
        $this->config['plugin_name'] = $this->ask(
            'Plugin Name (e.g., "My Awesome Plugin")',
            'My Plugin'
        );

        // Plugin slug (for file/folder names)
        $defaultSlug = $this->generateSlug($this->config['plugin_name']);
        $this->config['plugin_slug'] = $this->ask(
            'Plugin Slug (for files/folders)',
            $defaultSlug
        );

        // Validate slug
        if (!preg_match('/^[a-z0-9-]+$/', $this->config['plugin_slug'])) {
            $this->error("Plugin slug must contain only lowercase letters, numbers, and hyphens.");
        }

        // Text domain (usually same as slug)
        $this->config['text_domain'] = $this->ask(
            'Text Domain (for translations)',
            $this->config['plugin_slug']
        );

        // Vendor name (for composer.json)
        $this->config['vendor'] = $this->ask(
            'Vendor Name (for composer.json, e.g., "mycompany")',
            'mycompany'
        );

        // Validate vendor name
        if (!preg_match('/^[a-z0-9-]+$/', $this->config['vendor'])) {
            $this->error("Vendor name must contain only lowercase letters, numbers, and hyphens.");
        }

        // Namespace
        $defaultNamespace = $this->generateNamespace($this->config['vendor'], $this->config['plugin_name']);
        $this->config['namespace'] = $this->ask(
            'PHP Namespace (e.g., "MyCompany\\MyPlugin")',
            $defaultNamespace
        );

        // Validate namespace
        if (!preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$/', $this->config['namespace'])) {
            $this->error("Invalid namespace format. Use format like 'MyCompany\\MyPlugin'.");
        }

        // Plugin description
        $this->config['description'] = $this->ask(
            'Plugin Description',
            'A custom WordPress plugin built with Sitechips Core framework.'
        );

        // Author name
        $this->config['author'] = $this->ask(
            'Author Name',
            'Your Name'
        );

        // Author URI
        $this->config['author_uri'] = $this->ask(
            'Author URI (optional)',
            ''
        );

        // Plugin URI
        $this->config['plugin_uri'] = $this->ask(
            'Plugin URI (optional)',
            ''
        );

        // Main class name
        $this->config['main_class'] = $this->generateClassName($this->config['plugin_name']);

        // Calculate file name
        $this->config['plugin_file'] = $this->config['plugin_slug'] . '.php';

        // Strauss prefix (based on namespace)
        $this->config['strauss_prefix'] = str_replace('\\', '', $this->config['namespace']);
    }

    /**
     * Ask user for input
     */
    private function ask(string $question, string $default = ''): string
    {
        $defaultText = $default ? " [{$default}]" : '';
        echo COLOR_CYAN . $question . $defaultText . ': ' . COLOR_RESET;

        $input = trim(fgets(STDIN));
        return $input ?: $default;
    }

    /**
     * Confirm changes before proceeding
     */
    private function confirmChanges(): void
    {
        echo "\n" . COLOR_YELLOW . "Summary of changes:\n" . COLOR_RESET;
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Plugin Name:    " . $this->config['plugin_name'] . "\n";
        echo "Plugin File:    " . $this->config['plugin_file'] . "\n";
        echo "Text Domain:    " . $this->config['text_domain'] . "\n";
        echo "Vendor Name:    " . $this->config['vendor'] . "\n";
        echo "Namespace:      " . $this->config['namespace'] . "\n";
        echo "Main Class:     " . $this->config['main_class'] . "\n";
        echo "Composer Name:  " . $this->config['vendor'] . '/' . $this->config['plugin_slug'] . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        echo COLOR_YELLOW . "Continue with these settings? (y/n): " . COLOR_RESET;
        $confirm = strtolower(trim(fgets(STDIN)));

        if ($confirm !== 'y' && $confirm !== 'yes') {
            echo "\nSetup cancelled.\n";
            exit(0);
        }
    }

    /**
     * Process all files
     */
    private function processFiles(): void
    {
        echo "\n" . COLOR_GREEN . "Processing files...\n" . COLOR_RESET;

        // Rename main plugin file
        $this->renameMainFile();

        // Update file contents
        $this->updateMainPluginFile();
        $this->updateServiceLocatorFile();
        $this->updateComposerJson();
        $this->updateStraussConfig();
        $this->updateConfigFiles();
        $this->updateProviderFiles();
        $this->updateTestFiles();

        // Create marker file
        file_put_contents($this->baseDir . '/.setup-complete', date('Y-m-d H:i:s'));

        echo COLOR_GREEN . "\n✓ All files have been updated successfully!\n" . COLOR_RESET;
    }

    /**
     * Rename main plugin file
     */
    private function renameMainFile(): void
    {
        $oldFile = $this->baseDir . '/sitechips-boilerplate.php';
        $newFile = $this->baseDir . '/' . $this->config['plugin_file'];

        if ($oldFile !== $newFile) {
            rename($oldFile, $newFile);
            echo "  ✓ Renamed main plugin file\n";
        }
    }

    /**
     * Update main plugin file
     */
    private function updateMainPluginFile(): void
    {
        $file = $this->baseDir . '/' . $this->config['plugin_file'];
        $content = file_get_contents($file);

        // Update plugin header
        $content = preg_replace(
            '/Plugin Name:.*/',
            'Plugin Name: ' . $this->config['plugin_name'],
            $content
        );

        $content = preg_replace(
            '/Description:.*/',
            'Description: ' . $this->config['description'],
            $content
        );

        $content = preg_replace(
            '/Text Domain:.*/',
            'Text Domain: ' . $this->config['text_domain'],
            $content
        );

        $content = preg_replace(
            '/Author:.*/',
            'Author: ' . $this->config['author'],
            $content
        );

        if ($this->config['author_uri']) {
            $content = preg_replace(
                '/Author URI:.*/',
                'Author URI: ' . $this->config['author_uri'],
                $content
            );
        }

        if ($this->config['plugin_uri']) {
            $content = preg_replace(
                '/Plugin URI:.*/',
                'Plugin URI: ' . $this->config['plugin_uri'],
                $content
            );
        }

        // Update namespace usage
        $content = str_replace(
            'use Furgo\SitechipsBoilerplate\SitechipsBoilerplate;',
            'use ' . $this->config['namespace'] . '\\' . $this->config['main_class'] . ';',
            $content
        );

        // Update class references
        $content = str_replace(
            'SitechipsBoilerplate::',
            $this->config['main_class'] . '::',
            $content
        );

        // Update package name
        $content = str_replace(
            '@package Furgo\SitechipsBoilerplate',
            '@package ' . $this->config['namespace'],
            $content
        );

        // Update settings page slug
        $content = str_replace(
            'page=sitechips-boilerplate',
            'page=' . $this->config['plugin_slug'],
            $content
        );

        file_put_contents($file, $content);
        echo "  ✓ Updated main plugin file\n";
    }

    /**
     * Update service locator file
     */
    private function updateServiceLocatorFile(): void
    {
        $oldFile = $this->baseDir . '/src/SitechipsBoilerplate.php';
        $newFile = $this->baseDir . '/src/' . $this->config['main_class'] . '.php';

        $content = file_get_contents($oldFile);

        // Update namespace
        $content = str_replace(
            'namespace Furgo\SitechipsBoilerplate;',
            'namespace ' . $this->config['namespace'] . ';',
            $content
        );

        // Update class name
        $content = str_replace(
            'class SitechipsBoilerplate',
            'class ' . $this->config['main_class'],
            $content
        );

        // Update use statements
        $content = str_replace(
            'use Furgo\SitechipsBoilerplate\Providers',
            'use ' . $this->config['namespace'] . '\Providers',
            $content
        );

        // Update plugin file path
        $content = str_replace(
            '/sitechips-boilerplate.php',
            '/' . $this->config['plugin_file'],
            $content
        );

        // Update text domain
        $content = str_replace(
            "'sitechips-boilerplate'",
            "'" . $this->config['text_domain'] . "'",
            $content
        );

        // Update option names
        $content = str_replace(
            'sitechips_boilerplate_',
            str_replace('-', '_', $this->config['plugin_slug']) . '_',
            $content
        );

        // Update package
        $content = str_replace(
            '@package     Furgo\SitechipsBoilerplate',
            '@package     ' . $this->config['namespace'],
            $content
        );

        // Update class comments
        $content = str_replace(
            'Sitechips Boilerplate',
            $this->config['plugin_name'],
            $content
        );

        file_put_contents($newFile, $content);

        if ($oldFile !== $newFile) {
            unlink($oldFile);
        }

        echo "  ✓ Updated service locator class\n";
    }

    /**
     * Update composer.json
     */
    private function updateComposerJson(): void
    {
        $file = $this->baseDir . '/composer.json';
        $json = json_decode(file_get_contents($file), true);

        // Update name with vendor
        $json['name'] = $this->config['vendor'] . '/' . $this->config['plugin_slug'];

        // Update description
        $json['description'] = $this->config['description'];

        // Update authors
        $json['authors'] = [
            [
                'name' => $this->config['author'],
                'homepage' => $this->config['author_uri'] ?: null
            ]
        ];

        // Update autoload namespaces
        $json['autoload']['psr-4'] = [
            'Furgo\\Sitechips\\Core\\' => 'lib/',
            $this->config['namespace'] . '\\' => 'src/'
        ];

        $json['autoload-dev']['psr-4'] = [
            'Furgo\\Sitechips\\Core\\Tests\\' => 'tests/',
            $this->config['namespace'] . '\\Tests\\' => 'tests/'
        ];

        file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "  ✓ Updated composer.json\n";
    }

    /**
     * Update Strauss configuration
     */
    private function updateStraussConfig(): void
    {
        $file = $this->baseDir . '/.strauss.json';
        if (!file_exists($file)) {
            echo "  ⚠ .strauss.json not found, skipping\n";
            return;
        }

        $json = json_decode(file_get_contents($file), true);

        // Update namespace prefix
        $json['namespace_prefix'] = $this->config['namespace'] . '\\Libs\\';

        // Update classmap prefix (first 3 letters of plugin name, uppercase)
        $prefix = strtoupper(substr($this->config['strauss_prefix'], 0, 3)) . '_';
        $json['classmap_prefix'] = $prefix;
        $json['constant_prefix'] = $prefix;

        file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "  ✓ Updated .strauss.json\n";
    }

    /**
     * Update config files
     */
    private function updateConfigFiles(): void
    {
        // Update config/plugin.php
        $file = $this->baseDir . '/config/plugin.php';
        if (file_exists($file)) {
            $content = file_get_contents($file);

            $content = str_replace(
                "'text_domain' => 'sitechips-boilerplate'",
                "'text_domain' => '" . $this->config['text_domain'] . "'",
                $content
            );

            $content = str_replace(
                'sitechips_boilerplate_',
                str_replace('-', '_', $this->config['plugin_slug']) . '_',
                $content
            );

            $content = str_replace(
                'sitechips-boilerplate',
                $this->config['plugin_slug'],
                $content
            );

            $content = str_replace(
                'SitechipsBoilerplate/',
                $this->config['main_class'] . '/',
                $content
            );

            file_put_contents($file, $content);
            echo "  ✓ Updated config/plugin.php\n";
        }

        // Update config/admin-settings-page.php
        $file = $this->baseDir . '/config/admin-settings-page.php';
        if (file_exists($file)) {
            $content = file_get_contents($file);

            $content = str_replace(
                'sitechips_boilerplate_',
                str_replace('-', '_', $this->config['plugin_slug']) . '_',
                $content
            );

            $content = str_replace(
                'sitechips-boilerplate',
                $this->config['plugin_slug'],
                $content
            );

            $content = str_replace(
                'Sitechips Boilerplate',
                $this->config['plugin_name'],
                $content
            );

            file_put_contents($file, $content);
            echo "  ✓ Updated config/admin-settings-page.php\n";
        }
    }

    /**
     * Update provider files
     */
    private function updateProviderFiles(): void
    {
        $files = glob($this->baseDir . '/src/Providers/*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Update namespace
            $content = str_replace(
                'namespace Furgo\SitechipsBoilerplate\Providers;',
                'namespace ' . $this->config['namespace'] . '\Providers;',
                $content
            );

            // Update package
            $content = str_replace(
                '@package     Furgo\SitechipsBoilerplate\Providers',
                '@package     ' . $this->config['namespace'] . '\Providers',
                $content
            );

            // Update text domain
            $content = str_replace(
                "'sitechips-boilerplate'",
                "'" . $this->config['text_domain'] . "'",
                $content
            );

            // Update option names
            $content = str_replace(
                'sitechips_boilerplate_',
                str_replace('-', '_', $this->config['plugin_slug']) . '_',
                $content
            );

            // Update shortcode names
            $content = str_replace(
                'sitechips_hello',
                str_replace('-', '_', $this->config['plugin_slug']) . '_hello',
                $content
            );

            file_put_contents($file, $content);
        }

        echo "  ✓ Updated provider files\n";
    }

    /**
     * Update test files
     */
    private function updateTestFiles(): void
    {
        $files = glob($this->baseDir . '/tests/**/*.php', GLOB_BRACE);

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Update namespace
            $content = str_replace(
                'namespace Furgo\SitechipsBoilerplate\Tests',
                'namespace ' . $this->config['namespace'] . '\Tests',
                $content
            );

            $content = str_replace(
                'use Furgo\SitechipsBoilerplate\\',
                'use ' . $this->config['namespace'] . '\\',
                $content
            );

            // Update package
            $content = str_replace(
                '@package     Furgo\SitechipsBoilerplate\Tests',
                '@package     ' . $this->config['namespace'] . '\Tests',
                $content
            );

            file_put_contents($file, $content);
        }

        echo "  ✓ Updated test files\n";
    }

    /**
     * Print next steps
     */
    private function printNextSteps(): void
    {
        echo "\n" . COLOR_CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  Next Steps\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . COLOR_RESET;

        echo "\n1. " . COLOR_YELLOW . "IMPORTANT: Rename the plugin directory:" . COLOR_RESET . "\n";
        echo "   Current directory: " . COLOR_RED . "sitechips-boilerplate" . COLOR_RESET . "\n";
        echo "   Should be renamed to: " . COLOR_GREEN . $this->config['plugin_slug'] . COLOR_RESET . "\n";
        echo "   " . COLOR_CYAN . "cd .." . COLOR_RESET . "\n";
        echo "   " . COLOR_CYAN . "mv sitechips-boilerplate " . $this->config['plugin_slug'] . COLOR_RESET . "\n";
        echo "   " . COLOR_CYAN . "cd " . $this->config['plugin_slug'] . COLOR_RESET . "\n";

        echo "\n2. " . COLOR_YELLOW . "Update configuration files:" . COLOR_RESET . "\n";
        echo "   - Edit config/plugin.php for plugin-wide settings\n";
        echo "   - Edit config/admin-settings-page.php for admin settings\n";

        echo "\n3. " . COLOR_YELLOW . "Install dependencies:" . COLOR_RESET . "\n";
        echo "   " . COLOR_GREEN . "composer install" . COLOR_RESET . "\n";

        echo "\n4. " . COLOR_YELLOW . "Initialize Git repository:" . COLOR_RESET . "\n";
        echo "   " . COLOR_GREEN . "git init" . COLOR_RESET . "\n";
        echo "   " . COLOR_GREEN . "git add ." . COLOR_RESET . "\n";
        echo "   " . COLOR_GREEN . "git commit -m \"Initial commit\"" . COLOR_RESET . "\n";

        echo "\n5. " . COLOR_YELLOW . "Run tests to ensure everything works:" . COLOR_RESET . "\n";
        echo "   " . COLOR_GREEN . "composer test" . COLOR_RESET . "\n";

        echo "\n6. " . COLOR_YELLOW . "Delete this setup script:" . COLOR_RESET . "\n";
        echo "   " . COLOR_GREEN . "rm setup.php" . COLOR_RESET . "\n";

        echo "\n" . COLOR_GREEN . "Your plugin is ready for development!" . COLOR_RESET . "\n";

        echo "\n" . COLOR_CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  Rollback Option\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" . COLOR_RESET;

        echo "\nIf something went wrong, you can rollback:\n";
        echo COLOR_GREEN . "  php setup.php rollback" . COLOR_RESET . "\n";
        echo "\nBackup stored in: " . COLOR_YELLOW . basename($this->backupDir) . COLOR_RESET . "\n\n";
    }

    /**
     * Generate slug from plugin name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Generate namespace from vendor and plugin name
     */
    private function generateNamespace(string $vendor, string $name): string
    {
        // Vendor part
        $vendorParts = preg_split('/[^a-zA-Z0-9]+/', $vendor);
        $vendorParts = array_map('ucfirst', array_filter($vendorParts));
        $vendorNamespace = implode('', $vendorParts);

        // Plugin part
        $pluginParts = preg_split('/[^a-zA-Z0-9]+/', $name);
        $pluginParts = array_map('ucfirst', array_filter($pluginParts));
        $pluginNamespace = implode('', $pluginParts);

        return $vendorNamespace . '\\' . $pluginNamespace;
    }

    /**
     * Generate class name from plugin name
     */
    private function generateClassName(string $name): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $name);
        $parts = array_map('ucfirst', array_filter($parts));
        return implode('', $parts);
    }

    /**
     * Display error and exit
     */
    private function error(string $message): void
    {
        echo COLOR_RED . "\n✗ Error: " . $message . COLOR_RESET . "\n\n";
        exit(1);
    }
}

// Check for rollback command
if (isset($argv[1]) && $argv[1] === 'rollback') {
    echo COLOR_YELLOW . "\nAvailable backups:\n" . COLOR_RESET;
    $backups = glob(__DIR__ . '/.backup-*');

    if (empty($backups)) {
        echo COLOR_RED . "No backups found.\n" . COLOR_RESET;
        exit(1);
    }

    foreach ($backups as $index => $backup) {
        $config = json_decode(file_get_contents($backup . '/setup-config.json'), true);
        echo sprintf("%d) %s (created: %s)\n",
            $index + 1,
            basename($backup),
            $config['timestamp']
        );
    }

    echo "\nSelect backup to restore (number): ";
    $selection = (int) trim(fgets(STDIN)) - 1;

    if (!isset($backups[$selection])) {
        echo COLOR_RED . "Invalid selection.\n" . COLOR_RESET;
        exit(1);
    }

    $backupDir = $backups[$selection];
    $config = json_decode(file_get_contents($backupDir . '/setup-config.json'), true);

    echo "\nRestoring from " . basename($backupDir) . "...\n";

    // Restore files
    foreach ($config['original_files'] as $file) {
        $source = $backupDir . '/' . $file;
        $dest = __DIR__ . '/' . $file;

        if (file_exists($source)) {
            // Delete current file if it exists
            if (file_exists($dest)) {
                unlink($dest);
            }

            // Restore from backup
            copy($source, $dest);
            echo "  ✓ Restored " . $file . "\n";
        }
    }

    // Remove setup complete marker
    if (file_exists(__DIR__ . '/.setup-complete')) {
        unlink(__DIR__ . '/.setup-complete');
    }

    // Remove new files that were created during setup
    $newFiles = glob(__DIR__ . '/src/*.php');
    foreach ($newFiles as $file) {
        $baseName = basename($file);
        if ($baseName !== 'SitechipsBoilerplate.php' && !file_exists($backupDir . '/src/' . $baseName)) {
            unlink($file);
            echo "  ✓ Removed " . $baseName . "\n";
        }
    }

    echo COLOR_GREEN . "\n✓ Rollback completed!\n" . COLOR_RESET;
    echo "\nYou may want to remove the backup:\n";
    echo COLOR_YELLOW . "  rm -rf " . basename($backupDir) . "\n" . COLOR_RESET;
    exit(0);
}

// Run the setup
$setup = new PluginSetup();
$setup->run();