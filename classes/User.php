<?php
// FILE 3: classes/User.php
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

class User {
    private $db;
    private $lastError = '';
    private $currentUser = null;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, password, full_name, role 
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $this->currentUser = $user;
                return true;
            } else {
                $this->lastError = 'Invalid username or password';
                return false;
            }
        } catch (PDOException $e) {
            $this->lastError = 'Login failed. Please try again.';
            return false;
        }
    }

    public function getCurrentUser() {
        return $this->currentUser;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
?>