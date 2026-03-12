<?php

require_once __DIR__ . '/../../vendor/autoload.php';

class Database
{
    private $driver;
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $ssl;
    private $charset;
    private $prefix;
    public $conn;

    public function __construct()
    {
        // Check environment - default to local if not specified
        $env = isset($_ENV['ENVIRONMENT']) ? $_ENV['ENVIRONMENT'] : 'development';
        $this->prefix = $env === 'production' ? 'PROD_DB_' : 'LOCAL_DB_';
        
        // Set database connection parameters based on environment
        $this->host     = $_ENV[$this->prefix . 'HOST'];
        $this->port     = $_ENV[$this->prefix . 'PORT'];
        $this->db_name  = $_ENV[$this->prefix . 'DATABASE'];
        $this->username = $_ENV[$this->prefix . 'USERNAME'];
        $this->password = $_ENV[$this->prefix . 'PASSWORD'];
        $this->driver   = $_ENV[$this->prefix . 'DRIVER'];
        $this->ssl      = $_ENV[$this->prefix . 'SSL'] === 'true';
        $this->charset  = $_ENV[$this->prefix . 'CHARSET'] ?? 'utf8mb4';
    }

    public function getConnection()
    {
        $this->conn = null;

        try {
            // Build DSN with port
            $dsn = "{$this->driver}:host={$this->host};port={$this->port};dbname={$this->db_name}";
            
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            // Add SSL options if required
            if ($this->ssl) {
                if ($this->driver === 'pgsql') {
                    // For PostgreSQL
                    $dsn .= ";sslmode=require";
                    
                    // Handle CA certificate if provided
                    $caCert = $_ENV[$this->prefix . 'CA_CERTIFICATE'] ?? null;
                    if ($caCert) {
                        if (strpos($caCert, '-----BEGIN CERTIFICATE-----') !== false) {
                            // Certificate content in env variable
                            $tempCertFile = sys_get_temp_dir() . '/db_ca_cert.pem';
                            file_put_contents($tempCertFile, $caCert);
                            $dsn .= ";sslrootcert={$tempCertFile}";
                        } else {
                            // File path
                            $certPath = file_exists($caCert) ? $caCert : __DIR__ . '/../../' . $caCert;
                            if (file_exists($certPath)) {
                                $dsn .= ";sslrootcert={$certPath}";
                            }
                        }
                    }
                } else if ($this->driver === 'mysql') {
                    // For MySQL
                    $caCert = $_ENV[$this->prefix . 'CA_CERTIFICATE'] ?? null;
                    if ($caCert) {
                        if (strpos($caCert, '-----BEGIN CERTIFICATE-----') !== false) {
                            // Certificate content in env variable
                            $tempCertFile = sys_get_temp_dir() . '/db_ca_cert.pem';
                            file_put_contents($tempCertFile, $caCert);
                            $options[\PDO::MYSQL_ATTR_SSL_CA] = $tempCertFile;
                        } else {
                            // File path
                            $certPath = file_exists($caCert) ? $caCert : __DIR__ . '/../../' . $caCert;
                            if (file_exists($certPath)) {
                                $options[\PDO::MYSQL_ATTR_SSL_CA] = $certPath;
                            }
                        }
                        $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                    }
                }
            }
            
            $this->conn = new \PDO($dsn, $this->username, $this->password, $options);
            
        } catch (\PDOException $exception) {
            // Log the error message
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}