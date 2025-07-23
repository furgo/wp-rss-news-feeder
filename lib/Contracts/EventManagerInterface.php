<?php
/**
 * Event Manager Interface
 *
 * Defines the contract for event management in the Sitechips framework.
 * Provides an abstraction layer over WordPress hooks/actions with support
 * for internal event handling, return values, and listener management.
 *
 * @package     Furgo\Sitechips\Core\Contracts
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Contracts;

/**
 * Event Manager Interface
 *
 * Manages event listeners and dispatching with optional WordPress integration.
 * Supports both action-style events (no return) and filter-style events (with return values).
 *
 * @since 1.0.0
 */
interface EventManagerInterface
{
    /**
     * Register an event listener
     *
     * Adds a listener for the specified event. Listeners are called in priority order
     * (lower numbers first) when the event is dispatched. Optionally integrates with
     * WordPress hooks if available.
     *
     * @param string $event Event name to listen for
     * @param callable $listener Callback function to execute when event fires
     * @param int $priority Execution priority (lower = earlier, default: 10)
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function listen(string $event, callable $listener, int $priority = 10): void;

    /**
     * Dispatch an event
     *
     * Fires an event and executes all registered listeners. This is action-style
     * dispatching where return values are not collected. Use filter() for events
     * that modify values.
     *
     * @param string $event Event name to dispatch
     * @param mixed ...$args Arguments to pass to listeners
     *
     * @return mixed Last return value from listeners (if any)
     *
     * @since 1.0.0
     */
    public function dispatch(string $event, mixed ...$args): mixed;

    /**
     * Apply filters to a value
     *
     * Fires a filter event where each listener can modify and return the value.
     * The value is passed through all listeners in priority order, with each
     * listener receiving the result from the previous one.
     *
     * @param string $event Filter name
     * @param mixed $value Initial value to filter
     * @param mixed ...$args Additional arguments for listeners
     *
     * @return mixed Filtered value after all listeners have run
     *
     * @since 1.0.0
     */
    public function filter(string $event, mixed $value, mixed ...$args): mixed;

    /**
     * Remove an event listener
     *
     * Removes a previously registered listener from the specified event.
     * Also removes from WordPress hooks if integration is enabled.
     *
     * @param string $event Event name
     * @param callable $listener Listener to remove
     *
     * @return bool True if listener was found and removed, false otherwise
     *
     * @since 1.0.0
     */
    public function forget(string $event, callable $listener): bool;

    /**
     * Check if an event has listeners
     *
     * Determines whether any listeners are registered for the given event.
     * Only checks internal listeners, not WordPress hooks.
     *
     * @param string $event Event name to check
     *
     * @return bool True if event has listeners, false otherwise
     *
     * @since 1.0.0
     */
    public function hasListeners(string $event): bool;

    /**
     * Remove all listeners for an event
     *
     * Clears all registered listeners for the specified event.
     * If event is null, removes all listeners for all events.
     *
     * @param string|null $event Event name or null for all events
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function clear(?string $event = null): void;

    /**
     * Get all registered listeners for an event
     *
     * Returns array of listeners grouped by priority for the specified event.
     * Useful for debugging or introspection.
     *
     * @param string $event Event name
     *
     * @return array<int, callable[]> Listeners grouped by priority
     *
     * @since 1.0.0
     */
    public function getListeners(string $event): array;
}