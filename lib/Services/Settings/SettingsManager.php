<?php
/**
 * Settings Manager
 *
 * Core functionality for managing WordPress plugin settings using the Settings API.
 * Provides a fluent interface for defining sections and fields with automatic
 * registration, validation, and data persistence.
 *
 * ## Usage Example:
 * ```php
 * $settings = new SettingsManager('my_plugin_settings');
 *
 * $settings->addSection('general', 'General Settings')
 *          ->addField('api_key', 'API Key', 'general', 'text', [
 *              'description' => 'Your API key',
 *              'required' => true
 *          ])
 *          ->register();
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
 * Settings Manager Class
 *
 * Manages settings sections and fields for WordPress plugins.
 *
 * @since 1.0.0
 */
class SettingsManager
{
    /**
     * Option group/name for settings
     *
     * @var string
     */
    private string $optionName;

    /**
     * Settings sections
     *
     * @var array<string, array{title: string, callback: ?callable, priority: int}>
     */
    private array $sections = [];

    /**
     * Settings fields
     *
     * @var array<string, array>
     */
    private array $fields = [];

    /**
     * Field renderer instance
     *
     * @var FieldRenderer|null
     */
    private ?FieldRenderer $renderer = null;

    /**
     * Whether settings have been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Create new settings manager
     *
     * @param string $optionName WordPress option name for storing settings
     *
     * @throws InvalidArgumentException If option name is empty
     *
     * @since 1.0.0
     */
    public function __construct(string $optionName)
    {
        if (empty($optionName)) {
            throw new InvalidArgumentException('Option name cannot be empty');
        }

        $this->optionName = $optionName;
    }

    /**
     * Add a settings section
     *
     * @param string $id Section identifier
     * @param string $title Section title
     * @param callable|null $callback Optional callback for section description
     * @param int $priority Section priority for ordering (default: 10)
     *
     * @return self For method chaining
     *
     * @throws InvalidArgumentException If ID or title is empty
     *
     * @since 1.0.0
     */
    public function addSection(
        string $id,
        string $title,
        ?callable $callback = null,
        int $priority = 10
    ): self {
        if (empty($id)) {
            throw new InvalidArgumentException('Section ID cannot be empty');
        }

        if (empty($title)) {
            throw new InvalidArgumentException('Section title cannot be empty');
        }

        $this->sections[$id] = [
            'title' => $title,
            'callback' => $callback,
            'priority' => $priority
        ];

        return $this;
    }

    /**
     * Add a settings field
     *
     * @param string $id Field identifier
     * @param string $title Field title/label
     * @param string $section Section ID this field belongs to
     * @param string $type Field type (text, checkbox, select, etc.)
     * @param array<string, mixed> $args Additional field arguments
     *
     * @return self For method chaining
     *
     * @throws InvalidArgumentException If required parameters are empty or section doesn't exist
     *
     * @since 1.0.0
     */
    public function addField(
        string $id,
        string $title,
        string $section,
        string $type = 'text',
        array $args = []
    ): self {
        if (empty($id)) {
            throw new InvalidArgumentException('Field ID cannot be empty');
        }

        if (empty($title)) {
            throw new InvalidArgumentException('Field title cannot be empty');
        }

        if (!isset($this->sections[$section])) {
            throw new InvalidArgumentException("Section '$section' does not exist");
        }

        $this->fields[$id] = array_merge([
            'id' => $id,
            'title' => $title,
            'section' => $section,
            'type' => $type,
            'default' => '',
            'description' => '',
            'required' => false,
            'sanitize_callback' => null,
            'validate_callback' => null,
        ], $args);

        return $this;
    }

    /**
     * Get a single setting value
     *
     * @param string $fieldId Field identifier
     * @param mixed $default Default value if not set
     *
     * @return mixed Field value or default
     *
     * @since 1.0.0
     */
    public function getValue(string $fieldId, mixed $default = null): mixed
    {
        $values = $this->getValues();
        return $values[$fieldId] ?? $default;
    }

    /**
     * Get all setting values
     *
     * @return array<string, mixed> All settings values
     *
     * @since 1.0.0
     */
    public function getValues(): array
    {
        $values = get_option($this->optionName, []);

        // Ensure we return an array
        if (!is_array($values)) {
            return [];
        }

        // Apply defaults for missing values
        foreach ($this->fields as $fieldId => $field) {
            if (!isset($values[$fieldId]) && isset($field['default'])) {
                $values[$fieldId] = $field['default'];
            }
        }

        return $values;
    }

    /**
     * Register settings with WordPress
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

        add_action('admin_init', [$this, 'registerSettings']);
        $this->registered = true;
    }

    /**
     * Register settings callback
     *
     * @internal Called by WordPress admin_init hook
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function registerSettings(): void
    {
        // Register setting with sanitization
        \register_setting(
            $this->optionName,
            $this->optionName,
            [
                'sanitize_callback' => [$this, 'sanitizeValues'],
                'default' => []
            ]
        );

        // Sort sections by priority
        uasort($this->sections, fn($a, $b) => $a['priority'] <=> $b['priority']);

        // Register sections
        foreach ($this->sections as $id => $section) {
            \add_settings_section(
                $id,
                $section['title'],
                $section['callback'] ?? '__return_null',
                $this->optionName
            );
        }

        // Register fields
        foreach ($this->fields as $id => $field) {
            \add_settings_field(
                $id,
                $field['title'],
                [$this, 'renderField'],
                $this->optionName,
                $field['section'],
                ['field' => $field]
            );
        }
    }

    /**
     * Render a field
     *
     * @internal Called by WordPress for each field
     *
     * @param array{field: array} $args Field arguments
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function renderField(array $args): void
    {
        $field = $args['field'];
        $value = $this->getValue($field['id'], $field['default']);

        // Get renderer
        if ($this->renderer === null) {
            $this->renderer = new FieldRenderer($this->optionName);
        }

        // Render field
        $this->renderer->render($field, $value);
    }

    /**
     * Sanitize all field values
     *
     * @internal Called by WordPress when saving settings
     *
     * @param mixed $values Raw values from form
     *
     * @return array<string, mixed> Sanitized values
     *
     * @since 1.0.0
     */
    public function sanitizeValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $sanitized = [];

        foreach ($this->fields as $fieldId => $field) {
            $value = $values[$fieldId] ?? null;

            // Apply field-specific sanitization
            if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
                $sanitized[$fieldId] = call_user_func($field['sanitize_callback'], $value);
            } else {
                $sanitized[$fieldId] = $this->sanitizeByType($value, $field['type']);
            }

            // Validate if callback provided
            if (isset($field['validate_callback']) && is_callable($field['validate_callback'])) {
                $valid = call_user_func($field['validate_callback'], $sanitized[$fieldId]);
                if (!$valid) {
                    if (function_exists('add_settings_error')) {
                        \add_settings_error(
                            $this->optionName,
                            $fieldId,
                            sprintf('Invalid value for %s', $field['title'])
                        );
                    }
                    // Keep previous value on validation error
                    $sanitized[$fieldId] = $this->getValue($fieldId);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Default sanitization by field type
     *
     * @param mixed $value Value to sanitize
     * @param string $type Field type
     *
     * @return mixed Sanitized value
     *
     * @since 1.0.0
     */
    private function sanitizeByType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'text', 'password' => sanitize_text_field((string) $value),
            'textarea' => sanitize_textarea_field((string) $value),
            'email' => sanitize_email((string) $value),
            'url' => esc_url_raw((string) $value),
            'number' => (int) $value,
            'checkbox' => !empty($value) ? 1 : 0,
            'select', 'radio' => sanitize_text_field((string) $value),
            'color' => sanitize_hex_color((string) $value),
            default => sanitize_text_field((string) $value),
        };
    }

    /**
     * Get option name
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getOptionName(): string
    {
        return $this->optionName;
    }

    /**
     * Get all sections
     *
     * @return array<string, array{title: string, callback: ?callable, priority: int}>
     *
     * @since 1.0.0
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Get all fields
     *
     * @return array<string, array>
     *
     * @since 1.0.0
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}