<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE: vat_management.php - VAT Record Management System
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

// Initialize database and create VAT table if not exists
$database = Database::getInstance();
$pdo = $database->getConnection();

// Create VAT records table
$createVatTable = "
CREATE TABLE IF NOT EXISTS vat_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    record_year INT NOT NULL,
    record_month INT NOT NULL,
    sales_amount DECIMAL(15,2) DEFAULT 0.00,
    purchases_amount DECIMAL(15,2) DEFAULT 0.00,
    sales_vat DECIMAL(15,2) DEFAULT 0.00,
    purchases_vat DECIMAL(15,2) DEFAULT 0.00,
    net_vat DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_client_month (client_id, record_year, record_month)
)";
$pdo->exec($createVatTable);

$client = new Client();

// Get VAT clients (clients with VAT obligation)
$vatClients = $client->getVATClients();

// Handle filters
$filters = [
    'client_id' => $_GET['client_id'] ?? '',
    'year' => $_GET['year'] ?? date('Y')
];

// Get VAT records based on filters
$vatRecords = [];
$selectedClient = null;
$yearlyTotals = [];

if ($filters['client_id']) {
    $selectedClient = $client->getById($filters['client_id']);
    $vatRecords = getVATRecords($pdo, $filters['client_id'], $filters['year']);
    $yearlyTotals = calculateYearlyTotals($vatRecords);
}

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === 'pdf' && $filters['client_id']) {
    exportVATReport($selectedClient, $vatRecords, $yearlyTotals, $filters['year']);
    exit;
}

// Functions
function getVATClients($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, full_name, kra_pin 
        FROM clients 
        WHERE tax_obligations LIKE '%VAT%' 
        ORDER BY full_name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVATRecords($pdo, $clientId, $year) {
    $stmt = $pdo->prepare("
        SELECT * FROM vat_records 
        WHERE client_id = ? AND record_year = ? 
        ORDER BY record_month
    ");
    $stmt->execute([$clientId, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateYearlyTotals($records) {
    $totals = [
        'sales_amount' => 0,
        'purchases_amount' => 0,
        'sales_vat' => 0,
        'purchases_vat' => 0,
        'net_vat' => 0
    ];
    
    foreach ($records as $record) {
        $totals['sales_amount'] += $record['sales_amount'];
        $totals['purchases_amount'] += $record['purchases_amount'];
        $totals['sales_vat'] += $record['sales_vat'];
        $totals['purchases_vat'] += $record['purchases_vat'];
        $totals['net_vat'] += $record['net_vat'];
    }
    
    return $totals;
}

function exportVATReport($client, $records, $totals, $year) {
    // Simple HTML to PDF export (you can enhance this with proper PDF library)
    $html = generateVATReportHTML($client, $records, $totals, $year);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="VAT_Report_' . $client['full_name'] . '_' . $year . '.pdf"');
    
    // For now, we'll output HTML (you can integrate with libraries like TCPDF or mPDF)
    echo $html;
}

function generateVATReportHTML($client, $records, $totals, $year) {
    $html = '<!DOCTYPE html><html><head><title>VAT Report</title><style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .client-info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f2f2f2; }
        .totals { font-weight: bold; background-color: #e9ecef; }
    </style></head><body>';
    
    $html .= '<div class="header"><h1>VAT REPORT - ' . $year . '</h1></div>';
    $html .= '<div class="client-info">';
    $html .= '<p><strong>Client:</strong> ' . htmlspecialchars($client['full_name']) . '</p>';
    $html .= '<p><strong>KRA PIN:</strong> ' . htmlspecialchars($client['kra_pin']) . '</p>';
    $html .= '<p><strong>Report Date:</strong> ' . date('F j, Y') . '</p>';
    $html .= '</div>';
    
    $html .= '<table><thead><tr>';
    $html .= '<th>Month</th><th>Sales Amount</th><th>Sales VAT</th><th>Purchases Amount</th><th>Purchases VAT</th><th>Net VAT</th>';
    $html .= '</tr></thead><tbody>';
    
    $months = ['', 'January', 'February', 'March', 'April', 'May', 'June',
               'July', 'August', 'September', 'October', 'November', 'December'];
    
    for ($i = 1; $i <= 12; $i++) {
        $record = array_filter($records, function($r) use ($i) { return $r['record_month'] == $i; });
        $record = reset($record);
        
        $html .= '<tr>';
        $html .= '<td style="text-align: left;">' . $months[$i] . '</td>';
        $html .= '<td>' . number_format($record ? $record['sales_amount'] : 0, 2) . '</td>';
        $html .= '<td>' . number_format($record ? $record['sales_vat'] : 0, 2) . '</td>';
        $html .= '<td>' . number_format($record ? $record['purchases_amount'] : 0, 2) . '</td>';
        $html .= '<td>' . number_format($record ? $record['purchases_vat'] : 0, 2) . '</td>';
        $html .= '<td>' . number_format($record ? $record['net_vat'] : 0, 2) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '<tr class="totals">';
    $html .= '<td style="text-align: left;"><strong>TOTAL</strong></td>';
    $html .= '<td><strong>' . number_format($totals['sales_amount'], 2) . '</strong></td>';
    $html .= '<td><strong>' . number_format($totals['sales_vat'], 2) . '</strong></td>';
    $html .= '<td><strong>' . number_format($totals['purchases_amount'], 2) . '</strong></td>';
    $html .= '<td><strong>' . number_format($totals['purchases_vat'], 2) . '</strong></td>';
    $html .= '<td><strong>' . number_format($totals['net_vat'], 2) . '</strong></td>';
    $html .= '</tr>';
    
    $html .= '</tbody></table></body></html>';
    
    return $html;
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAT Management - Calyda Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        /* Enhanced Modern Sidebar Styles */
.sidebar {
    background: linear-gradient(145deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    backdrop-filter: blur(20px);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 
        0 0 40px rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    z-index: 1000;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

/* Sidebar Header Enhancement */
.sidebar .sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
}

.sidebar .logo-container {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.sidebar .logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 18px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.sidebar .logo-text {
    font-size: 20px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.sidebar .company-tagline {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 5px;
    letter-spacing: 0.5px;
}

/* Close Button for Mobile */
.sidebar-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 35px;
    height: 35px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: none;
}

.sidebar-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
}

/* Enhanced Navigation */
.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 15px 20px;
    border-radius: 12px;
    margin: 5px 15px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.sidebar .nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s;
}

.sidebar .nav-link:hover::before {
    left: 100%;
}

.sidebar .nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
}

.sidebar .nav-link.active::before {
    display: none;
}

.sidebar .nav-link i {
    width: 20px;
    margin-right: 12px;
    font-size: 16px;
}

/* System Status Section */
.sidebar .system-status {
    padding: 20px;
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar .uptime-display {
    background: rgba(255, 255, 255, 0.05);
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar .uptime-title {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.sidebar .uptime-value {
    font-size: 14px;
    font-weight: 600;
    color: #4ade80;
    display: flex;
    align-items: center;
}

.sidebar .uptime-value i {
    margin-right: 8px;
    font-size: 12px;
}

.sidebar .status-indicator {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    margin-right: 8px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* User Info Enhancement */
.sidebar .user-info {
    background: rgba(255, 255, 255, 0.05);
    padding: 15px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar .user-info .user-avatar {
    width: 35px;
    height: 35px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 14px;
    color: #fff;
    font-weight: 600;
}

.sidebar .user-info .user-details {
    flex: 1;
}

.sidebar .user-info .user-name {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 2px;
}

.sidebar .user-info .user-role {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.6);
}

/* Main Content Adjustment */
.main-content {
    margin-left: 280px;
    padding: 20px;
    background: #f8f9fa;
    min-height: 100vh;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
    
    .sidebar .nav-link {
        margin: 5px 10px;
        padding: 12px 15px;
    }
    
    .sidebar .system-status {
        padding: 15px;
    }
}

/* Mobile Menu Toggle Button */
.mobile-menu-toggle {
    position: fixed;
    top: 20px;
    left: 20px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 12px;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    z-index: 1001;
    transition: all 0.3s ease;
    display: none;
}

.mobile-menu-toggle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

@media (max-width: 768px) {
    .mobile-menu-toggle {
        display: block;
    }
}

/* Enhanced Card Styles */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}

/* Overlay for mobile */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.sidebar-overlay.show {
    opacity: 1;
    visibility: visible;
}
        .main-content {
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .vat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        .vat-summary {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        .totals-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
            /* Quick Actions Button Styles */
.quick-actions-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    transition: all 0.3s ease;
    animation: pulse 2s infinite;
}

.quick-actions-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(0,0,0,0.2);
}

@keyframes pulse {
    0% { box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); }
    50% { box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6); }
    100% { box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); }
}

.quick-actions-menu {
    position: fixed;
    bottom: 100px;
    right: 30px;
    z-index: 999;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    padding: 15px;
    min-width: 250px;
    transform: translateY(20px);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.quick-actions-menu.show {
    transform: translateY(0);
    opacity: 1;
    visibility: visible;
}

.quick-action-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    margin: 5px 0;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}

.quick-action-item:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transform: translateX(5px);
}

.quick-action-item i {
    margin-right: 12px;
    width: 20px;
    font-size: 16px;
}

.quick-action-item span {
    font-size: 14px;
    font-weight: 500;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .quick-actions-btn {
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
    }
    
    .quick-actions-menu {
        bottom: 80px;
        right: 20px;
        min-width: 200px;
    }
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
            <!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Enhanced Sidebar -->
<div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <button class="sidebar-close" onclick="closeSidebar()">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <div class="logo-text">Calyda Accounts</div>
                <div class="company-tagline">Professional Tax Solutions</div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a class="nav-link" href="./dashboard.php">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="./clients.php">
                <i class="fas fa-users"></i>Client Management
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link active" href="vat_management.php">
                <i class="fas fa-receipt"></i>VAT Management
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="./reports.php">
                <i class="fas fa-chart-bar"></i>Reports
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="./settings.php">
                <i class="fas fa-cog"></i>Settings
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="./logout.php">
                <i class="fas fa-sign-out-alt"></i>Logout
            </a>
        </div>
    </nav>

    <!-- System Status Section -->
    <div class="system-status">
        <div class="uptime-display">
            <div class="uptime-title">System Uptime</div>
            <div class="uptime-value">
                <div class="status-indicator"></div>
                <span id="systemUptime">Loading...</span>
            </div>
        </div>

        <!-- User Info -->
        <div class="user-info d-flex align-items-center">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <div class="user-role">Tax Professional</div>
            </div>
        </div>
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
                    <h2><i class="fas fa-receipt me-2"></i>VAT Management</h2>
                    <?php if ($selectedClient): ?>
                        <div>
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addVATModal">
                                <i class="fas fa-plus me-2"></i>Add VAT Entry
                            </button>
        <a href="/cal/utils/vat_export.php?client_id=<?php echo urlencode($filters['client_id']); ?>&year=<?php echo urlencode($filters['year']); ?>&format=pdf" 
           class="btn btn-primary" target="_blank">
                                <i class="fas fa-download me-2"></i>Export Report
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Client Selection and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Select Client & Year</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">VAT Client</label>
                                <select class="form-select" name="client_id" onchange="this.form.submit()">
                                    <option value="">Select a VAT Client</option>
                                    <?php foreach ($vatClients as $vatClient): ?>
                                        <option value="<?php echo $vatClient['id']; ?>" 
                                                <?php echo $filters['client_id'] == $vatClient['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vatClient['full_name'] . ' (' . $vatClient['kra_pin'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Year</label>
                                <select class="form-select" name="year" onchange="this.form.submit()">
                                    <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                        <option value="<?php echo $year; ?>" 
                                                <?php echo $filters['year'] == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!$selectedClient): ?>
                    <!-- No Client Selected -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">VAT Record Management</h4>
                            <p class="text-muted">Select a client with VAT obligations to manage their VAT records</p>
                            <?php if (empty($vatClients)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No clients with VAT obligations found. Clients must have VAT in their tax obligations to appear here.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Client Selected - Show VAT Records -->
                    
                    <!-- Client Info Card -->
                    <div class="card vat-card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="mb-1"><?php echo htmlspecialchars($selectedClient['full_name']); ?></h4>
                                    <p class="mb-0">
                                        <strong>KRA PIN:</strong> <?php echo htmlspecialchars($selectedClient['kra_pin']); ?> |
                                        <strong>Year:</strong> <?php echo $filters['year']; ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <h2 class="mb-0">
                                        <i class="fas fa-receipt me-2"></i>
                                        VAT Records
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Yearly Summary Cards -->
                    <?php if (!empty($yearlyTotals)): ?>
                        <div class="row mb-4">
                            <div class="col-md-2 col-sm-6 mb-3">
                                <div class="card vat-summary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-arrow-up fa-2x mb-2"></i>
                                        <h5>KES <?php echo number_format($yearlyTotals['sales_amount'], 2); ?></h5>
                                        <small>Total Sales</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6 mb-3">
                                <div class="card vat-summary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-plus fa-2x mb-2"></i>
                                        <h5>KES <?php echo number_format($yearlyTotals['sales_vat'], 2); ?></h5>
                                        <small>Sales VAT</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6 mb-3">
                                <div class="card vat-summary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-arrow-down fa-2x mb-2"></i>
                                        <h5>KES <?php echo number_format($yearlyTotals['purchases_amount'], 2); ?></h5>
                                        <small>Total Purchases</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6 mb-3">
                                <div class="card vat-summary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-minus fa-2x mb-2"></i>
                                        <h5>KES <?php echo number_format($yearlyTotals['purchases_vat'], 2); ?></h5>
                                        <small>Purchases VAT</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-12 mb-3">
                                <div class="card" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); color: white;">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calculator fa-2x mb-2"></i>
                                        <h4>KES <?php echo number_format($yearlyTotals['net_vat'], 2); ?></h4>
                                        <strong>Net VAT <?php echo $yearlyTotals['net_vat'] >= 0 ? 'Payable' : 'Refundable'; ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- VAT Records Table -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Monthly VAT Records - <?php echo $filters['year']; ?></h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th class="text-end">Sales Amount</th>
                                            <th class="text-end">Sales VAT (16%)</th>
                                            <th class="text-end">Purchases Amount</th>
                                            <th class="text-end">Purchases VAT (16%)</th>
                                            <th class="text-end">Net VAT</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $recordsByMonth = [];
                                        foreach ($vatRecords as $record) {
                                            $recordsByMonth[$record['record_month']] = $record;
                                        }
                                        
                                        for ($month = 1; $month <= 12; $month++):
                                            $record = $recordsByMonth[$month] ?? null;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $months[$month]; ?></strong></td>
                                                <td class="text-end">
                                                    KES <?php echo $record ? number_format($record['sales_amount'], 2) : '0.00'; ?>
                                                </td>
                                                <td class="text-end">
                                                    KES <?php echo $record ? number_format($record['sales_vat'], 2) : '0.00'; ?>
                                                </td>
                                                <td class="text-end">
                                                    KES <?php echo $record ? number_format($record['purchases_amount'], 2) : '0.00'; ?>
                                                </td>
                                                <td class="text-end">
                                                    KES <?php echo $record ? number_format($record['purchases_vat'], 2) : '0.00'; ?>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge <?php echo $record && $record['net_vat'] >= 0 ? 'bg-danger' : 'bg-success'; ?>">
                                                        KES <?php echo $record ? number_format($record['net_vat'], 2) : '0.00'; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($record): ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-warning" 
                                                                    onclick="editVATRecord(<?php echo $record['id']; ?>, <?php echo $month; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" 
                                                                    onclick="deleteVATRecord(<?php echo $record['id']; ?>, '<?php echo $months[$month]; ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="addVATRecord(<?php echo $month; ?>)">
                                                            <i class="fas fa-plus"></i> Add
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endfor; ?>
                                        
                                        <?php if (!empty($yearlyTotals)): ?>
                                            <tr class="totals-row">
                                                <td><strong>YEARLY TOTAL</strong></td>
                                                <td class="text-end"><strong>KES <?php echo number_format($yearlyTotals['sales_amount'], 2); ?></strong></td>
                                                <td class="text-end"><strong>KES <?php echo number_format($yearlyTotals['sales_vat'], 2); ?></strong></td>
                                                <td class="text-end"><strong>KES <?php echo number_format($yearlyTotals['purchases_amount'], 2); ?></strong></td>
                                                <td class="text-end"><strong>KES <?php echo number_format($yearlyTotals['purchases_vat'], 2); ?></strong></td>
                                                <td class="text-end">
                                                    <strong class="badge <?php echo $yearlyTotals['net_vat'] >= 0 ? 'bg-danger' : 'bg-success'; ?> fs-6">
                                                        KES <?php echo number_format($yearlyTotals['net_vat'], 2); ?>
                                                    </strong>
                                                </td>
                                                <td></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit VAT Modal -->
    <div class="modal fade" id="addVATModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vatModalTitle">Add VAT Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="vatForm" method="POST" action="/cal/ajax/vat_actions.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="client_id" value="<?php echo $filters['client_id']; ?>">
                        <input type="hidden" name="record_year" value="<?php echo $filters['year']; ?>">
                        <input type="hidden" name="record_id" id="recordId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Client</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo $selectedClient ? htmlspecialchars($selectedClient['full_name']) : ''; ?>" 
                                       readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Year</label>
                                <input type="text" class="form-control" value="<?php echo $filters['year']; ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Month *</label>
                                <select class="form-select" name="record_month" id="recordMonth" required>
                                    <option value="">Select Month</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Sales Amount (KES) *</label>
                                <input type="number" class="form-control" name="sales_amount" id="salesAmount" 
                                       step="0.01" min="0" required onchange="calculateVAT()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sales VAT (16%)</label>
                                <input type="number" class="form-control" name="sales_vat" id="salesVAT" 
                                       step="0.01" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Purchases Amount (KES) *</label>
                                <input type="number" class="form-control" name="purchases_amount" id="purchasesAmount" 
                                       step="0.01" min="0" required onchange="calculateVAT()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Purchases VAT (16%)</label>
                                <input type="number" class="form-control" name="purchases_vat" id="purchasesVAT" 
                                       step="0.01" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label">Net VAT (Sales VAT - Purchases VAT)</label>
                                <input type="number" class="form-control" name="net_vat" id="netVAT" 
                                       step="0.01" readonly>
                                <small class="form-text text-muted">
                                    Positive amount = VAT Payable | Negative amount = VAT Refundable
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save VAT Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <button class="btn btn-primary quick-actions-btn" id="quickActionsBtn" onclick="toggleQuickActions()">
    <i class="fas fa-plus" id="quickActionsIcon"></i>
</button>

<!-- Quick Actions Menu -->
<div class="quick-actions-menu" id="quickActionsMenu">
    <button class="quick-action-item" onclick="openAddClientModal()">
        <i class="fas fa-user-plus"></i>
        <span>Manage Clients</span>
    </button>
    
    <button class="quick-action-item" onclick="openVATManagement()">
        <i class="fas fa-receipt"></i>
        <span>VAT Management</span>
    </button>
    
    <button class="quick-action-item" onclick="generateReport()">
        <i class="fas fa-chart-line"></i>
        <span>Generate Report</span>
    </button>
    
    <button class="quick-action-item" onclick="bulkImport()">
        <i class="fas fa-upload"></i>
        <span>Bulk Import</span>
    </button>
    
    <button class="quick-action-item" onclick="exportData()">
        <i class="fas fa-download"></i>
        <span>Export Data</span>
    </button>
    
    <button class="quick-action-item" onclick="openSettings()">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
    </button>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function calculateVAT() {
            const salesAmount = parseFloat(document.getElementById('salesAmount').value) || 0;
            const purchasesAmount = parseFloat(document.getElementById('purchasesAmount').value) || 0;
            
            const salesVAT = salesAmount * 0.16;
            const purchasesVAT = purchasesAmount * 0.16;
            const netVAT = salesVAT - purchasesVAT;
            
            document.getElementById('salesVAT').value = salesVAT.toFixed(2);
            document.getElementById('purchasesVAT').value = purchasesVAT.toFixed(2);
            document.getElementById('netVAT').value = netVAT.toFixed(2);
        }

        function addVATRecord(month) {
            document.getElementById('vatModalTitle').textContent = 'Add VAT Record';
            document.querySelector('input[name="action"]').value = 'create';
            document.getElementById('recordId').value = '';
            document.getElementById('vatForm').reset();
            document.getElementById('recordMonth').value = month;
            // document.getElementById('recordMonth').disabled = true;
            new bootstrap.Modal(document.getElementById('addVATModal')).show();
        }

        function editVATRecord(recordId, month) {
            document.getElementById('vatModalTitle').textContent = 'Edit VAT Record';
            document.querySelector('input[name="action"]').value = 'update';
            document.getElementById('recordId').value = recordId;
            document.getElementById('recordMonth').value = month;
            // document.getElementById('recordMonth').disabled = true;
            
            // Fetch record data
            fetch(`/cal/ajax/vat_actions.php?action=get&id=${recordId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const record = data.record;
                        document.getElementById('salesAmount').value = record.sales_amount;
                        document.getElementById('purchasesAmount').value = record.purchases_amount;
                        document.getElementById('salesVAT').value = record.sales_vat;
                        document.getElementById('purchasesVAT').value = record.purchases_vat;
                        document.getElementById('netVAT').value = record.net_vat;
                    }
                });
            
            new bootstrap.Modal(document.getElementById('addVATModal')).show();
        }

        function deleteVATRecord(recordId, monthName) {
            if (confirm(`Are you sure you want to delete the VAT record for ${monthName}?`)) {
                fetch('/cal/ajax/vat_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${recordId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('VAT record deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Failed to delete VAT record', 'error');
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
        document.getElementById('vatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('/cal/ajax/vat_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('VAT record saved successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addVATModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Failed to save VAT record', 'error');
                }
            })
            .catch(error => {
                console.error('VAT Record Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        });

        // Reset form when modal is hidden
        document.getElementById('addVATModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('vatForm').reset();
            document.getElementById('recordMonth').disabled = false;
            document.getElementById('recordId').value = '';
        });
        // Enhanced Sidebar JavaScript

// System uptime tracking
let systemStartTime = new Date();
if (localStorage.getItem('systemStartTime')) {
    systemStartTime = new Date(localStorage.getItem('systemStartTime'));
} else {
    localStorage.setItem('systemStartTime', systemStartTime.toISOString());
}

// Update system uptime display
function updateSystemUptime() {
    const now = new Date();
    const uptime = now - systemStartTime;
    
    const days = Math.floor(uptime / (1000 * 60 * 60 * 24));
    const hours = Math.floor((uptime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((uptime % (1000 * 60 * 60)) / (1000 * 60));
    
    let uptimeString = '';
    if (days > 0) {
        uptimeString += `${days}d `;
    }
    if (hours > 0) {
        uptimeString += `${hours}h `;
    }
    uptimeString += `${minutes}m`;
    
    const uptimeElement = document.getElementById('systemUptime');
    if (uptimeElement) {
        uptimeElement.textContent = uptimeString;
    }
}

// Enhanced sidebar toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        
        // Prevent body scroll when sidebar is open on mobile
        if (window.innerWidth <= 768) {
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : 'auto';
        }
    }
}

// Close sidebar function
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Handle window resize
function handleResize() {
    if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar && overlay) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Update uptime immediately and then every minute
    updateSystemUptime();
    setInterval(updateSystemUptime, 60000);
    
    // Add resize event listener
    window.addEventListener('resize', handleResize);
    
    // Add click event to navigation links for mobile
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });
    
    // Add escape key listener to close sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 768) {
            closeSidebar();
        }
    });
    
    // Smooth scroll for navigation
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add loading state
            this.style.opacity = '0.7';
            setTimeout(() => {
                this.style.opacity = '1';
            }, 300);
        });
    });
});

// Add touch gesture support for mobile
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
}, false);

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleGesture();
}, false);

function handleGesture() {
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const swipeThreshold = 50;
        
        // Swipe right to open sidebar
        if (touchEndX - touchStartX > swipeThreshold && touchStartX < 50) {
            if (sidebar && !sidebar.classList.contains('show')) {
                toggleSidebar();
            }
        }
        
        // Swipe left to close sidebar
        if (touchStartX - touchEndX > swipeThreshold && sidebar && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    }
}

// System status check (optional - you can implement server-side status check)
function checkSystemStatus() {
    // This could be enhanced to check actual server status
    const statusIndicator = document.querySelector('.status-indicator');
    if (statusIndicator) {
        // Simulate status check
        fetch('/api/system-status')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'online') {
                    statusIndicator.style.background = '#4ade80';
                } else {
                    statusIndicator.style.background = '#f87171';
                }
            })
            .catch(() => {
                // If API is not available, assume system is online
                statusIndicator.style.background = '#4ade80';
            });
    }
}

// Check system status every 5 minutes
setInterval(checkSystemStatus, 300000);

// Performance optimization: Debounce resize events
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Use debounced resize handler
window.addEventListener('resize', debounce(handleResize, 250));
    </script>
    <script>
let quickActionsOpen = false;

function toggleQuickActions() {
    const menu = document.getElementById('quickActionsMenu');
    const icon = document.getElementById('quickActionsIcon');
    
    if (quickActionsOpen) {
        menu.classList.remove('show');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-plus');
        quickActionsOpen = false;
    } else {
        menu.classList.add('show');
        icon.classList.remove('fa-plus');
        icon.classList.add('fa-times');
        quickActionsOpen = true;
    }
}

// Close menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('quickActionsMenu');
    const btn = document.getElementById('quickActionsBtn');
    
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
        if (quickActionsOpen) {
            toggleQuickActions();
        }
    }
});

// Quick Action Functions
function openAddClientModal() {
    toggleQuickActions();
    window.location.href = './clients.php';
}

function openVATManagement() {
    toggleQuickActions();
    window.location.href = 'vat_management.php';
}

function generateReport() {
    toggleQuickActions();
    window.location.href = './reports.php';
}

function bulkImport() {
    toggleQuickActions();
    // Add bulk import functionality
    showToast('Bulk import feature coming soon!', 'info');
}

function exportData() {
    toggleQuickActions();
    // Add export functionality
    const currentDate = new Date().toISOString().split('T')[0];
    const filename = `clients_export_${currentDate}.csv`;
    
    // Create CSV content
    let csvContent = "Full Name,KRA PIN,Phone,Email,Client Type,County,ETIMS Status,Registration Date\n";
    
    // You can get the clients data from your PHP and add to CSV
    // For now, showing a placeholder
    showToast('Export functionality will be implemented based on your data structure', 'info');
}

function openSettings() {
    toggleQuickActions();
    window.location.href = './settings.php';
}

// Update the existing showToast function to handle 'info' type
function showToast(message, type) {
    const toast = document.createElement('div');
    let alertClass = 'alert-primary';
    
    switch(type) {
        case 'success':
            alertClass = 'alert-success';
            break;
        case 'error':
            alertClass = 'alert-danger';
            break;
        case 'info':
            alertClass = 'alert-info';
            break;
        default:
            alertClass = 'alert-primary';
    }
    
    toast.className = `alert ${alertClass} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close ms-2" onclick="this.parentElement.remove()"></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>
</body>
</html>