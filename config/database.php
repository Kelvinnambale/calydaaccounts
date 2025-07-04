<?php
// FILE 2: config/database.php
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

class Database {
    private static $instance = null;
    private $connection;
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                ]
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function createTables() {
        $queries = [
            // Users table
            "CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                role ENUM('admin', 'user') DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // Clients table
            "CREATE TABLE IF NOT EXISTS clients (
                id INT PRIMARY KEY AUTO_INCREMENT,
                full_name VARCHAR(100) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                kra_pin VARCHAR(20) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                tax_obligations JSON NOT NULL,
                email_address VARCHAR(100) NOT NULL,
                id_number VARCHAR(20) UNIQUE NOT NULL,
                client_type ENUM('Individual', 'Company', 'Both') NOT NULL,
                county VARCHAR(50) NOT NULL,
                etims_status ENUM('Registered', 'Pending', 'Not Registered') DEFAULT 'Not Registered',
                registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )"
        ];

        foreach ($queries as $query) {
            $this->connection->exec($query);
        }

        // Create default admin user if not exists
        $this->createDefaultAdmin();
    }

    private function createDefaultAdmin() {
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->connection->prepare("
                INSERT INTO users (username, email, password, full_name, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'admin',
                'admin@calydaaccounts.com',
                password_hash('admin123', PASSWORD_DEFAULT),
                'System Administrator',
                'admin'
            ]);
        }
    }
}
?>
