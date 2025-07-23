<?php
/**
 * Null Logger Implementation
 *
 * PSR-3 compliant logger that discards all log messages.
 * Useful for testing or when logging should be disabled without
 * changing application code.
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
use Stringable;

/**
 * Null Logger Class
 *
 * A no-operation logger implementation that silently discards all log messages.
 * Implements PSR-3 LoggerInterface for compatibility but performs no actual logging.
 *
 * @since 1.0.0
 */
class NullLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * Logs with an arbitrary level
     *
     * This implementation discards all log messages without any processing.
     *
     * @param mixed $level Level (debug, info, notice, warning, error, critical, alert, emergency)
     * @param string|Stringable $message Message to log
     * @param array<string, mixed> $context Context array
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Intentionally do nothing - null logger discards all messages
    }
}