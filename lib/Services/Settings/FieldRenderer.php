<?php
/**
 * Field Renderer
 *
 * Renders form fields for the WordPress Settings API.
 * Supports common field types with proper escaping and WordPress standards.
 *
 * @package     Furgo\Sitechips\Core\Services\Settings
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Services\Settings;

/**
 * Field Renderer Class
 *
 * Handles rendering of different field types for settings forms.
 *
 * @since 1.0.0
 */
class FieldRenderer
{
    /**
     * Option name for form fields
     *
     * @var string
     */
    private string $optionName;

    /**
     * Create new field renderer
     *
     * @param string $optionName WordPress option name
     *
     * @since 1.0.0
     */
    public function __construct(string $optionName)
    {
        $this->optionName = $optionName;
    }

    /**
     * Render a field based on its type
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current field value
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function render(array $field, mixed $value): void
    {
        $type = $field['type'] ?? 'text';

        // Render field by type
        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'password':
                $this->renderTextField($field, $value, $type);
                break;

            case 'number':
                $this->renderNumberField($field, $value);
                break;

            case 'textarea':
                $this->renderTextarea($field, $value);
                break;

            case 'checkbox':
                $this->renderCheckbox($field, $value);
                break;

            case 'radio':
                $this->renderRadio($field, $value);
                break;

            case 'select':
                $this->renderSelect($field, $value);
                break;

            case 'color':
                $this->renderColorPicker($field, $value);
                break;

            default:
                $this->renderCustomField($field, $value);
        }

        // Render description if provided
        if (!empty($field['description'])) {
            printf(
                '<p class="description">%s</p>',
                esc_html($field['description'])
            );
        }
    }

    /**
     * Render text-based input fields
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     * @param string $type Input type
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderTextField(array $field, mixed $value, string $type = 'text'): void
    {
        printf(
            '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" %s />',
            esc_attr($type),
            esc_attr($field['id']),
            esc_attr($this->optionName),
            esc_attr($field['id']),
            esc_attr((string) $value),
            $field['required'] ? 'required' : ''
        );
    }

    /**
     * Render number input field
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderNumberField(array $field, mixed $value): void
    {
        $min = isset($field['min']) ? sprintf('min="%d"', $field['min']) : '';
        $max = isset($field['max']) ? sprintf('max="%d"', $field['max']) : '';
        $step = isset($field['step']) ? sprintf('step="%s"', $field['step']) : '';

        printf(
            '<input type="number" id="%s" name="%s[%s]" value="%s" class="small-text" %s %s %s %s />',
            esc_attr($field['id']),
            esc_attr($this->optionName),
            esc_attr($field['id']),
            esc_attr((string) $value),
            $min,
            $max,
            $step,
            $field['required'] ? 'required' : ''
        );
    }

    /**
     * Render textarea field
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderTextarea(array $field, mixed $value): void
    {
        $rows = $field['rows'] ?? 5;
        $cols = $field['cols'] ?? 50;

        printf(
            '<textarea id="%s" name="%s[%s]" rows="%d" cols="%d" class="large-text" %s>%s</textarea>',
            esc_attr($field['id']),
            esc_attr($this->optionName),
            esc_attr($field['id']),
            $rows,
            $cols,
            $field['required'] ? 'required' : '',
            esc_textarea((string) $value)
        );
    }

    /**
     * Render checkbox field
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderCheckbox(array $field, mixed $value): void
    {
        printf(
            '<label for="%s"><input type="checkbox" id="%s" name="%s[%s]" value="1" %s /> %s</label>',
            esc_attr($field['id']),
            esc_attr($field['id']),
            esc_attr($this->optionName),
            esc_attr($field['id']),
            checked(1, (int) $value, false),
            esc_html($field['label'] ?? 'Enable')
        );
    }

    /**
     * Render radio button group
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderRadio(array $field, mixed $value): void
    {
        $options = $field['options'] ?? [];

        foreach ($options as $optionValue => $optionLabel) {
            printf(
                '<label><input type="radio" name="%s[%s]" value="%s" %s /> %s</label><br />',
                esc_attr($this->optionName),
                esc_attr($field['id']),
                esc_attr((string) $optionValue),
                checked($optionValue, $value, false),
                esc_html($optionLabel)
            );
        }
    }

    /**
     * Render select dropdown
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderSelect(array $field, mixed $value): void
    {
        $options = $field['options'] ?? [];
        $multiple = $field['multiple'] ?? false;

        printf(
            '<select id="%s" name="%s[%s]%s" %s>',
            esc_attr($field['id']),
            esc_attr($this->optionName),
            esc_attr($field['id']),
            $multiple ? '[]' : '',
            $multiple ? 'multiple' : ''
        );

        // Add empty option if not required and not multiple
        if (!$field['required'] && !$multiple) {
            echo '<option value="">— Select —</option>';
        }

        foreach ($options as $optionValue => $optionLabel) {
            $selected = $multiple && is_array($value)
                ? in_array($optionValue, $value, true)
                : $optionValue == $value;

            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr((string) $optionValue),
                selected($selected, true, false),
                esc_html($optionLabel)
            );
        }

        echo '</select>';
    }

    /**
     * Render color picker field
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderColorPicker(array $field, mixed $value): void
    {
        // Default to white if no value
        $value = !empty($value) ? $value : '#ffffff';

        printf(
            '<input type="text" id="%s" name="%s[%s]" value="%s" class="wp-color-picker" data-default-color="%s" />',
            esc_attr($field['id']),
            esc_attr($this->optionName),
            esc_attr($field['id']),
            esc_attr((string) $value),
            esc_attr($field['default'] ?? '#ffffff')
        );

        // Add script to initialize color picker
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            add_action('admin_footer', function() use ($field) {
                printf(
                    '<script>jQuery(document).ready(function($) { $("#%s").wpColorPicker(); });</script>',
                    esc_js($field['id'])
                );
            });
        }
    }

    /**
     * Render custom field type
     *
     * @param array<string, mixed> $field Field configuration
     * @param mixed $value Current value
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function renderCustomField(array $field, mixed $value): void
    {
        // Allow custom rendering callback
        if (isset($field['render_callback']) && is_callable($field['render_callback'])) {
            call_user_func($field['render_callback'], $field, $value, $this->optionName);
        } else {
            // Fallback to text field
            $this->renderTextField($field, $value);
        }
    }
}