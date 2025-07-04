<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', realpath(__DIR__ . '/../'));

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/classes/Client.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$clientId = $_GET['client_id'] ?? '';
$year = $_GET['year'] ?? date('Y');
$format = $_GET['format'] ?? 'pdf';

if (!$clientId) {
    die('Client ID is required');
}

$client = new Client();
$selectedClient = $client->getById($clientId);

if (!$selectedClient) {
    die('Client not found');
}

$database = Database::getInstance();
$pdo = $database->getConnection();

$stmt = $pdo->prepare("SELECT * FROM vat_records WHERE client_id = ? AND record_year = ? ORDER BY record_month");
$stmt->execute([$clientId, $year]);
$vatRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

if ($format === 'excel') {
    exportExcel($selectedClient, $year, $vatRecords, $totals, $months);
} else {
    exportPDF($selectedClient, $year, $vatRecords, $totals, $months);
}

function exportPDF($client, $year, $records, $totals, $months) {
    $html = '<h1>VAT Report for ' . htmlspecialchars($client['full_name']) . ' - ' . $year . '</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
    $html .= '<tr><th>Month</th><th>Sales Amount</th><th>Purchases Amount</th><th>Sales VAT</th><th>Purchases VAT</th><th>Net VAT</th></tr>';

    $recordsByMonth = [];
    foreach ($records as $r) {
        $recordsByMonth[$r['record_month']] = $r;
    }

    for ($m = 1; $m <= 12; $m++) {
        $r = $recordsByMonth[$m] ?? null;
        $html .= '<tr>';
        $html .= '<td>' . $months[$m] . '</td>';
        $html .= '<td>' . ($r ? number_format($r['sales_amount'], 2) : '0.00') . '</td>';
        $html .= '<td>' . ($r ? number_format($r['purchases_amount'], 2) : '0.00') . '</td>';
        $html .= '<td>' . ($r ? number_format($r['sales_vat'], 2) : '0.00') . '</td>';
        $html .= '<td>' . ($r ? number_format($r['purchases_vat'], 2) : '0.00') . '</td>';
        $html .= '<td>' . ($r ? number_format($r['net_vat'], 2) : '0.00') . '</td>';
        $html .= '</tr>';
    }

    $html .= '<tr><th>Total</th>';
    $html .= '<th>' . number_format($totals['sales_amount'], 2) . '</th>';
    $html .= '<th>' . number_format($totals['purchases_amount'], 2) . '</th>';
    $html .= '<th>' . number_format($totals['sales_vat'], 2) . '</th>';
    $html .= '<th>' . number_format($totals['purchases_vat'], 2) . '</th>';
    $html .= '<th>' . number_format($totals['net_vat'], 2) . '</th>';
    $html .= '</tr></table>';

    $mpdf = new mPDF('L', 'A4');
    $mpdf->WriteHTML($html);
    $filename = 'VAT_Report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $client['full_name']) . '_' . $year . '.pdf';
    $mpdf->Output($filename, 'D');
    exit;
}

function exportExcel($client, $year, $records, $totals, $months) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'VAT Report for ' . $client['full_name']);
    $sheet->setCellValue('A2', 'Year: ' . $year);

    $sheet->setCellValue('A4', 'Month');
    $sheet->setCellValue('B4', 'Sales Amount');
    $sheet->setCellValue('C4', 'Purchases Amount');
    $sheet->setCellValue('D4', 'Sales VAT');
    $sheet->setCellValue('E4', 'Purchases VAT');
    $sheet->setCellValue('F4', 'Net VAT');

    $row = 5;
    $recordsByMonth = [];
    foreach ($records as $r) {
        $recordsByMonth[$r['record_month']] = $r;
    }

    foreach ($months as $num => $name) {
        $r = $recordsByMonth[$num] ?? null;
        $sheet->setCellValue('A' . $row, $name);
        $sheet->setCellValue('B' . $row, $r ? $r['sales_amount'] : 0);
        $sheet->setCellValue('C' . $row, $r ? $r['purchases_amount'] : 0);
        $sheet->setCellValue('D' . $row, $r ? $r['sales_vat'] : 0);
        $sheet->setCellValue('E' . $row, $r ? $r['purchases_vat'] : 0);
        $sheet->setCellValue('F' . $row, $r ? $r['net_vat'] : 0);
        $row++;
    }

    $sheet->setCellValue('A' . $row, 'Total');
    $sheet->setCellValue('B' . $row, $totals['sales_amount']);
    $sheet->setCellValue('C' . $row, $totals['purchases_amount']);
    $sheet->setCellValue('D' . $row, $totals['sales_vat']);
    $sheet->setCellValue('E' . $row, $totals['purchases_vat']);
    $sheet->setCellValue('F' . $row, $totals['net_vat']);

    $writer = new Xlsx($spreadsheet);
    $filename = 'VAT_Report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $client['full_name']) . '_' . $year . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer->save('php://output');
    exit;
}
