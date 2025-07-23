<?php
// tests/bootstrap.php
declare(strict_types=1);

// Test-Umgebung
if (!defined('SITECHIPS_TESTS')) {
    define('SITECHIPS_TESTS', true);
}

// Composer Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// WordPress ist bereits geladen in DDEV - wp-load.php wird das richtige tun
$table_prefix = 'wp_';
require_once dirname(__DIR__, 4) . '/wp-load.php';