<?php
namespace Furgo\Sitechips\Core\Tests\Integration;

use Furgo\Sitechips\Core\Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Log-File leeren
        if (defined('SITECHIPS_TEST_LOG_FILE') && file_exists(SITECHIPS_TEST_LOG_FILE)) {
            file_put_contents(SITECHIPS_TEST_LOG_FILE, '');
        }

        // Load all WordPress admin includes for integration tests
        $this->loadWordPressAdminIncludes();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Log-File aufräumen
        if (defined('SITECHIPS_TEST_LOG_FILE') && file_exists(SITECHIPS_TEST_LOG_FILE)) {
            unlink(SITECHIPS_TEST_LOG_FILE);
        }
    }

    protected function getErrorLogContent(): string
    {
        if (!defined('SITECHIPS_TEST_LOG_FILE') || !file_exists(SITECHIPS_TEST_LOG_FILE)) {
            return '';
        }

        return file_get_contents(SITECHIPS_TEST_LOG_FILE) ?: '';
    }

    protected function assertErrorLogContains(string $expected): void
    {
        $content = $this->getErrorLogContent();
        $this->assertStringContainsString($expected, $content);
    }
    /**
     * Load WordPress admin includes
     */
    protected function loadWordPressAdminIncludes(): void
    {
        // Menu functions
        if (!function_exists('add_menu_page')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Settings API functions
        if (!function_exists('add_settings_section')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
        }

        // Options functions
        if (!function_exists('register_setting')) {
            require_once ABSPATH . 'wp-admin/includes/options.php';
        }

        // Additional admin functions
        if (!function_exists('submit_button')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
        }

        // User functions
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
    }

}