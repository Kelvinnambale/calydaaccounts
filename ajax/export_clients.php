<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/database.php';

global $pdo;
$pdo = Database::getInstance()->getConnection();

require_once ROOT_PATH . '/classes/Client.php';

$client = new Client();

$filters = [
    'search' => $_GET['search'] ?? '',
    'client_type' => $_GET['client_type'] ?? '',
    'tax_obligation' => $_GET['tax_obligation'] ?? '',
    'county' => $_GET['county'] ?? '',
    'etims_status' => $_GET['etims_status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$clientsData = $client->getAll($filters);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="clients_export_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

$output = fopen('php://output', 'w');

// CSV header row
fputcsv($output, ['Full Name', 'KRA PIN', 'Phone Number', 'Email Address', 'Client Type', 'County', 'ETIMS Status', 'Registration Date']);

foreach ($clientsData as $clientRow) {
    fputcsv($output, [
        $clientRow['full_name'],
        $clientRow['kra_pin'],
        $clientRow['phone_number'],
        $clientRow['email_address'],
        $clientRow['client_type'],
        $clientRow['county'],
        $clientRow['etims_status'],
        date('Y-m-d', strtotime($clientRow['registration_date']))
    ]);
}

fclose($output);
exit;
?>
