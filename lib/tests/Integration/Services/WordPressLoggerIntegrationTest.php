<?php
/**
 * WordPress Logger Integration Tests
 *
 * Integration tests for WordPressLogger that test real error_log output.
 * These tests require the integration test environment with custom error_log path.
 *
 * @package     Furgo\Sitechips\Core\Tests\Integration\Services
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Integration\Services;

use Furgo\Sitechips\Core\Services\WordPressLogger;
use Furgo\Sitechips\Core\Tests\Integration\IntegrationTestCase;
use Psr\Log\LogLevel;

/**
 * WordPress Logger Integration Test Class
 *
 * @since 1.0.0
 * @coversDefaultClass \Furgo\Sitechips\Core\Services\WordPressLogger
 */
class WordPressLoggerIntegrationTest extends IntegrationTestCase
{
    /**
     * @var WordPressLogger
     */
    private WordPressLogger $logger;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new WordPressLogger('test-plugin');
    }

    // ========================================================================
    // Error Log Output Tests
    // ========================================================================

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that emergency messages are written to error_log
     */
    public function testEmergencyMessagesAreLogged(): void
    {
        $this->logger->emergency('System is down!');

        $this->assertErrorLogContains('[test-plugin] EMERGENCY:');
        $this->assertErrorLogContains('System is down!');
    }

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that alert messages are written to error_log
     */
    public function testAlertMessagesAreLogged(): void
    {
        $this->logger->alert('Database connection lost');

        $this->assertErrorLogContains('[test-plugin] ALERT:');
        $this->assertErrorLogContains('Database connection lost');
    }

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that critical messages are written to error_log
     */
    public function testCriticalMessagesAreLogged(): void
    {
        $this->logger->critical('Application component unavailable');

        $this->assertErrorLogContains('[test-plugin] CRITICAL:');
        $this->assertErrorLogContains('Application component unavailable');
    }

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that error messages are written to error_log
     */
    public function testErrorMessagesAreLogged(): void
    {
        $this->logger->error('Runtime error occurred');

        $this->assertErrorLogContains('[test-plugin] ERROR:');
        $this->assertErrorLogContains('Runtime error occurred');
    }

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that warning messages are written to error_log
     */
    public function testWarningMessagesAreLogged(): void
    {
        $this->logger->warning('Deprecated function used');

        $this->assertErrorLogContains('[test-plugin] WARNING:');
        $this->assertErrorLogContains('Deprecated function used');
    }

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that notice messages are written to error_log
     */
    public function testNoticeMessagesAreLogged(): void
    {
        $this->logger->notice('Unusual event occurred');

        $this->assertErrorLogContains('[test-plugin] NOTICE:');
        $this->assertErrorLogContains('Unusual event occurred');
    }

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that info messages are written to error_log
     */
    public function testInfoMessagesAreLogged(): void
    {
        $this->logger->info('User logged in');

        $this->assertErrorLogContains('[test-plugin] INFO:');
        $this->assertErrorLogContains('User logged in');
    }

    /**
     * @group integration
     * @group logger
     * @group output
     *
     * Tests that debug messages are written to error_log
     */
    public function testDebugMessagesAreLogged(): void
    {
        $this->logger->debug('Entering function processData()');

        $this->assertErrorLogContains('[test-plugin] DEBUG:');
        $this->assertErrorLogContains('Entering function processData()');
    }

    // ========================================================================
    // Message Formatting Tests
    // ========================================================================

    /**
     * @group integration
     * @group logger
     * @group formatting
     *
     * Tests that messages include timestamp
     */
    public function testMessagesIncludeTimestamp(): void
    {
        $this->logger->info('Test message');

        // Check for timestamp format YYYY-MM-DD HH:MM:SS
        $this->assertErrorLogMatches('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/');
    }

    /**
     * @group integration
     * @group logger
     * @group formatting
     *
     * Tests that messages include plugin slug
     */
    public function testMessagesIncludePluginSlug(): void
    {
        $customLogger = new WordPressLogger('my-custom-plugin');
        $customLogger->error('Custom plugin error');

        $this->assertErrorLogContains('[my-custom-plugin] ERROR:');
    }

    /**
     * @group integration
     * @group logger
     * @group formatting
     *
     * Tests message formatting without timestamp
     */
    public function testMessageFormattingWithoutTimestamp(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false);
        $logger->error('No timestamp message');

        $content = $this->getErrorLogContent();

        // Should not contain timestamp pattern
        $this->assertDoesNotMatchRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
        $this->assertStringContainsString('[test-plugin] ERROR: No timestamp message', $content);
    }

    // ========================================================================
    // Context Interpolation Tests
    // ========================================================================

    /**
     * @group integration
     * @group logger
     * @group context
     *
     * Tests that context values are interpolated into messages
     */
    public function testContextInterpolation(): void
    {
        $this->logger->info('User {username} logged in from {ip}', [
            'username' => 'john_doe',
            'ip' => '192.168.1.1'
        ]);

        $this->assertErrorLogContains('User john_doe logged in from 192.168.1.1');
    }

    /**
     * @group integration
     * @group logger
     * @group context
     *
     * Tests that array context is JSON encoded
     */
    public function testArrayContextEncoding(): void
    {
        $this->logger->error('Import failed', [
            'file' => 'data.csv',
            'errors' => ['line 1', 'line 5'],
            'count' => 2
        ]);

        $this->assertErrorLogContains('Import failed');
        $this->assertErrorLogContains('Context: {"file":"data.csv","errors":["line 1","line 5"],"count":2}');
    }

    /**
     * @group integration
     * @group logger
     * @group context
     *
     * Tests exception context handling
     */
    public function testExceptionContext(): void
    {
        $exception = new \RuntimeException('Test exception');

        $this->logger->error('Operation failed', [
            'exception' => $exception
        ]);

        $this->assertErrorLogContains('Operation failed');
        $this->assertErrorLogContains('Exception: Test exception in');
        $this->assertErrorLogContains('WordPressLoggerIntegrationTest.php');
    }

    /**
     * @group integration
     * @group logger
     * @group context
     *
     * Tests message formatting without context
     */
    public function testMessageFormattingWithoutContext(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, true, false);
        $logger->error('Error without context', ['ignored' => 'value']);

        $content = $this->getErrorLogContent();

        $this->assertStringContainsString('Error without context', $content);
        $this->assertStringNotContainsString('Context:', $content);
        $this->assertStringNotContainsString('ignored', $content);
    }

    // ========================================================================
    // Log Level Filtering Tests
    // ========================================================================

    /**
     * @group integration
     * @group logger
     * @group levels
     *
     * Tests that all messages are logged when WP_DEBUG is true
     */
    public function testAllMessagesLoggedInDebugMode(): void
    {
        // WP_DEBUG is true in test environment
        $logger = new WordPressLogger('test-plugin', LogLevel::ERROR);

        // Even though min level is ERROR, debug messages should be logged
        $logger->debug('Debug message in WP_DEBUG mode');
        $logger->info('Info message in WP_DEBUG mode');

        $this->assertErrorLogContains('Debug message in WP_DEBUG mode');
        $this->assertErrorLogContains('Info message in WP_DEBUG mode');
    }

    /**
     * @group integration
     * @group logger
     * @group levels
     *
     * Tests changing minimum log level at runtime
     */
    public function testChangeMinimumLogLevel(): void
    {
        $this->clearErrorLog();

        // Start with ERROR level
        $logger = new WordPressLogger('test-plugin', LogLevel::ERROR);

        // Change to WARNING
        $logger->setMinLevel(LogLevel::WARNING);

        // Log warning (should appear)
        $logger->warning('This warning should be logged');

        $this->assertErrorLogContains('This warning should be logged');
    }

    // ========================================================================
    // Special Character Handling Tests
    // ========================================================================

    /**
     * @group integration
     * @group logger
     * @group encoding
     *
     * Tests that special characters are handled correctly
     */
    public function testSpecialCharacterHandling(): void
    {
        $this->logger->info('Special chars: äöü ñ € " \' < >');

        $this->assertErrorLogContains('Special chars: äöü ñ € " \' < >');
    }

    /**
     * @group integration
     * @group logger
     * @group encoding
     *
     * Tests multi-line messages
     */
    public function testMultiLineMessages(): void
    {
        $message = "Line 1\nLine 2\nLine 3";
        $this->logger->error($message);

        $content = $this->getErrorLogContent();

        // PHP error_log adds the date prefix only to first line
        $this->assertStringContainsString("Line 1\nLine 2\nLine 3", $content);
    }

    // ========================================================================
    // Performance and Edge Cases
    // ========================================================================

    /**
     * @group integration
     * @group logger
     * @group performance
     *
     * Tests logging many messages in sequence
     */
    public function testRapidSequentialLogging(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->logger->info("Message $i");
        }

        $content = $this->getErrorLogContent();

        for ($i = 1; $i <= 5; $i++) {
            $this->assertStringContainsString("Message $i", $content);
        }
    }

    /**
     * @group integration
     * @group logger
     * @group edge-cases
     *
     * Tests empty message logging
     */
    public function testEmptyMessageLogging(): void
    {
        $this->logger->error('');

        // Should still log with level and timestamp
        $this->assertErrorLogContains('[test-plugin] ERROR:');
    }

    /**
     * @group integration
     * @group logger
     * @group edge-cases
     *
     * Tests very long message logging
     */
    public function testVeryLongMessageLogging(): void
    {
        $longMessage = str_repeat('A very long message. ', 100);
        $this->logger->error($longMessage);

        $this->assertErrorLogContains($longMessage);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Assert that error log matches a regular expression
     *
     * @param string $pattern Regular expression pattern
     */
    protected function assertErrorLogMatches(string $pattern): void
    {
        $content = $this->getErrorLogContent();
        $this->assertMatchesRegularExpression($pattern, $content);
    }

    /**
     * Assert that error log does not contain string
     *
     * @param string $unexpected String that should not be in log
     */
    protected function assertErrorLogNotContains(string $unexpected): void
    {
        $content = $this->getErrorLogContent();
        $this->assertStringNotContainsString($unexpected, $content);
    }

    /**
     * Clear error log between tests when needed
     */
    protected function clearErrorLog(): void
    {
        if (defined('SITECHIPS_TEST_LOG_FILE') && file_exists(SITECHIPS_TEST_LOG_FILE)) {
            file_put_contents(SITECHIPS_TEST_LOG_FILE, '');
        }
    }
}