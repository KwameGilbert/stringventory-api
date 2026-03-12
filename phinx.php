<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check environment - default to local if not specified
$env = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'development';
$prefix = $env === 'production' ? 'PROD_DB_' : 'LOCAL_DB_';

$driver = $_ENV[$prefix . 'DRIVER'] ?? $_ENV[$prefix . 'ADAPTER'] ?? 'mysql';

// Resolve CA certificate path
$caCertificate = $_ENV[$prefix . 'CA_CERTIFICATE'] ?? null;
$sslMode = $_ENV[$prefix . 'SSL'] ?? null;

$caPath = null;
if ($caCertificate) {
    // Check if it's certificate content (starts with -----BEGIN CERTIFICATE-----)
    if (strpos($caCertificate, '-----BEGIN CERTIFICATE-----') !== false) {
        // Certificate content is in the environment variable
        // Create a temporary file for the certificate
        $tempCertFile = sys_get_temp_dir() . '/db_ca_cert.pem';
        file_put_contents($tempCertFile, $caCertificate);
        $caPath = $tempCertFile;
    } else {
        // It's a file path
        if (file_exists($caCertificate)) {
            $caPath = $caCertificate;
        } elseif (file_exists(__DIR__ . '/' . $caCertificate)) {
            $caPath = realpath(__DIR__ . '/' . $caCertificate);
        }
    }
}

// Build base connection config for development (MySQL)
$developmentConfig = [
    'adapter' => $_ENV['LOCAL_DB_DRIVER'] ?? $_ENV['LOCAL_DB_ADAPTER'] ?? 'mysql',
    'host' => $_ENV['LOCAL_DB_HOST'] ?? '127.0.0.1',
    'name' => $_ENV['LOCAL_DB_DATABASE'] ?? 'eventic',
    'user' => $_ENV['LOCAL_DB_USERNAME'] ?? 'root',
    'pass' => $_ENV['LOCAL_DB_PASSWORD'] ?? '',
    'port' => $_ENV['LOCAL_DB_PORT'] ?? '3306',
    'charset' => $_ENV['LOCAL_DB_CHARSET'] ?? 'utf8mb4',
];

// Build base connection config for production (MySQL)
$productionConfig = [
    'adapter' => $_ENV['PROD_DB_DRIVER'] ?? $_ENV['PROD_DB_ADAPTER'] ?? 'mysql',
    'host' => $_ENV['PROD_DB_HOST'],
    'name' => $_ENV['PROD_DB_DATABASE'],
    'user' => $_ENV['PROD_DB_USERNAME'],
    'pass' => $_ENV['PROD_DB_PASSWORD'],
    'port' => $_ENV['PROD_DB_PORT'],
    'charset' => 'utf8',    
];

// Add SSL for production MySQL
$prodCaCert = $_ENV['PROD_DB_CA_CERTIFICATE'] ?? null;
$prodSslMode = $_ENV['PROD_DB_SSL'] ?? 'require';

if ($prodCaCert) {
    // Check if it's certificate content (starts with -----BEGIN CERTIFICATE-----)
    if (strpos($prodCaCert, '-----BEGIN CERTIFICATE-----') !== false) {
        // Certificate content is in the environment variable
        // Create a temporary file for the certificate
        $tempCertFile = sys_get_temp_dir() . '/phinx_ca_cert.pem';
        file_put_contents($tempCertFile, $prodCaCert);
        $productionConfig['ssl_ca'] = $tempCertFile;
    } else {
        // It's a file path
        $prodCaPath = null;
        if (file_exists($prodCaCert)) {
            $prodCaPath = $prodCaCert;
        } elseif (file_exists(__DIR__ . '/' . $prodCaCert)) {
            $prodCaPath = realpath(__DIR__ . '/' . $prodCaCert);
        }
        
        if ($prodCaPath) {
            $productionConfig['ssl_ca'] = $prodCaPath;
        }
    }
}

if ($prodSslMode) {
    $productionConfig['sslmode'] = $prodSslMode;
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => $env,
        'development' => $developmentConfig,
        'production' => $productionConfig,
    ],
    'version_order' => 'creation'
];
