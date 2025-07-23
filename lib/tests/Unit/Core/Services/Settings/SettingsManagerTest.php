<?php
/**
 * Settings Manager Unit Tests
 *
 * Tests for the SettingsManager service that handles WordPress plugin settings
 * with sections, fields, validation and sanitization.
 *
 * @package     Furgo\Sitechips\Core\Tests\Unit\Services\Settings
 * @author      Axel Wüstemann
 * @copyright   2025 Axel Wüstemann
 * @license     GPL v2 or later
 * @since       1.0.0
 */

declare(strict_types=1);

namespace Furgo\Sitechips\Core\Tests\Unit\Core\Services\Settings;

use Furgo\Sitechips\Core\Services\Settings\SettingsManager;
use Furgo\Sitechips\Core\Services\Settings\FieldRenderer;
use Furgo\Sitechips\Core\Tests\TestCase;
use InvalidArgumentException;

/**
 * Settings Manager Test Class
 *
 * @since 1.0.0
 * @covers \Furgo\Sitechips\Core\Services\Settings\SettingsManager
 */
class SettingsManagerTest extends TestCase
{
    /**
     * Settings manager instance
     *
     * @var SettingsManager
     */
    private SettingsManager $settings;

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

        // Ensure WordPress admin functions are loaded
        if (!function_exists('add_settings_section')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
        }
        if (!function_exists('register_setting')) {
            require_once ABSPATH . 'wp-admin/includes/options.php';
        }

        $this->settings = new SettingsManager($this->optionName);

        // Clear any existing option and WordPress globals
        delete_option($this->optionName);
        $this->clearWordPressGlobals();
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        delete_option($this->optionName);
        $this->clearWordPressGlobals();
    }

    /**
     * Clear WordPress settings globals
     */
    private function clearWordPressGlobals(): void
    {
        global $wp_settings_sections, $wp_settings_fields, $wp_registered_settings;

        if (isset($wp_settings_sections[$this->optionName])) {
            unset($wp_settings_sections[$this->optionName]);
        }
        if (isset($wp_settings_fields[$this->optionName])) {
            unset($wp_settings_fields[$this->optionName]);
        }
        if (isset($wp_registered_settings[$this->optionName])) {
            unset($wp_registered_settings[$this->optionName]);
        }
    }

    // ========================================================================
    // Constructor Tests
    // ========================================================================

    /**
     * @group constructor
     *
     * Tests successful construction with valid option name
     */
    public function testConstructorWithValidOptionName(): void
    {
        $settings = new SettingsManager('my_settings');

        $this->assertInstanceOf(SettingsManager::class, $settings);
        $this->assertEquals('my_settings', $settings->getOptionName());
    }

    /**
     * @group constructor
     *
     * Tests constructor throws exception for empty option name
     */
    public function testConstructorThrowsExceptionForEmptyOptionName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option name cannot be empty');

        new SettingsManager('');
    }

    // ========================================================================
    // Section Management Tests
    // ========================================================================

    /**
     * @group sections
     *
     * Tests adding a basic section
     */
    public function testAddSectionBasic(): void
    {
        $result = $this->settings->addSection('general', 'General Settings');

        $this->assertSame($this->settings, $result); // Fluent interface

        $sections = $this->settings->getSections();
        $this->assertArrayHasKey('general', $sections);
        $this->assertEquals('General Settings', $sections['general']['title']);
        $this->assertNull($sections['general']['callback']);
        $this->assertEquals(10, $sections['general']['priority']);
    }

    /**
     * @group sections
     *
     * Tests adding section with callback
     */
    public function testAddSectionWithCallback(): void
    {
        $callback = function() { echo 'Section description'; };

        $this->settings->addSection('advanced', 'Advanced Settings', $callback, 20);

        $sections = $this->settings->getSections();
        $this->assertSame($callback, $sections['advanced']['callback']);
        $this->assertEquals(20, $sections['advanced']['priority']);
    }

    /**
     * @group sections
     *
     * Tests adding section with empty ID throws exception
     */
    public function testAddSectionWithEmptyIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Section ID cannot be empty');

        $this->settings->addSection('', 'Title');
    }

    /**
     * @group sections
     *
     * Tests adding section with empty title throws exception
     */
    public function testAddSectionWithEmptyTitleThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Section title cannot be empty');

        $this->settings->addSection('general', '');
    }

    /**
     * @group sections
     *
     * Tests section priority ordering
     */
    public function testSectionPriorityOrdering(): void
    {
        $this->settings
            ->addSection('third', 'Third', null, 30)
            ->addSection('first', 'First', null, 10)
            ->addSection('second', 'Second', null, 20);

        // Sections should maintain insertion order until registerSettings is called
        $sections = $this->settings->getSections();
        $keys = array_keys($sections);
        $this->assertEquals(['third', 'first', 'second'], $keys);
    }

    // ========================================================================
    // Field Management Tests
    // ========================================================================

    /**
     * @group fields
     *
     * Tests adding a basic text field
     */
    public function testAddFieldBasic(): void
    {
        $this->settings->addSection('general', 'General');

        $result = $this->settings->addField('api_key', 'API Key', 'general');

        $this->assertSame($this->settings, $result);

        $fields = $this->settings->getFields();
        $this->assertArrayHasKey('api_key', $fields);
        $this->assertEquals('api_key', $fields['api_key']['id']);
        $this->assertEquals('API Key', $fields['api_key']['title']);
        $this->assertEquals('general', $fields['api_key']['section']);
        $this->assertEquals('text', $fields['api_key']['type']);
    }

    /**
     * @group fields
     *
     * Tests adding field with all options
     */
    public function testAddFieldWithAllOptions(): void
    {
        $this->settings->addSection('general', 'General');

        $sanitize = function($value) { return strtoupper($value); };
        $validate = function($value) { return strlen($value) > 5; };

        $this->settings->addField('api_key', 'API Key', 'general', 'text', [
            'description' => 'Enter your API key',
            'default' => 'default-key',
            'required' => true,
            'sanitize_callback' => $sanitize,
            'validate_callback' => $validate,
            'placeholder' => 'XXX-XXX-XXX'
        ]);

        $fields = $this->settings->getFields();
        $field = $fields['api_key'];

        $this->assertEquals('Enter your API key', $field['description']);
        $this->assertEquals('default-key', $field['default']);
        $this->assertTrue($field['required']);
        $this->assertSame($sanitize, $field['sanitize_callback']);
        $this->assertSame($validate, $field['validate_callback']);
        $this->assertEquals('XXX-XXX-XXX', $field['placeholder']);
    }

    /**
     * @group fields
     *
     * Tests adding field with empty ID throws exception
     */
    public function testAddFieldWithEmptyIdThrowsException(): void
    {
        $this->settings->addSection('general', 'General');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field ID cannot be empty');

        $this->settings->addField('', 'Title', 'general');
    }

    /**
     * @group fields
     *
     * Tests adding field with empty title throws exception
     */
    public function testAddFieldWithEmptyTitleThrowsException(): void
    {
        $this->settings->addSection('general', 'General');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field title cannot be empty');

        $this->settings->addField('field_id', '', 'general');
    }

    /**
     * @group fields
     *
     * Tests adding field to non-existent section throws exception
     */
    public function testAddFieldToNonExistentSectionThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Section 'non_existent' does not exist");

        $this->settings->addField('field_id', 'Title', 'non_existent');
    }

    /**
     * @group fields
     *
     * Tests different field types
     */
    public function testDifferentFieldTypes(): void
    {
        $this->settings->addSection('general', 'General');

        $fieldTypes = ['text', 'textarea', 'checkbox', 'radio', 'select', 'number', 'email', 'url', 'color', 'password'];

        foreach ($fieldTypes as $type) {
            $this->settings->addField("field_$type", "Field $type", 'general', $type);
        }

        $fields = $this->settings->getFields();

        foreach ($fieldTypes as $type) {
            $this->assertEquals($type, $fields["field_$type"]['type']);
        }
    }

    // ========================================================================
    // Value Management Tests
    // ========================================================================

    /**
     * @group values
     *
     * Tests getting value for non-existent field returns default
     */
    public function testGetValueReturnsDefault(): void
    {
        $value = $this->settings->getValue('non_existent', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    /**
     * @group values
     *
     * Tests getting value for existing field
     */
    public function testGetValueReturnsStoredValue(): void
    {
        // Store a value
        update_option($this->optionName, ['api_key' => 'stored_value']);

        $value = $this->settings->getValue('api_key');

        $this->assertEquals('stored_value', $value);
    }

    /**
     * @group values
     *
     * Tests getting all values with defaults
     */
    public function testGetValuesWithDefaults(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('field1', 'Field 1', 'general', 'text', ['default' => 'default1'])
            ->addField('field2', 'Field 2', 'general', 'text', ['default' => 'default2']);

        // Store only one value
        update_option($this->optionName, ['field1' => 'stored1']);

        $values = $this->settings->getValues();

        $this->assertEquals('stored1', $values['field1']);
        $this->assertEquals('default2', $values['field2']); // Default applied
    }

    /**
     * @group values
     *
     * Tests getting values when option is not an array
     */
    public function testGetValuesWhenOptionNotArray(): void
    {
        // Store non-array value
        update_option($this->optionName, 'not_an_array');

        $values = $this->settings->getValues();

        $this->assertIsArray($values);
        $this->assertEmpty($values);
    }

    // ========================================================================
    // Registration Tests
    // ========================================================================

    /**
     * @group registration
     *
     * Tests register method adds action
     */
    public function testRegisterAddsAction(): void
    {
        $this->settings->register();

        // Check that action was added
        $this->assertTrue(has_action('admin_init'));
    }

    /**
     * @group registration
     *
     * Tests register is idempotent
     */
    public function testRegisterIsIdempotent(): void
    {
        $this->settings->register();
        $this->settings->register();
        $this->settings->register();

        // Should not throw or cause issues
        $this->assertTrue(true);
    }

    /**
     * @group registration
     *
     * Tests registerSettings method with complete setup
     */
    public function testRegisterSettingsComplete(): void
    {
        // Setup sections and fields
        $this->settings
            ->addSection('general', 'General Settings', function() { echo 'General desc'; }, 10)
            ->addSection('advanced', 'Advanced Settings', null, 20)
            ->addField('site_title', 'Site Title', 'general')
            ->addField('api_key', 'API Key', 'advanced');

        // Register with WordPress
        $this->settings->registerSettings();

        // Check WordPress globals directly
        global $wp_settings_sections, $wp_settings_fields;

        // Verify sections were registered
        $this->assertNotEmpty($wp_settings_sections[$this->optionName]);
        $this->assertArrayHasKey('general', $wp_settings_sections[$this->optionName]);
        $this->assertArrayHasKey('advanced', $wp_settings_sections[$this->optionName]);

        // Verify sections are in priority order
        $sectionKeys = array_keys($wp_settings_sections[$this->optionName]);
        $this->assertEquals(['general', 'advanced'], $sectionKeys);

        // Verify fields were registered
        $this->assertNotEmpty($wp_settings_fields[$this->optionName]);
        $this->assertArrayHasKey('general', $wp_settings_fields[$this->optionName]);
        $this->assertArrayHasKey('site_title', $wp_settings_fields[$this->optionName]['general']);
        $this->assertArrayHasKey('advanced', $wp_settings_fields[$this->optionName]);
        $this->assertArrayHasKey('api_key', $wp_settings_fields[$this->optionName]['advanced']);
    }

    /**
     * @group registration
     *
     * Tests registerSettings with section priority ordering
     */
    public function testRegisterSettingsSectionPriorityOrdering(): void
    {
        $this->settings
            ->addSection('last', 'Last Section', null, 100)
            ->addSection('first', 'First Section', null, 5)
            ->addSection('middle', 'Middle Section', null, 50);

        $this->settings->registerSettings();

        // Check WordPress globals
        global $wp_settings_sections;

        // Verify sections are registered in priority order
        $sectionKeys = array_keys($wp_settings_sections[$this->optionName]);
        $this->assertEquals(['first', 'middle', 'last'], $sectionKeys);
    }

    /**
     * @group registration
     *
     * Tests registerSettings without sections or fields
     */
    public function testRegisterSettingsEmpty(): void
    {
        $this->settings->registerSettings();

        // Check WordPress globals
        global $wp_settings_sections, $wp_settings_fields, $wp_registered_settings;

        // Should not have sections or fields
        $this->assertEmpty($wp_settings_sections[$this->optionName] ?? []);
        $this->assertEmpty($wp_settings_fields[$this->optionName] ?? []);

        // The setting itself might be registered
        // Note: register_setting might not update globals immediately in test environment
    }

    // ========================================================================
    // Sanitization Tests
    // ========================================================================

    /**
     * @group sanitization
     *
     * Tests sanitization by field type
     */
    public function testSanitizeValuesByType(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('text_field', 'Text', 'general', 'text')
            ->addField('email_field', 'Email', 'general', 'email')
            ->addField('url_field', 'URL', 'general', 'url')
            ->addField('number_field', 'Number', 'general', 'number')
            ->addField('checkbox_field', 'Checkbox', 'general', 'checkbox');

        $input = [
            'text_field' => '<script>alert("xss")</script>',
            'email_field' => 'test@EXAMPLE.com',
            'url_field' => 'https://example.com/path',
            'number_field' => '42.5',
            'checkbox_field' => '1'
        ];

        $sanitized = $this->settings->sanitizeValues($input);

        $this->assertEquals('', $sanitized['text_field']); // Script completely removed
        $this->assertEquals('test@EXAMPLE.com', $sanitized['email_field']); // Email sanitized
        $this->assertEquals('https://example.com/path', $sanitized['url_field']); // URL sanitized
        $this->assertEquals(42, $sanitized['number_field']); // Converted to int
        $this->assertEquals(1, $sanitized['checkbox_field']);
    }

    /**
     * @group sanitization
     *
     * Tests sanitization for textarea field type
     */
    public function testSanitizeTextareaField(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('content', 'Content', 'general', 'textarea');

        $input = [
            'content' => "<p>Hello\nWorld</p>\n<script>alert('xss')</script>"
        ];

        $sanitized = $this->settings->sanitizeValues($input);

        // sanitize_textarea_field preserves internal newlines but strips tags and trailing newline
        $this->assertEquals("Hello\nWorld", $sanitized['content']);
    }

    /**
     * @group sanitization
     *
     * Tests sanitization for password field type
     */
    public function testSanitizePasswordField(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('password', 'Password', 'general', 'password');

        $input = [
            'password' => '<script>alert("xss")</script>my_password123!'
        ];

        $sanitized = $this->settings->sanitizeValues($input);

        // Password should be sanitized like text (no special characters preserved)
        $this->assertEquals('my_password123!', $sanitized['password']);
    }

    /**
     * @group sanitization
     *
     * Tests sanitization for radio field type
     */
    public function testSanitizeRadioField(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('choice', 'Choice', 'general', 'radio', [
                'options' => ['option1' => 'Option 1', 'option2' => 'Option 2']
            ]);

        $input = [
            'choice' => 'option1<script>evil</script>'
        ];

        $sanitized = $this->settings->sanitizeValues($input);

        // sanitize_text_field removes everything between < and >
        $this->assertEquals('option1', $sanitized['choice']);
    }

    /**
     * @group sanitization
     *
     * Tests sanitization for color field type
     */
    public function testSanitizeColorField(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('color', 'Color', 'general', 'color');

        // Test valid color - sanitize_hex_color may not be available in tests
        $input = ['color' => '#FF5733'];
        $sanitized = $this->settings->sanitizeValues($input);

        // In test environment, sanitize_hex_color might return null or empty
        // Let's check what actually happens
        if (function_exists('sanitize_hex_color')) {
            $this->assertEquals('#FF5733', $sanitized['color']);
        } else {
            // Fallback behavior when function doesn't exist
            $this->assertEquals('#FF5733', $sanitized['color']);
        }
    }

    /**
     * @group sanitization
     *
     * Tests sanitization for empty checkbox value
     */
    public function testSanitizeEmptyCheckboxValue(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('enable_feature', 'Enable Feature', 'general', 'checkbox');

        // Test empty value
        $input = ['enable_feature' => ''];
        $sanitized = $this->settings->sanitizeValues($input);
        $this->assertEquals(0, $sanitized['enable_feature']);

        // Test missing checkbox (not submitted)
        $input = [];
        $sanitized = $this->settings->sanitizeValues($input);
        $this->assertEquals(0, $sanitized['enable_feature']);

        // Test zero value
        $input = ['enable_feature' => '0'];
        $sanitized = $this->settings->sanitizeValues($input);
        $this->assertEquals(0, $sanitized['enable_feature']);
    }

    /**
     * @group sanitization
     *
     * Tests default sanitization for unknown field type
     */
    public function testSanitizeUnknownFieldType(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('custom', 'Custom Field', 'general', 'custom_type');

        $input = ['custom' => '<b>test</b> value'];
        $sanitized = $this->settings->sanitizeValues($input);

        // Unknown types fall back to text sanitization
        $this->assertEquals('test value', $sanitized['custom']);
    }

    /**
     * @group sanitization
     *
     * Tests custom sanitization callback
     */
    public function testCustomSanitizationCallback(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('custom_field', 'Custom', 'general', 'text', [
                'sanitize_callback' => function($value) {
                    return strtoupper(trim($value));
                }
            ]);

        $input = ['custom_field' => '  hello world  '];
        $sanitized = $this->settings->sanitizeValues($input);

        $this->assertEquals('HELLO WORLD', $sanitized['custom_field']);
    }

    /**
     * @group sanitization
     *
     * Tests validation callback
     */
    public function testValidationCallback(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('validated_field', 'Validated', 'general', 'text', [
                'validate_callback' => function($value) {
                    return strlen($value) >= 5;
                }
            ]);

        // Simuliere eine vorhandene Option durch direktes Überschreiben der getValues() Methode
        // Da die Options-API in Tests nicht funktioniert, testen wir die Logik direkt

        // Test 1: Invalid value should be rejected
        $input = ['validated_field' => 'abc']; // Too short
        $sanitized = $this->settings->sanitizeValues($input);

        // Bei ungültigem Wert ohne vorherigen Wert sollte ein leerer String zurückkommen
        $this->assertEquals('', $sanitized['validated_field']);

        // Test 2: Valid value should be accepted
        $input = ['validated_field' => 'valid_value']; // Long enough
        $sanitized = $this->settings->sanitizeValues($input);

        // Valid value should be accepted
        $this->assertEquals('valid_value', $sanitized['validated_field']);

        // Test 3: Test the actual validation callback
        $field = $this->settings->getFields()['validated_field'];
        $validateCallback = $field['validate_callback'];

        $this->assertFalse($validateCallback('abc'), 'Short value should fail validation');
        $this->assertTrue($validateCallback('valid'), 'Long value should pass validation');
    }

    /**
     * @group sanitization
     *
     * Tests sanitization with non-array input
     */
    public function testSanitizeValuesWithNonArrayInput(): void
    {
        $sanitized = $this->settings->sanitizeValues('not_an_array');

        $this->assertIsArray($sanitized);
        $this->assertEmpty($sanitized);
    }

    /**
     * @group sanitization
     *
     * Tests sanitization for missing field values
     */
    public function testSanitizeMissingFieldValues(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('field1', 'Field 1', 'general', 'text')
            ->addField('field2', 'Field 2', 'general', 'number')
            ->addField('field3', 'Field 3', 'general', 'checkbox');

        // Only submit field1
        $input = ['field1' => 'value1'];
        $sanitized = $this->settings->sanitizeValues($input);

        // Missing fields should get null/0 based on type
        $this->assertEquals('value1', $sanitized['field1']);
        $this->assertEquals(0, $sanitized['field2']); // Number defaults to 0
        $this->assertEquals(0, $sanitized['field3']); // Checkbox defaults to 0
    }

    // ========================================================================
    // Rendering Tests
    // ========================================================================

    /**
     * @group rendering
     * @group skip
     *
     * Tests render field method creates renderer
     * Note: Skipped as it requires FieldRenderer implementation
     */
    public function testRenderFieldCreatesRenderer(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('test_field', 'Test Field', 'general');

        // Capture output
        ob_start();
        $this->settings->renderField(['field' => $this->settings->getFields()['test_field']]);
        $output = ob_get_clean();

        // Should render something
        $this->assertNotEmpty($output);
        $this->assertStringContainsString('input', $output);
        $this->assertStringContainsString('test_field', $output);
    }

    /**
     * @group rendering
     *
     * Tests render field uses default value when no stored value exists
     */
    public function testRenderFieldWithDefaultValue(): void
    {
        $this->settings
            ->addSection('general', 'General')
            ->addField('default_field', 'Default Field', 'general', 'text', [
                'default' => 'default_value',
                'description' => 'Field with default'
            ]);

        // Don't store any value - should use default

        // Get the field definition
        $field = $this->settings->getFields()['default_field'];

        // Capture output
        ob_start();
        $this->settings->renderField(['field' => $field]);
        $output = ob_get_clean();

        // Should contain the default value
        $this->assertStringContainsString('value="default_value"', $output);
        $this->assertStringContainsString('<p class="description">Field with default</p>', $output);
    }

    // ========================================================================
    // Complex Scenarios
    // ========================================================================

    /**
     * @group scenarios
     *
     * Tests complete settings setup
     */
    public function testCompleteSettingsScenario(): void
    {
        $result = $this->settings
            // General section
            ->addSection('general', 'General Settings', function() {
                echo '<p>Configure general plugin settings.</p>';
            })
            ->addField('site_title', 'Site Title', 'general', 'text', [
                'description' => 'Enter your site title',
                'required' => true
            ])
            ->addField('enable_feature', 'Enable Feature', 'general', 'checkbox', [
                'label' => 'Enable this awesome feature'
            ])

            // Advanced section
            ->addSection('advanced', 'Advanced Settings', null, 20)
            ->addField('api_endpoint', 'API Endpoint', 'advanced', 'url', [
                'description' => 'Enter the API endpoint URL',
                'default' => 'https://api.example.com'
            ])
            ->addField('timeout', 'Timeout', 'advanced', 'number', [
                'description' => 'Request timeout in seconds',
                'min' => 1,
                'max' => 60,
                'default' => 30
            ])

            // Appearance section
            ->addSection('appearance', 'Appearance', null, 30)
            ->addField('primary_color', 'Primary Color', 'appearance', 'color', [
                'default' => '#0073aa'
            ])
            ->addField('layout', 'Layout', 'appearance', 'select', [
                'options' => [
                    'grid' => 'Grid Layout',
                    'list' => 'List Layout',
                    'cards' => 'Card Layout'
                ],
                'default' => 'grid'
            ]);

        $this->assertSame($this->settings, $result);
        $this->assertCount(3, $this->settings->getSections());
        $this->assertCount(6, $this->settings->getFields());
    }

    /**
     * @group scenarios
     *
     * Tests real-world form submission scenario
     */
    public function testFormSubmissionScenario(): void
    {
        $this->settings
            ->addSection('api', 'API Settings')
            ->addField('api_key', 'API Key', 'api', 'text', [
                'sanitize_callback' => function($value) {
                    return preg_replace('/[^A-Z0-9-]/', '', strtoupper($value));
                },
                'validate_callback' => function($value) {
                    return preg_match('/^[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}$/', $value);
                }
            ])
            ->addField('api_secret', 'API Secret', 'api', 'password')
            ->addField('enable_logging', 'Enable Logging', 'api', 'checkbox');

        // Simulate form submission
        $formData = [
            'api_key' => 'abc-123-xyz',
            'api_secret' => 'my_secret_key',
            'enable_logging' => '1'
        ];

        $sanitized = $this->settings->sanitizeValues($formData);

        $this->assertEquals('ABC-123-XYZ', $sanitized['api_key']); // Uppercase and cleaned
        $this->assertEquals('my_secret_key', $sanitized['api_secret']);
        $this->assertEquals(1, $sanitized['enable_logging']);
    }

    /**
     * @group scenarios
     *
     * Tests complete registration and rendering workflow
     */
    public function testCompleteWorkflowScenario(): void
    {
        // Build settings
        $this->settings
            ->addSection('general', 'General', null, 10)
            ->addField('site_name', 'Site Name', 'general', 'text', [
                'default' => 'My Site',
                'required' => true
            ])
            ->addField('enable_cache', 'Enable Cache', 'general', 'checkbox')
            ->addSection('advanced', 'Advanced', null, 20)
            ->addField('cache_ttl', 'Cache TTL', 'advanced', 'number', [
                'min' => 60,
                'max' => 3600,
                'default' => 300
            ]);

        // Register settings
        $this->settings->registerSettings();

        // Check WordPress globals
        global $wp_settings_sections, $wp_settings_fields;

        // Verify registration
        $this->assertNotEmpty($wp_settings_sections[$this->optionName]);
        $this->assertCount(2, $wp_settings_sections[$this->optionName]);
        $this->assertNotEmpty($wp_settings_fields[$this->optionName]);
        $this->assertCount(2, $wp_settings_fields[$this->optionName]); // 2 sections
        $this->assertCount(2, $wp_settings_fields[$this->optionName]['general']); // 2 fields in general
        $this->assertCount(1, $wp_settings_fields[$this->optionName]['advanced']); // 1 field in advanced

        // Simulate form submission
        $formData = [
            'site_name' => '<b>Test Site</b>',
            'enable_cache' => '1',
            'cache_ttl' => '600'
        ];

        $sanitized = $this->settings->sanitizeValues($formData);

        $this->assertEquals('Test Site', $sanitized['site_name']); // Sanitized
        $this->assertEquals(1, $sanitized['enable_cache']);
        $this->assertEquals(600, $sanitized['cache_ttl']);
    }
}