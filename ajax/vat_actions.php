<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// FILE: ajax/vat_actions.php - VAT Record CRUD Operations
session_start();

define('SYSTEM_ACCESS', true);

// Security checks
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include required files
require_once '../config/database.php';
require_once '../config/constants.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

// Get the action from POST or GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createVATRecord();
            break;
        case 'get':
            getVATRecord();
            break;
        case 'update':
            updateVATRecord();
            break;
        case 'delete':
            deleteVATRecord();
            break;
        case 'get_all':
            getAllVATRecords();
            break;
        case 'get_yearly_summary':
            getYearlySummary();
            break;
        case 'bulk_import':
            bulkImportVATRecords();
            break;
        default:
            throw new Exception('Invalid action specified');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Create a new VAT record
 */
function createVATRecord() {
    global $pdo;
    
    // Validate required fields
    $requiredFields = ['client_id', 'record_year', 'record_month', 'sales_amount', 'purchases_amount'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $clientId = (int) $_POST['client_id'];
    $recordYear = (int) $_POST['record_year'];
    $recordMonth = (int) $_POST['record_month'];
    $salesAmount = (float) $_POST['sales_amount'];
    $purchasesAmount = (float) $_POST['purchases_amount'];
    
    // Validate month
    if ($recordMonth < 1 || $recordMonth > 12) {
        throw new Exception('Invalid month specified');
    }
    
    // Validate year
    if ($recordYear < 2020 || $recordYear > (date('Y') + 1)) {
        throw new Exception('Invalid year specified');
    }
    
    // Validate amounts
    if ($salesAmount < 0 || $purchasesAmount < 0) {
        throw new Exception('Amounts cannot be negative');
    }
    
    // Calculate VAT amounts (16% VAT rate in Kenya)
    $salesVAT = $salesAmount * 0.16;
    $purchasesVAT = $purchasesAmount * 0.16;
    $netVAT = $salesVAT - $purchasesVAT;
    
    // Check if record already exists
    $checkStmt = $pdo->prepare("
        SELECT id FROM vat_records 
        WHERE client_id = ? AND record_year = ? AND record_month = ?
    ");
    $checkStmt->execute([$clientId, $recordYear, $recordMonth]);
    
    if ($checkStmt->fetch()) {
        throw new Exception('VAT record for this month already exists');
    }
    
    // Verify client exists and has VAT obligations
    $clientStmt = $pdo->prepare("
        SELECT id, full_name, tax_obligations 
        FROM clients 
        WHERE id = ? AND tax_obligations LIKE '%VAT%'
    ");
    $clientStmt->execute([$clientId]);
    $client = $clientStmt->fetch();
    
    if (!$client) {
        throw new Exception('Client not found or does not have VAT obligations');
    }
    
    // Insert new VAT record
    $insertStmt = $pdo->prepare("
        INSERT INTO vat_records (
            client_id, record_year, record_month, sales_amount, purchases_amount,
            sales_vat, purchases_vat, net_vat, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $insertStmt->execute([
        $clientId, $recordYear, $recordMonth, $salesAmount, $purchasesAmount,
        $salesVAT, $purchasesVAT, $netVAT
    ]);
    
    if ($result) {
        $recordId = $pdo->lastInsertId();
        
        // Log the activity
        logActivity("Created VAT record for {$client['full_name']} - " . 
                   getMonthName($recordMonth) . " {$recordYear}");
        
        echo json_encode([
            'success' => true,
            'message' => 'VAT record created successfully',
            'record_id' => $recordId,
            'data' => [
                'id' => $recordId,
                'client_id' => $clientId,
                'record_year' => $recordYear,
                'record_month' => $recordMonth,
                'sales_amount' => $salesAmount,
                'purchases_amount' => $purchasesAmount,
                'sales_vat' => $salesVAT,
                'purchases_vat' => $purchasesVAT,
                'net_vat' => $netVAT
            ]
        ]);
    } else {
        throw new Exception('Failed to create VAT record');
    }
}

/**
 * Get a specific VAT record
 */
function getVATRecord() {
    global $pdo;
    
    $recordId = $_GET['id'] ?? null;
    if (!$recordId) {
        throw new Exception('Record ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT vr.*, c.full_name, c.kra_pin
        FROM vat_records vr
        JOIN clients c ON vr.client_id = c.id
        WHERE vr.id = ?
    ");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        throw new Exception('VAT record not found');
    }
    
    echo json_encode([
        'success' => true,
        'record' => $record
    ]);
}

/**
 * Update an existing VAT record
 */
function updateVATRecord() {
    global $pdo;
    
    $recordId = $_POST['record_id'] ?? null;
    if (!$recordId) {
        throw new Exception('Record ID is required');
    }
    
    // Validate required fields
    $requiredFields = ['sales_amount', 'purchases_amount'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("Field '$field' is required");
        }
    }
    
    $salesAmount = (float) $_POST['sales_amount'];
    $purchasesAmount = (float) $_POST['purchases_amount'];
    
    // Validate amounts
    if ($salesAmount < 0 || $purchasesAmount < 0) {
        throw new Exception('Amounts cannot be negative');
    }
    
    // Calculate VAT amounts
    $salesVAT = $salesAmount * 0.16;
    $purchasesVAT = $purchasesAmount * 0.16;
    $netVAT = $salesVAT - $purchasesVAT;
    
    // Check if record exists
    $checkStmt = $pdo->prepare("
        SELECT vr.id, c.full_name, vr.record_year, vr.record_month
        FROM vat_records vr
        JOIN clients c ON vr.client_id = c.id
        WHERE vr.id = ?
    ");
    $checkStmt->execute([$recordId]);
    $existingRecord = $checkStmt->fetch();
    
    if (!$existingRecord) {
        throw new Exception('VAT record not found');
    }
    
    // Update the record
    $updateStmt = $pdo->prepare("
        UPDATE vat_records 
        SET sales_amount = ?, purchases_amount = ?, sales_vat = ?, 
            purchases_vat = ?, net_vat = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $updateStmt->execute([
        $salesAmount, $purchasesAmount, $salesVAT, 
        $purchasesVAT, $netVAT, $recordId
    ]);
    
    if ($result) {
        // Log the activity
        logActivity("Updated VAT record for {$existingRecord['full_name']} - " . 
                   getMonthName($existingRecord['record_month']) . " {$existingRecord['record_year']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'VAT record updated successfully',
            'data' => [
                'id' => $recordId,
                'sales_amount' => $salesAmount,
                'purchases_amount' => $purchasesAmount,
                'sales_vat' => $salesVAT,
                'purchases_vat' => $purchasesVAT,
                'net_vat' => $netVAT
            ]
        ]);
    } else {
        throw new Exception('Failed to update VAT record');
    }
}

/**
 * Delete a VAT record
 */
function deleteVATRecord() {
    global $pdo;
    
    $recordId = $_POST['id'] ?? null;
    if (!$recordId) {
        throw new Exception('Record ID is required');
    }
    
    // Get record details before deletion for logging
    $stmt = $pdo->prepare("
        SELECT vr.*, c.full_name
        FROM vat_records vr
        JOIN clients c ON vr.client_id = c.id
        WHERE vr.id = ?
    ");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();
    
    if (!$record) {
        throw new Exception('VAT record not found');
    }
    
    // Delete the record
    $deleteStmt = $pdo->prepare("DELETE FROM vat_records WHERE id = ?");
    $result = $deleteStmt->execute([$recordId]);
    
    if ($result) {
        // Log the activity
        logActivity("Deleted VAT record for {$record['full_name']} - " . 
                   getMonthName($record['record_month']) . " {$record['record_year']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'VAT record deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete VAT record');
    }
}

/**
 * Get all VAT records for a client and year
 */
function getAllVATRecords() {
    global $pdo;
    
    $clientId = $_GET['client_id'] ?? null;
    $year = $_GET['year'] ?? date('Y');
    
    if (!$clientId) {
        throw new Exception('Client ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT vr.*, c.full_name, c.kra_pin
        FROM vat_records vr
        JOIN clients c ON vr.client_id = c.id
        WHERE vr.client_id = ? AND vr.record_year = ?
        ORDER BY vr.record_month ASC
    ");
    $stmt->execute([$clientId, $year]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'records' => $records,
        'count' => count($records)
    ]);
}

/**
 * Get yearly summary for a client
 */
function getYearlySummary() {
    global $pdo;
    
    $clientId = $_GET['client_id'] ?? null;
    $year = $_GET['year'] ?? date('Y');
    
    if (!$clientId) {
        throw new Exception('Client ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(sales_amount) as total_sales,
            SUM(purchases_amount) as total_purchases,
            SUM(sales_vat) as total_sales_vat,
            SUM(purchases_vat) as total_purchases_vat,
            SUM(net_vat) as total_net_vat,
            COUNT(*) as months_recorded
        FROM vat_records
        WHERE client_id = ? AND record_year = ?
    ");
    $stmt->execute([$clientId, $year]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get client details
    $clientStmt = $pdo->prepare("SELECT full_name, kra_pin FROM clients WHERE id = ?");
    $clientStmt->execute([$clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'client' => $client,
        'year' => $year,
        'summary' => $summary
    ]);
}

/**
 * Bulk import VAT records (for CSV/Excel import)
 */
function bulkImportVATRecords() {
    global $pdo;
    
    if (!isset($_FILES['vat_file']) || $_FILES['vat_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Please upload a valid file');
    }
    
    $clientId = $_POST['client_id'] ?? null;
    $year = $_POST['year'] ?? null;
    
    if (!$clientId || !$year) {
        throw new Exception('Client ID and year are required');
    }
    
    // Process CSV file
    $file = $_FILES['vat_file']['tmp_name'];
    $handle = fopen($file, 'r');
    
    if (!$handle) {
        throw new Exception('Cannot read uploaded file');
    }
    
    $imported = 0;
    $errors = [];
    $header = fgetcsv($handle); // Skip header row
    
    $pdo->beginTransaction();
    
    try {
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 3) continue; // Skip incomplete rows
            
            $month = (int) $data[0];
            $salesAmount = (float) $data[1];
            $purchasesAmount = (float) $data[2];
            
            // Validate data
            if ($month < 1 || $month > 12) {
                $errors[] = "Invalid month: $month";
                continue;
            }
            
            if ($salesAmount < 0 || $purchasesAmount < 0) {
                $errors[] = "Negative amounts not allowed for month $month";
                continue;
            }
            
            // Calculate VAT
            $salesVAT = $salesAmount * 0.16;
            $purchasesVAT = $purchasesAmount * 0.16;
            $netVAT = $salesVAT - $purchasesVAT;
            
            // Insert or update record
            $stmt = $pdo->prepare("
                INSERT INTO vat_records 
                (client_id, record_year, record_month, sales_amount, purchases_amount, sales_vat, purchases_vat, net_vat, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                sales_amount = VALUES(sales_amount),
                purchases_amount = VALUES(purchases_amount),
                sales_vat = VALUES(sales_vat),
                purchases_vat = VALUES(purchases_vat),
                net_vat = VALUES(net_vat),
                updated_at = NOW()
            ");
            
            if ($stmt->execute([$clientId, $year, $month, $salesAmount, $purchasesAmount, $salesVAT, $purchasesVAT, $netVAT])) {
                $imported++;
            } else {
                $errors[] = "Failed to import data for month $month";
            }
        }
        
        $pdo->commit();
        fclose($handle);
        
        // Log the activity
        logActivity("Bulk imported $imported VAT records for client ID $clientId - Year $year");
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully imported $imported records",
            'imported' => $imported,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        fclose($handle);
        throw $e;
    }
}

/**
 * Helper function to get month name
 */
function getMonthName($monthNumber) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    return $months[$monthNumber] ?? 'Unknown';
}

/**
 * Helper function to log activities
 */
function logActivity($description) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, created_at)
            VALUES (?, 'VAT_MANAGEMENT', ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $description]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Helper function to validate VAT calculation
 */
function validateVATCalculation($salesAmount, $purchasesAmount, $salesVAT, $purchasesVAT, $netVAT) {
    $expectedSalesVAT = round($salesAmount * 0.16, 2);
    $expectedPurchasesVAT = round($purchasesAmount * 0.16, 2);
    $expectedNetVAT = round($expectedSalesVAT - $expectedPurchasesVAT, 2);
    
    return [
        'sales_vat_valid' => abs($salesVAT - $expectedSalesVAT) < 0.01,
        'purchases_vat_valid' => abs($purchasesVAT - $expectedPurchasesVAT) < 0.01,
        'net_vat_valid' => abs($netVAT - $expectedNetVAT) < 0.01,
        'expected_sales_vat' => $expectedSalesVAT,
        'expected_purchases_vat' => $expectedPurchasesVAT,
        'expected_net_vat' => $expectedNetVAT
    ];
}
?>