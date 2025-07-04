<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE: reports.php - Reports & Export System
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

$database = Database::getInstance();
$pdo = $database->getConnection();

require_once 'classes/Client.php';

// Initialize database tables
Database::getInstance()->createTables();

$client = new Client();

// Handle filters
$filters = [
    'report_type' => $_GET['report_type'] ?? 'client_information',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'client_type' => $_GET['client_type'] ?? '',
    'tax_obligation' => $_GET['tax_obligation'] ?? '',
    'county' => $_GET['county'] ?? '',
    'etims_status' => $_GET['etims_status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Ensure tax_obligation filter is properly formatted for JSON_CONTAINS if used
if (!empty($filters['tax_obligation'])) {
    $filters['tax_obligation'] = $filters['tax_obligation'];
}

// Pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$perPage = 10; // Items per page

// Get report data based on filters
$reportData = [];
$reportTitle = '';
$totalRecords = 0;

switch ($filters['report_type']) {
    case 'client_information':
        $paginatedData = $client->getAllPaginated($filters, $page, $perPage);
        $reportData = $paginatedData['data'];
        $totalRecords = $paginatedData['total'];
        $reportTitle = 'Client Information Report';
        break;
    
    case 'client_summary':
        $stats = $client->getStats();
        $reportData = [
            'stats' => $stats,
            'by_county' => $client->getClientsByCounty($filters),
            'by_type' => $stats['by_type'],
            'by_etims' => $client->getClientsByEtimsStatus($filters)
        ];
        $reportTitle = 'Client Summary Report';
        $totalRecords = $stats['total_clients'];
        break;
    
    case 'registration_trends':
        $reportData = $client->getRegistrationTrends($filters);
        $reportTitle = 'Client Registration Trends';
        $totalRecords = count($reportData);
        break;
}

// Generate date range text
$dateRangeText = '';
if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
    $dateRangeText = 'Period: ';
    if (!empty($filters['date_from'])) {
        $dateRangeText .= date('M j, Y', strtotime($filters['date_from']));
    } else {
        $dateRangeText .= 'Beginning';
    }
    $dateRangeText .= ' to ';
    if (!empty($filters['date_to'])) {
        $dateRangeText .= date('M j, Y', strtotime($filters['date_to']));
    } else {
        $dateRangeText .= 'Present';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Calyda Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/style.css">
    <style>
        body { background-color: #f8f9fa; }
        
        .main-content {
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .report-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-5px);
        }
        .export-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
        }
        .export-btn:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
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
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
            
            .main-content {
                margin-left: 0;
            }
        }
        @media print {
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
 <div class="container-fluid">
       <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            
            <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

            <div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
                <div class="sidebar-header">
                    <button class="sidebar-close" onclick="closeSidebar()">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <div class="logo-container">
                        <div class="logo-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <div class="logo-text" style="margin-top:40px;">Calyda Accounts</div>
                            <div class="company-tagline">Professional Tax Solutions</div>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <div class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="clients.php">
                            <i class="fas fa-users"></i>Client Management
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="vat_management.php">
                            <i class="fas fa-receipt"></i>VAT Management
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar"></i>Reports
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i>Settings
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </a>
                    </div>
                </nav>

                <div class="system-status">
                    <div class="uptime-display">
                        <div class="uptime-title">System Uptime</div>
                        <div class="uptime-value">
                            <div class="status-indicator"></div>
                            <span id="systemUptime">Loading...</span>
                        </div>
                    </div>

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
                    <h2><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h2>
                    <div class="no-print">
                        <button class="btn export-btn" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </button>
                    </div>
                </div>

                <!-- Report Type Selection -->
                <div class="row mb-4 no-print">
                    <div class="col-md-4 mb-3">
                        <div class="card report-card h-100" onclick="selectReport('client_information')">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-3x mb-3"></i>
                                <h5>Client Information</h5>
                                <p class="mb-0">Detailed client records with all information</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card report-card h-100" onclick="selectReport('client_summary')">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-pie fa-3x mb-3"></i>
                                <h5>Client Summary</h5>
                                <p class="mb-0">Statistical overview and breakdowns</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card report-card h-100" onclick="selectReport('registration_trends')">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-3x mb-3"></i>
                                <h5>Registration Trends</h5>
                                <p class="mb-0">Client registration patterns over time</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-filter me-2"></i>Report Filters
                        </h5>
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($filters['report_type']); ?>">
                            
                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Client Type</label>
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
                                <label class="form-label">County</label>
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
                                <label class="form-label">Tax Obligation</label>
                                <select class="form-select" name="tax_obligation">
                                    <option value="">All Obligations</option>
                                    <?php foreach (TAX_OBLIGATIONS as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filters['tax_obligation'] == $key ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">ETIMS Status</label>
                                <select class="form-select" name="etims_status">
                                    <option value="">All Status</option>
                                    <option value="Registered" <?php echo $filters['etims_status'] == 'Registered' ? 'selected' : ''; ?>>Registered</option>
                                    <option value="Pending" <?php echo $filters['etims_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Not Registered" <?php echo $filters['etims_status'] == 'Not Registered' ? 'selected' : ''; ?>>Not Registered</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Name or KRA PIN" value="<?php echo htmlspecialchars($filters['search']); ?>">
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="mb-1"><?php echo $reportTitle; ?></h4>
                                <p class="text-muted mb-0">
                                    Generated on <?php echo date('F j, Y \a\t g:i A'); ?>
                                    <?php if ($dateRangeText): ?>
                                        <br><?php echo $dateRangeText; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <h5 class="mb-0">Total Records: <?php echo number_format($totalRecords); ?></h5>
                            </div>
                        </div>

                        <?php if ($filters['report_type'] == 'client_information'): ?>
                            <!-- Client Information Report -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Full Name</th>
                                            <th>KRA PIN</th>
                                            <th>Phone Number</th>
                                            <th>Email Address</th>
                                            <th>Client Type</th>
                                            <th>County</th>
                                            <th>Tax Obligations</th>
                                            <th>ETIMS Status</th>
                                            <th>Registration Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reportData)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-4">
                                                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No data found for the selected criteria.</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reportData as $index => $clientData): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo htmlspecialchars($clientData['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($clientData['kra_pin']); ?></td>
                                                    <td><?php echo htmlspecialchars($clientData['phone_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($clientData['email_address']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($clientData['client_type']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($clientData['county']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $obligations = is_array($clientData['tax_obligations']) ? $clientData['tax_obligations'] : json_decode($clientData['tax_obligations'], true);
                                                        if (is_array($obligations)) {
                                                            foreach ($obligations as $obligation): 
                                                        ?>
                                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($obligation); ?></span>
                                                        <?php 
                                                            endforeach; 
                                                        } 
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClass = $clientData['etims_status'] == 'Registered' ? 'bg-success' : 
                                                                      ($clientData['etims_status'] == 'Pending' ? 'bg-warning' : 'bg-danger');
                                                        ?>
                                                        <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($clientData['etims_status']); ?></span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($clientData['registration_date'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <?php if ($totalRecords > $perPage): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <?php
                                $totalPages = ceil($totalRecords / $perPage);
                                $currentPage = $page;
                                $baseUrl = strtok($_SERVER["REQUEST_URI"], '?');
                                $queryParams = $_GET;
                                ?>
                                <!-- Previous Page Link -->
                                <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                    <?php
                                    $queryParams['page'] = $currentPage - 1;
                                    $prevUrl = $baseUrl . '?' . http_build_query($queryParams);
                                    ?>
                                    <a class="page-link" href="<?php echo $prevUrl; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <!-- Page Number Links -->
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php
                                    $queryParams['page'] = $i;
                                    $pageUrl = $baseUrl . '?' . http_build_query($queryParams);
                                    ?>
                                    <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Page Link -->
                                <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                    <?php
                                    $queryParams['page'] = $currentPage + 1;
                                    $nextUrl = $baseUrl . '?' . http_build_query($queryParams);
                                    ?>
                                    <a class="page-link" href="<?php echo $nextUrl; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php elseif ($filters['report_type'] == 'client_summary'): ?>
                    <!-- Client Summary Report -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h3><?php echo $reportData['stats']['total_clients']; ?></h3>
                                    <p class="mb-0">Total Clients</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                                    <h3><?php echo $reportData['stats']['recent_registrations']; ?></h3>
                                    <p class="mb-0">This Month</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-building fa-2x mb-2"></i>
                                    <h3><?php echo count(array_filter($reportData['by_type'], function($item) { return $item['client_type'] == 'Company'; })) > 0 ? array_filter($reportData['by_type'], function($item) { return $item['client_type'] == 'Company'; })[0]['count'] : 0; ?></h3>
                                    <p class="mb-0">Companies</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-user fa-2x mb-2"></i>
                                    <h3><?php echo count(array_filter($reportData['by_type'], function($item) { return $item['client_type'] == 'Individual'; })) > 0 ? array_filter($reportData['by_type'], function($item) { return $item['client_type'] == 'Individual'; })[0]['count'] : 0; ?></h3>
                                    <p class="mb-0">Individuals</p>
                                </div>
                            </div>
                        </div>
                    </div>

                            <!-- Charts -->
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <h6>Clients by Type</h6>
                                    <canvas id="clientTypeChart"></canvas>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <h6>ETIMS Registration Status</h6>
                                    <canvas id="etimsChart"></canvas>
                                </div>
                            </div>

                            <!-- County Breakdown -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>County</th>
                                            <th>Total Clients</th>
                                            <th>Companies</th>
                                            <th>Individuals</th>
                                            <th>ETIMS Registered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($reportData['by_county']) && !empty($reportData['by_county'])): ?>
                                            <?php foreach ($reportData['by_county'] as $countyData): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($countyData['county']); ?></td>
                                                    <td><?php echo $countyData['total']; ?></td>
                                                    <td><?php echo $countyData['companies'] ?? 0; ?></td>
                                                    <td><?php echo $countyData['individuals'] ?? 0; ?></td>
                                                    <td><?php echo $countyData['etims_registered'] ?? 0; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <p class="text-muted">No county data available.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php elseif ($filters['report_type'] == 'registration_trends'): ?>
                            <!-- Registration Trends Report -->
                            <div class="chart-container mb-4">
                                <canvas id="trendsChart"></canvas>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>New Registrations</th>
                                            <th>Companies</th>
                                            <th>Individuals</th>
                                            <th>Running Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reportData)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <p class="text-muted">No trend data available for the selected period.</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php 
                                            $runningTotal = 0;
                                            foreach ($reportData as $trendData): 
                                                $runningTotal += $trendData['count'];
                                            ?>
                                                <tr>
                                                    <td><?php echo date('F Y', strtotime($trendData['month'] . '-01')); ?></td>
                                                    <td><?php echo $trendData['count']; ?></td>
                                                    <td><?php echo $trendData['companies'] ?? 0; ?></td>
                                                    <td><?php echo $trendData['individuals'] ?? 0; ?></td>
                                                    <td><?php echo $runningTotal; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="./js/script.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('show');
        }


        function selectReport(reportType) {
            const url = new URL(window.location);
            url.searchParams.set('report_type', reportType);
            window.location.href = url.toString();
        }

        function exportReport(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            
            // Create a temporary form for export
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'ajax/export_report.php';
            
            for (const [key, value] of params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Initialize charts if on summary report
        <?php if ($filters['report_type'] == 'client_summary'): ?>
        // Client Type Chart
        const clientTypeCtx = document.getElementById('clientTypeChart').getContext('2d');
        new Chart(clientTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($reportData['by_type'], 'client_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($reportData['by_type'], 'count')); ?>,
                    backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#f5576c']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // ETIMS Status Chart
        const etimsCtx = document.getElementById('etimsChart').getContext('2d');
        new Chart(etimsCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($reportData['by_etims'], 'etims_status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($reportData['by_etims'], 'count')); ?>,
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($filters['report_type'] == 'registration_trends' && !empty($reportData)): ?>
        // Registration Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($item) { return date('M Y', strtotime($item['month'] . '-01')); }, $reportData)); ?>,
                datasets: [{
                    label: 'New Registrations',
                    data: <?php echo json_encode(array_column($reportData, 'count')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close ms-2" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
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