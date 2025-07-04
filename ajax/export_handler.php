<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE: ajax/export_handler.php
session_start();
define('SYSTEM_ACCESS', true);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/constants.php';
require_once '../config/database.php';

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $export_type = $_POST['export_type'] ?? '';
    $filters = $_POST['filters'] ?? [];
    
    switch ($export_type) {
        case 'clients':
            exportClients($db, $filters);
            break;
        case 'vat_records':
            exportVATRecords($db, $filters);
            break;
        case 'client_with_vat':
            exportClientWithVAT($db, $filters);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid export type']);
            exit;
    }
}

function exportClients($db, $filters) {
    $query = "SELECT 
                c.full_name,
                c.kra_pin,
                c.phone_number,
                c.email_address,
                c.client_type,
                c.county,
                c.etims_status,
                c.registration_date,
                c.created_by,
                c.updated_at,
                c.id_number,
                c.password,
                c.registration_date,
                c.tax_obligations
              FROM clients c 
              WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if (!empty($filters['client_type'])) {
        $query .= " AND c.client_type = ?";
        $params[] = $filters['client_type'];
    }
    
    if (!empty($filters['county'])) {
        $query .= " AND c.county = ?";
        $params[] = $filters['county'];
    }
    
    if (!empty($filters['etims_status'])) {
        $query .= " AND c.etims_status = ?";
        $params[] = $filters['etims_status'];
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND c.registration_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND c.registration_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['has_vat']) && $filters['has_vat'] === 'yes') {
        $query .= " AND c.tax_obligations LIKE '%VAT%'";
    }
    
    $query .= " ORDER BY c.full_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    generateCSV($results, 'clients_export_' . date('Y-m-d_H-i-s') . '.csv');
}

function exportVATRecords($db, $filters) {
    $query = "SELECT 
                c.full_name,
                c.kra_pin,
                v.record_month,
                v.record_year,
                v.sales_amount,
                v.sales_vat,
                v.purchases_amount,
                v.net_vat,
                v.created_at,
                v.updated_at
              FROM vat_records v
              JOIN clients c ON v.client_id = c.id
              WHERE c.tax_obligations LIKE '%VAT%'";
    
    $params = [];
    
    // Apply filters
    if (!empty($filters['client_id'])) {
        $query .= " AND v.client_id = ?";
        $params[] = $filters['client_id'];
    }
    
    if (!empty($filters['record_year'])) {
        $query .= " AND v.record_year = ?";
        $params[] = $filters['record_year'];
    }
    
    if (!empty($filters['record_month'])) {
        $query .= " AND v.record_month = ?";
        $params[] = $filters['record_month'];
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND v.created_at >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND v.created_at <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['min_amount'])) {
        $query .= " AND v.sales_amount >= ?";
        $params[] = $filters['min_amount'];
    }
    
    if (!empty($filters['max_amount'])) {
        $query .= " AND v.sales_amount <= ?";
        $params[] = $filters['max_amount'];
    }
    
    $query .= " ORDER BY c.full_name, v.record_year DESC, v.record_month DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    generateCSV($results, 'vat_records_export_' . date('Y-m-d_H-i-s') . '.csv');
}

function exportClientWithVAT($db, $filters) {
    $query = "SELECT 
                c.full_name,
                c.kra_pin,
                c.phone_number,
                c.email_address,
                c.client_type,
                c.county,
                c.etims_status,
                c.registration_date,
                COALESCE(v.record_count, 0) as vat_record_count,
                COALESCE(v.total_sales, 0) as total_sales_amount,
                COALESCE(v.total_vat, 0) as total_vat_amount,
                COALESCE(v.latest_record, 'N/A') as latest_vat_record
              FROM clients c
              LEFT JOIN (
                  SELECT 
                      client_id,
                      COUNT(*) as record_count,
                      SUM(sales_amount) as total_sales,
                      SUM(net_vat) as total_vat,
                      MAX(CONCAT(record_year, '-', LPAD(record_month, 2, '0'))) as latest_record
                  FROM vat_records
                  GROUP BY client_id
              ) v ON c.id = v.client_id
              WHERE c.tax_obligations LIKE '%VAT%'";
    
    $params = [];
    
    // Apply filters
    if (!empty($filters['county'])) {
        $query .= " AND c.county = ?";
        $params[] = $filters['county'];
    }
    
    if (!empty($filters['client_type'])) {
        $query .= " AND c.client_type = ?";
        $params[] = $filters['client_type'];
    }
    
    if (!empty($filters['has_records']) && $filters['has_records'] === 'yes') {
        $query .= " AND v.record_count > 0";
    } elseif (!empty($filters['has_records']) && $filters['has_records'] === 'no') {
        $query .= " AND (v.record_count = 0 OR v.record_count IS NULL)";
    }
    
    $query .= " ORDER BY c.full_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    generateCSV($results, 'clients_with_vat_export_' . date('Y-m-d_H-i-s') . '.csv');
}

function generateCSV($data, $filename) {
    if (empty($data)) {
        // Instead of showing error on whole screen, return a JSON error with 204 No Content status
        http_response_code(204);
        echo json_encode(['error' => 'No data found for export']);
        exit;
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, array_keys($data[0]));
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Get filter options for dropdowns
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_filter_options':
            $options = [];
            
            // Get client types
            $stmt = $db->prepare("SELECT DISTINCT client_type FROM clients WHERE client_type IS NOT NULL ORDER BY client_type");
            $stmt->execute();
            $options['client_types'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get counties
            $stmt = $db->prepare("SELECT DISTINCT county FROM clients WHERE county IS NOT NULL ORDER BY county");
            $stmt->execute();
            $options['counties'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get ETIMS statuses
            $stmt = $db->prepare("SELECT DISTINCT etims_status FROM clients WHERE etims_status IS NOT NULL ORDER BY etims_status");
            $stmt->execute();
            $options['etims_statuses'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get VAT clients for dropdown
            $stmt = $db->prepare("SELECT id, full_name FROM clients WHERE tax_obligations LIKE '%VAT%' ORDER BY full_name");
            $stmt->execute();
            $options['vat_clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get years from VAT records
            $stmt = $db->prepare("SELECT DISTINCT record_year FROM vat_records ORDER BY record_year DESC");
            $stmt->execute();
            $options['years'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            header('Content-Type: application/json');
            echo json_encode($options);
            break;
    }
}
?>