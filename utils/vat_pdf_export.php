<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE: utils/vat_pdf_export.php - Enhanced VAT PDF/Excel Export
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/classes/Client.php';

// You'll need to install these libraries via Composer:
// composer require mpdf/mpdf
// composer require phpoffice/phpspreadsheet
require_once ROOT_PATH . '/vendor/autoload.php';

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get parameters
$clientId = $_GET['client_id'] ?? '';
$year = $_GET['year'] ?? date('Y');
$format = $_GET['format'] ?? 'pdf'; // pdf or excel

if (!$clientId) {
    die('Client ID is required');
}

$client = new Client();
$selectedClient = $client->getById($clientId);

if (!$selectedClient) {
    die('Client not found');
}

// Check if client has VAT obligations
$taxObligations = $selectedClient['tax_obligations'];
if (is_array($taxObligations)) {
    $taxObligations = implode(',', $taxObligations);
}
if (strpos($taxObligations, 'VAT') === false) {
    die('Client does not have VAT obligations');
}

$database = Database::getInstance();
$pdo = $database->getConnection();

// Get VAT records
$stmt = $pdo->prepare("
    SELECT * FROM vat_records 
    WHERE client_id = ? AND record_year = ? 
    ORDER BY record_month
");
$stmt->execute([$clientId, $year]);
$vatRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totals = [
    'sales_amount' => 0,
    'purchases_amount' => 0,
    'sales_vat' => 0,
    'purchases_vat' => 0,
    'net_vat' => 0
];

foreach ($vatRecords as $record) {
    $totals['sales_amount'] += $record['sales_amount'];
    $totals['purchases_amount'] += $record['purchases_amount'];
    $totals['sales_vat'] += $record['sales_vat'];
    $totals['purchases_vat'] += $record['purchases_vat'];
    $totals['net_vat'] += $record['net_vat'];
}

// Create records array by month
$recordsByMonth = [];
foreach ($vatRecords as $record) {
    $recordsByMonth[$record['record_month']] = $record;
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

if ($format === 'excel') {
    exportToExcel($selectedClient, $year, $months, $recordsByMonth, $totals, $vatRecords);
} else {
    exportToPDF($selectedClient, $year, $months, $recordsByMonth, $totals, $vatRecords);
}

function exportToPDF($selectedClient, $year, $months, $recordsByMonth, $totals, $vatRecords) {
    $html = generateHTML($selectedClient, $year, $months, $recordsByMonth, $totals, $vatRecords);
    
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L', // Landscape for better table display
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 16,
        'margin_bottom' => 16,
        'margin_header' => 9,
        'margin_footer' => 9
    ]);
    
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($html);
    
    $filename = 'VAT_Report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $selectedClient['full_name']) . '_' . $year . '.pdf';
    $mpdf->Output($filename, 'D'); // 'D' forces download
}

function exportToExcel($selectedClient, $year, $months, $recordsByMonth, $totals, $vatRecords) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator("VAT Management System")
        ->setLastModifiedBy("VAT Management System")
        ->setTitle("VAT Report - " . $selectedClient['full_name'])
        ->setSubject("VAT Compliance Report")
        ->setDescription("VAT report for " . $selectedClient['full_name'] . " - " . $year);
    
    // Header section
    $sheet->setCellValue('A1', 'VAT COMPLIANCE REPORT');
    $sheet->setCellValue('A2', 'Financial Year: ' . $year);
    $sheet->setCellValue('A3', 'Generated on: ' . date('F j, Y \a\t g:i A'));
    
    // Client information section
    $row = 5;
    $sheet->setCellValue('A' . $row, 'CLIENT INFORMATION');
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Business Name:');
    $sheet->setCellValue('B' . $row, $selectedClient['full_name']);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'KRA PIN:');
    $sheet->setCellValue('B' . $row, $selectedClient['kra_pin']);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Client Type:');
    $sheet->setCellValue('B' . $row, $selectedClient['client_type']);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'County:');
    $sheet->setCellValue('B' . $row, $selectedClient['county']);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Email:');
    $sheet->setCellValue('B' . $row, $selectedClient['email'] ?? '');
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Phone:');
    $sheet->setCellValue('B' . $row, $selectedClient['phone'] ?? '');
    $row++;
    
    // Summary section
    $row += 2;
    $sheet->setCellValue('A' . $row, 'SUMMARY');
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total Sales:');
    $sheet->setCellValue('B' . $row, 'KSh ' . number_format($totals['sales_amount'], 2));
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total Purchases:');
    $sheet->setCellValue('B' . $row, 'KSh ' . number_format($totals['purchases_amount'], 2));
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Output VAT:');
    $sheet->setCellValue('B' . $row, 'KSh ' . number_format($totals['sales_vat'], 2));
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Input VAT:');
    $sheet->setCellValue('B' . $row, 'KSh ' . number_format($totals['purchases_vat'], 2));
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Net VAT:');
    $sheet->setCellValue('B' . $row, 'KSh ' . number_format($totals['net_vat'], 2));
    $row += 3;
    
    // Table headers
    $headerRow = $row;
    $sheet->setCellValue('A' . $row, 'Month');
    $sheet->setCellValue('B' . $row, 'Sales Amount');
    $sheet->setCellValue('C' . $row, 'Purchases Amount');
    $sheet->setCellValue('D' . $row, 'Output VAT (16%)');
    $sheet->setCellValue('E' . $row, 'Input VAT (16%)');
    $sheet->setCellValue('F' . $row, 'Net VAT');
    $sheet->setCellValue('G' . $row, 'Status');
    $row++;
    
    // Data rows
    foreach ($months as $monthNum => $monthName) {
        $sheet->setCellValue('A' . $row, $monthName);
        
        if (isset($recordsByMonth[$monthNum])) {
            $record = $recordsByMonth[$monthNum];
            $sheet->setCellValue('B' . $row, $record['sales_amount']);
            $sheet->setCellValue('C' . $row, $record['purchases_amount']);
            $sheet->setCellValue('D' . $row, $record['sales_vat']);
            $sheet->setCellValue('E' . $row, $record['purchases_vat']);
            $sheet->setCellValue('F' . $row, $record['net_vat']);
            
            if ($record['net_vat'] > 0) {
                $status = 'Payable';
            } elseif ($record['net_vat'] < 0) {
                $status = 'Refund';
            } else {
                $status = 'Nil';
            }
            $sheet->setCellValue('G' . $row, $status);
        } else {
            $sheet->setCellValue('B' . $row, 0);
            $sheet->setCellValue('C' . $row, 0);
            $sheet->setCellValue('D' . $row, 0);
            $sheet->setCellValue('E' . $row, 0);
            $sheet->setCellValue('F' . $row, 0);
            $sheet->setCellValue('G' . $row, 'No Record');
        }
        $row++;
    }
    
    // Totals row
    $totalsRow = $row;
    $sheet->setCellValue('A' . $row, 'YEARLY TOTALS');
    $sheet->setCellValue('B' . $row, $totals['sales_amount']);
    $sheet->setCellValue('C' . $row, $totals['purchases_amount']);
    $sheet->setCellValue('D' . $row, $totals['sales_vat']);
    $sheet->setCellValue('E' . $row, $totals['purchases_vat']);
    $sheet->setCellValue('F' . $row, $totals['net_vat']);
    
    if ($totals['net_vat'] > 0) {
        $totalStatus = 'PAYABLE';
    } elseif ($totals['net_vat'] < 0) {
        $totalStatus = 'REFUND';
    } else {
        $totalStatus = 'NIL';
    }
    $sheet->setCellValue('G' . $row, $totalStatus);
    
    // Formatting
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A5')->getFont()->setBold(true);
    $sheet->getStyle('A' . ($headerRow - 3))->getFont()->setBold(true);
    
    // Header row formatting
    $headerRange = 'A' . $headerRow . ':G' . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF4472C4');
    $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
    
    // Totals row formatting
    $totalsRange = 'A' . $totalsRow . ':G' . $totalsRow;
    $sheet->getStyle($totalsRange)->getFont()->setBold(true);
    $sheet->getStyle($totalsRange)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFF2F2F2');
    
    // Number formatting for currency columns
    $currencyRange = 'B' . ($headerRow + 1) . ':F' . $totalsRow;
    $sheet->getStyle($currencyRange)->getNumberFormat()
        ->setFormatCode('#,##0.00');
    
    // Auto-size columns
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Add borders to the table
    $tableRange = 'A' . $headerRow . ':G' . $totalsRow;
    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    
    $filename = 'VAT_Report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $selectedClient['full_name']) . '_' . $year . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function generateHTML($selectedClient, $year, $months, $recordsByMonth, $totals, $vatRecords) {
    // Tax obligations handling
    $taxObligationsDisplay = $selectedClient['tax_obligations'];
    if (is_array($taxObligationsDisplay)) {
        $taxObligationsDisplay = implode(', ', $taxObligationsDisplay);
    }
    
    $completionRate = (count($vatRecords) / 12) * 100;
    $missingMonths = 12 - count($vatRecords);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; line-height: 1.4; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #2c3e50; padding-bottom: 20px; }
            .header h1 { color: #2c3e50; margin: 0; font-size: 24px; font-weight: bold; }
            .client-info { background: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .client-info h2 { margin: 0 0 10px 0; color: #2c3e50; font-size: 18px; }
            .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
            .summary-card { background: #e3f2fd; padding: 15px; border-radius: 5px; text-align: center; }
            .summary-card h3 { margin: 0 0 5px 0; font-size: 18px; color: #1976d2; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th { background: #2c3e50; color: white; padding: 10px; text-align: left; font-size: 12px; }
            td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 11px; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .totals-row { background: #f8f9fa; font-weight: bold; }
            .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>VAT COMPLIANCE REPORT</h1>
            <p><strong>Financial Year:</strong> <?php echo $year; ?></p>
            <p><strong>Generated on:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></p>
        </div>

        <div class="client-info">
            <h2>Client Information</h2>
            <p><strong>Business Name:</strong> <?php echo htmlspecialchars($selectedClient['full_name']); ?></p>
            <p><strong>KRA PIN:</strong> <?php echo htmlspecialchars($selectedClient['kra_pin']); ?></p>
            <p><strong>Client Type:</strong> <?php echo htmlspecialchars($selectedClient['client_type']); ?></p>
            <p><strong>County:</strong> <?php echo htmlspecialchars($selectedClient['county']); ?></p>
            <p><strong>Tax Obligations:</strong> <?php echo htmlspecialchars($taxObligationsDisplay); ?></p>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <h3>KSh <?php echo number_format($totals['sales_amount'], 2); ?></h3>
                <p>Total Sales</p>
            </div>
            <div class="summary-card">
                <h3>KSh <?php echo number_format($totals['purchases_amount'], 2); ?></h3>
                <p>Total Purchases</p>
            </div>
            <div class="summary-card">
                <h3>KSh <?php echo number_format($totals['net_vat'], 2); ?></h3>
                <p><?php echo $totals['net_vat'] >= 0 ? 'Net VAT Payable' : 'Net VAT Refund'; ?></p>
            </div>
        </div>

        <?php if (empty($vatRecords)): ?>
            <div style="text-align: center; color: #999; font-style: italic;">
                <h3>No VAT records found for <?php echo $year; ?></h3>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Sales Amount</th>
                        <th class="text-right">Purchases Amount</th>
                        <th class="text-right">Output VAT (16%)</th>
                        <th class="text-right">Input VAT (16%)</th>
                        <th class="text-right">Net VAT</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($months as $monthNum => $monthName): ?>
                        <tr>
                            <td><?php echo $monthName; ?></td>
                            <?php if (isset($recordsByMonth[$monthNum])): ?>
                                <?php $record = $recordsByMonth[$monthNum]; ?>
                                <td class="text-right">KSh <?php echo number_format($record['sales_amount'], 2); ?></td>
                                <td class="text-right">KSh <?php echo number_format($record['purchases_amount'], 2); ?></td>
                                <td class="text-right">KSh <?php echo number_format($record['sales_vat'], 2); ?></td>
                                <td class="text-right">KSh <?php echo number_format($record['purchases_vat'], 2); ?></td>
                                <td class="text-right">KSh <?php echo number_format($record['net_vat'], 2); ?></td>
                                <td class="text-center">
                                    <?php
                                    if ($record['net_vat'] > 0) echo 'Payable';
                                    elseif ($record['net_vat'] < 0) echo 'Refund';
                                    else echo 'Nil';
                                    ?>
                                </td>
                            <?php else: ?>
                                <td class="text-right">-</td>
                                <td class="text-right">-</td>
                                <td class="text-right">-</td>
                                <td class="text-right">-</td>
                                <td class="text-right">-</td>
                                <td class="text-center">No Record</td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="totals-row">
                        <td><strong>YEARLY TOTALS</strong></td>
                        <td class="text-right"><strong>KSh <?php echo number_format($totals['sales_amount'], 2); ?></strong></td>
                        <td class="text-right"><strong>KSh <?php echo number_format($totals['purchases_amount'], 2); ?></strong></td>
                        <td class="text-right"><strong>KSh <?php echo number_format($totals['sales_vat'], 2); ?></strong></td>
                        <td class="text-right"><strong>KSh <?php echo number_format($totals['purchases_vat'], 2); ?></strong></td>
                        <td class="text-right"><strong>KSh <?php echo number_format($totals['net_vat'], 2); ?></strong></td>
                        <td class="text-center">
                            <strong>
                                <?php
                                if ($totals['net_vat'] > 0) echo 'PAYABLE';
                                elseif ($totals['net_vat'] < 0) echo 'REFUND'; 
                                else echo 'NIL';
                                ?>
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

        <div class="footer">
            <p><strong>VAT Management System Report</strong></p>
            <p>Generated: <?php echo date('F j, Y \a\t g:i A'); ?> | Year: <?php echo $year; ?> | Client: <?php echo htmlspecialchars($selectedClient['full_name']); ?></p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>