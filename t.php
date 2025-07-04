<?php
// ============================================
// CALYDA ACCOUNTS INTERNAL RECORD MANAGEMENT SYSTEM
// Complete implementation with all required files
// ============================================

// FILE 1: config/constants.php
?>
<?php
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'calyda_accounts');
define('DB_USER', 'root');
define('DB_PASS', '');

// System Configuration
define('SYSTEM_NAME', 'Calyda Accounts - Internal Record Management System');
define('SYSTEM_VERSION', '1.0.0');
define('DEFAULT_TIMEZONE', 'Africa/Nairobi');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Kenyan Counties
define('KENYAN_COUNTIES', [
    'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet', 'Embu', 'Garissa', 
    'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho', 'Kiambu', 'Kilifi', 
    'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui', 'Kwale', 'Laikipia', 'Lamu', 'Machakos', 
    'Makueni', 'Mandera', 'Marsabit', 'Meru', 'Migori', 'Mombasa', 'Murang\'a', 
    'Nairobi', 'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 'Nyeri', 
    'Samburu', 'Siaya', 'Taita-Taveta', 'Tana River', 'Tharaka-Nithi', 'Trans Nzoia', 
    'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot'
]);

// Tax Obligations
define('TAX_OBLIGATIONS', [
    'VAT' => 'Value Added Tax',
    'Income Tax' => 'Income Tax',
    'PAYEE' => 'Pay As You Earn',
    'Rental' => 'Rental Income Tax',
    'TOT' => 'Turnover Tax',
    'Partnership' => 'Partnership Tax',
    'Other' => 'Other Tax Obligations'
]);

// Client Types
define('CLIENT_TYPES', [
    'Individual' => 'Individual',
    'Company' => 'Company',
    'Both' => 'Both Individual & Company'
]);
?>

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

<?php
// FILE 4: classes/Client.php
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

class Client {
    private $db;
    private $lastError = '';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data) {
        try {
            // Check if KRA PIN or ID Number already exists
            if ($this->krapinExists($data['kra_pin'])) {
                $this->lastError = 'KRA PIN already exists in the system';
                return false;
            }

            if ($this->idNumberExists($data['id_number'])) {
                $this->lastError = 'ID Number already exists in the system';
                return false;
            }

            $stmt = $this->db->prepare("
                INSERT INTO clients (
                    full_name, phone_number, kra_pin, password, tax_obligations,
                    email_address, id_number, client_type, county, etims_status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $data['full_name'],
                $data['phone_number'],
                $data['kra_pin'],
                $data['password'],
                json_encode($data['tax_obligations']),
                $data['email_address'],
                $data['id_number'],
                $data['client_type'],
                $data['county'],
                $data['etims_status'],
                $data['created_by'] ?? null
            ]);

            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            $this->lastError = 'Failed to create client: ' . $e->getMessage();
            return false;
        }
    }

    public function update($id, $data) {
        try {
            // Check if KRA PIN or ID Number already exists for other clients
            if ($this->krapiExistsForOther($data['kra_pin'], $id)) {
                $this->lastError = 'KRA PIN already exists for another client';
                return false;
            }

            if ($this->idNumberExistsForOther($data['id_number'], $id)) {
                $this->lastError = 'ID Number already exists for another client';
                return false;
            }

            $stmt = $this->db->prepare("
                UPDATE clients SET 
                    full_name = ?, phone_number = ?, kra_pin = ?, password = ?,
                    tax_obligations = ?, email_address = ?, id_number = ?,
                    client_type = ?, county = ?, etims_status = ?
                WHERE id = ?
            ");

            return $stmt->execute([
                $data['full_name'],
                $data['phone_number'],
                $data['kra_pin'],
                $data['password'],
                json_encode($data['tax_obligations']),
                $data['email_address'],
                $data['id_number'],
                $data['client_type'],
                $data['county'],
                $data['etims_status'],
                $id
            ]);
        } catch (PDOException $e) {
            $this->lastError = 'Failed to update client: ' . $e->getMessage();
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM clients WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            $this->lastError = 'Failed to delete client: ' . $e->getMessage();
            return false;
        }
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        
        if ($client && $client['tax_obligations']) {
            $client['tax_obligations'] = json_decode($client['tax_obligations'], true);
        }
        
        return $client;
    }

    public function getAll($filters = []) {
        $query = "SELECT * FROM clients WHERE 1=1";
        $params = [];

        // Search filters
        if (!empty($filters['search'])) {
            $query .= " AND (full_name LIKE ? OR kra_pin LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Client type filter
        if (!empty($filters['client_type'])) {
            $query .= " AND client_type = ?";
            $params[] = $filters['client_type'];
        }

        // Tax obligation filter
        if (!empty($filters['tax_obligation'])) {
            $query .= " AND JSON_CONTAINS(tax_obligations, ?)";
            $params[] = '"' . $filters['tax_obligation'] . '"';
        }

        // County filter
        if (!empty($filters['county'])) {
            $query .= " AND county = ?";
            $params[] = $filters['county'];
        }

        // ETIMS status filter
        if (!empty($filters['etims_status'])) {
            $query .= " AND etims_status = ?";
            $params[] = $filters['etims_status'];
        }

        $query .= " ORDER BY registration_date DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $clients = $stmt->fetchAll();

        // Decode tax obligations for each client
        foreach ($clients as &$client) {
            if ($client['tax_obligations']) {
                $client['tax_obligations'] = json_decode($client['tax_obligations'], true);
            }
        }

        return $clients;
    }

    public function getStats() {
        $stats = [];

        // Total clients
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM clients");
        $stats['total_clients'] = $stmt->fetch()['total'];

        // Clients by type
        $stmt = $this->db->query("
            SELECT client_type, COUNT(*) as count 
            FROM clients 
            GROUP BY client_type
        ");
        $stats['by_type'] = $stmt->fetchAll();

        // ETIMS registration status
        $stmt = $this->db->query("
            SELECT etims_status, COUNT(*) as count 
            FROM clients 
            GROUP BY etims_status
        ");
        $stats['etims_status'] = $stmt->fetchAll();

        // Recent registrations (last 30 days)
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM clients 
            WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['recent_registrations'] = $stmt->fetch()['count'];

        return $stats;
    }

    private function krapiExists($krapin) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE kra_pin = ?");
        $stmt->execute([$krapin]);
        return $stmt->fetchColumn() > 0;
    }

    private function idNumberExists($idNumber) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE id_number = ?");
        $stmt->execute([$idNumber]);
        return $stmt->fetchColumn() > 0;
    }

    private function krapiExistsForOther($krapin, $excludeId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE kra_pin = ? AND id != ?");
        $stmt->execute([$krapin, $excludeId]);
        return $stmt->fetchColumn() > 0;
    }

    private function idNumberExistsForOther($idNumber, $excludeId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM clients WHERE id_number = ? AND id != ?");
        $stmt->execute([$idNumber, $excludeId]);
        return $stmt->fetchColumn() > 0;
    }

    public function getLastError() {
        return $this->lastError;
    }
}
?>

<?php
// FILE 5: dashboard.php - Main Dashboard
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', __DIR__);

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'classes/Client.php';

// Initialize database tables
Database::getInstance()->createTables();

$client = new Client();
$stats = $client->getStats();

// Handle filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'client_type' => $_GET['client_type'] ?? '',
    'tax_obligation' => $_GET['tax_obligation'] ?? '',
    'county' => $_GET['county'] ?? '',
    'etims_status' => $_GET['etims_status'] ?? ''
];

$clients = $client->getAll($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Calyda Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .table th {
            background-color: #f8f9fa;
            border: none;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                left: -250px;
                width: 250px;
                z-index: 1000;
                transition: left 0.3s;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
                <div class="p-3">
                    <h5 class="text-center mb-4">
                        <i class="fas fa-building me-2"></i>
                        Calyda Accounts
                    </h5>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="clients.php">
                            <i class="fas fa-users me-2"></i>Client Management
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
                <div class="mt-auto p-3">
                    <small class="text-light">
                        Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </small>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Mobile Menu Toggle -->
                <div class="d-md-none mb-3">
                    <button class="btn btn-primary" type="button" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i> Menu
                    </button>
                </div>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                        <i class="fas fa-plus me-2"></i>Add New Client
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h3><?php echo $stats['total_clients']; ?></h3>
                                <p class="mb-0">Total Clients</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                                <h3><?php echo $stats['recent_registrations']; ?></h3>
                                <p class="mb-0">New This Month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-building fa-2x mb-2"></i>
                                <h3><?php echo count(array_filter($stats['by_type'], function($item) { return $item['client_type'] == 'Company'; })) > 0 ? array_filter($stats['by_type'], function($item) { return $item['client_type'] == 'Company'; })[0]['count'] : 0; ?></h3>
                                <p class="mb-0">Companies</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-user fa-2x mb-2"></i>
                                <h3><?php echo count(array_filter($stats['by_type'], function($item) { return $item['client_type'] == 'Individual'; })) > 0 ? array_filter($stats['by_type'], function($item) { return $item['client_type'] == 'Individual'; })[0]['count'] : 0; ?></h3>
                                <p class="mb-0">Individuals</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Filter Clients</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Search by name or KRA PIN" value="<?php echo htmlspecialchars($filters['search']); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="client_type">
                                    <option value="">All Types</option>
                                    <?php foreach (CLIENT_TYPES as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filters['client_type'] == $key ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="tax_obligation">
                                    <option value="">All Tax Obligations</option>
                                    <?php foreach (TAX_OBLIGATIONS as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filters['tax_obligation'] == $key ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="county">
                                    <option value="">All Counties</option>
                                    <?php foreach (KENYAN_COUNTIES as $county): ?>
                                        <option value="<?php echo $county; ?>" <?php echo $filters['county'] == $county ? 'selected' : ''; ?>>
                                            <?php echo $county; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="etims_status">
                                    <option value="">All ETIMS Status</option>
                                    <option value="Registered" <?php echo $filters['etims_status'] == 'Registered' ? 'selected' : ''; ?>>Registered</option>
                                    <option value="Pending" <?php echo $filters['etims_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Not Registered" <?php echo $filters['etims_status'] == 'Not Registered' ? 'selected' : ''; ?>>Not Registered</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Clients Table -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Client Records (<?php echo count($clients); ?> found)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Full Name</th>
                                        <th>KRA PIN</th>
                                        <th>Phone</th>
                                        <th>Client Type</th>
                                        <th>County</th>
                                        <th>ETIMS Status</th>
                                        <th>Registration Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No clients found. Add your first client to get started!</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $clientData): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($clientData['full_name']); ?></td>
                                                <td>
                                                    <span class="me-2"><?php echo htmlspecialchars($clientData['kra_pin']); ?></span>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?php echo htmlspecialchars($clientData['kra_pin']); ?>')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </td>
                                                <td><?php echo htmlspecialchars($clientData['phone_number']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($clientData['client_type']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($clientData['county']); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = $clientData['etims_status'] == 'Registered' ? 'bg-success' : 
                                                                  ($clientData['etims_status'] == 'Pending' ? 'bg-warning' : 'bg-danger');
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($clientData['etims_status']); ?></span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($clientData['registration_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="viewClient(<?php echo $clientData['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-warning" onclick="editClient(<?php echo $clientData['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deleteClient(<?php echo $clientData['id']; ?>, '<?php echo htmlspecialchars($clientData['full_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Client Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addClientForm" method="POST" action="ajax/client_actions.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone_number" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">KRA PIN *</label>
                                <input type="text" class="form-control" name="kra_pin" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="password" id="clientPassword" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                        <i class="fas fa-random"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard(document.getElementById('clientPassword').value)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email_address" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ID Number *</label>
                                <input type="text" class="form-control" name="id_number" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Type *</label>
                                <select class="form-select" name="client_type" required>
                                    <option value="">Select Type</option>
                                    <?php foreach (CLIENT_TYPES as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">County *</label>
                                <select class="form-select" name="county" required>
                                    <option value="">Select County</option>
                                    <?php foreach (KENYAN_COUNTIES as $county): ?>
                                        <option value="<?php echo $county; ?>"><?php echo $county; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tax Obligations *</label>
                            <div class="row">
                                <?php foreach (TAX_OBLIGATIONS as $key => $value): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tax_obligations[]" value="<?php echo $key; ?>" id="tax_<?php echo str_replace(' ', '_', $key); ?>">
                                            <label class="form-check-label" for="tax_<?php echo str_replace(' ', '_', $key); ?>">
                                                <?php echo $value; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ETIMS Registration Status</label>
                            <select class="form-select" name="etims_status">
                                <option value="Not Registered">Not Registered</option>
                                <option value="Pending">Pending</option>
                                <option value="Registered">Registered</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Client Modal -->
    <div class="modal fade" id="viewClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Client Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewClientContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Client Modal -->
    <div class="modal fade" id="editClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editClientContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function generatePassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            let password = '';
            for (let i = 0; i < 8; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('clientPassword').value = password;
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show toast notification
                showToast('Copied to clipboard!', 'success');
            });
        }

        function viewClient(id) {
            fetch(`ajax/client_actions.php?action=view&id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('viewClientContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('viewClientModal')).show();
                });
        }

        function editClient(id) {
            fetch(`ajax/client_actions.php?action=edit&id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('editClientContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('editClientModal')).show();
                });
        }

        function deleteClient(id, name) {
            if (confirm(`Are you sure you want to delete client: ${name}?`)) {
                fetch('ajax/client_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Client deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Failed to delete client', 'error');
                    }
                });
            }
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close ms-2" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        // Handle form submission
        document.getElementById('addClientForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/client_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Client added successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addClientModal')).hide();
                    this.reset();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Failed to add client', 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred. Please try again.', 'error');
            });
        });
    </script>
</body>
</html>

<?php
// FILE 6: ajax/client_actions.php - Handle AJAX requests
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../classes/Client.php';

$client = new Client();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create':
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'kra_pin' => strtoupper(trim($_POST['kra_pin'] ?? '')),
            'password' => $_POST['password'] ?? '',
            'tax_obligations' => $_POST['tax_obligations'] ?? [],
            'email_address' => trim($_POST['email_address'] ?? ''),
            'id_number' => trim($_POST['id_number'] ?? ''),
            'client_type' => $_POST['client_type'] ?? '',
            'county' => $_POST['county'] ?? '',
            'etims_status' => $_POST['etims_status'] ?? 'Not Registered',
            'created_by' => $_SESSION['user_id']
        ];

        // Validation
        if (empty($data['full_name']) || empty($data['phone_number']) || empty($data['kra_pin']) || 
            empty($data['password']) || empty($data['email_address']) || empty($data['id_number']) || 
            empty($data['client_type']) || empty($data['county'])) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }

        if (empty($data['tax_obligations'])) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one tax obligation']);
            exit;
        }

        $result = $client->create($data);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Client created successfully', 'id' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => $client->getLastError()]);
        }
        break;

    case 'view':
        $id = $_GET['id'] ?? 0;
        $clientData = $client->getById($id);
        
        if (!$clientData) {
            echo '<div class="alert alert-danger">Client not found</div>';
            exit;
        }
        ?>
        <div class="row">
            <div class="col-md-6">
                <h6>Personal Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Full Name:</strong></td><td><?php echo htmlspecialchars($clientData['full_name']); ?></td></tr>
                    <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($clientData['phone_number']); ?></td></tr>
                    <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($clientData['email_address']); ?></td></tr>
                    <tr><td><strong>ID Number:</strong></td><td><?php echo htmlspecialchars($clientData['id_number']); ?></td></tr>
                    <tr><td><strong>Client Type:</strong></td><td><?php echo htmlspecialchars($clientData['client_type']); ?></td></tr>
                    <tr><td><strong>County:</strong></td><td><?php echo htmlspecialchars($clientData['county']); ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Account Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>KRA PIN:</strong></td>
                        <td>
                            <?php echo htmlspecialchars($clientData['kra_pin']); ?>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?php echo htmlspecialchars($clientData['kra_pin']); ?>')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Password:</strong></td>
                        <td>
                            <?php echo htmlspecialchars($clientData['password']); ?>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?php echo htmlspecialchars($clientData['password']); ?>')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                    </tr>
                    <tr><td><strong>ETIMS Status:</strong></td><td>
                        <span class="badge bg-<?php echo $clientData['etims_status'] == 'Registered' ? 'success' : ($clientData['etims_status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                            <?php echo htmlspecialchars($clientData['etims_status']); ?>
                        </span>
                    </td></tr>
                    <tr><td><strong>Registration Date:</strong></td><td><?php echo date('M j, Y g:i A', strtotime($clientData['registration_date'])); ?></td></tr>
                </table>
                
                <h6>Tax Obligations</h6>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($clientData['tax_obligations'] as $tax): ?>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($tax); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        break;

    case 'edit':
        $id = $_GET['id'] ?? 0;
        $clientData = $client->getById($id);
        
        if (!$clientData) {
            echo '<div class="alert alert-danger">Client not found</div>';
            exit;
        }
        ?>
        <form id="editClientForm" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($clientData['full_name']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($clientData['phone_number']); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">KRA PIN *</label>
                    <input type="text" class="form-control" name="kra_pin" value="<?php echo htmlspecialchars($clientData['kra_pin']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password *</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="password" id="editClientPassword" value="<?php echo htmlspecialchars($clientData['password']); ?>" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="generatePasswordEdit()">
                            <i class="fas fa-random"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard(document.getElementById('editClientPassword').value)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email Address *</label>
                    <input type="email" class="form-control" name="email_address" value="<?php echo htmlspecialchars($clientData['email_address']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">ID Number *</label>
                    <input type="text" class="form-control" name="id_number" value="<?php echo htmlspecialchars($clientData['id_number']); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Client Type *</label>
                    <select class="form-select" name="client_type" required>
                        <?php foreach (CLIENT_TYPES as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo $clientData['client_type'] == $key ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">County *</label>
                    <select class="form-select" name="county" required>
                        <?php foreach (KENYAN_COUNTIES as $county): ?>
                            <option value="<?php echo $county; ?>" <?php echo $clientData['county'] == $county ? 'selected' : ''; ?>>
                                <?php echo $county; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Tax Obligations *</label>
                <div class="row">
                    <?php foreach (TAX_OBLIGATIONS as $key => $value): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tax_obligations[]" value="<?php echo $key; ?>" 
                                       id="edit_tax_<?php echo str_replace(' ', '_', $key); ?>"
                                       <?php echo in_array($key, $clientData['tax_obligations']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edit_tax_<?php echo str_replace(' ', '_', $key); ?>">
                                    <?php echo $value; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">ETIMS Registration Status</label>
                <select class="form-select" name="etims_status">
                    <option value="Not Registered" <?php echo $clientData['etims_status'] == 'Not Registered' ? 'selected' : ''; ?>>Not Registered</option>
                    <option value="Pending" <?php echo $clientData['etims_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Registered" <?php echo $clientData['etims_status'] == 'Registered' ? 'selected' : ''; ?>>Registered</option>
                </select>
            </div>
            <div class="text-end">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Client</button>
            </div>
        </form>
        
        <script>
            function generatePasswordEdit() {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
                let password = '';
                for (let i = 0; i < 8; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                document.getElementById('editClientPassword').value = password;
            }

            document.getElementById('editClientForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('ajax/client_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        parent.showToast('Client updated successfully!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editClientModal')).hide();
                        setTimeout(() => parent.location.reload(), 1500);
                    } else {
                        parent.showToast(data.message || 'Failed to update client', 'error');
                    }
                })
                .catch(error => {
                    parent.showToast('An error occurred. Please try again.', 'error');
                });
            });
        </script>
        <?php
        break;

    case 'update':
        $id = $_POST['id'] ?? 0;
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'kra_pin' => strtoupper(trim($_POST['kra_pin'] ?? '')),
            'password' => $_POST['password'] ?? '',
            'tax_obligations' => $_POST['tax_obligations'] ?? [],
            'email_address' => trim($_POST['email_address'] ?? ''),
            'id_number' => trim($_POST['id_number'] ?? ''),
            'client_type' => $_POST['client_type'] ?? '',
            'county' => $_POST['county'] ?? '',
            'etims_status' => $_POST['etims_status'] ?? 'Not Registered'
        ];

        // Validation
        if (empty($data['full_name']) || empty($data['phone_number']) || empty($data['kra_pin']) || 
            empty($data['password']) || empty($data['email_address']) || empty($data['id_number']) || 
            empty($data['client_type']) || empty($data['county'])) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }

        if (empty($data['tax_obligations'])) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one tax obligation']);
            exit;
        }

        $result = $client->update($id, $data);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Client updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $client->getLastError()]);
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? 0;
        $result = $client->delete($id);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Client deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $client->getLastError()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

<?php
// FILE 7: logout.php
session_start();
session_destroy();
header('Location: index.php');
exit;
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE 8: reports.php - Reports Page
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', __DIR__);

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'classes/Client.php';

$client = new Client();
$stats = $client->getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Calyda Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content { padding: 20px; }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h5 class="text-center mb-4">
                        <i class="fas fa-building me-2"></i>
                        Calyda Accounts
                    </h5>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="clients.php">
                            <i class="fas fa-users me-2"></i>Client Management
                        </a>
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Reports & Analytics</h2>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center>
                                <h5 class="card-title">Total Clients</h5>
                                <p class="card-text"><?php echo $stats['total_clients']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Active Clients</h5>
                                <p class="card-text"><?php echo $stats['active_clients']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Pending Clients</h5>
                                <p class="card-text"><?php echo $stats['pending_clients']; ?></p>
                            </div>
                        </div>
                    </div>                                          
                    <div class="col-md-3 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Inactive Clients</h5>
                                <p class="card-text"><?php echo $stats['inactive_clients']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Charts -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Client Distribution by Type</h5>
                                <canvas id="clientTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Client Distribution by County</h5>
                                <canvas id="clientCountyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client Type Distribution Chart
        const clientTypeData = <?php echo json_encode($stats['client_type_distribution']); ?>;
        const clientTypeLabels = Object.keys(clientTypeData);
        const clientTypeCounts = Object.values(clientTypeData);         
        const clientTypeChart = new Chart(document.getElementById('clientTypeChart'), {
            type: 'pie',
            data: {
                labels: clientTypeLabels,
                datasets: [{
                    data: clientTypeCounts,
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545'],
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Client Distribution by Type'
                    }
                }
            }
        });

        // Client County Distribution Chart
        const clientCountyData = <?php echo json_encode($stats['client_county_distribution']); ?>;
        const clientCountyLabels = Object.keys(clientCountyData);
        const clientCountyCounts = Object.values(clientCountyData);         
        const clientCountyChart = new Chart(document.getElementById('clientCountyChart'), {
            type: 'bar',
            data: {
                labels: clientCountyLabels,
                datasets: [{
                    label: 'Number of Clients',
                    data: clientCountyCounts,
                    backgroundColor: '#007bff',
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Client Distribution by County'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>

