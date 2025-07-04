<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ajax/export_report.php - Export report data as CSV based on filters from reports.php

session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', __DIR__ . '/../');

require_once ROOT_PATH . 'config/constants.php';
require_once ROOT_PATH . 'config/database.php';
require_once ROOT_PATH . 'classes/Client.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$client = new Client();

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

// Ensure tax_obligation filter is properly formatted for JSON_CONTAINS if used
if (!empty($filters['tax_obligation'])) {
    $filters['tax_obligation'] = $filters['tax_obligation'];
}

// Prepare CSV output
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_export_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

$output = fopen('php://output', 'w');

switch ($filters['report_type']) {
    case 'client_information':
        $data = $client->getAll($filters);
        // CSV header
        fputcsv($output, ['Full Name', 'KRA PIN', 'Phone Number', 'Email Address', 'Client Type', 'County', 'Tax Obligations', 'ETIMS Status', 'Registration Date']);
        foreach ($data as $row) {
            $taxObligations = is_array($row['tax_obligations']) ? implode('; ', $row['tax_obligations']) : $row['tax_obligations'];
            fputcsv($output, [
                $row['full_name'],
                $row['kra_pin'],
                $row['phone_number'],
                $row['email_address'],
                $row['client_type'],
                $row['county'],
                $taxObligations,
                $row['etims_status'],
                date('Y-m-d', strtotime($row['registration_date']))
            ]);
        }
        break;

    case 'client_summary':
        // For summary, export stats and breakdowns as CSV
        $stats = $client->getStats();
        $byCounty = $client->getClientsByCounty($filters);
        $byType = $stats['by_type'];
        $byEtims = $client->getClientsByEtimsStatus($filters);

        // Export stats
        fputcsv($output, ['Client Summary Report']);
        fputcsv($output, ['Total Clients', $stats['total_clients']]);
        fputcsv($output, ['Recent Registrations', $stats['recent_registrations']]);
        fputcsv($output, []);

        // Export by county
        fputcsv($output, ['Clients by County']);
        fputcsv($output, ['County', 'Total', 'Companies', 'Individuals', 'ETIMS Registered']);
        foreach ($byCounty as $county) {
            fputcsv($output, [
                $county['county'],
                $county['total'],
                $county['companies'] ?? 0,
                $county['individuals'] ?? 0,
                $county['etims_registered'] ?? 0
            ]);
        }
        fputcsv($output, []);

        // Export by type
        fputcsv($output, ['Clients by Type']);
        fputcsv($output, ['Client Type', 'Count']);
        foreach ($byType as $type) {
            fputcsv($output, [$type['client_type'], $type['count']]);
        }
        fputcsv($output, []);

        // Export by ETIMS status
        fputcsv($output, ['Clients by ETIMS Status']);
        fputcsv($output, ['ETIMS Status', 'Count']);
        foreach ($byEtims as $etims) {
            fputcsv($output, [$etims['etims_status'], $etims['count']]);
        }
        break;

    case 'registration_trends':
        $data = $client->getRegistrationTrends($filters);
        fputcsv($output, ['Client Registration Trends']);
        fputcsv($output, ['Month', 'New Registrations', 'Companies', 'Individuals', 'Running Total']);
        $runningTotal = 0;
        foreach ($data as $row) {
            $runningTotal += $row['count'];
            fputcsv($output, [
                date('F Y', strtotime($row['month'] . '-01')),
                $row['count'],
                $row['companies'] ?? 0,
                $row['individuals'] ?? 0,
                $runningTotal
            ]);
        }
        break;

    default:
        // Default to client information
        $data = $client->getAll($filters);
        fputcsv($output, ['Full Name', 'KRA PIN', 'Phone Number', 'Email Address', 'Client Type', 'County', 'Tax Obligations', 'ETIMS Status', 'Registration Date']);
        foreach ($data as $row) {
            $taxObligations = is_array($row['tax_obligations']) ? implode('; ', $row['tax_obligations']) : $row['tax_obligations'];
            fputcsv($output, [
                $row['full_name'],
                $row['kra_pin'],
                $row['phone_number'],
                $row['email_address'],
                $row['client_type'],
                $row['county'],
                $taxObligations,
                $row['etims_status'],
                date('Y-m-d', strtotime($row['registration_date']))
            ]);
        }
        break;
}

fclose($output);
exit;
?>
