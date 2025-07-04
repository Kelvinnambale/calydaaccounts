<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE: clients.php - Client Management Page
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

// Handle bulk actions
if ($_POST['bulk_action'] ?? '' && !empty($_POST['selected_clients'])) {
    $selectedClients = $_POST['selected_clients'];
    $bulkAction = $_POST['bulk_action'];
    
    switch ($bulkAction) {
        case 'delete':
            foreach ($selectedClients as $clientId) {
                $client->delete($clientId);
            }
            $successMessage = count($selectedClients) . " clients deleted successfully.";
            break;
        case 'update_etims':
            $etimsStatus = $_POST['bulk_etims_status'];
            foreach ($selectedClients as $clientId) {
                $client->updateEtimsStatus($clientId, $etimsStatus);
            }
            $successMessage = count($selectedClients) . " clients updated with ETIMS status: " . $etimsStatus;
            break;
    }
}

// Handle filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'client_type' => $_GET['client_type'] ?? '',
    'tax_obligation' => $_GET['tax_obligation'] ?? '',
    'county' => $_GET['county'] ?? '',
    'etims_status' => $_GET['etims_status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$perPage = (int)($_GET['per_page'] ?? 25);
$sortBy = $_GET['sort_by'] ?? 'registration_date';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

$result = $client->getAllPaginated($filters, $page, $perPage, $sortBy, $sortOrder);
$clients = $result['data'];
$totalClients = $result['total'];
$totalPages = ceil($totalClients / $perPage);

$stats = $client->getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management - Calyda Accounts</title>
    <link rel="stylesheet" href="./css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            cursor: pointer;
            user-select: none;
        }
        .table th:hover {
            background-color: #e9ecef;
        }
        .sort-icon {
            margin-left: 5px;
            opacity: 0.5;
        }
        .sort-active {
            opacity: 1;
        }
        .bulk-actions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            margin-right: 10px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .filters-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
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
            .table-responsive {
                font-size: 0.875rem;
            }
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
            <a class="nav-link active" href="clients.php">
                <i class="fas fa-users"></i>Client Management
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="vat_management.php">
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

                <!-- Success Message -->
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Client Management</h2>
                        <p class="text-muted mb-0">Manage and organize your client database</p>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="exportClients()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                            <i class="fas fa-plus me-2"></i>Add New Client
                        </button>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?php echo $stats['total_clients']; ?></h4>
                                <small>Total Clients</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                                <h4><?php echo $stats['recent_registrations']; ?></h4>
                                <small>This Month</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4><?php echo array_sum(array_column(array_filter($stats['etims_status'], function($item) { return $item['etims_status'] == 'Registered'; }), 'count')); ?></h4>
                                <small>ETIMS Registered</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h4><?php echo array_sum(array_column(array_filter($stats['etims_status'], function($item) { return $item['etims_status'] == 'Pending'; }), 'count')); ?></h4>
                                <small>ETIMS Pending</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card filters-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" placeholder="Name, KRA PIN, Phone..." value="<?php echo htmlspecialchars($filters['search']); ?>">
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
                                <label class="form-label">ETIMS Status</label>
                                <select class="form-select" name="etims_status">
                                    <option value="">All Status</option>
                                    <option value="Registered" <?php echo $filters['etims_status'] == 'Registered' ? 'selected' : ''; ?>>Registered</option>
                                    <option value="Pending" <?php echo $filters['etims_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Not Registered" <?php echo $filters['etims_status'] == 'Not Registered' ? 'selected' : ''; ?>>Not Registered</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <select class="form-select" name="per_page" onchange="updatePerPage(this.value)">
                                    <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25 per page</option>
                                    <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 per page</option>
                                    <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100 per page</option>
                                </select>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST" id="bulkActionForm">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <span class="fw-bold"><span id="selectedCount">0</span> clients selected</span>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="bulk_action" required>
                                    <option value="">Select Action</option>
                                    <option value="delete">Delete Selected</option>
                                    <option value="update_etims">Update ETIMS Status</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="bulk_etims_status" style="display: none;">
                                    <option value="Registered">Registered</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Not Registered">Not Registered</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-warning me-2">Apply</button>
                                <button type="button" class="btn btn-secondary" onclick="clearSelection()">Clear</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Clients Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                Client Records 
                                <span class="badge bg-secondary"><?php echo number_format($totalClients); ?></span>
                            </h5>
                            <div class="text-muted">
                                Showing <?php echo (($page - 1) * $perPage) + 1; ?>-<?php echo min($page * $perPage, $totalClients); ?> of <?php echo number_format($totalClients); ?>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th onclick="sortTable('full_name')">
                                            Client
                                            <i class="fas fa-sort sort-icon <?php echo $sortBy == 'full_name' ? 'sort-active' : ''; ?>"></i>
                                        </th>
                                        <th onclick="sortTable('kra_pin')">
                                            KRA PIN
                                            <i class="fas fa-sort sort-icon <?php echo $sortBy == 'kra_pin' ? 'sort-active' : ''; ?>"></i>
                                        </th>
                                        <th onclick="sortTable('phone_number')">
                                            Contact
                                            <i class="fas fa-sort sort-icon <?php echo $sortBy == 'phone_number' ? 'sort-active' : ''; ?>"></i>
                                        </th>
                                        <th onclick="sortTable('client_type')">
                                            Type
                                            <i class="fas fa-sort sort-icon <?php echo $sortBy == 'client_type' ? 'sort-active' : ''; ?>"></i>
                                        </th>
                                        <th onclick="sortTable('county')">
                                            County
                                            <i class="fas fa-sort sort-icon <?php echo $sortBy == 'county' ? 'sort-active' : ''; ?>"></i>
                                        </th>
                                        <th onclick="sortTable('etims_status')">
                                            ETIMS
                                            <i class="fas fa-sort sort-icon <?php echo $sortBy == 'etims_status' ? 'sort-active' : ''; ?>"></i>
                                        </th>
                                        <th onclick="sortTable('registration_date')">
                                            Registered
                                            <i class="fas fa-sort sort-icon <?php echo $sortBy == 'registration_date' ? 'sort-active' : ''; ?>"></i>
                                        </th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No clients found matching your criteria.</p>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                                    <i class="fas fa-plus me-2"></i>Add Your First Client
                                                </button>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $clientData): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input client-checkbox" value="<?php echo $clientData['id']; ?>">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        $initials = strtoupper(substr($clientData['full_name'], 0, 1) . substr(strstr($clientData['full_name'], ' '), 1, 1));
                                                        $colors = ['#6c63ff', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7'];
                                                        $colorIndex = crc32($clientData['full_name']) % count($colors);
                                                        ?>
                                                        <div class="client-avatar" style="background-color: <?php echo $colors[$colorIndex]; ?>">
                                                            <?php echo $initials; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($clientData['full_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($clientData['id_number']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="me-2"><?php echo htmlspecialchars($clientData['kra_pin']); ?></span>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?php echo htmlspecialchars($clientData['kra_pin']); ?>')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($clientData['phone_number']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($clientData['email_address']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info status-badge"><?php echo htmlspecialchars($clientData['client_type']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($clientData['county']); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = $clientData['etims_status'] == 'Registered' ? 'bg-success' : 
                                                                  ($clientData['etims_status'] == 'Pending' ? 'bg-warning text-dark' : 'bg-danger');
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?> status-badge"><?php echo htmlspecialchars($clientData['etims_status']); ?></span>
                                                </td>
                                                <td>
                                                    <div><?php echo date('M j, Y', strtotime($clientData['registration_date'])); ?></div>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($clientData['registration_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="viewClient(<?php echo $clientData['id']; ?>)" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-warning" onclick="editClient(<?php echo $clientData['id']; ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deleteClient(<?php echo $clientData['id']; ?>, '<?php echo htmlspecialchars($clientData['full_name']); ?>')" title="Delete">
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

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Client pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Client Modal (Same as dashboard) -->
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
    <!-- Quick Actions Menu -->
<div class="quick-actions-menu" id="quickActionsMenu">
    <button class="quick-action-item" onclick="openAddClientModal()">
        <i class="fas fa-user-plus"></i>
        <span>Add New Client</span>
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
    <script src="./js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let selectedClients = [];

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
            if (confirm(`Are you sure you want to delete client: ${name}?\n\nThis action cannot be undone.`)) {
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

        function exportClients() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'ajax/export_clients.php?' + params.toString();
            showToast('Export started! Download will begin shortly...', 'info');
        }

        function sortTable(column) {
            const params = new URLSearchParams(window.location.search);
            const currentSort = params.get('sort_by');
            const currentOrder = params.get('sort_order') || 'ASC';
            
            if (currentSort === column) {
                params.set('sort_order', currentOrder === 'ASC' ? 'DESC' : 'ASC');
            } else {
                params.set('sort_by', column);
                params.set('sort_order', 'ASC');
            }
            
            params.set('page', '1'); // Reset to first page
            window.location.search = params.toString();
        }

        function updatePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', '1'); // Reset to first page
            window.location.search = params.toString();
        }

        function clearFilters() {
            window.location.href = 'clients.php';
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'info')} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close ms-2" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        // Bulk selection functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const clientCheckboxes = document.querySelectorAll('.client-checkbox');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCountSpan = document.getElementById('selectedCount');
            const bulkActionSelect = document.querySelector('select[name="bulk_action"]');
            const etimsStatusSelect = document.querySelector('select[name="bulk_etims_status"]');

            // Select all functionality
            selectAllCheckbox?.addEventListener('change', function() {
                clientCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectedClients();
            });

            // Individual checkbox functionality
            clientCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectedClients();
                    
                    // Update select all checkbox
                    const checkedCount = document.querySelectorAll('.client-checkbox:checked').length;
                    selectAllCheckbox.checked = checkedCount === clientCheckboxes.length;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < clientCheckboxes.length;
                });
            });

            // Show/hide ETIMS status dropdown based on bulk action
            bulkActionSelect?.addEventListener('change', function() {
                if (this.value === 'update_etims') {
                    etimsStatusSelect.style.display = 'block';
                    etimsStatusSelect.required = true;
                } else {
                    etimsStatusSelect.style.display = 'none';
                    etimsStatusSelect.required = false;
                }
            });

            function updateSelectedClients() {
                selectedClients = Array.from(document.querySelectorAll('.client-checkbox:checked')).map(cb => cb.value);
                selectedCountSpan.textContent = selectedClients.length;
                
                if (selectedClients.length > 0) {
                    bulkActions.style.display = 'block';
                } else {
                    bulkActions.style.display = 'none';
                }
            }

            // Bulk action form submission
            document.getElementById('bulkActionForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const action = bulkActionSelect.value;
                if (!action) {
                    showToast('Please select an action', 'error');
                    return;
                }

                if (selectedClients.length === 0) {
                    showToast('Please select at least one client', 'error');
                    return;
                }

                let confirmMessage = '';
                if (action === 'delete') {
                    confirmMessage = `Are you sure you want to delete ${selectedClients.length} selected clients?\n\nThis action cannot be undone.`;
                } else if (action === 'update_etims') {
                    const status = etimsStatusSelect.value;
                    confirmMessage = `Are you sure you want to update ETIMS status to "${status}" for ${selectedClients.length} selected clients?`;
                }

                if (confirm(confirmMessage)) {
                    // Add selected client IDs to form
                    selectedClients.forEach(clientId => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_clients[]';
                        input.value = clientId;
                        this.appendChild(input);
                    });
                    
                    this.submit();
                }
            });
        });

        function clearSelection() {
            document.querySelectorAll('.client-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            document.getElementById('bulkActions').style.display = 'none';
            selectedClients = [];
        }

        // Handle add client form submission
        document.getElementById('addClientForm')?.addEventListener('submit', function(e) {
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
                console.error('Add Client Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        });

        // Auto-refresh page every 5 minutes to keep data current
        setInterval(() => {
            const lastActivity = localStorage.getItem('lastActivity');
            const now = Date.now();
            
            // Only refresh if user has been inactive for less than 30 seconds (active session)
            if (!lastActivity || (now - parseInt(lastActivity)) < 30000) {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Track user activity
        document.addEventListener('click', () => {
            localStorage.setItem('lastActivity', Date.now().toString());
        });

        document.addEventListener('keypress', () => {
            localStorage.setItem('lastActivity', Date.now().toString());
        });
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
    new bootstrap.Modal(document.getElementById('addClientModal')).show();
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
    window.location.href = '../settings.php';
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