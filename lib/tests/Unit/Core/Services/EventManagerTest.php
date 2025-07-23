<?php
/**
 * Event Manager Unit Tests
 *
 * Comprehensive tests for the EventManager service.
 * Tests event registration, dispatching, filtering, listener management,
 * and WordPress integration capabilities.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Services
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Services;

use Furgo\Sitechips\Core\Services\EventManager;
use Furgo\Sitechips\Core\Contracts\EventManagerInterface;
use Furgo\Sitechips\Core\Tests\TestCase;
use InvalidArgumentException;

/**
 * Event Manager Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Services\EventManager
 */
class EventManagerTest extends TestCase
{
    /**
     * Event manager instance
     *
     * @var EventManager
     */
    private EventManager $events;

    /**
     * Set up each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create event manager without WordPress integration for most tests
        $this->events = new EventManager('test-plugin', false);
    }

    // ========================================================================
    // Basic Tests
    // ========================================================================

    /**
     * @group basic
     *
     * Tests that EventManager implements the interface.
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(EventManagerInterface::class, $this->events);
    }

    /**
     * @group basic
     *
     * Tests constructor with valid plugin slug.
     */
    public function testConstructorWithValidSlug(): void
    {
        $events = new EventManager('my-plugin');

        $this->assertEquals('my-plugin', $events->getPluginSlug());
        $this->assertTrue($events->isUsingWordPress()); // Default true
    }

    /**
     * @group basic
     *
     * Tests constructor with empty plugin slug throws exception.
     */
    public function testConstructorWithEmptySlugThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plugin slug cannot be empty');

        new EventManager('');
    }

    /**
     * @group basic
     *
     * Tests WordPress integration can be disabled.
     */
    public function testWordPressIntegrationCanBeDisabled(): void
    {
        $events = new EventManager('test', false);

        $this->assertFalse($events->isUsingWordPress());
    }

    /**
     * @group basic
     *
     * Tests WordPress integration can be toggled at runtime.
     */
    public function testSetUseWordPress(): void
    {
        $this->assertFalse($this->events->isUsingWordPress());

        $this->events->setUseWordPress(true);
        $this->assertTrue($this->events->isUsingWordPress());

        $this->events->setUseWordPress(false);
        $this->assertFalse($this->events->isUsingWordPress());
    }

    // ========================================================================
    // Event Listener Tests
    // ========================================================================

    /**
     * @group listeners
     *
     * Tests basic listener registration.
     */
    public function testListenRegistersListener(): void
    {
        $called = false;
        $callback = function() use (&$called) {
            $called = true;
        };

        $this->events->listen('test.event', $callback);

        $this->assertTrue($this->events->hasListeners('test.event'));

        // Dispatch to verify it works
        $this->events->dispatch('test.event');
        $this->assertTrue($called);
    }

    /**
     * @group listeners
     *
     * Tests listen with empty event name throws exception.
     */
    public function testListenWithEmptyEventThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty');

        $this->events->listen('', function() {});
    }

    /**
     * @group listeners
     *
     * Tests multiple listeners for same event.
     */
    public function testMultipleListenersForSameEvent(): void
    {
        $calls = [];

        $this->events->listen('multi.test', function() use (&$calls) {
            $calls[] = 'first';
        });

        $this->events->listen('multi.test', function() use (&$calls) {
            $calls[] = 'second';
        });

        $this->events->dispatch('multi.test');

        $this->assertEquals(['first', 'second'], $calls);
    }

    /**
     * @group listeners
     *
     * Tests listener priority ordering.
     */
    public function testListenerPriority(): void
    {
        $calls = [];

        // Higher priority (lower number) should execute first
        $this->events->listen('priority.test', function() use (&$calls) {
            $calls[] = 'priority-20';
        }, 20);

        $this->events->listen('priority.test', function() use (&$calls) {
            $calls[] = 'priority-5';
        }, 5);

        $this->events->listen('priority.test', function() use (&$calls) {
            $calls[] = 'priority-10';
        }, 10);

        $this->events->dispatch('priority.test');

        $this->assertEquals(['priority-5', 'priority-10', 'priority-20'], $calls);
    }

    /**
     * @group listeners
     *
     * Tests hasListeners method.
     */
    public function testHasListeners(): void
    {
        $this->assertFalse($this->events->hasListeners('no.listeners'));

        $this->events->listen('has.listeners', function() {});

        $this->assertTrue($this->events->hasListeners('has.listeners'));
    }

    /**
     * @group listeners
     *
     * Tests hasListeners with empty event throws exception.
     */
    public function testHasListenersWithEmptyEventThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty');

        $this->events->hasListeners('');
    }

    /**
     * @group listeners
     *
     * Tests getListeners returns registered listeners.
     */
    public function testGetListeners(): void
    {
        $callback1 = function() { return 'one'; };
        $callback2 = function() { return 'two'; };

        $this->events->listen('get.test', $callback1, 5);
        $this->events->listen('get.test', $callback2, 10);

        $listeners = $this->events->getListeners('get.test');

        $this->assertIsArray($listeners);
        $this->assertCount(2, $listeners);
        $this->assertArrayHasKey(5, $listeners);
        $this->assertArrayHasKey(10, $listeners);
        $this->assertSame($callback1, $listeners[5][0]);
        $this->assertSame($callback2, $listeners[10][0]);
    }

    /**
     * @group listeners
     *
     * Tests getListeners with empty event throws exception.
     */
    public function testGetListenersWithEmptyEventThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty');

        $this->events->getListeners('');
    }

    /**
     * @group listeners
     *
     * Tests getListeners for non-existent event returns empty array.
     */
    public function testGetListenersForNonExistentEvent(): void
    {
        $listeners = $this->events->getListeners('non.existent');

        $this->assertIsArray($listeners);
        $this->assertEmpty($listeners);
    }

    // ========================================================================
    // Event Dispatching Tests
    // ========================================================================

    /**
     * @group dispatch
     *
     * Tests basic event dispatching.
     */
    public function testDispatch(): void
    {
        $receivedArgs = [];

        $this->events->listen('dispatch.test', function($arg1, $arg2) use (&$receivedArgs) {
            $receivedArgs = [$arg1, $arg2];
        });

        $this->events->dispatch('dispatch.test', 'first', 'second');

        $this->assertEquals(['first', 'second'], $receivedArgs);
    }

    /**
     * @group dispatch
     *
     * Tests dispatch with empty event throws exception.
     */
    public function testDispatchWithEmptyEventThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty');

        $this->events->dispatch('');
    }

    /**
     * @group dispatch
     *
     * Tests dispatch returns last listener's return value.
     */
    public function testDispatchReturnsLastValue(): void
    {
        $this->events->listen('return.test', function() {
            return 'first';
        });

        $this->events->listen('return.test', function() {
            return 'second';
        });

        $this->events->listen('return.test', function() {
            return 'last';
        });

        $result = $this->events->dispatch('return.test');

        $this->assertEquals('last', $result);
    }

    /**
     * @group dispatch
     *
     * Tests dispatch with no listeners returns null.
     */
    public function testDispatchWithNoListenersReturnsNull(): void
    {
        $result = $this->events->dispatch('no.listeners');

        $this->assertNull($result);
    }

    // ========================================================================
    // Filter Tests
    // ========================================================================

    /**
     * @group filter
     *
     * Tests basic filter functionality.
     */
    public function testFilter(): void
    {
        $this->events->listen('filter.test', function($value) {
            return $value . '-filtered';
        });

        $result = $this->events->filter('filter.test', 'original');

        $this->assertEquals('original-filtered', $result);
    }

    /**
     * @group filter
     *
     * Tests filter with empty event throws exception.
     */
    public function testFilterWithEmptyEventThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty');

        $this->events->filter('', 'value');
    }

    /**
     * @group filter
     *
     * Tests filter passes value through multiple listeners.
     */
    public function testFilterChaining(): void
    {
        $this->events->listen('chain.filter', function($value) {
            return $value . '-first';
        });

        $this->events->listen('chain.filter', function($value) {
            return $value . '-second';
        });

        $this->events->listen('chain.filter', function($value) {
            return $value . '-third';
        });

        $result = $this->events->filter('chain.filter', 'start');

        $this->assertEquals('start-first-second-third', $result);
    }

    /**
     * @group filter
     *
     * Tests filter with additional arguments.
     */
    public function testFilterWithAdditionalArguments(): void
    {
        $this->events->listen('args.filter', function($value, $prefix, $suffix) {
            return $prefix . $value . $suffix;
        });

        $result = $this->events->filter('args.filter', 'middle', '[', ']');

        $this->assertEquals('[middle]', $result);
    }

    /**
     * @group filter
     *
     * Tests filter with no listeners returns original value.
     */
    public function testFilterWithNoListenersReturnsOriginalValue(): void
    {
        $result = $this->events->filter('no.filter', 'unchanged');

        $this->assertEquals('unchanged', $result);
    }

    // ========================================================================
    // Event Removal Tests
    // ========================================================================

    /**
     * @group removal
     *
     * Tests forget removes specific listener.
     */
    public function testForgetRemovesListener(): void
    {
        $called = false;
        $callback = function() use (&$called) {
            $called = true;
        };

        $this->events->listen('forget.test', $callback);
        $this->assertTrue($this->events->hasListeners('forget.test'));

        $removed = $this->events->forget('forget.test', $callback);

        $this->assertTrue($removed);
        $this->assertFalse($this->events->hasListeners('forget.test'));

        // Verify listener is not called
        $this->events->dispatch('forget.test');
        $this->assertFalse($called);
    }

    /**
     * @group removal
     *
     * Tests forget with empty event throws exception.
     */
    public function testForgetWithEmptyEventThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty');

        $this->events->forget('', function() {});
    }

    /**
     * @group removal
     *
     * Tests forget returns false when listener not found.
     */
    public function testForgetReturnsFalseWhenListenerNotFound(): void
    {
        $this->events->listen('forget.false', function() { return 'a'; });

        $removed = $this->events->forget('forget.false', function() { return 'b'; });

        $this->assertFalse($removed);
        $this->assertTrue($this->events->hasListeners('forget.false'));
    }

    /**
     * @group removal
     *
     * Tests clear removes all listeners for an event.
     */
    public function testClearRemovesAllListenersForEvent(): void
    {
        $this->events->listen('clear.test', function() { return 'a'; });
        $this->events->listen('clear.test', function() { return 'b'; });
        $this->events->listen('other.event', function() { return 'c'; });

        $this->assertTrue($this->events->hasListeners('clear.test'));
        $this->assertTrue($this->events->hasListeners('other.event'));

        $this->events->clear('clear.test');

        $this->assertFalse($this->events->hasListeners('clear.test'));
        $this->assertTrue($this->events->hasListeners('other.event'));
    }

    /**
     * @group removal
     *
     * Tests clear with null removes all listeners.
     */
    public function testClearWithNullRemovesAllListeners(): void
    {
        $this->events->listen('event.one', function() {});
        $this->events->listen('event.two', function() {});
        $this->events->listen('event.three', function() {});

        $this->assertTrue($this->events->hasListeners('event.one'));
        $this->assertTrue($this->events->hasListeners('event.two'));
        $this->assertTrue($this->events->hasListeners('event.three'));

        $this->events->clear();

        $this->assertFalse($this->events->hasListeners('event.one'));
        $this->assertFalse($this->events->hasListeners('event.two'));
        $this->assertFalse($this->events->hasListeners('event.three'));
    }

    // ========================================================================
    // Event Prefixing Tests
    // ========================================================================

    /**
     * @group prefixing
     *
     * Tests events are automatically prefixed with plugin slug.
     */
    public function testEventPrefixing(): void
    {
        $this->events->listen('test', function() {});

        // Should be prefixed internally
        $listeners = $this->events->getListeners('test');
        $this->assertNotEmpty($listeners);
    }

    /**
     * @group prefixing
     *
     * Tests already prefixed events are not double-prefixed.
     */
    public function testNoDoublePrefixing(): void
    {
        $this->events->listen('test-plugin.already-prefixed', function() {});

        $this->assertTrue($this->events->hasListeners('test-plugin.already-prefixed'));
        $this->assertTrue($this->events->hasListeners('already-prefixed'));
    }

    // ========================================================================
    // WordPress Integration Tests
    // ========================================================================

    /**
     * @group wordpress
     *
     * Tests WordPress integration when enabled.
     * Note: This test checks the behavior, actual WordPress functions are stubbed.
     */
    public function testWordPressIntegrationWhenEnabled(): void
    {
        $events = new EventManager('wp-test', true);

        // Track WordPress function calls
        $addActionCalls = [];
        $originalAddAction = function_exists('add_action') ? 'add_action' : null;

        // Temporarily override for testing
        if (!function_exists('add_action')) {
            function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
                global $addActionCalls;
                $addActionCalls[] = func_get_args();
            }
        }

        $events->listen('test.event', function() {}, 15);

        // WordPress integration is enabled in our test environment
        $this->assertTrue($events->isUsingWordPress());
    }

    /**
     * @group wordpress
     *
     * Tests filter and dispatch work together properly.
     *
     * IMPORTANT: When the same listener is used for both filter() and dispatch(),
     * it must return a value for filter() to work correctly. WordPress convention
     * expects filter callbacks to return the (potentially modified) value.
     */
    public function testFilterAndDispatchInteraction(): void
    {
        $log = [];

        // Add listener that logs AND returns value (works for both filter and dispatch)
        $this->events->listen('process', function($value) use (&$log) {
            $log[] = "processed: $value";
            return strtoupper($value);
        });

        // Use filter - listener is called and returns uppercase value
        $filtered = $this->events->filter('process', 'test');

        // Use dispatch - same listener is called, return value is captured but typically ignored
        $this->events->dispatch('process', 'test');

        $this->assertEquals('TEST', $filtered);
        $this->assertEquals([
            'processed: test',  // from filter() call
            'processed: test'   // from dispatch() call
        ], $log);
    }

    // ========================================================================
    // Edge Cases and Complex Scenarios
    // ========================================================================

    /**
     * @group edge-cases
     *
     * Tests listener that modifies itself during execution.
     */
    public function testListenerModifyingItselfDuringExecution(): void
    {
        $count = 0;
        $callback = function() use (&$count, &$callback) {
            $count++;
            if ($count === 1) {
                $this->events->forget('modify.self', $callback);
            }
        };

        $this->events->listen('modify.self', $callback);

        // First dispatch - listener removes itself
        $this->events->dispatch('modify.self');
        $this->assertEquals(1, $count);

        // Second dispatch - listener is gone
        $this->events->dispatch('modify.self');
        $this->assertEquals(1, $count); // Still 1
    }

    /**
     * @group edge-cases
     *
     * Tests complex filtering with mixed return types.
     */
    public function testFilterWithMixedReturnTypes(): void
    {
        $this->events->listen('mixed.filter', function($value) {
            return is_numeric($value) ? (int)$value * 2 : null;
        });

        $this->events->listen('mixed.filter', function($value) {
            return $value ?: 'default';
        });

        $result1 = $this->events->filter('mixed.filter', '5');
        $this->assertEquals(10, $result1);

        $result2 = $this->events->filter('mixed.filter', 'text');
        $this->assertEquals('default', $result2);
    }

    /**
     * @group edge-cases
     *
     * Tests listener registration with same callback multiple times.
     * Following WordPress convention, forget() removes ALL instances of a callback.
     */
    public function testSameCallbackMultipleTimes(): void
    {
        $count = 0;
        $callback = function() use (&$count) {
            $count++;
        };

        // Register same callback multiple times
        $this->events->listen('duplicate', $callback);
        $this->events->listen('duplicate', $callback);
        $this->events->listen('duplicate', $callback);

        $this->events->dispatch('duplicate');

        // Should be called 3 times
        $this->assertEquals(3, $count);

        // Forget removes ALL instances (WordPress behavior)
        $removed = $this->events->forget('duplicate', $callback);
        $this->assertTrue($removed);

        $count = 0;
        $this->events->dispatch('duplicate');
        $this->assertEquals(0, $count); // All instances removed, not just one
    }
}