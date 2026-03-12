<?php

namespace App\Config;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use PDO;

/**
 * Eloquent ORM Bootstrap
 * This class initializes Eloquent ORM for use outside of Laravel
 */
class EloquentBootstrap
{
    private static $capsule = null;

    /**
     * Initialize Eloquent ORM
     * @return Capsule
     */
    public static function boot(): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        $capsule = new Capsule;

        // Check environment - default to local if not specified
        $env = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'development';
        $prefix = $env === 'production' ? 'PROD_DB_' : 'LOCAL_DB_';

        $driver = $_ENV[$prefix . 'DRIVER'] ?? 'mysql';

        // Build base connection config
        $connectionConfig = [
            'driver' => $driver,
            'host' => $_ENV[$prefix . 'HOST'],
            'port' => $_ENV[$prefix . 'PORT'],
            'database' => $_ENV[$prefix . 'DATABASE'],
            'username' => $_ENV[$prefix . 'USERNAME'],
            'password' => $_ENV[$prefix . 'PASSWORD'],
            'charset' => $driver === 'pgsql' ? 'utf8' : ($_ENV[$prefix . 'CHARSET'] ?? 'utf8mb4'),
            'prefix' => '',
            'strict' => true,
        ];

        // Add MySQL specific options
        if ($driver === 'mysql') {
            $connectionConfig['collation'] = $_ENV[$prefix . 'COLLATION'] ?? 'utf8mb4_unicode_ci';
            $connectionConfig['engine'] = null;
        }

        // Add PostgreSQL specific options
        if ($driver === 'pgsql') {
            $connectionConfig['schema'] = 'public';
        }

        // Handle SSL/CA Certificate
        $caCertificate = $_ENV[$prefix . 'CA_CERTIFICATE'] ?? null;
        $sslMode = $_ENV[$prefix . 'SSL'] ?? null;

        if ($caCertificate || $sslMode) {
            // Resolve CA certificate path or content
            $caPath = null;
            if ($caCertificate) {
                // Check if it's certificate content (starts with -----BEGIN CERTIFICATE-----)
                if (strpos($caCertificate, '-----BEGIN CERTIFICATE-----') !== false) {
                    // Certificate content is in the environment variable
                    // Create a temporary file for the certificate
                    $tempCertFile = sys_get_temp_dir() . '/eloquent_ca_cert.pem';
                    file_put_contents($tempCertFile, $caCertificate);
                    $caPath = $tempCertFile;
                } else {
                    // It's a file path - check if absolute path exists, otherwise look relative to project root
                    if (file_exists($caCertificate)) {
                        $caPath = $caCertificate;
                    } elseif (file_exists(__DIR__ . '/../../' . $caCertificate)) {
                        $caPath = realpath(__DIR__ . '/../../' . $caCertificate);
                    } elseif (file_exists(__DIR__ . '/' . $caCertificate)) {
                        $caPath = realpath(__DIR__ . '/' . $caCertificate);
                    }
                }
            }

            if ($driver === 'pgsql') {
                // PostgreSQL uses sslmode in the connection string
                $connectionConfig['sslmode'] = $sslMode ?? 'require';

                if ($caPath) {
                    $connectionConfig['sslrootcert'] = $caPath;
                }
            } elseif ($driver === 'mysql') {
                // MySQL uses PDO options for SSL
                $options = [];

                if ($caPath) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                }

                if ($sslMode === 'verify_ca' || $sslMode === 'verify_identity') {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                } elseif ($sslMode === 'require' || $sslMode === 'required') {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }

                if (!empty($options)) {
                    $connectionConfig['options'] = $options;
                }
            }
        }

        // Add default database connection
        $capsule->addConnection($connectionConfig);

        // Set the event dispatcher used by Eloquent models
        $capsule->setEventDispatcher(new Dispatcher(new Container));

        // Make this Capsule instance available globally via static methods
        $capsule->setAsGlobal();

        // Setup the Eloquent ORM
        $capsule->bootEloquent();

        self::$capsule = $capsule;

        return $capsule;
    }

    /**
     * Get the Capsule instance
     * @return Capsule|null
     */
    public static function getCapsule(): ?Capsule
    {
        return self::$capsule;
    }

    /**
     * Get the database connection
     * @return \Illuminate\Database\Connection
     */
    public static function getConnection()
    {
        if (self::$capsule === null) {
            self::boot();
        }
        return self::$capsule->getConnection();
    }
}
