<?php
/**
 * Bootable Interface
 *
 * Interface for components that need to be booted after registration.
 * The boot phase occurs after all services are registered and WordPress
 * is ready for hook registration and initialization.
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
 * Bootable Interface
 *
 * Components implementing this interface will have their boot() method
 * called after the registration phase is complete and WordPress is ready
 * for hook registration and initialization.
 *
 * @since 1.0.0
 */
interface Bootable
{
    /**
     * Boot the component
     *
     * This method is called after service registration is complete and
     * WordPress is ready. Use this for WordPress hook registration,
     * event listeners, and other initialization that requires WordPress
     * to be fully loaded or other services to be available.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function boot(): void;
}