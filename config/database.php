<?php
/**
 * Database Connection Configuration
 * Uses PDO (PHP Data Objects) for secure database interactions.
 */

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cloud_team_management_db');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In development, show details. In production, hide sensitive details.
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    // Get the database connection instance (Singleton)
    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }
}

// Helper function for procedural styles
function getDBConnection() {
    return Database::getConnection();
}

// Test the connection if the script is run directly from the command line
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    try {
        $db = Database::getConnection();
        echo "Successfully connected to the database: " . DB_NAME . "\n";
        
        $stmt = $db->query("SELECT DATABASE()");
        $currentDb = $stmt->fetchColumn();
        echo "Current Database in Use: " . $currentDb . "\n";
    } catch (Exception $e) {
        echo "Test connection failed: " . $e->getMessage() . "\n";
    }
}
