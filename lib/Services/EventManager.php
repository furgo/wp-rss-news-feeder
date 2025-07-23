<?php
/**
 * Event Manager Implementation
 *
 * Provides event management functionality with optional WordPress integration.
 * Manages internal event listeners while optionally delegating to WordPress
 * hooks system for compatibility with the WordPress ecosystem.
 *
 * ## Usage Example:
 * ```php
 * $events = new EventManager('my-plugin');
 *
 * // Register listener
 * $events->listen('user.created', function($user) {
 *     echo "User {$user->name} was created";
 * });
 *
 * // Dispatch event
 * $events->dispatch('user.created', $user);
 *
 * // Filter value
 * $events->listen('user.name', function($name) {
 *     return strtoupper($name);
 * });
 * $filtered = $events->filter('user.name', 'john doe');
 * // $filtered = 'JOHN DOE'
 * ```
 *
 * @package     Furgo\Sitechips\Core\Services
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Services;

use Furgo\Sitechips\Core\Contracts\EventManagerInterface;
use InvalidArgumentException;

/**
 * Event Manager Class
 *
 * Manages event registration and dispatching with WordPress integration support.
 * Can operate in standalone mode for testing or integrate with WordPress hooks.
 *
 * @since 1.0.0
 */
class EventManager implements EventManagerInterface
{
    /**
     * Registered event listeners grouped by event and priority
     *
     * @var array<string, array<int, callable[]>>
     */
    private array $listeners = [];

    /**
     * Plugin slug for prefixing events
     *
     * @var string
     */
    private string $pluginSlug;

    /**
     * Whether to integrate with WordPress hooks
     *
     * @var bool
     */
    private bool $useWordPress;

    /**
     * Maximum number of arguments to pass to WordPress hooks
     *
     * @var int
     */
    private const WP_MAX_ACCEPTED_ARGS = 10;

    /**
     * Create new event manager instance
     *
     * @param string $pluginSlug Plugin slug for event prefixing
     * @param bool $useWordPress Whether to integrate with WordPress (default: true)
     *
     * @throws InvalidArgumentException If plugin slug is empty
     *
     * @since 1.0.0
     */
    public function __construct(string $pluginSlug, bool $useWordPress = true)
    {
        if (empty($pluginSlug)) {
            throw new InvalidArgumentException('Plugin slug cannot be empty');
        }

        $this->pluginSlug = $pluginSlug;
        $this->useWordPress = $useWordPress && function_exists('add_action');
    }

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
     * @throws InvalidArgumentException If event name is empty
     *
     * @since 1.0.0
     */
    public function listen(string $event, callable $listener, int $priority = 10): void
    {
        if (empty($event)) {
            throw new InvalidArgumentException('Event name cannot be empty');
        }

        // Add to internal listeners
        $prefixedEvent = $this->prefixEvent($event);
        $this->listeners[$prefixedEvent][$priority][] = $listener;

        // Register with WordPress if enabled
        if ($this->useWordPress) {
            add_action($prefixedEvent, $listener, $priority, self::WP_MAX_ACCEPTED_ARGS);
        }
    }

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
     * @throws InvalidArgumentException If event name is empty
     *
     * @since 1.0.0
     */
    public function dispatch(string $event, mixed ...$args): mixed
    {
        if (empty($event)) {
            throw new InvalidArgumentException('Event name cannot be empty');
        }

        $prefixedEvent = $this->prefixEvent($event);
        $result = null;

        // Dispatch to WordPress first if enabled
        if ($this->useWordPress && function_exists('do_action')) {
            do_action($prefixedEvent, ...$args);
        }

        // Then dispatch to internal listeners
        if (isset($this->listeners[$prefixedEvent])) {
            $result = $this->executeListeners($prefixedEvent, $args);
        }

        return $result;
    }

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
     * @throws InvalidArgumentException If event name is empty
     *
     * @since 1.0.0
     */
    public function filter(string $event, mixed $value, mixed ...$args): mixed
    {
        if (empty($event)) {
            throw new InvalidArgumentException('Event name cannot be empty');
        }

        $prefixedEvent = $this->prefixEvent($event);

        // Apply WordPress filters first if enabled
        if ($this->useWordPress && function_exists('apply_filters')) {
            $value = apply_filters($prefixedEvent, $value, ...$args);
        }

        // Then apply internal filters
        if (isset($this->listeners[$prefixedEvent])) {
            $value = $this->executeFilters($prefixedEvent, $value, $args);
        }

        return $value;
    }

    /**
     * Remove an event listener
     *
     * Removes ALL instances of the specified listener from the event.
     * If the same callback was registered multiple times, all instances
     * will be removed. Also removes from WordPress hooks if integration
     * is enabled.
 *
     * @param string $event Event name
     * @param callable $listener Listener to remove
     *
     * @return bool True if listener was found and removed, false otherwise
     *
     * @throws InvalidArgumentException If event name is empty
     *
     * @since 1.0.0
     */
    public function forget(string $event, callable $listener): bool
    {
        if (empty($event)) {
            throw new InvalidArgumentException('Event name cannot be empty');
        }

        $prefixedEvent = $this->prefixEvent($event);
        $removed = false;

        // Remove from WordPress if enabled
        if ($this->useWordPress && function_exists('remove_action')) {
            remove_action($prefixedEvent, $listener);
        }

        // Remove from internal listeners
        if (isset($this->listeners[$prefixedEvent])) {
            foreach ($this->listeners[$prefixedEvent] as $priority => &$callbacks) {
                $before = count($callbacks);
                $callbacks = array_filter($callbacks, fn($cb) => $cb !== $listener);
                $after = count($callbacks);

                if ($before > $after) {
                    $removed = true;
                }

                // Clean up empty priority groups
                if (empty($callbacks)) {
                    unset($this->listeners[$prefixedEvent][$priority]);
                }
            }

            // Clean up empty events
            if (empty($this->listeners[$prefixedEvent])) {
                unset($this->listeners[$prefixedEvent]);
            }
        }

        return $removed;
    }

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
     * @throws InvalidArgumentException If event name is empty
     *
     * @since 1.0.0
     */
    public function hasListeners(string $event): bool
    {
        if (empty($event)) {
            throw new InvalidArgumentException('Event name cannot be empty');
        }

        $prefixedEvent = $this->prefixEvent($event);

        return isset($this->listeners[$prefixedEvent]) &&
            !empty($this->listeners[$prefixedEvent]);
    }

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
    public function clear(?string $event = null): void
    {
        if ($event === null) {
            // Clear all events
            $this->listeners = [];
        } else {
            // Clear specific event
            $prefixedEvent = $this->prefixEvent($event);
            unset($this->listeners[$prefixedEvent]);
        }

        // Note: We cannot clear WordPress hooks as we don't track them
    }

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
     * @throws InvalidArgumentException If event name is empty
     *
     * @since 1.0.0
     */
    public function getListeners(string $event): array
    {
        if (empty($event)) {
            throw new InvalidArgumentException('Event name cannot be empty');
        }

        $prefixedEvent = $this->prefixEvent($event);

        if (!isset($this->listeners[$prefixedEvent])) {
            return [];
        }

        // Return sorted by priority
        $listeners = $this->listeners[$prefixedEvent];
        ksort($listeners, SORT_NUMERIC);

        return $listeners;
    }

    /**
     * Enable or disable WordPress integration
     *
     * Allows toggling WordPress hook integration at runtime.
     * Useful for testing or standalone usage.
     *
     * @param bool $use Whether to use WordPress hooks
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function setUseWordPress(bool $use): void
    {
        $this->useWordPress = $use && function_exists('add_action');
    }

    /**
     * Check if WordPress integration is enabled
     *
     * @return bool True if WordPress hooks are being used
     *
     * @since 1.0.0
     */
    public function isUsingWordPress(): bool
    {
        return $this->useWordPress;
    }

    /**
     * Get the plugin slug
     *
     * @return string Plugin slug used for event prefixing
     *
     * @since 1.0.0
     */
    public function getPluginSlug(): string
    {
        return $this->pluginSlug;
    }

    /**
     * Prefix event name with plugin slug
     *
     * @param string $event Event name
     *
     * @return string Prefixed event name
     *
     * @since 1.0.0
     */
    private function prefixEvent(string $event): string
    {
        // Don't double-prefix
        if (str_starts_with($event, $this->pluginSlug . '.')) {
            return $event;
        }

        return $this->pluginSlug . '.' . $event;
    }

    /**
     * Execute listeners for an event
     *
     * Calls all listeners in priority order and returns the last result.
     * Used for action-style events.
     *
     * @param string $prefixedEvent Already prefixed event name
     * @param array<mixed> $args Arguments to pass to listeners
     *
     * @return mixed Last return value from listeners
     *
     * @since 1.0.0
     */
    private function executeListeners(string $prefixedEvent, array $args): mixed
    {
        $result = null;
        $listeners = $this->listeners[$prefixedEvent];

        // Sort by priority (ascending)
        ksort($listeners, SORT_NUMERIC);

        foreach ($listeners as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $result = call_user_func_array($callback, $args);
            }
        }

        return $result;
    }

    /**
     * Execute filter listeners for an event
     *
     * Passes value through all listeners in priority order.
     * Each listener receives the result from the previous one.
     *
     * @param string $prefixedEvent Already prefixed event name
     * @param mixed $value Initial value to filter
     * @param array<mixed> $args Additional arguments for listeners
     *
     * @return mixed Filtered value
     *
     * @since 1.0.0
     */
    private function executeFilters(string $prefixedEvent, mixed $value, array $args): mixed
    {
        $listeners = $this->listeners[$prefixedEvent];

        // Sort by priority (ascending)
        ksort($listeners, SORT_NUMERIC);

        foreach ($listeners as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                // Prepend value to arguments for filter-style calls
                $value = call_user_func_array($callback, array_merge([$value], $args));
            }
        }

        return $value;
    }
}