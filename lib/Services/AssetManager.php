<?php
/**
 * Asset Manager Service
 *
 * Manages registration and enqueuing of JavaScript and CSS assets for WordPress plugins.
 * Provides a fluent interface for adding scripts and styles with automatic URL resolution.
 *
 * ## Usage Example:
 * ```php
 * $assets = new AssetManager($pluginUrl, '1.0.0');
 *
 * $assets->addScript('my-admin', 'assets/js/admin.js', ['jquery'])
 *        ->addStyle('my-admin', 'assets/css/admin.css')
 *        ->enqueue();
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

use InvalidArgumentException;

/**
 * Asset Manager Class
 *
 * Handles registration and enqueuing of plugin assets with WordPress.
 *
 * @since 1.0.0
 */
class AssetManager
{
    /**
     * Plugin URL for resolving relative asset paths
     *
     * @var string
     */
    private string $pluginUrl;

    /**
     * Default version string for cache busting
     *
     * @var string
     */
    private string $version;

    /**
     * Registered scripts
     *
     * @var array<string, array>
     */
    private array $scripts = [];

    /**
     * Registered styles
     *
     * @var array<string, array>
     */
    private array $styles = [];

    /**
     * Inline scripts to add
     *
     * @var array<string, string>
     */
    private array $inlineScripts = [];

    /**
     * Inline styles to add
     *
     * @var array<string, string>
     */
    private array $inlineStyles = [];

    /**
     * Create new asset manager instance
     *
     * @param string $pluginUrl Base URL for the plugin
     * @param string $version Default version for assets
     *
     * @throws InvalidArgumentException If plugin URL or version is empty
     *
     * @since 1.0.0
     */
    public function __construct(string $pluginUrl, string $version)
    {
        if (empty($pluginUrl)) {
            throw new InvalidArgumentException('Plugin URL cannot be empty');
        }

        if (empty($version)) {
            throw new InvalidArgumentException('Version cannot be empty');
        }

        $this->pluginUrl = rtrim($pluginUrl, '/');
        $this->version = $version;
    }

    /**
     * Register a script for enqueuing
     *
     * @param string $handle Unique script handle
     * @param string $src Script source URL or relative path
     * @param array<string> $deps Script dependencies
     * @param bool $inFooter Whether to load in footer (default: true)
     *
     * @return self For method chaining
     *
     * @throws InvalidArgumentException If handle is empty
     *
     * @since 1.0.0
     */
    public function addScript(
        string $handle,
        string $src,
        array $deps = [],
        bool $inFooter = true
    ): self {
        if (empty($handle)) {
            throw new InvalidArgumentException('Script handle cannot be empty');
        }

        $this->scripts[$handle] = [
            'src' => $this->resolveUrl($src),
            'deps' => $deps,
            'ver' => $this->version,
            'footer' => $inFooter
        ];

        return $this;
    }

    /**
     * Register a style for enqueuing
     *
     * @param string $handle Unique style handle
     * @param string $src Style source URL or relative path
     * @param array<string> $deps Style dependencies
     * @param string $media Media query (default: 'all')
     *
     * @return self For method chaining
     *
     * @throws InvalidArgumentException If handle is empty
     *
     * @since 1.0.0
     */
    public function addStyle(
        string $handle,
        string $src,
        array $deps = [],
        string $media = 'all'
    ): self {
        if (empty($handle)) {
            throw new InvalidArgumentException('Style handle cannot be empty');
        }

        $this->styles[$handle] = [
            'src' => $this->resolveUrl($src),
            'deps' => $deps,
            'ver' => $this->version,
            'media' => $media
        ];

        return $this;
    }

    /**
     * Add localization data for a script
     *
     * @param string $handle Script handle to attach data to
     * @param string $objectName JavaScript object name
     * @param array<string, mixed> $data Data to localize
     *
     * @return self For method chaining
     *
     * @throws InvalidArgumentException If script not registered
     *
     * @since 1.0.0
     */
    public function localizeScript(string $handle, string $objectName, array $data): self
    {
        if (!isset($this->scripts[$handle])) {
            throw new InvalidArgumentException("Script handle '$handle' not registered");
        }

        $this->scripts[$handle]['localize'] = [
            'name' => $objectName,
            'data' => $data
        ];

        return $this;
    }

    /**
     * Add inline script to be printed after a script
     *
     * @param string $handle Script handle to attach to
     * @param string $data Inline JavaScript code
     *
     * @return self For method chaining
     *
     * @since 1.0.0
     */
    public function addInlineScript(string $handle, string $data): self
    {
        $this->inlineScripts[$handle] = $data;
        return $this;
    }

    /**
     * Add inline style to be printed after a style
     *
     * @param string $handle Style handle to attach to
     * @param string $data Inline CSS code
     *
     * @return self For method chaining
     *
     * @since 1.0.0
     */
    public function addInlineStyle(string $handle, string $data): self
    {
        $this->inlineStyles[$handle] = $data;
        return $this;
    }

    /**
     * Enqueue all registered scripts and styles
     *
     * Should be called during appropriate WordPress hooks (wp_enqueue_scripts,
     * admin_enqueue_scripts, etc).
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function enqueue(): void
    {
        if (!function_exists('wp_enqueue_script')) {
            return; // Not in WordPress context
        }

        // Enqueue scripts
        foreach ($this->scripts as $handle => $script) {
            wp_enqueue_script(
                $handle,
                $script['src'],
                $script['deps'],
                $script['ver'],
                $script['footer']
            );

            // Add localization if present
            if (isset($script['localize']) && function_exists('wp_localize_script')) {
                wp_localize_script(
                    $handle,
                    $script['localize']['name'],
                    $script['localize']['data']
                );
            }

            // Add inline script if present
            if (isset($this->inlineScripts[$handle]) && function_exists('wp_add_inline_script')) {
                wp_add_inline_script($handle, $this->inlineScripts[$handle]);
            }
        }

        // Enqueue styles
        foreach ($this->styles as $handle => $style) {
            wp_enqueue_style(
                $handle,
                $style['src'],
                $style['deps'],
                $style['ver'],
                $style['media']
            );

            // Add inline style if present
            if (isset($this->inlineStyles[$handle]) && function_exists('wp_add_inline_style')) {
                wp_add_inline_style($handle, $this->inlineStyles[$handle]);
            }
        }
    }

    /**
     * Resolve URL from relative path or absolute URL
     *
     * @param string $src Source path or URL
     *
     * @return string Resolved URL
     *
     * @since 1.0.0
     */
    private function resolveUrl(string $src): string
    {
        // Empty source returns empty (WordPress handles as inline only)
        if (empty($src)) {
            return '';
        }

        // Already an absolute URL or protocol-relative
        if (filter_var($src, FILTER_VALIDATE_URL) || strpos($src, '//') === 0) {
            return $src;
        }

        // Relative to plugin
        return $this->pluginUrl . '/' . ltrim($src, '/');
    }
}