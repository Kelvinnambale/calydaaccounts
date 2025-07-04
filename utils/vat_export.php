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

global $pdo;
$pdo = Database::getInstance()->getConnection();

require_once ROOT_PATH . '/classes/Client.php';

$clientId = $_GET['client_id'] ?? '';
$year = $_GET['year'] ?? date('Y');

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

exportCSV($selectedClient, $year, $vatRecords, $totals, $months);

function exportCSV($client, $year, $records, $totals, $months) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="VAT_Report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $client['full_name']) . '_' . $year . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Write header row
    fputcsv($output, ['Month', 'Sales Amount', 'Purchases Amount', 'Sales VAT', 'Purchases VAT', 'Net VAT']);

    $recordsByMonth = [];
    foreach ($records as $r) {
        $recordsByMonth[$r['record_month']] = $r;
    }

    // Write data rows for each month
    for ($m = 1; $m <= 12; $m++) {
        $r = $recordsByMonth[$m] ?? null;
        fputcsv($output, [
            $months[$m],
            $r ? number_format($r['sales_amount'], 2) : '0.00',
            $r ? number_format($r['purchases_amount'], 2) : '0.00',
            $r ? number_format($r['sales_vat'], 2) : '0.00',
            $r ? number_format($r['purchases_vat'], 2) : '0.00',
            $r ? number_format($r['net_vat'], 2) : '0.00'
        ]);
    }

    // Write totals row
    fputcsv($output, [
        'Total',
        number_format($totals['sales_amount'], 2),
        number_format($totals['purchases_amount'], 2),
        number_format($totals['sales_vat'], 2),
        number_format($totals['purchases_vat'], 2),
        number_format($totals['net_vat'], 2)
    ]);

    fclose($output);
    exit;
}
