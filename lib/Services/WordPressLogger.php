<?php
/**
 * WordPress Logger Implementation
 *
 * PSR-3 compliant logger that writes to WordPress debug log using error_log().
 * Formats messages with plugin prefix, log level, and context information.
 * Respects WP_DEBUG and WP_DEBUG_LOG constants.
 *
 * @package     Furgo\Sitechips\Core\Services
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;
use InvalidArgumentException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * WordPress Logger Class
 *
 * Logs messages to WordPress debug log with configurable formatting.
 * Supports all PSR-3 log levels and context interpolation.
 *
 * @since 1.0.0
 */
class WordPressLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * Plugin slug for log message prefixing
     *
     * @var string
     */
    private string $pluginSlug;

    /**
     * Minimum log level for messages to be logged
     *
     * @var string
     */
    private string $minLevel;

    /**
     * Whether to include timestamp in log messages
     *
     * @var bool
     */
    private bool $includeTimestamp;

    /**
     * Whether to include context in log messages
     *
     * @var bool
     */
    private bool $includeContext;

    /**
     * Log level priorities for comparison
     *
     * @var array<string, int>
     */
    private const LEVELS = [
        LogLevel::DEBUG     => 100,
        LogLevel::INFO      => 200,
        LogLevel::NOTICE    => 250,
        LogLevel::WARNING   => 300,
        LogLevel::ERROR     => 400,
        LogLevel::CRITICAL  => 500,
        LogLevel::ALERT     => 550,
        LogLevel::EMERGENCY => 600,
    ];

    /**
     * Create new WordPress logger instance
     *
     * @param string $pluginSlug Plugin slug for message prefixing
     * @param string $minLevel Minimum log level (default: debug)
     * @param bool $includeTimestamp Whether to include timestamp (default: true)
     * @param bool $includeContext Whether to include context data (default: true)
     *
     * @throws InvalidArgumentException If plugin slug is empty or min level is invalid
     *
     * @since 1.0.0
     */
    public function __construct(
        string $pluginSlug,
        string $minLevel = LogLevel::DEBUG,
        bool $includeTimestamp = true,
        bool $includeContext = true
    ) {
        if (empty($pluginSlug)) {
            throw new InvalidArgumentException('Plugin slug cannot be empty');
        }

        if (!isset(self::LEVELS[$minLevel])) {
            throw new InvalidArgumentException("Invalid log level: $minLevel");
        }

        $this->pluginSlug = $pluginSlug;
        $this->minLevel = $minLevel;
        $this->includeTimestamp = $includeTimestamp;
        $this->includeContext = $includeContext;
    }

    /**
     * Logs with an arbitrary level
     *
     * @param mixed $level Level (debug, info, notice, warning, error, critical, alert, emergency)
     * @param string|Stringable $message Message to log
     * @param array<string, mixed> $context Context array
     *
     * @return void
     *
     * @throws InvalidArgumentException If level is not a valid log level
     *
     * @since 1.0.0
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Validate log level
        if (!is_string($level) || !isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }

        // Check if we should log based on WP_DEBUG settings
        if (!$this->shouldLog($level)) {
            return;
        }

        // Format the log entry
        $formattedMessage = $this->formatLogEntry($level, (string) $message, $context);

        // Write to log
        $this->writeLog($formattedMessage);
    }

    /**
     * Set minimum log level
     *
     * Only messages with this level or higher will be logged.
     *
     * @param string $level Minimum log level
     *
     * @return void
     *
     * @throws InvalidArgumentException If level is invalid
     *
     * @since 1.0.0
     */
    public function setMinLevel(string $level): void
    {
        if (!isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }

        $this->minLevel = $level;
    }

    /**
     * Get current minimum log level
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getMinLevel(): string
    {
        return $this->minLevel;
    }

    /**
     * Check if a message should be logged
     *
     * Public for testability. Determines if a message should be logged
     * based on log level and WordPress debug settings.
     *
     * @param string $level Log level to check
     *
     * @return bool True if message should be logged
     *
     * @since 1.0.0
     */
    public function shouldLog(string $level): bool
    {
        // Always respect WP_DEBUG_LOG if explicitly set to false
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === false) {
            return false;
        }

        // In debug mode, log everything
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // Otherwise, check against minimum level
        return self::LEVELS[$level] >= self::LEVELS[$this->minLevel];
    }

    /**
     * Format complete log entry
     *
     * Public for testability. Formats the complete log message including
     * timestamp, plugin prefix, level, interpolated message and context.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Context data
     *
     * @return string Formatted log entry
     *
     * @since 1.0.0
     */
    public function formatLogEntry(string $level, string $message, array $context): string
    {
        return $this->formatMessage($level, $message, $context);
    }

    /**
     * Write formatted message to log
     *
     * Protected to allow extension for different log destinations.
     * Default implementation uses error_log().
     *
     * @param string $message Formatted message to write
     *
     * @return void
     *
     * @since 1.0.0
     */
    protected function writeLog(string $message): void
    {
        error_log($message);
    }

    /**
     * Format log message with prefix, level, and context
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Context data
     *
     * @return string Formatted message
     *
     * @since 1.0.0
     */
    private function formatMessage(string $level, string $message, array $context): string
    {
        $parts = [];

        // Add timestamp if enabled
        if ($this->includeTimestamp) {
            $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $parts[] = "[$timestamp]";
        }

        // Add plugin prefix and level
        $parts[] = sprintf('[%s] %s:', $this->pluginSlug, strtoupper($level));

        // Interpolate context values into message
        $interpolatedMessage = $this->interpolate($message, $context);
        $parts[] = $interpolatedMessage;

        // Add context if enabled and not empty
        if ($this->includeContext && !empty($context)) {
            $contextString = $this->formatContext($context);
            if ($contextString !== '') {
                $parts[] = $contextString;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Interpolate context values into message placeholders
     *
     * @param string $message Message with placeholders
     * @param array<string, mixed> $context Context values
     *
     * @return string Interpolated message
     *
     * @since 1.0.0
     */
    private function interpolate(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        // Build replacement array
        $replace = [];
        foreach ($context as $key => $val) {
            // Skip special context keys
            if ($key === 'exception') {
                continue;
            }

            $placeholder = '{' . $key . '}';
            $replace[$placeholder] = $this->stringify($val);
        }

        // Replace placeholders
        return strtr($message, $replace);
    }

    /**
     * Format context data for logging
     *
     * @param array<string, mixed> $context Context data
     *
     * @return string Formatted context
     *
     * @since 1.0.0
     */
    private function formatContext(array $context): string
    {
        // Handle exception in context
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            unset($context['exception']);

            $exceptionInfo = sprintf(
                'Exception: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );

            // Add trace in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $exceptionInfo .= "\nTrace: " . $exception->getTraceAsString();
            }

            // Add remaining context if any
            if (!empty($context)) {
                return $exceptionInfo . ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
            }

            return $exceptionInfo;
        }

        // Format regular context
        if (!empty($context)) {
            return 'Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return '';
    }

    /**
     * Convert value to string representation
     *
     * @param mixed $value Value to stringify
     *
     * @return string String representation
     *
     * @since 1.0.0
     */
    private function stringify(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        if (is_object($value)) {
            if ($value instanceof Stringable) {
                return (string) $value;
            }
            return get_class($value);
        }

        return gettype($value);
    }
}