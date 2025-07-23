<?php
/**
 * Field Renderer Unit Tests
 *
 * Tests for the FieldRenderer service that renders form fields
 * for the WordPress Settings API.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Services\Settings
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Services\Settings;

use Furgo\Sitechips\Core\Services\Settings\FieldRenderer;
use Furgo\Sitechips\Core\Tests\TestCase;

/**
 * Field Renderer Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Services\Settings\FieldRenderer
 */
class FieldRendererTest extends TestCase
{
    /**
     * Field renderer instance
     *
     * @var FieldRenderer
     */
    private FieldRenderer $renderer;

    /**
     * Test option name
     *
     * @var string
     */
    private string $optionName = 'test_plugin_settings';

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new FieldRenderer($this->optionName);
    }

    /**
     * @group text-fields
     *
     * Tests rendering a basic text field
     */
    public function testRenderTextField(): void
    {
        $field = [
            'id' => 'test_field',
            'type' => 'text',
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, 'test value');
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="text"', $output);
        $this->assertStringContainsString('id="test_field"', $output);
        $this->assertStringContainsString('name="test_plugin_settings[test_field]"', $output);
        $this->assertStringContainsString('value="test value"', $output);
        $this->assertStringContainsString('class="regular-text"', $output);
        $this->assertStringNotContainsString('required', $output);
    }

    /**
     * @group text-fields
     *
     * Tests rendering required text field
     */
    public function testRenderRequiredTextField(): void
    {
        $field = [
            'id' => 'required_field',
            'type' => 'text',
            'required' => true,
            'description' => 'This field is required'
        ];

        ob_start();
        $this->renderer->render($field, '');
        $output = ob_get_clean();

        $this->assertStringContainsString('required', $output);
        $this->assertStringContainsString('<p class="description">This field is required</p>', $output);
    }

    /**
     * @group text-fields
     *
     * Tests rendering email field
     */
    public function testRenderEmailField(): void
    {
        $field = [
            'id' => 'email_field',
            'type' => 'email',
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, 'test@example.com');
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="email"', $output);
        $this->assertStringContainsString('value="test@example.com"', $output);
    }

    /**
     * @group text-fields
     *
     * Tests rendering URL field
     */
    public function testRenderUrlField(): void
    {
        $field = [
            'id' => 'url_field',
            'type' => 'url',
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, 'https://example.com');
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="url"', $output);
        $this->assertStringContainsString('value="https://example.com"', $output);
    }

    /**
     * @group text-fields
     *
     * Tests rendering password field
     */
    public function testRenderPasswordField(): void
    {
        $field = [
            'id' => 'password_field',
            'type' => 'password',
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, 'secret');
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="password"', $output);
        $this->assertStringContainsString('value="secret"', $output);
    }

    /**
     * @group number-field
     *
     * Tests rendering number field
     */
    public function testRenderNumberField(): void
    {
        $field = [
            'id' => 'number_field',
            'type' => 'number',
            'min' => 1,
            'max' => 100,
            'step' => 5,
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, 50);
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="number"', $output);
        $this->assertStringContainsString('value="50"', $output);
        $this->assertStringContainsString('min="1"', $output);
        $this->assertStringContainsString('max="100"', $output);
        $this->assertStringContainsString('step="5"', $output);
        $this->assertStringContainsString('class="small-text"', $output);
    }

    /**
     * @group textarea
     *
     * Tests rendering textarea field
     */
    public function testRenderTextarea(): void
    {
        $field = [
            'id' => 'textarea_field',
            'type' => 'textarea',
            'rows' => 10,
            'cols' => 80,
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, "Line 1\nLine 2");
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('id="textarea_field"', $output);
        $this->assertStringContainsString('rows="10"', $output);
        $this->assertStringContainsString('cols="80"', $output);
        $this->assertStringContainsString('class="large-text"', $output);
        $this->assertStringContainsString(">Line 1\nLine 2</textarea>", $output);
    }

    /**
     * @group checkbox
     *
     * Tests rendering checkbox field
     */
    public function testRenderCheckbox(): void
    {
        $field = [
            'id' => 'checkbox_field',
            'type' => 'checkbox',
            'label' => 'Enable feature'
        ];

        ob_start();
        $this->renderer->render($field, 1);
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="checkbox"', $output);
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('checked=\'checked\'', $output);
        $this->assertStringContainsString('Enable feature</label>', $output);
    }

    /**
     * @group checkbox
     *
     * Tests rendering unchecked checkbox
     */
    public function testRenderUncheckedCheckbox(): void
    {
        $field = [
            'id' => 'checkbox_field',
            'type' => 'checkbox'
        ];

        ob_start();
        $this->renderer->render($field, 0);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('checked', $output);
        $this->assertStringContainsString('Enable</label>', $output); // Default label
    }

    /**
     * @group radio
     *
     * Tests rendering radio buttons
     */
    public function testRenderRadio(): void
    {
        $field = [
            'id' => 'radio_field',
            'type' => 'radio',
            'options' => [
                'option1' => 'Option 1',
                'option2' => 'Option 2',
                'option3' => 'Option 3'
            ]
        ];

        ob_start();
        $this->renderer->render($field, 'option2');
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="radio"', $output);
        $this->assertStringContainsString('value="option1"', $output);
        $this->assertStringContainsString('value="option2"', $output);
        $this->assertStringContainsString('checked=\'checked\'', $output);
        $this->assertStringContainsString('value="option3"', $output);
        $this->assertStringContainsString('Option 1</label>', $output);
        $this->assertStringContainsString('Option 2</label>', $output);
        $this->assertStringContainsString('Option 3</label>', $output);

        // Verify option2 is checked
        $this->assertMatchesRegularExpression('/value="option2"\s+checked=\'checked\'/', $output);
    }

    /**
     * @group select
     *
     * Tests rendering select dropdown
     */
    public function testRenderSelect(): void
    {
        $field = [
            'id' => 'select_field',
            'type' => 'select',
            'options' => [
                'small' => 'Small',
                'medium' => 'Medium',
                'large' => 'Large'
            ],
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, 'medium');
        $output = ob_get_clean();

        $this->assertStringContainsString('<select', $output);
        $this->assertStringContainsString('id="select_field"', $output);
        $this->assertStringContainsString('<option value="">— Select —</option>', $output); // Empty option
        $this->assertStringContainsString('<option value="small"', $output);
        $this->assertStringContainsString('<option value="medium"', $output);
        $this->assertStringContainsString('selected=\'selected\'', $output);
        $this->assertStringContainsString('<option value="large"', $output);

        // Verify medium is selected
        $this->assertMatchesRegularExpression('/<option value="medium"\s+selected=\'selected\'>Medium<\/option>/', $output);
    }

    /**
     * @group select
     *
     * Tests rendering multiple select
     */
    public function testRenderMultipleSelect(): void
    {
        $field = [
            'id' => 'multi_select',
            'type' => 'select',
            'multiple' => true,
            'options' => [
                'red' => 'Red',
                'green' => 'Green',
                'blue' => 'Blue'
            ],
            'required' => false  // Add required field to avoid warning
        ];

        ob_start();
        $this->renderer->render($field, ['red', 'blue']);
        $output = ob_get_clean();

        $this->assertStringContainsString('name="test_plugin_settings[multi_select][]"', $output);
        $this->assertStringContainsString('multiple', $output);
        $this->assertStringNotContainsString('— Select —', $output); // No empty option for multiple

        // Check that red and blue are selected
        $this->assertMatchesRegularExpression('/<option value="red"\s+selected=\'selected\'>Red<\/option>/', $output);
        $this->assertMatchesRegularExpression('/<option value="blue"\s+selected=\'selected\'>Blue<\/option>/', $output);

        // Check that green is NOT selected
        $this->assertDoesNotMatchRegularExpression('/<option value="green"\s+selected=\'selected\'>/', $output);
    }

    /**
     * @group color
     *
     * Tests rendering color picker field
     */
    public function testRenderColorPicker(): void
    {
        $field = [
            'id' => 'color_field',
            'type' => 'color',
            'default' => '#0073aa'
        ];

        ob_start();
        $this->renderer->render($field, '#ff0000');
        $output = ob_get_clean();

        $this->assertStringContainsString('<input type="text"', $output);
        $this->assertStringContainsString('class="wp-color-picker"', $output);
        $this->assertStringContainsString('value="#ff0000"', $output);
        $this->assertStringContainsString('data-default-color="#0073aa"', $output);
    }

    /**
     * @group color
     *
     * Tests rendering color picker with empty value
     */
    public function testRenderColorPickerEmptyValue(): void
    {
        $field = [
            'id' => 'color_field',
            'type' => 'color'
        ];

        ob_start();
        $this->renderer->render($field, '');
        $output = ob_get_clean();

        $this->assertStringContainsString('value="#ffffff"', $output); // Default to white
        $this->assertStringContainsString('data-default-color="#ffffff"', $output);
    }

    /**
     * @group custom
     *
     * Tests rendering custom field with callback
     */
    public function testRenderCustomFieldWithCallback(): void
    {
        $field = [
            'id' => 'custom_field',
            'type' => 'custom',
            'render_callback' => function($field, $value, $optionName) {
                echo '<div class="custom-field">';
                echo '<span>' . esc_html($value) . '</span>';
                echo '</div>';
            }
        ];

        ob_start();
        $this->renderer->render($field, 'custom value');
        $output = ob_get_clean();

        $this->assertStringContainsString('<div class="custom-field">', $output);
        $this->assertStringContainsString('<span>custom value</span>', $output);
    }

    /**
     * @group custom
     *
     * Tests rendering unknown field type
     */
    public function testRenderUnknownFieldType(): void
    {
        $field = [
            'id' => 'unknown_field',
            'type' => 'unknown_type',
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, 'fallback value');
        $output = ob_get_clean();

        // Should fallback to text field
        $this->assertStringContainsString('<input type="text"', $output);
        $this->assertStringContainsString('value="fallback value"', $output);
    }

    /**
     * @group escaping
     *
     * Tests proper escaping of field values
     */
    public function testFieldValueEscaping(): void
    {
        $field = [
            'id' => 'xss_test',
            'type' => 'text',
            'required' => false
        ];

        ob_start();
        $this->renderer->render($field, '<script>alert("xss")</script>');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
    }

    /**
     * @group escaping
     *
     * Tests proper escaping in descriptions
     */
    public function testDescriptionEscaping(): void
    {
        $field = [
            'id' => 'field_with_desc',
            'type' => 'text',
            'required' => false,
            'description' => '<strong>HTML</strong> should be escaped'
        ];

        ob_start();
        $this->renderer->render($field, '');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<strong>HTML</strong>', $output);
        $this->assertStringContainsString('&lt;strong&gt;HTML&lt;/strong&gt; should be escaped', $output);
    }
}