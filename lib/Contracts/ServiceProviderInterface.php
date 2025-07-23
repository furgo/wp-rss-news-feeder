<?php
/**
 * Service Provider Interface
 *
 * Defines the contract for service providers in the Sitechips framework.
 * Service providers are responsible for registering services into the
 * container and booting them when needed.
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
 * Service Provider Interface
 *
 * Service providers organize related services and their dependencies.
 * They handle the registration phase (defining services) and the boot
 * phase (WordPress integration).
 *
 * @since 1.0.0
 */
interface ServiceProviderInterface
{
    /**
     * Register services into the container
     *
     * This method is called during the registration phase, before WordPress
     * is fully loaded. Only register services here - do not call WordPress
     * functions or register hooks.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function register(): void;

    /**
     * Boot services after registration
     *
     * This method is called after all providers have been registered and
     * WordPress is ready. Use this for WordPress hook registration and
     * initialization that requires other services to be available.
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function boot(): void;

    /**
     * Check if the provider has been registered
     *
     * @return bool True if registered, false otherwise
     *
     * @since 1.0.0
     */
    public function isRegistered(): bool;

    /**
     * Check if the provider has been booted
     *
     * @return bool True if booted, false otherwise
     *
     * @since 1.0.0
     */
    public function isBooted(): bool;

    /**
     * Mark the provider as registered
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function markAsRegistered(): void;

    /**
     * Mark the provider as booted
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function markAsBooted(): void;
}