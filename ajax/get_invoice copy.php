<?php
// FILE: ajax/get_invoice.php - Get Invoice Details
session_start();
define('SYSTEM_ACCESS', true);

if (!isset($_SESSION['user_id']) || !isset($_GET['invoice_number'])) {
    exit('Access denied');
}

require_once '../config/database.php';
$database = Database::getInstance();
$pdo = $database->getConnection();

$invoiceNumber = $_GET['invoice_number'];

// Get invoice details with client information
$stmt = $pdo->prepare("
    SELECT i.*, c.full_name, c.email_address, c.phone_number, c.county as client_county,
           fr.tax_obligation, fr.filing_period
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN filing_records fr ON i.invoice_number = fr.invoice_number
    WHERE i.invoice_number = ?
");
$stmt->execute([$invoiceNumber]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    exit('Invoice not found');
}
?>
<div class="invoice-preview">
    <!-- Invoice Header -->
    <div class="invoice-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2 class="mb-0">
                    <i class="fas fa-file-invoice me-2"></i>
                    INVOICE
                </h2>
                <p class="mb-0 opacity-75">Professional Tax Services</p>
            </div>
            <div class="col-md-6 text-md-end">
                <h4 class="mb-0"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h4>
                <p class="mb-0 opacity-75">Date: <?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Invoice Body -->
    <div class="invoice-body">
        <div class="row mb-4">
            <!-- From Section -->
            <div class="col-md-6">
                <h6 class="text-primary mb-3">FROM:</h6>
                <div class="mb-2">
                    <strong><?php echo htmlspecialchars($invoice['company_name']); ?></strong>
                </div>
                <div class="mb-1">
                    <i class="fas fa-envelope me-2 text-muted"></i>
                    <?php echo htmlspecialchars($invoice['company_email']); ?>
                </div>
                <div class="mb-1">
                    <i class="fas fa-phone me-2 text-muted"></i>
                    <?php echo htmlspecialchars($invoice['company_phone']); ?>
                </div>
                <div class="mb-1">
                    <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                    <?php echo nl2br(htmlspecialchars($invoice['company_address'])); ?>
                </div>
                <div class="mb-1">
                    <i class="fas fa-location-arrow me-2 text-muted"></i>
                    <?php echo htmlspecialchars($invoice['company_county']); ?>
                </div>
            </div>

            <!-- To Section -->
            <div class="col-md-6">
                <h6 class="text-primary mb-3">TO:</h6>
                <div class="mb-2">
                    <strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong>
                </div>
                <div class="mb-1">
                    <i class="fas fa-envelope me-2 text-muted"></i>
                    <?php echo htmlspecialchars($invoice['email_address']); ?>
                </div>
                <div class="mb-1">
                    <i class="fas fa-phone me-2 text-muted"></i>
                    <?php echo htmlspecialchars($invoice['phone_number']); ?>
                </div>
                <div class="mb-1">
                    <i class="fas fa-location-arrow me-2 text-muted"></i>
                    <?php echo htmlspecialchars($invoice['client_county']); ?>
                </div>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-primary mb-3">INVOICE DETAILS:</h6>
                <div class="mb-1">
                    <strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                </div>
                <div class="mb-1">
                    <strong>Invoice Date:</strong> <?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?>
                </div>
                <div class="mb-1">
                    <strong>Due Date:</strong> <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?>
                </div>
                <?php if ($invoice['tax_obligation']): ?>
                <div class="mb-1">
                    <strong>Tax Obligation:</strong> <?php echo htmlspecialchars($invoice['tax_obligation']); ?>
                </div>
                <div class="mb-1">
                    <strong>Filing Period:</strong> <?php echo htmlspecialchars($invoice['filing_period']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary mb-3">PAYMENT STATUS:</h6>
                <div class="mb-1">
                    <strong>Status:</strong> 
                    <span class="badge <?php echo $invoice['status'] === 'Paid' ? 'bg-success' : ($invoice['status'] === 'Overdue' ? 'bg-danger' : 'bg-warning'); ?>">
                        <?php echo htmlspecialchars($invoice['status']); ?>
                    </span>
                </div>
                <div class="mb-1">
                    <strong>Total Amount:</strong> 
                    <span class="text-success fw-bold">KSh <?php echo number_format($invoice['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Services Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Service Description</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">VAT (16%)</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo nl2br(htmlspecialchars($invoice['service_description'])); ?></td>
                        <td class="text-end">KSh <?php echo number_format($invoice['amount'], 2); ?></td>
                        <td class="text-end">KSh <?php echo number_format($invoice['tax_amount'], 2); ?></td>
                        <td class="text-end"><strong>KSh <?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="row">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h6 class="mb-2">Payment Instructions:</h6>
                    <p class="mb-1">Please make payment within 30 days of invoice date.</p>
                    <p class="mb-1">For any queries, contact us at <?php echo htmlspecialchars($invoice['company_email']); ?></p>
                    <p class="mb-0">Thank you for your business!</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Invoice Summary</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>KSh <?php echo number_format($invoice['amount'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>VAT (16%):</span>
                            <span>KSh <?php echo number_format($invoice['tax_amount'], 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total Amount:</strong>
                            <strong class="text-success">KSh <?php echo number_format($invoice['total_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Footer -->
    <div class="invoice-footer">
        <div class="row">
            <div class="col-md-12">
                <p class="mb-1"><strong>Terms & Conditions:</strong></p>
                <p class="small text-muted mb-0">
                    Payment is due within 30 days of invoice date. Late payments may incur additional charges. 
                    All services are rendered in accordance with professional standards and applicable regulations.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
/* Print styles */
@media print {
    .invoice-preview {
        margin: 0;
        padding: 0;
        box-shadow: none;
    }
    
    .invoice-header {
        color: #333 !important;
        background: #f8f9fa !important;
        border: 1px solid #dee2e6;
    }
    
    .invoice-body {
        border: 1px solid #dee2e6;
        border-top: none;
    }
    
    .invoice-footer {
        border: 1px solid #dee2e6;
        border-top: none;
    }
}
</style>