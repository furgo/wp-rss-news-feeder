<?php
/**
 * Settings Page
 *
 * Renders WordPress admin settings pages with configurable menu position.
 * Works together with SettingsManager to provide a complete settings solution.
 *
 * ## Usage Example:
 * ```php
 * $settings = new SettingsManager('my_plugin_settings');
 * $settings->addSection('general', 'General')
 *          ->addField('api_key', 'API Key', 'general');
 *
 * $page = new SettingsPage($settings, [
 *     'page_title' => 'My Plugin Settings',
 *     'menu_title' => 'My Plugin',
 *     'capability' => 'manage_options',
 *     'menu_slug'  => 'my-plugin',
 *     'position'   => 'settings' // or 'tools', 'toplevel'
 * ]);
 *
 * $page->register();
 * ```
 *
 * @package     Furgo\Sitechips\Core\Services\Settings
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Services\Settings;

use InvalidArgumentException;

/**
 * Settings Page Class
 *
 * Creates and manages WordPress admin settings pages.
 *
 * @since 1.0.0
 */
class SettingsPage
{
    /**
     * Settings manager instance
     *
     * @var SettingsManager
     */
    private SettingsManager $settings;

    /**
     * Page configuration
     *
     * @var array{
     *   page_title: string,
     *   menu_title: string,
     *   capability: string,
     *   menu_slug: string,
     *   position: string,
     *   icon_url?: string,
     *   menu_position?: int
     * }
     */
    private array $config;

    /**
     * Whether page has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Create new settings page
     *
     * @param SettingsManager $settings Settings manager instance
     * @param array<string, mixed> $config Page configuration
     *
     * @throws InvalidArgumentException If required config missing
     *
     * @since 1.0.0
     */
    public function __construct(SettingsManager $settings, array $config)
    {
        $this->settings = $settings;
        $this->config = $this->validateConfig($config);
    }

    /**
     * Register the settings page
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        if ($this->registered || !function_exists('add_action')) {
            return;
        }

        // Register settings manager
        $this->settings->register();

        // Register menu
        add_action('admin_menu', [$this, 'addMenuPage']);

        // Register assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        $this->registered = true;
    }

    /**
     * Add menu page
     *
     * @internal Called by WordPress admin_menu hook
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function addMenuPage(): void
    {
        $position = $this->config['position'];
        $callback = [$this, 'renderPage'];

        switch ($position) {
            case 'toplevel':
                add_menu_page(
                    $this->config['page_title'],
                    $this->config['menu_title'],
                    $this->config['capability'],
                    $this->config['menu_slug'],
                    $callback,
                    $this->config['icon_url'] ?? 'dashicons-admin-generic',
                    $this->config['menu_position'] ?? null
                );
                break;

            case 'settings':
                add_options_page(
                    $this->config['page_title'],
                    $this->config['menu_title'],
                    $this->config['capability'],
                    $this->config['menu_slug'],
                    $callback
                );
                break;

            case 'tools':
                add_management_page(
                    $this->config['page_title'],
                    $this->config['menu_title'],
                    $this->config['capability'],
                    $this->config['menu_slug'],
                    $callback
                );
                break;

            case 'users':
                add_users_page(
                    $this->config['page_title'],
                    $this->config['menu_title'],
                    $this->config['capability'],
                    $this->config['menu_slug'],
                    $callback
                );
                break;

            case 'plugins':
                add_plugins_page(
                    $this->config['page_title'],
                    $this->config['menu_title'],
                    $this->config['capability'],
                    $this->config['menu_slug'],
                    $callback
                );
                break;

            case 'theme':
                add_theme_page(
                    $this->config['page_title'],
                    $this->config['menu_title'],
                    $this->config['capability'],
                    $this->config['menu_slug'],
                    $callback
                );
                break;

            default:
                // Default to settings
                add_options_page(
                    $this->config['page_title'],
                    $this->config['menu_title'],
                    $this->config['capability'],
                    $this->config['menu_slug'],
                    $callback
                );
        }
    }

    /**
     * Render the settings page
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function renderPage(): void
    {
        if (!current_user_can($this->config['capability'])) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->config['page_title']); ?></h1>

            <?php settings_errors($this->settings->getOptionName()); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->settings->getOptionName());
                do_settings_sections($this->settings->getOptionName());
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue assets for settings page
     *
     * @internal Called by WordPress admin_enqueue_scripts hook
     *
     * @param string $hook Current admin page hook
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function enqueueAssets(string $hook): void
    {
        // Get the hook suffix for our page
        $pageHook = $this->getPageHook();

        // Only load on our settings page
        if ($hook !== $pageHook) {
            return;
        }

        // Enqueue color picker if we have color fields
        if ($this->hasColorFields()) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');

            // Initialize color pickers
            wp_add_inline_script('wp-color-picker', '
                jQuery(document).ready(function($) {
                    $(".wp-color-picker").wpColorPicker();
                });
            ');
        }

        // Allow additional assets via action
        do_action($this->config['menu_slug'] . '_enqueue_assets');
    }

    /**
     * Validate configuration
     *
     * @param array<string, mixed> $config Raw configuration
     *
     * @return array Validated configuration
     *
     * @throws InvalidArgumentException If required fields missing
     *
     * @since 1.0.0
     */
    private function validateConfig(array $config): array
    {
        $required = ['page_title', 'menu_title', 'capability', 'menu_slug'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new InvalidArgumentException("Required config field '$field' is missing");
            }
        }

        // Set defaults
        $config['position'] = $config['position'] ?? 'settings';

        // Validate position
        $validPositions = ['toplevel', 'settings', 'tools', 'users', 'plugins', 'theme'];
        if (!in_array($config['position'], $validPositions, true)) {
            throw new InvalidArgumentException("Invalid menu position: {$config['position']}");
        }

        return $config;
    }

    /**
     * Check if settings have color fields
     *
     * @return bool
     *
     * @since 1.0.0
     */
    private function hasColorFields(): bool
    {
        foreach ($this->settings->getFields() as $field) {
            if ($field['type'] === 'color') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the page hook for this settings page
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function getPageHook(): string
    {
        $position = $this->config['position'];
        $slug = $this->config['menu_slug'];

        return match ($position) {
            'toplevel' => 'toplevel_page_' . $slug,
            'settings' => 'settings_page_' . $slug,
            'tools' => 'tools_page_' . $slug,
            'users' => 'users_page_' . $slug,
            'plugins' => 'plugins_page_' . $slug,
            'theme' => 'appearance_page_' . $slug,
            default => 'settings_page_' . $slug,
        };
    }

    /**
     * Get settings manager
     *
     * @return SettingsManager
     *
     * @since 1.0.0
     */
    public function getSettingsManager(): SettingsManager
    {
        return $this->settings;
    }

    /**
     * Get page configuration
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}