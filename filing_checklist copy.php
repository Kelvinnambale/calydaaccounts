<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE: filing_dashboard.php - Dashboard with Filing Analytics (CORRECTED VERSION)
session_start();
define('SYSTEM_ACCESS', true);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/constants.php';
require_once 'config/database.php';
$database = Database::getInstance();
$pdo = $database->getConnection();

// Helper function to safely format numbers
function safe_number_format($number, $decimals = 0, $decimal_separator = '.', $thousands_separator = ',') {
    if ($number === null || $number === '') {
        return number_format(0, $decimals, $decimal_separator, $thousands_separator);
    }
    return number_format((float)$number, $decimals, $decimal_separator, $thousands_separator);
}

// Get filing statistics
$stats = [];

// Total clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients");
$stats['total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Filed this month
$stmt = $pdo->query("SELECT COUNT(*) as total FROM filing_records WHERE MONTH(filing_date) = MONTH(CURDATE()) AND YEAR(filing_date) = YEAR(CURDATE())");
$stats['filed_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending filings
$stmt = $pdo->query("SELECT COUNT(*) as total FROM filing_records WHERE status = 'Pending'");
$stats['pending_filings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Revenue this month - Fixed with COALESCE to handle null values
$stmt = $pdo->query("SELECT COALESCE(SUM(amount_charged), 0) as total FROM filing_records WHERE MONTH(filing_date) = MONTH(CURDATE()) AND YEAR(filing_date) = YEAR(CURDATE())");
$stats['revenue_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Overdue filings
$stmt = $pdo->query("SELECT COUNT(*) as total FROM filing_records WHERE status = 'Overdue'");
$stats['overdue_filings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Outstanding invoices
$stmt = $pdo->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'Pending'");
$stats['outstanding_invoices'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent activities
$stmt = $pdo->query("
    SELECT fr.*, c.full_name, c.kra_pin 
    FROM filing_records fr
    JOIN clients c ON fr.client_id = c.id
    ORDER BY fr.created_at DESC
    LIMIT 10
");
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filing trends (last 6 months) - Fixed with COALESCE
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(filing_date, '%Y-%m') as month,
        COUNT(*) as count,
        COALESCE(SUM(amount_charged), 0) as revenue
    FROM filing_records 
    WHERE filing_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(filing_date, '%Y-%m')
    ORDER BY month ASC
");
$filing_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top clients by revenue - Fixed with COALESCE
$stmt = $pdo->query("
    SELECT 
        c.full_name,
        c.kra_pin,
        COUNT(fr.id) as total_filings,
        COALESCE(SUM(fr.amount_charged), 0) as total_revenue
    FROM clients c
    LEFT JOIN filing_records fr ON c.id = fr.client_id
    GROUP BY c.id
    ORDER BY total_revenue DESC
    LIMIT 5
");
$top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming deadlines
$stmt = $pdo->query("
    SELECT DISTINCT
        c.full_name,
        c.kra_pin,
        JSON_EXTRACT(c.tax_obligations, '$[0]') as next_obligation,
        CASE 
            WHEN JSON_EXTRACT(c.tax_obligations, '$[0]') LIKE '%Income%' THEN CONCAT(YEAR(CURDATE()), '-12-31')
            ELSE LAST_DAY(CURDATE())
        END as deadline
    FROM clients c
    WHERE c.tax_obligations IS NOT NULL
    AND c.tax_obligations != '[]'
    LIMIT 10
");
$upcoming_deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filing Dashboard - Calyda Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .dashboard-card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .dashboard-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
        }
        .stat-card { 
            background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-info) 100%);
            color: white;
        }
        .stat-card.success { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .stat-card.warning { 
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        .stat-card.danger { 
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        .stat-icon { 
            font-size: 2.5rem; 
            opacity: 0.8; 
        }
        .chart-container { 
            position: relative; 
            height: 300px; 
        }
        .activity-item { 
            padding: 15px;
            border-left: 4px solid var(--bs-primary);
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
        }
        .deadline-item {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .deadline-item.urgent {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-12 p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Filing Dashboard</h2>
                        <p class="text-muted mb-0">Overview of tax filing activities and performance</p>
                    </div>
                    <div>
                        <button class="btn btn-primary me-2" onclick="window.location.href='filing_checklist.php'">
                            <i class="fas fa-list-check me-2"></i>View Checklist
                        </button>
                        <button class="btn btn-success" onclick="generateReport()">
                            <i class="fas fa-file-export me-2"></i>Export Report
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-0">Total Clients</h5>
                                        <h2 class="mb-0"><?php echo $stats['total_clients'] ?? 0; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card stat-card success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-0">Filed This Month</h5>
                                        <h2 class="mb-0"><?php echo $stats['filed_this_month'] ?? 0; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card stat-card warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-0">Pending Filings</h5>
                                        <h2 class="mb-0"><?php echo $stats['pending_filings'] ?? 0; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card dashboard-card stat-card danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-0">Revenue (Month)</h5>
                                        <h2 class="mb-0">KSh <?php echo safe_number_format($stats['revenue_this_month']); ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Analytics -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Filing Trends (Last 6 Months)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="filingTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Top Clients by Revenue</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($top_clients as $client): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <strong><?php echo htmlspecialchars($client['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($client['kra_pin']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success">KSh <?php echo safe_number_format($client['total_revenue']); ?></div>
                                        <small class="text-muted"><?php echo $client['total_filings'] ?? 0; ?> filings</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity and Deadlines -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Filing Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                <p class="text-muted text-center">No recent filing activities found.</p>
                                <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                            <span class="text-muted">- <?php echo htmlspecialchars($activity['tax_obligation'] ?? 'N/A'); ?></span><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($activity['filing_period'] ?? 'N/A'); ?> • 
                                                <?php echo date('M j, Y', strtotime($activity['filing_date'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $activity['status'] === 'Filed' ? 'success' : 'warning'; ?>">
                                                <?php echo $activity['status'] ?? 'Unknown'; ?>
                                            </span>
                                            <div class="small text-muted">KSh <?php echo safe_number_format($activity['amount_charged']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Upcoming Deadlines</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_deadlines)): ?>
                                <p class="text-muted text-center">No upcoming deadlines found.</p>
                                <?php else: ?>
                                <?php foreach ($upcoming_deadlines as $deadline): ?>
                                <?php 
                                $days_until = (strtotime($deadline['deadline']) - time()) / (60 * 60 * 24);
                                $is_urgent = $days_until <= 7;
                                ?>
                                <div class="deadline-item <?php echo $is_urgent ? 'urgent' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($deadline['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($deadline['kra_pin']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo trim($deadline['next_obligation'] ?? 'N/A', '"'); ?></div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($deadline['deadline'])); ?>
                                                (<?php echo ceil($days_until); ?> days)
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Filing Trends Chart
        const trendsCtx = document.getElementById('filingTrendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($item) { return date('M Y', strtotime($item['month'] . '-01')); }, $filing_trends)); ?>,
                datasets: [{
                    label: 'Filings',
                    data: <?php echo json_encode(array_column($filing_trends, 'count')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (KSh)',
                    data: <?php echo json_encode(array_column($filing_trends, 'revenue')); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Filings'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (KSh)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        function generateReport() {
            // This would generate a comprehensive report
            showToast('Report generation feature coming soon!', 'info');
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            let alertClass = 'alert-primary';
            
            switch(type) {
                case 'success': alertClass = 'alert-success'; break;
                case 'error': alertClass = 'alert-danger'; break;
                case 'info': alertClass = 'alert-info'; break;
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