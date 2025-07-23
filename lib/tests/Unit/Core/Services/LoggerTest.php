<?php
/**
 * Logger Implementation Tests
 *
 * Tests for PSR-3 logger implementations: NullLogger and WordPressLogger.
 * Verifies PSR-3 compliance, configuration options, and WordPress integration.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Services
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Services;

use Furgo\Sitechips\Core\Services\NullLogger;
use Furgo\Sitechips\Core\Services\WordPressLogger;
use Furgo\Sitechips\Core\Tests\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use InvalidArgumentException;
use Exception;

/**
 * Logger Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Services\NullLogger
 * @covers \Furgo\Sitechips\Core\Services\WordPressLogger
 */
class LoggerTest extends TestCase
{
    /**
     * Captured error_log output
     *
     * @var array<string>
     */
    private array $errorLogOutput = [];

    /**
     * Original error handler
     *
     * @var callable|null
     */
    private $originalErrorHandler;

    /**
     * Set up each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear error log output
        $this->errorLogOutput = [];

        // Set custom error handler to capture error_log output
        $this->originalErrorHandler = set_error_handler([$this, 'errorHandler']);
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Restore original error handler
        if ($this->originalErrorHandler) {
            set_error_handler($this->originalErrorHandler);
        }
    }

    /**
     * Custom error handler to capture error_log output
     */
    public function errorHandler($errno, $errstr, $errfile, $errline): bool
    {
        // Capture error_log output
        if (str_contains($errstr, '[')) {
            $this->errorLogOutput[] = $errstr;
        }

        // Call original handler if exists
        if ($this->originalErrorHandler) {
            return call_user_func($this->originalErrorHandler, $errno, $errstr, $errfile, $errline);
        }

        return false;
    }

    // ========================================================================
    // NullLogger Tests
    // ========================================================================

    /**
     * @group null-logger
     *
     * Tests NullLogger implements PSR-3 interface.
     */
    public function testNullLoggerImplementsPsr3(): void
    {
        $logger = new NullLogger();

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * @group null-logger
     *
     * Tests NullLogger silently discards all log levels.
     */
    public function testNullLoggerDiscardsAllLevels(): void
    {
        $logger = new NullLogger();

        // Test all PSR-3 levels
        $logger->emergency('Emergency message');
        $logger->alert('Alert message');
        $logger->critical('Critical message');
        $logger->error('Error message');
        $logger->warning('Warning message');
        $logger->notice('Notice message');
        $logger->info('Info message');
        $logger->debug('Debug message');

        // Custom level via log()
        $logger->log(LogLevel::INFO, 'Custom log message');

        // Nothing should be logged
        $this->assertEmpty($this->errorLogOutput);
    }

    /**
     * @group null-logger
     *
     * Tests NullLogger handles context and placeholders.
     */
    public function testNullLoggerHandlesContext(): void
    {
        $logger = new NullLogger();

        // With context
        $logger->info('User {user} logged in', ['user' => 'john']);

        // With exception
        $exception = new Exception('Test exception');
        $logger->error('Error occurred', ['exception' => $exception]);

        // With complex context
        $logger->debug('Complex context', [
            'array' => [1, 2, 3],
            'object' => new \stdClass(),
            'null' => null,
            'bool' => true
        ]);

        // Nothing should be logged
        $this->assertEmpty($this->errorLogOutput);
    }

    // ========================================================================
    // WordPressLogger Basic Tests
    // ========================================================================

    /**
     * @group wordpress-logger
     *
     * Tests WordPressLogger implements PSR-3 interface.
     */
    public function testWordPressLoggerImplementsPsr3(): void
    {
        $logger = new WordPressLogger('test-plugin');

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * @group wordpress-logger
     *
     * Tests WordPressLogger constructor validation.
     */
    public function testWordPressLoggerConstructorValidation(): void
    {
        // Valid construction
        $logger = new WordPressLogger('test-plugin');
        $this->assertInstanceOf(WordPressLogger::class, $logger);

        // Empty plugin slug throws exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plugin slug cannot be empty');

        new WordPressLogger('');
    }

    /**
     * @group wordpress-logger
     *
     * Tests invalid minimum log level throws exception.
     */
    public function testWordPressLoggerInvalidMinLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level: invalid');

        new WordPressLogger('test-plugin', 'invalid');
    }

    /**
     * @group wordpress-logger
     *
     * Tests basic logging functionality through formatLogEntry.
     */
    public function testWordPressLoggerBasicLogging(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, false);

        // Test formatting without timestamp and context
        $formatted = $logger->formatLogEntry(LogLevel::INFO, 'Test message', []);

        $this->assertStringContainsString('[test-plugin] INFO:', $formatted);
        $this->assertStringContainsString('Test message', $formatted);
        $this->assertStringNotContainsString('Context:', $formatted);
    }

    /**
     * @group wordpress-logger
     *
     * Tests log level filtering through shouldLog method.
     */
    public function testWordPressLoggerLevelFiltering(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::WARNING, false, false);

        // The behavior depends on WP_DEBUG_LOG constant
        // If WP_DEBUG_LOG is false, shouldLog returns false regardless of level
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === false) {
            $this->assertFalse($logger->shouldLog(LogLevel::DEBUG));
            $this->assertFalse($logger->shouldLog(LogLevel::WARNING));
            $this->assertFalse($logger->shouldLog(LogLevel::ERROR));
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            // With WP_DEBUG true, all levels should log
            $this->assertTrue($logger->shouldLog(LogLevel::DEBUG));
            $this->assertTrue($logger->shouldLog(LogLevel::WARNING));
            $this->assertTrue($logger->shouldLog(LogLevel::ERROR));
        } else {
            // Without WP_DEBUG, respects minimum level
            $this->assertFalse($logger->shouldLog(LogLevel::DEBUG));
            $this->assertFalse($logger->shouldLog(LogLevel::INFO));
            $this->assertTrue($logger->shouldLog(LogLevel::WARNING));
            $this->assertTrue($logger->shouldLog(LogLevel::ERROR));
        }

        // Test that minimum level getter/setter works
        $this->assertEquals(LogLevel::WARNING, $logger->getMinLevel());
        $logger->setMinLevel(LogLevel::ERROR);
        $this->assertEquals(LogLevel::ERROR, $logger->getMinLevel());
    }

    /**
     * @group wordpress-logger
     *
     * Tests context interpolation through formatLogEntry.
     */
    public function testWordPressLoggerContextInterpolation(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, false);

        $formatted = $logger->formatLogEntry(
            LogLevel::INFO,
            'User {username} performed {action}',
            ['username' => 'john_doe', 'action' => 'login']
        );

        $this->assertStringContainsString('User john_doe performed login', $formatted);

        // Test with missing placeholders
        $formatted2 = $logger->formatLogEntry(
            LogLevel::INFO,
            'User {username} did something',
            ['other' => 'value']
        );

        $this->assertStringContainsString('User {username} did something', $formatted2);
    }

    /**
     * @group wordpress-logger
     *
     * Tests setMinLevel with invalid level throws exception.
     */
    public function testWordPressLoggerInvalidMinLevelThrowsException(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::WARNING, false, false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level: invalid');

        $logger->setMinLevel('invalid');
    }

    /**
     * @group wordpress-logger
     *
     * Tests context formatting when enabled.
     */
    public function testWordPressLoggerContextFormatting(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, true);

        $formatted = $logger->formatLogEntry(
            LogLevel::INFO,
            'Test message',
            ['user_id' => 123, 'action' => 'update']
        );

        $this->assertStringContainsString('Context:', $formatted);
        $this->assertStringContainsString('"user_id":123', $formatted);
        $this->assertStringContainsString('"action":"update"', $formatted);

        // Test empty context
        $formatted2 = $logger->formatLogEntry(LogLevel::INFO, 'No context', []);
        $this->assertStringNotContainsString('Context:', $formatted2);
    }

    /**
     * @group wordpress-logger
     *
     * Tests exception handling in context.
     */
    public function testWordPressLoggerExceptionContext(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, true);

        $exception = new Exception('Test exception');
        $formatted = $logger->formatLogEntry(
            LogLevel::ERROR,
            'Error occurred',
            ['exception' => $exception]
        );

        $this->assertStringContainsString('Exception: Test exception', $formatted);
        $this->assertStringContainsString('LoggerTest.php', $formatted);

        // Test with additional context
        $formatted2 = $logger->formatLogEntry(
            LogLevel::ERROR,
            'Error with context',
            ['exception' => $exception, 'user_id' => 123]
        );

        $this->assertStringContainsString('Exception: Test exception', $formatted2);
        $this->assertStringContainsString('"user_id":123', $formatted2);
    }

    /**
     * @group wordpress-logger
     *
     * Tests timestamp inclusion in formatted output.
     */
    public function testWordPressLoggerTimestamp(): void
    {
        // Logger with timestamp
        $loggerWithTimestamp = new WordPressLogger('test-plugin', LogLevel::DEBUG, true, false);
        $formatted = $loggerWithTimestamp->formatLogEntry(LogLevel::INFO, 'Test message', []);

        // Check for timestamp format [YYYY-MM-DD HH:MM:SS]
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $formatted);

        // Logger without timestamp
        $loggerWithoutTimestamp = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, false);
        $formatted2 = $loggerWithoutTimestamp->formatLogEntry(LogLevel::INFO, 'Test message', []);

        $this->assertDoesNotMatchRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $formatted2);
    }

    /**
     * @group wordpress-logger
     *
     * Tests setMinLevel and getMinLevel methods.
     */
    public function testWordPressLoggerMinLevelMethods(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG);

        $this->assertEquals(LogLevel::DEBUG, $logger->getMinLevel());

        $logger->setMinLevel(LogLevel::ERROR);
        $this->assertEquals(LogLevel::ERROR, $logger->getMinLevel());

        // Invalid level throws exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level: invalid');

        $logger->setMinLevel('invalid');
    }

    /**
     * @group wordpress-logger
     *
     * Tests logging with invalid level throws exception.
     */
    public function testWordPressLoggerInvalidLogLevel(): void
    {
        $logger = new WordPressLogger('test-plugin');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level: invalid');

        $logger->log('invalid', 'Test message');
    }

    /**
     * @group wordpress-logger
     *
     * Tests complex value stringification in context.
     */
    public function testWordPressLoggerComplexValues(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, true);

        $object = new \stdClass();
        $object->test = 'value';

        $formatted = $logger->formatLogEntry(LogLevel::INFO, 'Complex {type}', [
            'type' => 'values',
            'null' => null,
            'bool_true' => true,
            'bool_false' => false,
            'array' => [1, 2, 3],
            'object' => $object
        ]);

        // Check interpolation
        $this->assertStringContainsString('Complex values', $formatted);

        // Check context formatting
        // Note: Objects in context are JSON-encoded, not stringified
        $this->assertStringContainsString('"null":null', $formatted);
        $this->assertStringContainsString('"bool_true":true', $formatted);
        $this->assertStringContainsString('"bool_false":false', $formatted);
        $this->assertStringContainsString('"array":[1,2,3]', $formatted);
        $this->assertStringContainsString('"object":{"test":"value"}', $formatted); // Changed this line
    }

    /**
     * @group wordpress-logger
     *
     * Tests all PSR-3 convenience methods format correctly.
     */
    public function testWordPressLoggerAllPsr3Methods(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, false);

        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG
        ];

        foreach ($levels as $level) {
            $formatted = $logger->formatLogEntry($level, "Test $level message", []);
            $this->assertStringContainsString(strtoupper($level) . ':', $formatted);
            $this->assertStringContainsString("Test $level message", $formatted);
        }
    }

    /**
     * @group wordpress-logger
     *
     * Tests Stringable message support in formatting.
     */
    public function testWordPressLoggerStringableMessage(): void
    {
        $logger = new WordPressLogger('test-plugin', LogLevel::DEBUG, false, false);

        // Note: formatLogEntry expects string, so we test the log method still works
        $stringable = new class implements \Stringable {
            public function __toString(): string {
                return 'Stringable message';
            }
        };

        // Test that log() accepts Stringable and converts it
        $logger->log(LogLevel::INFO, $stringable);

        // Test formatting with the string value
        $formatted = $logger->formatLogEntry(LogLevel::INFO, 'Stringable message', []);
        $this->assertStringContainsString('Stringable message', $formatted);
    }
}