<?php
ob_start();
/**
 * Application Entry Point
 * 
 * Main entry point for the Eventic API
 */

// Define application paths
require_once __DIR__ . '/../src/config/Constants.php';

// Load Composer autoloader
require_once BASE . 'vendor/autoload.php';

// Bootstrap the application
$app = require_once BOOTSTRAP . 'app.php';

// Run the application
$app->run();