<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// FILE: ajax/get_invoice.php - Invoice Display and Print Handler
session_start();
define('SYSTEM_ACCESS', true);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/constants.php';
require_once '../config/database.php';
$database = Database::getInstance();
$pdo = $database->getConnection();

$invoiceNumber = $_GET['invoice_number'] ?? '';
$isPrint = isset($_GET['print']);

if (!$invoiceNumber) {
    echo "Invoice not found.";
    exit;
}

// Get invoice details
$stmt = $pdo->prepare("
    SELECT i.*, c.full_name, c.kra_pin, c.email_address, c.phone_number
           , fr.tax_obligation, fr.filing_period
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN filing_records fr ON fr.invoice_number = i.invoice_number
    WHERE i.invoice_number = ?
");
$stmt->execute([$invoiceNumber]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo "Invoice not found.";
    exit;
}

if ($isPrint) {
    // Print version with minimal HTML
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .invoice-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
            .company-info { float: left; width: 50%; }
            .invoice-info { float: right; width: 50%; text-align: right; }
            .client-info { clear: both; margin: 20px 0; }
            .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .invoice-table th { background-color: #f2f2f2; }
            .total-section { text-align: right; margin-top: 20px; }
            .total-line { margin: 5px 0; }
            .grand-total { font-weight: bold; font-size: 1.2em; border-top: 2px solid #333; padding-top: 10px; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="invoice-header">
            <div class="company-info">
                <h2><?php echo htmlspecialchars($invoice['company_name']); ?></h2>
                <p><?php echo htmlspecialchars($invoice['company_address']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($invoice['company_phone']); ?></p>
                <p>Email: <?php echo htmlspecialchars($invoice['company_email']); ?></p>
            </div>
            <div class="invoice-info">
                <h1>INVOICE</h1>
                <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></p>
                <p><strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></p>
            </div>
        </div>
        
        <div class="client-info">
            <h3>Bill To:</h3>
            <p><strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong></p>
            <p>KRA PIN: <?php echo htmlspecialchars($invoice['kra_pin']); ?></p>
            <?php if ($invoice['email_address']): ?>
            <p>Email: <?php echo htmlspecialchars($invoice['email_address']); ?></p>
            <?php endif; ?>
            <?php if ($invoice['phone_number']): ?>
            <p>Phone: <?php echo htmlspecialchars($invoice['phone_number']); ?></p>
            <?php endif; ?>
        </div>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount (KSh)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($invoice['service_description']); ?></td>
                    <td><?php echo number_format($invoice['amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="total-section">
            <div class="total-line">Subtotal: KSh <?php echo number_format($invoice['amount'], 2); ?></div>
            <div class="total-line">VAT (<?php echo isset($invoice['tax_rate']) ? $invoice['tax_rate'] : 'N/A'; ?>%): KSh <?php echo number_format($invoice['tax_amount'], 2); ?></div>
            <div class="total-line grand-total">Total: KSh <?php echo number_format($invoice['total_amount'], 2); ?></div>
        </div>
        
        <div style="margin-top: 40px; font-size: 12px; color: #666;">
            <p>Thank you for your business!</p>
            <p>Payment terms: Net 30 days</p>
        </div>
    </body>
    </html>
    <?php
} else {
    // Modal view version
    ?>
    <div class="invoice-display">
        <div class="row mb-4">
            <div class="col-md-6">
                <h4><?php echo htmlspecialchars($invoice['company_name']); ?></h4>
                <p class="mb-1"><?php echo htmlspecialchars($invoice['company_address']); ?></p>
                <p class="mb-1">Phone: <?php echo htmlspecialchars($invoice['company_phone']); ?></p>
                <p class="mb-1">Email: <?php echo htmlspecialchars($invoice['company_email']); ?></p>
            </div>
            <div class="col-md-6 text-end">
                <h2 class="text-primary">INVOICE</h2>
                <p class="mb-1"><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></p>
                <p class="mb-1"><strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></p>
                <span class="badge <?php echo $invoice['status'] === 'Paid' ? 'bg-success' : ($invoice['status'] === 'Overdue' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                    <?php echo $invoice['status']; ?>
                </span>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Bill To:</h5>
                <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong></p>
                <p class="mb-1">KRA PIN: <?php echo htmlspecialchars($invoice['kra_pin']); ?></p>
                <?php if ($invoice['email_address']): ?>
                <p class="mb-1">Email: <?php echo htmlspecialchars($invoice['email_address']); ?></p>
                <?php endif; ?>
                <?php if ($invoice['phone_number']): ?>
                <p class="mb-1">Phone: <?php echo htmlspecialchars($invoice['phone_number']); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($invoice['tax_obligation']): ?>
            <div class="col-md-6">
                <h5>Service Details:</h5>
                <p class="mb-1"><strong>Tax Obligation:</strong> <?php echo htmlspecialchars($invoice['tax_obligation']); ?></p>
                <p class="mb-1"><strong>Filing Period:</strong> <?php echo htmlspecialchars($invoice['filing_period']); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount (KSh)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['service_description']); ?></td>
                        <td class="text-end"><?php echo number_format($invoice['amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="row">
            <div class="col-md-6"></div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-end">KSh <?php echo number_format($invoice['amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>VAT (<?php echo $invoice['tax_rate']; ?>%):</strong></td>
                        <td class="text-end">KSh <?php echo number_format($invoice['tax_amount'], 2); ?></td>
                    </tr>
                    <tr class="table-primary">
                        <td><strong>Total:</strong></td>
                        <td class="text-end"><strong>KSh <?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                <p>Thank you for your business!</p>
                <p>Payment terms: Net 30 days</p>
            </small>
        </div>
    </div>
    <?php
}
?>

<?php
// FILE: Complete the main invoice_system.php file (add the missing JavaScript)
// Add this JavaScript to the end of your invoice_system.php file before the closing </body> tag
?>

<script>
// Complete the showToast function that was cut off
function showToast(message, type) {
    const toast = document.createElement('div');
    let alertClass = 'alert-primary';
    
    switch(type) {
        case 'success': alertClass = 'alert-success'; break;
        case 'error': alertClass = 'alert-danger'; break;
        case 'info': alertClass = 'alert-info'; break;
        case 'warning': alertClass = 'alert-warning'; break;
    }
    
    toast.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}

// Auto-update overdue invoices
function checkOverdueInvoices() {
    const today = new Date();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const dueDateCell = row.cells[5]; // Due date column
        const statusCell = row.cells[6];   // Status column
        
        if (dueDateCell && statusCell) {
            const dueDate = new Date(dueDateCell.textContent);
            const statusBadge = statusCell.querySelector('.badge');
            
            if (statusBadge && statusBadge.textContent.trim() === 'Pending' && dueDate < today) {
                statusBadge.className = 'badge status-badge bg-danger';
                statusBadge.textContent = 'Overdue';
            }
        }
    });
}

// Run overdue check on page load
document.addEventListener('DOMContentLoaded', function() {
    checkOverdueInvoices();
    
    // Auto-fill client filings when selecting from uninvoiced tab
    const uninvoicedButtons = document.querySelectorAll('[onclick*="createInvoiceFromFiling"]');
    uninvoicedButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Small delay to ensure modal is shown
            setTimeout(calculateTotal, 100);
        });
    });
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+N to create new invoice
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        new bootstrap.Modal(document.getElementById('createInvoiceModal')).show();
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            bootstrap.Modal.getInstance(modal)?.hide();
        });
    }
});

// Enhanced form validation
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    const amount = parseFloat(document.querySelector('input[name="amount"]').value);
    const dueDate = new Date(document.querySelector('input[name="due_date"]').value);
    const today = new Date();
    
    if (amount <= 0) {
        e.preventDefault();
        showToast('Amount must be greater than zero', 'error');
        return;
    }
    
    if (dueDate < today) {
        if (!confirm('Due date is in the past. Are you sure you want to continue?')) {
            e.preventDefault();
            return;
        }
    }
});

// Export invoice data
function exportInvoiceData() {
    const invoiceData = [];
    const rows = document.querySelectorAll('#all-invoices tbody tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 7) {
            invoiceData.push({
                invoice_number: cells[0].textContent.trim(),
                client: cells[1].textContent.trim(),
                service: cells[2].textContent.trim(),
                amount: cells[3].textContent.trim(),
                date: cells[4].textContent.trim(),
                due_date: cells[5].textContent.trim(),
                status: cells[6].textContent.trim()
            });
        }
    });
    
    const csvContent = "data:text/csv;charset=utf-8," + 
        "Invoice Number,Client,Service,Amount,Date,Due Date,Status\n" +
        invoiceData.map(row => Object.values(row).join(",")).join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "invoices_export.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Add export button to the page
document.addEventListener('DOMContentLoaded', function() {
    const headerDiv = document.querySelector('.d-flex.justify-content-between.align-items-center');
    if (headerDiv) {
        const buttonContainer = headerDiv.querySelector('div:last-child');
        if (buttonContainer) {
            const exportButton = document.createElement('button');
            exportButton.className = 'btn btn-outline-info me-2';
            exportButton.innerHTML = '<i class="fas fa-download me-2"></i>Export CSV';
            exportButton.onclick = exportInvoiceData;
            buttonContainer.insertBefore(exportButton, buttonContainer.firstChild);
        }
    }
});
</script>

<?php
// FILE: Database schema for invoices table
// Run this SQL to create the invoices table if it doesn't exist
/*
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    filing_record_id INT NULL,
    service_description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 16.00,
    tax_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Pending', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Pending',
    company_name VARCHAR(255) NOT NULL,
    company_email VARCHAR(255) NOT NULL,
    company_phone VARCHAR(50) NOT NULL,
    company_address TEXT NOT NULL,
    company_county VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (filing_record_id) REFERENCES filing_records(id) ON DELETE SET NULL
);

-- Index for better performance
CREATE INDEX idx_invoices_client_id ON invoices(client_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_due_date ON invoices(due_date);
CREATE INDEX idx_invoices_invoice_number ON invoices(invoice_number);
*/
?>