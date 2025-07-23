<?php
// tests/bootstrap.php
declare(strict_types=1);

// Test-Umgebung
if (!defined('SITECHIPS_TESTS')) {
    define('SITECHIPS_TESTS', true);
}

// Composer Autoloader
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// WordPress ist bereits geladen in DDEV - wp-load.php wird das richtige tun
$table_prefix = 'wp_';
require_once dirname(__DIR__, 5) . '/wp-load.php';

// Settings API Funktionen laden
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/options.php';