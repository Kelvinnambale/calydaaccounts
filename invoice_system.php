<?php
error_reporting(E_ALL); 
ini_set('display_errors', 1);
// FILE: invoice_system.php - Complete Invoice System with Filing Integration
session_start();
define('SYSTEM_ACCESS', true);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/constants.php';
require_once 'config/database.php';
$database = Database::getInstance();
$pdo = $database->getConnection();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_invoice':
            try {
                $clientId = $_POST['client_id'];
                $filingRecordId = $_POST['filing_record_id'] ?? null;
                $serviceDescription = $_POST['service_description'];
                $amount = floatval($_POST['amount']);
                $taxRate = floatval($_POST['tax_rate'] ?? 16);
                $dueDate = $_POST['due_date'];
                
                // Calculate tax and total
                $taxAmount = ($amount * $taxRate) / 100;
                $totalAmount = $amount + $taxAmount;
                
                // Generate invoice number
                $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // Get company details (you should have these in your config or database)
                $companyDetails = [
                    'company_name' => 'Calyda Accounts',
                    'company_email' => 'info@calydaaccounts.com',
                    'company_phone' => '+254-700-000-000',
                    'company_address' => 'Nairobi, Kenya',
                    'company_county' => 'Nairobi'
                ];
                
                $stmt = $pdo->prepare("
                    INSERT INTO invoices (
                        invoice_number, client_id, service_description,
                        amount, tax_amount, total_amount, invoice_date, due_date,
                        status, company_name, company_email, company_phone, company_address, company_county
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'Pending', ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $invoiceNumber, $clientId, $serviceDescription,
                    $amount, $taxAmount, $totalAmount, $dueDate,
                    $companyDetails['company_name'], $companyDetails['company_email'],
                    $companyDetails['company_phone'], $companyDetails['company_address'],
                    $companyDetails['company_county']
                ]);
                
                // If this is linked to a filing record, update the filing record
                if ($filingRecordId) {
                    $stmt = $pdo->prepare("UPDATE filing_records SET invoice_number = ? WHERE id = ?");
                    $stmt->execute([$invoiceNumber, $filingRecordId]);
                }
                
                echo json_encode(['success' => true, 'invoice_number' => $invoiceNumber]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'update_invoice_status':
            try {
                $invoiceId = $_POST['invoice_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
                $stmt->execute([$status, $invoiceId]);
                
                echo json_encode(['success' => true]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_client_filings':
            try {
                $clientId = $_POST['client_id'];
                
                $stmt = $pdo->prepare("
                    SELECT id, tax_obligation, filing_period, status, amount_charged, filing_date
                    FROM filing_records 
                    WHERE client_id = ? AND invoice_number IS NULL
                    ORDER BY filing_date DESC
                ");
                $stmt->execute([$clientId]);
                $filings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'filings' => $filings]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Get all clients for dropdown
$stmt = $pdo->query("SELECT id, full_name, kra_pin FROM clients ORDER BY full_name");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all invoices with client information
$stmt = $pdo->query("
    SELECT i.*, c.full_name, c.kra_pin, c.email_address,
           fr.tax_obligation, fr.filing_period
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN filing_records fr ON fr.invoice_number = i.invoice_number
    ORDER BY i.created_at DESC
");
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filing records that need invoicing
$stmt = $pdo->query("
    SELECT fr.*, c.full_name, c.kra_pin
    FROM filing_records fr
    JOIN clients c ON fr.client_id = c.id
    WHERE fr.invoice_number IS NULL AND fr.status = 'Filed'
    ORDER BY fr.filing_date DESC
");
$uninvoiced_filings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice System - Calyda Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .card-header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border-radius: 15px 15px 0 0; }
        .btn-primary { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); border: none; }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); border: none; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .status-badge { font-size: 0.8em; }
        .invoice-actions { white-space: nowrap; }
        .alert-uninvoiced { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-file-invoice-dollar me-2"></i>Invoice System</h2>
                <p class="text-muted mb-0">Manage invoices and billing for tax filing services</p>
            </div>
            <div>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                    <i class="fas fa-plus me-2"></i>Create Invoice
                </button>
                <a href="filing_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Uninvoiced Filings Alert -->
        <?php if (!empty($uninvoiced_filings)): ?>
        <div class="alert alert-uninvoiced alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attention!</strong> You have <?php echo count($uninvoiced_filings); ?> completed filings that need invoicing.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Invoices</h6>
                                <h3><?php echo count($invoices); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Paid Invoices</h6>
                                <h3><?php echo count(array_filter($invoices, fn($inv) => $inv['status'] === 'Paid')); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Pending Invoices</h6>
                                <h3><?php echo count(array_filter($invoices, fn($inv) => $inv['status'] === 'Pending')); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Overdue Invoices</h6>
                                <h3><?php echo count(array_filter($invoices, fn($inv) => $inv['status'] === 'Overdue')); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="invoiceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-invoices-tab" data-bs-toggle="tab" data-bs-target="#all-invoices" type="button" role="tab">
                    <i class="fas fa-list me-2"></i>All Invoices
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="uninvoiced-tab" data-bs-toggle="tab" data-bs-target="#uninvoiced" type="button" role="tab">
                    <i class="fas fa-exclamation-circle me-2"></i>Uninvoiced Filings
                    <?php if (!empty($uninvoiced_filings)): ?>
                    <span class="badge bg-danger ms-1"><?php echo count($uninvoiced_filings); ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="invoiceTabsContent">
            <!-- All Invoices Tab -->
            <div class="tab-pane fade show active" id="all-invoices" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>All Invoices</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Client</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                            <?php if ($invoice['tax_obligation']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($invoice['tax_obligation']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($invoice['kra_pin']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['service_description']); ?></td>
                                        <td>
                                            <strong>KSh <?php echo number_format($invoice['total_amount'], 2); ?></strong><br>
                                            <small class="text-muted">+VAT: KSh <?php echo number_format($invoice['tax_amount'], 2); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($invoice['due_date'])); ?></td>
                                        <td>
                                            <span class="badge status-badge <?php echo $invoice['status'] === 'Paid' ? 'bg-success' : ($invoice['status'] === 'Overdue' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                                <?php echo $invoice['status']; ?>
                                            </span>
                                        </td>
                                        <td class="invoice-actions">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewInvoice('<?php echo $invoice['invoice_number']; ?>')" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-success" onclick="updateInvoiceStatus(<?php echo $invoice['id']; ?>, 'Paid')" title="Mark as Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" onclick="printInvoice('<?php echo $invoice['invoice_number']; ?>')" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Uninvoiced Filings Tab -->
            <div class="tab-pane fade" id="uninvoiced" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Uninvoiced Filings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($uninvoiced_filings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>All filings are properly invoiced!</h5>
                            <p class="text-muted">No completed filings are waiting for invoicing.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Client</th>
                                        <th>Tax Obligation</th>
                                        <th>Filing Period</th>
                                        <th>Filing Date</th>
                                        <th>Amount Charged</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($uninvoiced_filings as $filing): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($filing['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($filing['kra_pin']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($filing['tax_obligation']); ?></td>
                                        <td><?php echo htmlspecialchars($filing['filing_period']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($filing['filing_date'])); ?></td>
                                        <td>
                                            <strong>KSh <?php echo number_format($filing['amount_charged'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="createInvoiceFromFiling(<?php echo $filing['id']; ?>, <?php echo $filing['client_id']; ?>, '<?php echo addslashes($filing['tax_obligation']); ?>', <?php echo $filing['amount_charged']; ?>)">
                                                <i class="fas fa-plus me-1"></i>Create Invoice
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Invoice Modal -->
    <div class="modal fade" id="createInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="invoiceForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client *</label>
                                    <select class="form-select" name="client_id" required onchange="loadClientFilings(this.value)">
                                        <option value="">Select Client</option>
                                        <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['full_name']); ?> (<?php echo htmlspecialchars($client['kra_pin']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Link to Filing (Optional)</label>
                                    <select class="form-select" name="filing_record_id" id="filingRecordSelect">
                                        <option value="">Select Filing Record</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Description *</label>
                            <textarea class="form-control" name="service_description" rows="3" required placeholder="Describe the services provided..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Amount (KSh) *</label>
                                    <input type="number" class="form-control" name="amount" step="0.01" required onchange="calculateTotal()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" name="tax_rate" value="16" step="0.01" onchange="calculateTotal()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Due Date *</label>
                                    <input type="date" class="form-control" name="due_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tax Amount (KSh)</label>
                                    <input type="text" class="form-control" id="taxAmountDisplay" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Total Amount (KSh)</label>
                                    <input type="text" class="form-control" id="totalAmountDisplay" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Invoice View Modal -->
    <div class="modal fade" id="invoiceViewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Invoice Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="invoiceViewContent">
                    <!-- Invoice content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printInvoice()">Print Invoice</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set default due date to 30 days from today
        document.addEventListener('DOMContentLoaded', function() {
            const dueDateInput = document.querySelector('input[name="due_date"]');
            const today = new Date();
            const dueDate = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000));
            dueDateInput.value = dueDate.toISOString().split('T')[0];
        });

        // Load client filings when client is selected
        function loadClientFilings(clientId) {
            if (!clientId) {
                document.getElementById('filingRecordSelect').innerHTML = '<option value="">Select Filing Record</option>';
                return;
            }

            fetch('invoice_system.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_client_filings&client_id=' + clientId
            })
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('filingRecordSelect');
                select.innerHTML = '<option value="">Select Filing Record</option>';
                
                if (data.success && data.filings.length > 0) {
                    data.filings.forEach(filing => {
                        const option = document.createElement('option');
                        option.value = filing.id;
                        option.textContent = `${filing.tax_obligation} - ${filing.filing_period} (KSh ${parseFloat(filing.amount_charged).toFixed(2)})`;
                        select.appendChild(option);
                    });
                }
            });
        }

        // Calculate total amount
        function calculateTotal() {
            const amount = parseFloat(document.querySelector('input[name="amount"]').value) || 0;
            const taxRate = parseFloat(document.querySelector('input[name="tax_rate"]').value) || 0;
            
            const taxAmount = (amount * taxRate) / 100;
            const totalAmount = amount + taxAmount;
            
            document.getElementById('taxAmountDisplay').value = taxAmount.toFixed(2);
            document.getElementById('totalAmountDisplay').value = totalAmount.toFixed(2);
        }

        // Handle form submission
        document.getElementById('invoiceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_invoice');
            
            fetch('invoice_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Invoice created successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('createInvoiceModal')).hide();
                    location.reload();
                } else {
                    showToast('Error creating invoice: ' + data.error, 'error');
                }
            });
        });

        // Create invoice from filing
        function createInvoiceFromFiling(filingId, clientId, taxObligation, amount) {
            const modal = new bootstrap.Modal(document.getElementById('createInvoiceModal'));
            
            // Pre-fill form
            document.querySelector('select[name="client_id"]').value = clientId;
            document.querySelector('input[name="amount"]').value = amount;
            document.querySelector('textarea[name="service_description"]').value = `Tax filing service for ${taxObligation}`;
            
            // Load client filings and select the current one
            loadClientFilings(clientId);
            setTimeout(() => {
                document.querySelector('select[name="filing_record_id"]').value = filingId;
            }, 500);
            
            calculateTotal();
            modal.show();
        }

        // View invoice
        function viewInvoice(invoiceNumber) {
            fetch('ajax/get_invoice.php?invoice_number=' + invoiceNumber)
            .then(response => response.text())
            .then(html => {
                document.getElementById('invoiceViewContent').innerHTML = html;
                new bootstrap.Modal(document.getElementById('invoiceViewModal')).show();
            });
        }

        // Update invoice status
        function updateInvoiceStatus(invoiceId, status) {
            if (confirm('Are you sure you want to mark this invoice as ' + status + '?')) {
                fetch('invoice_system.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=update_invoice_status&invoice_id=' + invoiceId + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Invoice status updated successfully!', 'success');
                        location.reload();
                    } else {
                        showToast('Error updating invoice status: ' + data.error, 'error');
                    }
                });
            }
        }

        // Print invoice
        function printInvoice(invoiceNumber) {
            if (invoiceNumber) {
                window.open('ajax/get_invoice.php?invoice_number=' + invoiceNumber + '&print=1', '_blank');
            } else {
                window.print();
            }
        }

        // Toast notification function
        function showToast(message, type) {
            const toast = document.createElement('div');
            let alertClass = 'alert-primary';
            
            switch(type) {
                case 'success': alertClass = 'alert-success'; break;
                case 'error': alertClass = 'alert-danger'; break;
                case 'info': alertClass = 'alert-info'; break;
                default: alertClass = 'alert-primary';
            }
            
            toast.className = `alert ${alertClass} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close ms-2" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
    </script>
</body>
</html>
