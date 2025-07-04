<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// FILE: ajax/export_report.php - Export Report Handler
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/classes/Client.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Initialize database tables
Database::getInstance()->createTables();

$client = new Client();

// Get export parameters
$export_format = $_POST['export'] ?? '';
$filters = [
    'report_type' => $_POST['report_type'] ?? 'client_information',
    'date_from' => $_POST['date_from'] ?? '',
    'date_to' => $_POST['date_to'] ?? '',
    'client_type' => $_POST['client_type'] ?? '',
    'tax_obligation' => $_POST['tax_obligation'] ?? '',
    'county' => $_POST['county'] ?? '',
    'etims_status' => $_POST['etims_status'] ?? '',
    'search' => $_POST['search'] ?? ''
];

// Validate export format
if (!in_array($export_format, ['pdf', 'excel'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid export format']);
    exit;
}

// Get report data
$reportData = [];
$reportTitle = '';

switch ($filters['report_type']) {
    case 'client_information':
        $reportData = $client->getAll($filters);
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
        break;
    
    case 'registration_trends':
        $reportData = $client->getRegistrationTrends($filters);
        $reportTitle = 'Client Registration Trends';
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

if ($export_format === 'pdf') {
    exportToPDF($reportData, $reportTitle, $filters, $dateRangeText);
} else {
    exportToExcel($reportData, $reportTitle, $filters, $dateRangeText);
}

function exportToPDF($reportData, $reportTitle, $filters, $dateRangeText) {
    // Create HTML content for PDF
    $html = generatePDFHTML($reportData, $reportTitle, $filters, $dateRangeText);
    
    // Use DomPDF or similar library (this is a basic implementation)
    // For production, you should install a proper PDF library like DomPDF or TCPDF
    
    // Basic PDF generation using HTML to PDF conversion
    $filename = sanitizeFilename($reportTitle) . '_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // For demonstration, we'll create a simple PDF-like output
    // In production, use a proper PDF library
    echo generateSimplePDF($html);
}

function exportToExcel($reportData, $reportTitle, $filters, $dateRangeText) {
    $filename = sanitizeFilename($reportTitle) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Generate Excel content
    generateExcelContent($reportData, $reportTitle, $filters, $dateRangeText);
}

function generatePDFHTML($reportData, $reportTitle, $filters, $dateRangeText) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($reportTitle); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                margin: 20px;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            .header h1 {
                margin: 0;
                color: #2c3e50;
                font-size: 24px;
            }
            .header p {
                margin: 5px 0;
                color: #666;
            }
            .company-info {
                text-align: center;
                margin-bottom: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f8f9fa;
                font-weight: bold;
            }
            .summary-stats {
                display: flex;
                justify-content: space-around;
                margin: 20px 0;
            }
            .stat-box {
                text-align: center;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                margin: 0 10px;
            }
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                color: #2c3e50;
            }
            .badge {
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                color: white;
            }
            .badge-info { background-color: #17a2b8; }
            .badge-success { background-color: #28a745; }
            .badge-warning { background-color: #ffc107; color: #212529; }
            .badge-danger { background-color: #dc3545; }
            .badge-secondary { background-color: #6c757d; }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="company-info">
            <h2>CALYDA ACCOUNTS</h2>
            <p>Professional Accounting & Tax Services</p>
        </div>
        
        <div class="header">
            <h1><?php echo htmlspecialchars($reportTitle); ?></h1>
            <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <?php if ($dateRangeText): ?>
                <p><?php echo htmlspecialchars($dateRangeText); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($filters['report_type'] == 'client_information'): ?>
            <p><strong>Total Records:</strong> <?php echo count($reportData); ?></p>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>KRA PIN</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>County</th>
                        <th>ETIMS Status</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $index => $client): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($client['kra_pin']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone_number']); ?></td>
                            <td><?php echo htmlspecialchars($client['email_address']); ?></td>
                            <td><?php echo htmlspecialchars($client['client_type']); ?></td>
                            <td><?php echo htmlspecialchars($client['county']); ?></td>
                            <td><?php echo htmlspecialchars($client['etims_status']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($client['registration_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($filters['report_type'] == 'client_summary'): ?>
            <div class="summary-stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $reportData['stats']['total_clients']; ?></div>
                    <div>Total Clients</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $reportData['stats']['recent_registrations']; ?></div>
                    <div>This Month</div>
                </div>
            </div>

            <h3>Client Distribution by County</h3>
            <table>
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
                    <?php if (isset($reportData['by_county'])): ?>
                        <?php foreach ($reportData['by_county'] as $county): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($county['county']); ?></td>
                                <td><?php echo $county['total']; ?></td>
                                <td><?php echo $county['companies'] ?? 0; ?></td>
                                <td><?php echo $county['individuals'] ?? 0; ?></td>
                                <td><?php echo $county['etims_registered'] ?? 0; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($filters['report_type'] == 'registration_trends'): ?>
            <p><strong>Total Records:</strong> <?php echo count($reportData); ?></p>
            <table>
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
                    <?php 
                    $runningTotal = 0;
                    foreach ($reportData as $trend): 
                        $runningTotal += $trend['count'];
                    ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                            <td><?php echo $trend['count']; ?></td>
                            <td><?php echo $trend['companies'] ?? 0; ?></td>
                            <td><?php echo $trend['individuals'] ?? 0; ?></td>
                            <td><?php echo $runningTotal; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="footer">
            <p>This report was generated by Calyda Accounts System on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>© <?php echo date('Y'); ?> Calyda Accounts. All rights reserved.</p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function generateSimplePDF($html) {
    // This is a basic implementation
    // For production, use DomPDF, TCPDF, or wkhtmltopdf
    
    // Convert HTML to a simple text-based PDF format
    $pdf_content = "%PDF-1.4\n";
    $pdf_content .= "1 0 obj\n";
    $pdf_content .= "<<\n";
    $pdf_content .= "/Type /Catalog\n";
    $pdf_content .= "/Pages 2 0 R\n";
    $pdf_content .= ">>\n";
    $pdf_content .= "endobj\n";
    
    // Note: This is a very basic PDF structure
    // For production, you should use a proper PDF library
    
    return $pdf_content;
}

function generateExcelContent($reportData, $reportTitle, $filters, $dateRangeText) {
    // Create CSV content that Excel can read
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add report header
    fputcsv($output, [$reportTitle]);
    fputcsv($output, ['Generated on: ' . date('F j, Y \a\t g:i A')]);
    if ($dateRangeText) {
        fputcsv($output, [$dateRangeText]);
    }
    fputcsv($output, []); // Empty row
    
    if ($filters['report_type'] == 'client_information') {
        // Headers
        fputcsv($output, [
            'No.',
            'Full Name',
            'KRA PIN',
            'Phone Number',
            'Email Address',
            'Client Type',
            'County',
            'Tax Obligations',
            'ETIMS Status',
            'Registration Date'
        ]);
        
        // Data rows
        foreach ($reportData as $index => $client) {
            $obligations = is_array($client['tax_obligations']) ? 
                implode(', ', $client['tax_obligations']) : 
                (is_string($client['tax_obligations']) ? 
                    implode(', ', json_decode($client['tax_obligations'], true) ?: []) : 
                    ''
                );
            
            fputcsv($output, [
                $index + 1,
                $client['full_name'],
                $client['kra_pin'],
                $client['phone_number'],
                $client['email_address'],
                $client['client_type'],
                $client['county'],
                $obligations,
                $client['etims_status'],
                date('M j, Y', strtotime($client['registration_date']))
            ]);
        }
        
    } elseif ($filters['report_type'] == 'client_summary') {
        // Summary statistics
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Clients', $reportData['stats']['total_clients']]);
        fputcsv($output, ['Recent Registrations (This Month)', $reportData['stats']['recent_registrations']]);
        fputcsv($output, []);
        
        // County breakdown
        fputcsv($output, ['COUNTY BREAKDOWN']);
        fputcsv($output, ['County', 'Total Clients', 'Companies', 'Individuals', 'ETIMS Registered']);
        
        if (isset($reportData['by_county'])) {
            foreach ($reportData['by_county'] as $county) {
                fputcsv($output, [
                    $county['county'],
                    $county['total'],
                    $county['companies'] ?? 0,
                    $county['individuals'] ?? 0,
                    $county['etims_registered'] ?? 0
                ]);
            }
        }
        
    } elseif ($filters['report_type'] == 'registration_trends') {
        // Headers
        fputcsv($output, ['Month', 'New Registrations', 'Companies', 'Individuals', 'Running Total']);
        
        // Data rows
        $runningTotal = 0;
        foreach ($reportData as $trend) {
            $runningTotal += $trend['count'];
            fputcsv($output, [
                date('F Y', strtotime($trend['month'] . '-01')),
                $trend['count'],
                $trend['companies'] ?? 0,
                $trend['individuals'] ?? 0,
                $runningTotal
            ]);
        }
    }
    
    fclose($output);
}

function sanitizeFilename($filename) {
    // Remove or replace invalid characters
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
    $filename = preg_replace('/_{2,}/', '_', $filename);
    return trim($filename, '_');
}
?>