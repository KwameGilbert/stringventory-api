<?php

/**
 * Application Bootstrap
 * 
 * Main application bootstrap file that orchestrates all initialization
 */

use DI\Container;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use App\Helper\LoggerFactory;
use App\Config\EloquentBootstrap;


// Load environment variables
$dotenv = Dotenv::createImmutable(BASE);
$dotenv->load();

// Load application configuration
$config = require CONFIG . '/AppConfig.php';

// ==================== DI CONTAINER SETUP ====================

$container = new Container();

// Bootstrap Eloquent ORM
require_once CONFIG . 'EloquentBootstrap.php';
$capsule = EloquentBootstrap::boot();
$container->set('db', $capsule);

// Setup Loggers
if (class_exists(LoggerFactory::class)) {
    $loggerFactory = new LoggerFactory($config['name']);
    $container->set('logger', $loggerFactory->getLogger());
    $container->set('httpLogger', $loggerFactory->getHttpLogger());
}

// Register services, controllers, and middleware
$registerServices = require BOOTSTRAP . 'services.php';
$container = $registerServices($container);

// ==================== APPLICATION SETUP ====================

// Set container on AppFactory
AppFactory::setContainer($container);

// Create Slim App instance
$app = AppFactory::create();

// Set base path
// $app->setBasePath($config['base_path']);

// ==================== MIDDLEWARE SETUP ====================

$registerMiddleware = require BOOTSTRAP . 'middleware.php';
$app = $registerMiddleware($app, $container, $config);

// ==================== ROUTES SETUP ====================

$registerRoutes = require BOOTSTRAP . 'routes.php';
$app = $registerRoutes($app, $config);

// ==================== RETURN APP ====================

return $app;
