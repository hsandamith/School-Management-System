<?php


class DatabaseConnection
{
    private static $instance = null;
    private $connection;


    private const DB_HOST = 'localhost';
    private const DB_NAME = 'schema';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';


    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=" . self::DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];

            $this->connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the error and display a generic message
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection error. Please try again later.");
        }
    }


    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function getConnection()
    {
        return $this->connection;
    }


    private function __clone()
    {
    }


    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }
}


function getDBConnection()
{
    return DatabaseConnection::getInstance()->getConnection();
}


