<?php
declare(strict_types=1);

// Integration Test Environment
define('SITECHIPS_INTEGRATION_TESTS', true);
define('SITECHIPS_TESTS', true);

// Composer Autoloader
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// WordPress Konstanten für Tests setzen
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// WordPress laden
$table_prefix = 'wp_';
require_once dirname(__DIR__, 6) . '/wp-load.php';

// Temporäres Log-File für Tests
define('SITECHIPS_TEST_LOG_FILE', sys_get_temp_dir() . '/sitechips-test-' . uniqid() . '.log');

// Log-File erstellen
if (!file_exists(SITECHIPS_TEST_LOG_FILE)) {
    touch(SITECHIPS_TEST_LOG_FILE);
}

ini_set('error_log', SITECHIPS_TEST_LOG_FILE);
ini_set('log_errors', '1');