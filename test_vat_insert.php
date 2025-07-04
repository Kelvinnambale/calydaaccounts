<?php
// FILE: test_vat_insert.php - Test VAT record insertion

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SYSTEM_ACCESS', true);
define('ROOT_PATH', dirname(__FILE__));

require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/database.php';

$database = Database::getInstance();
$pdo = $database->getConnection();

echo "<h2>VAT Record Insertion Test</h2>";

// Simulate POST data for testing
$client_id = 1; // Make sure this client exists in `clients` table
$record_year = date('Y');
$record_month = 5;
$sales_amount = 10000;
$purchases_amount = 3000;

// Validate required fields
if (!$client_id || !$record_year || !$record_month) {
    die("Client ID, year, and month are required.");
}

// Validate month
if ($record_month < 1 || $record_month > 12) {
    die("Invalid month value: $record_month");
}

// Calculate VAT values
$sales_vat = round($sales_amount * 0.16, 2);
$purchases_vat = round($purchases_amount * 0.16, 2);
$net_vat = round($sales_vat - $purchases_vat, 2);

// Check if client has VAT obligation
try {
    $checkStmt = $pdo->prepare("
        SELECT tax_obligations FROM clients 
        WHERE id = ? AND JSON_CONTAINS(tax_obligations, '\"VAT\"')
    ");
    $checkStmt->execute([$client_id]);
    if (!$checkStmt->fetch()) {
        die("Client does not have VAT obligations.");
    }

    // Check if record already exists
    $existingStmt = $pdo->prepare("
        SELECT id FROM vat_records 
        WHERE client_id = ? AND record_year = ? AND record_month = ?
    ");
    $existingStmt->execute([$client_id, $record_year, $record_month]);
    if ($existingStmt->fetch()) {
        die("A VAT record for this client and month already exists.");
    }

    // Insert new VAT record
    $insertStmt = $pdo->prepare("
        INSERT INTO vat_records (
            client_id, record_year, record_month,
            sales_amount, purchases_amount,
            sales_vat, purchases_vat, net_vat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $success = $insertStmt->execute([
        $client_id, $record_year, $record_month,
        $sales_amount, $purchases_amount,
        $sales_vat, $purchases_vat, $net_vat
    ]);

    if ($success) {
        echo "<div style='color: green;'>✅ VAT record inserted successfully!</div>";
        echo "<pre>Last Insert ID: " . $pdo->lastInsertId() . "</pre>";
        echo "<pre>Test Data:</pre>";
        echo "<pre>" . print_r([
            'Client ID' => $client_id,
            'Year' => $record_year,
            'Month' => $record_month,
            'Sales Amount' => $sales_amount,
            'Purchases Amount' => $purchases_amount,
            'Sales VAT' => $sales_vat,
            'Purchases VAT' => $purchases_vat,
            'Net VAT' => $net_vat
        ], true) . "</pre>";
    } else {
        echo "<div style='color: red;'>❌ Failed to insert VAT record.</div>";
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}