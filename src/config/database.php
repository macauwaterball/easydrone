<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $host = getenv('DB_HOST') ?: 'db';
            $dbname = getenv('DB_NAME') ?: 'drone_soccer';
            $username = getenv('DB_USER') ?: 'dronesoccer';
            $password = getenv('DB_PASSWORD') ?: 'Qweszxc!23';

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                PDO::MYSQL_ATTR_SSL_CA => false
            ];

            $this->pdo = new PDO($dsn, $username, $password, $options);
            
            // 測試連接
            $this->pdo->query('SELECT 1');
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("無法連接到數據庫，請稍後再試");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}