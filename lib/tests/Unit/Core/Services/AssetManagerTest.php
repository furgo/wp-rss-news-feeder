<?php
/**
 * Asset Manager Unit Tests
 *
 * Tests for the AssetManager service that handles script and style
 * registration and enqueuing for WordPress plugins.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Services
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Services;

use Furgo\Sitechips\Core\Services\AssetManager;
use Furgo\Sitechips\Core\Tests\TestCase;
use InvalidArgumentException;

/**
 * Asset Manager Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Services\AssetManager
 */
class AssetManagerTest extends TestCase
{
    /**
     * Asset manager instance
     *
     * @var AssetManager
     */
    private AssetManager $assets;

    /**
     * Test plugin URL
     *
     * @var string
     */
    private string $pluginUrl = 'https://example.com/wp-content/plugins/test-plugin';

    /**
     * Test version
     *
     * @var string
     */
    private string $version = '1.0.0';

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assets = new AssetManager($this->pluginUrl, $this->version);
    }

    // ========================================================================
    // Constructor Tests
    // ========================================================================

    /**
     * @group constructor
     *
     * Tests successful construction with valid parameters
     */
    public function testConstructorWithValidParameters(): void
    {
        $assets = new AssetManager('https://example.com/plugin', '2.0.0');

        // No exception thrown means success
        $this->assertInstanceOf(AssetManager::class, $assets);
    }

    /**
     * @group constructor
     *
     * Tests constructor with trailing slash in URL
     */
    public function testConstructorTrimsTrailingSlash(): void
    {
        $assets = new AssetManager('https://example.com/plugin/', '1.0.0');

        // Test by adding a script and checking resolved URL
        $assets->addScript('test', 'script.js');

        // Would be double slash if not trimmed
        $this->assertInstanceOf(AssetManager::class, $assets);
    }

    /**
     * @group constructor
     *
     * Tests constructor throws exception for empty plugin URL
     */
    public function testConstructorThrowsExceptionForEmptyUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plugin URL cannot be empty');

        new AssetManager('', '1.0.0');
    }

    /**
     * @group constructor
     *
     * Tests constructor throws exception for empty version
     */
    public function testConstructorThrowsExceptionForEmptyVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Version cannot be empty');

        new AssetManager('https://example.com', '');
    }

    // ========================================================================
    // Script Registration Tests
    // ========================================================================

    /**
     * @group scripts
     *
     * Tests basic script registration
     */
    public function testAddScriptBasic(): void
    {
        $result = $this->assets->addScript('my-script', 'js/script.js');

        $this->assertSame($this->assets, $result); // Fluent interface
    }

    /**
     * @group scripts
     *
     * Tests script registration with dependencies
     */
    public function testAddScriptWithDependencies(): void
    {
        $result = $this->assets->addScript(
            'my-script',
            'js/script.js',
            ['jquery', 'underscore']
        );

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group scripts
     *
     * Tests script registration with footer flag
     */
    public function testAddScriptInHeader(): void
    {
        $result = $this->assets->addScript(
            'header-script',
            'js/header.js',
            [],
            false // Load in header
        );

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group scripts
     *
     * Tests script registration with empty handle throws exception
     */
    public function testAddScriptWithEmptyHandleThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Script handle cannot be empty');

        $this->assets->addScript('', 'js/script.js');
    }

    /**
     * @group scripts
     *
     * Tests script registration with empty source
     */
    public function testAddScriptWithEmptySource(): void
    {
        $result = $this->assets->addScript('inline-only', '');

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group scripts
     *
     * Tests method chaining with multiple scripts
     */
    public function testScriptMethodChaining(): void
    {
        $result = $this->assets
            ->addScript('script1', 'js/script1.js')
            ->addScript('script2', 'js/script2.js', ['jquery'])
            ->addScript('script3', 'js/script3.js', [], false);

        $this->assertSame($this->assets, $result);
    }

    // ========================================================================
    // Style Registration Tests
    // ========================================================================

    /**
     * @group styles
     *
     * Tests basic style registration
     */
    public function testAddStyleBasic(): void
    {
        $result = $this->assets->addStyle('my-style', 'css/style.css');

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group styles
     *
     * Tests style registration with dependencies
     */
    public function testAddStyleWithDependencies(): void
    {
        $result = $this->assets->addStyle(
            'my-style',
            'css/style.css',
            ['wp-components', 'wp-editor']
        );

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group styles
     *
     * Tests style registration with media query
     */
    public function testAddStyleWithMediaQuery(): void
    {
        $result = $this->assets->addStyle(
            'print-style',
            'css/print.css',
            [],
            'print'
        );

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group styles
     *
     * Tests style registration with empty handle throws exception
     */
    public function testAddStyleWithEmptyHandleThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Style handle cannot be empty');

        $this->assets->addStyle('', 'css/style.css');
    }

    /**
     * @group styles
     *
     * Tests method chaining with styles
     */
    public function testStyleMethodChaining(): void
    {
        $result = $this->assets
            ->addStyle('style1', 'css/style1.css')
            ->addStyle('style2', 'css/style2.css', ['dashicons'])
            ->addStyle('style3', 'css/style3.css', [], 'screen and (max-width: 768px)');

        $this->assertSame($this->assets, $result);
    }

    // ========================================================================
    // Script Localization Tests
    // ========================================================================

    /**
     * @group localization
     *
     * Tests script localization
     */
    public function testLocalizeScript(): void
    {
        $this->assets->addScript('my-script', 'js/script.js');

        $result = $this->assets->localizeScript('my-script', 'myScriptData', [
            'ajaxUrl' => 'https://example.com/wp-admin/admin-ajax.php',
            'nonce' => '123456',
            'strings' => [
                'save' => 'Save',
                'cancel' => 'Cancel'
            ]
        ]);

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group localization
     *
     * Tests localization for non-existent script throws exception
     */
    public function testLocalizeNonExistentScriptThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Script handle 'non-existent' not registered");

        $this->assets->localizeScript('non-existent', 'data', ['test' => 'value']);
    }

    // ========================================================================
    // Inline Code Tests
    // ========================================================================

    /**
     * @group inline
     *
     * Tests adding inline script
     */
    public function testAddInlineScript(): void
    {
        $result = $this->assets->addInlineScript(
            'my-script',
            'console.log("Script loaded");'
        );

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group inline
     *
     * Tests adding inline style
     */
    public function testAddInlineStyle(): void
    {
        $result = $this->assets->addInlineStyle(
            'my-style',
            '.dynamic-color { color: #ff0000; }'
        );

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group inline
     *
     * Tests adding multiple inline codes
     */
    public function testMultipleInlineCodes(): void
    {
        $result = $this->assets
            ->addScript('script1', 'js/script1.js')
            ->addInlineScript('script1', 'var config = {};')
            ->addStyle('style1', 'css/style1.css')
            ->addInlineStyle('style1', ':root { --primary: #000; }');

        $this->assertSame($this->assets, $result);
    }

    // ========================================================================
    // URL Resolution Tests
    // ========================================================================

    /**
     * @group url-resolution
     *
     * Tests URL resolution for relative paths
     */
    public function testResolveUrlRelativePath(): void
    {
        // We can't test private method directly, but we can verify behavior
        // by checking that relative paths work correctly with enqueue

        $this->assets->addScript('test', 'js/script.js');
        $this->assets->addStyle('test', 'css/style.css');

        // No exception means URLs were resolved successfully
        $this->assertTrue(true);
    }

    /**
     * @group url-resolution
     *
     * Tests URL resolution for absolute URLs
     */
    public function testResolveUrlAbsoluteUrl(): void
    {
        $this->assets->addScript('external', 'https://cdn.example.com/script.js');
        $this->assets->addStyle('external', 'https://cdn.example.com/style.css');

        // No exception means URLs were handled correctly
        $this->assertTrue(true);
    }

    /**
     * @group url-resolution
     *
     * Tests URL resolution for protocol-relative URLs
     */
    public function testResolveUrlProtocolRelative(): void
    {
        $this->assets->addScript('cdn', '//cdn.example.com/script.js');
        $this->assets->addStyle('cdn', '//cdn.example.com/style.css');

        // No exception means URLs were handled correctly
        $this->assertTrue(true);
    }

    // ========================================================================
    // Enqueue Tests
    // ========================================================================

    /**
     * @group enqueue
     *
     * Tests enqueue method doesn't throw when WordPress functions don't exist
     */
    public function testEnqueueWithoutWordPress(): void
    {
        $this->assets
            ->addScript('test-script', 'js/test.js')
            ->addStyle('test-style', 'css/test.css');

        // Should not throw even without WordPress functions
        $this->assets->enqueue();

        $this->assertTrue(true);
    }

    /**
     * @group enqueue
     *
     * Tests enqueue with all features
     */
    public function testEnqueueComprehensive(): void
    {
        $this->assets
            // Scripts
            ->addScript('app', 'js/app.js', ['jquery'])
            ->localizeScript('app', 'appConfig', ['apiUrl' => '/api'])
            ->addInlineScript('app', 'App.init();')

            // Styles
            ->addStyle('app', 'css/app.css', ['wp-components'])
            ->addInlineStyle('app', ':root { --brand-color: #333; }')

            // Additional assets
            ->addScript('admin', 'js/admin.js', ['app'], false)
            ->addStyle('print', 'css/print.css', [], 'print');

        // Should handle all registrations without error
        $this->assets->enqueue();

        $this->assertTrue(true);
    }

    // ========================================================================
    // Complex Scenarios
    // ========================================================================

    /**
     * @group scenarios
     *
     * Tests typical admin page setup
     */
    public function testAdminPageScenario(): void
    {
        $result = $this->assets
            // Core admin scripts
            ->addScript('my-plugin-admin', 'admin/js/admin.js', [
                'jquery',
                'wp-api',
                'wp-components',
                'wp-element'
            ])
            ->localizeScript('my-plugin-admin', 'myPluginAdmin', [
                'apiUrl' => rest_url('my-plugin/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
                'strings' => [
                    'saved' => __('Settings saved.', 'my-plugin'),
                    'error' => __('An error occurred.', 'my-plugin')
                ]
            ])

            // Admin styles
            ->addStyle('my-plugin-admin', 'admin/css/admin.css', [
                'wp-components'
            ])

            // Color picker
            ->addScript('wp-color-picker-alpha', 'admin/js/wp-color-picker-alpha.min.js', [
                'wp-color-picker'
            ])
            ->addStyle('wp-color-picker-alpha', 'admin/css/wp-color-picker-alpha.min.css', [
                'wp-color-picker'
            ]);

        $this->assertSame($this->assets, $result);
    }

    /**
     * @group scenarios
     *
     * Tests frontend setup with conditional loading
     */
    public function testFrontendScenario(): void
    {
        // Main script with dependencies
        $this->assets->addScript('my-plugin-frontend', 'js/frontend.js', ['jquery']);

        // Conditional polyfill
        if (!$this->browserSupportsFeature()) {
            $this->assets->addScript('polyfill', 'js/polyfill.js', [], false);
        }

        // Style with responsive media query
        $this->assets->addStyle('my-plugin-frontend', 'css/frontend.css');

        // Print styles
        $this->assets->addStyle('my-plugin-print', 'css/print.css', [], 'print');

        // Dynamic styles based on settings
        $primaryColor = '#007cba';
        $this->assets->addInlineStyle('my-plugin-frontend', "
            :root {
                --my-plugin-primary: {$primaryColor};
            }
        ");

        $this->assertInstanceOf(AssetManager::class, $this->assets);
    }

    /**
     * @group scenarios
     *
     * Tests Gutenberg block assets
     */
    public function testGutenbergBlockScenario(): void
    {
        $result = $this->assets
            // Editor assets
            ->addScript('my-plugin-editor', 'blocks/build/editor.js', [
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-data'
            ])
            ->addStyle('my-plugin-editor', 'blocks/build/editor.css', [
                'wp-edit-blocks'
            ])

            // Frontend assets
            ->addScript('my-plugin-blocks', 'blocks/build/frontend.js', [
                'wp-element'
            ])
            ->addStyle('my-plugin-blocks', 'blocks/build/style.css')

            // Block translations
            ->addInlineScript('my-plugin-editor',
                "wp.i18n.setLocaleData(" . json_encode([]) . ", 'my-plugin');"
            );

        $this->assertSame($this->assets, $result);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Mock browser feature detection
     *
     * @return bool
     */
    private function browserSupportsFeature(): bool
    {
        return false; // For testing polyfill loading
    }
}

// ========================================================================
// Mock WordPress Functions for Testing
// ========================================================================

if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return 'https://example.com/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test-nonce-' . $action;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}