<?php
// FILE: ajax/client_actions.php - Fixed version with proper JSON handling

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/constants.php';
require_once '../config/database.php';

$database = Database::getInstance();
$pdo = $database->getConnection();

require_once '../classes/Client.php';

$client = new Client();
$action = $_REQUEST['action'] ?? '';

// Set appropriate headers based on action
if (in_array($action, ['view', 'edit'])) {
    header('Content-Type: text/html; charset=utf-8');
} else {
    header('Content-Type: application/json; charset=utf-8');
}

switch ($action) {
    case 'create':
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'kra_pin' => strtoupper(trim($_POST['kra_pin'] ?? '')),
            'password' => $_POST['password'] ?? '',
            'tax_obligations' => $_POST['tax_obligations'] ?? [],
            'email_address' => trim($_POST['email_address'] ?? ''),
            'id_number' => trim($_POST['id_number'] ?? ''),
            'client_type' => $_POST['client_type'] ?? '',
            'county' => $_POST['county'] ?? '',
            'etims_status' => $_POST['etims_status'] ?? 'Not Registered',
            'created_by' => $_SESSION['user_id']
        ];

        // Validation
        if (empty($data['full_name']) || empty($data['phone_number']) || empty($data['kra_pin']) || 
            empty($data['password']) || empty($data['email_address']) || empty($data['id_number']) || 
            empty($data['client_type']) || empty($data['county'])) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }

        if (empty($data['tax_obligations'])) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one tax obligation']);
            exit;
        }

        $result = $client->create($data);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Client created successfully', 'id' => $result]);
        } else {
            error_log('Client creation error: ' . $client->getLastError());
            echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $client->getLastError()]);
        }
        break;

    case 'view':
        $id = $_GET['id'] ?? 0;
        $clientData = $client->getById($id);
        
        if (!$clientData) {
            echo '<div class="alert alert-danger">Client not found</div>';
            exit;
        }
        ?>
        <div class="row">
            <div class="col-md-6">
                <h6>Personal Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Full Name:</strong></td><td><?php echo htmlspecialchars($clientData['full_name']); ?></td></tr>
                    <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($clientData['phone_number']); ?></td></tr>
                    <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($clientData['email_address']); ?></td></tr>
                    <tr><td><strong>ID Number:</strong></td><td><?php echo htmlspecialchars($clientData['id_number']); ?></td></tr>
                    <tr><td><strong>Client Type:</strong></td><td><?php echo htmlspecialchars($clientData['client_type']); ?></td></tr>
                    <tr><td><strong>County:</strong></td><td><?php echo htmlspecialchars($clientData['county']); ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Account Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>KRA PIN:</strong></td>
                        <td>
                            <?php echo htmlspecialchars($clientData['kra_pin']); ?>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?php echo htmlspecialchars($clientData['kra_pin']); ?>')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Password:</strong></td>
                        <td>
                            <?php echo htmlspecialchars($clientData['password']); ?>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?php echo htmlspecialchars($clientData['password']); ?>')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                    </tr>
                    <tr><td><strong>ETIMS Status:</strong></td><td>
                        <span class="badge bg-<?php echo $clientData['etims_status'] == 'Registered' ? 'success' : ($clientData['etims_status'] == 'Pending' ? 'warning' : 'danger'); ?>">
                            <?php echo htmlspecialchars($clientData['etims_status']); ?>
                        </span>
                    </td></tr>
                    <tr><td><strong>Registration Date:</strong></td><td><?php echo date('M j, Y g:i A', strtotime($clientData['registration_date'])); ?></td></tr>
                </table>
                
                <h6>Tax Obligations</h6>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($clientData['tax_obligations'] as $tax): ?>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($tax); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        break;

    case 'edit':
        $id = $_GET['id'] ?? 0;
        $clientData = $client->getById($id);
        
        if (!$clientData) {
            echo '<div class="alert alert-danger">Client not found</div>';
            exit;
        }
        ?>
        <form id="editClientForm" method="POST" onsubmit="return false;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($clientData['full_name']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($clientData['phone_number']); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">KRA PIN *</label>
                    <input type="text" class="form-control" name="kra_pin" value="<?php echo htmlspecialchars($clientData['kra_pin']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password *</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="password" id="editClientPassword" value="<?php echo htmlspecialchars($clientData['password']); ?>" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="generatePasswordEdit()">
                            <i class="fas fa-random"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard(document.getElementById('editClientPassword').value)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email Address *</label>
                    <input type="email" class="form-control" name="email_address" value="<?php echo htmlspecialchars($clientData['email_address']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">ID Number *</label>
                    <input type="text" class="form-control" name="id_number" value="<?php echo htmlspecialchars($clientData['id_number']); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Client Type *</label>
                    <select class="form-select" name="client_type" required>
                        <?php foreach (CLIENT_TYPES as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo $clientData['client_type'] == $key ? 'selected' : ''; ?>>
                                <?php echo $value; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">County *</label>
                    <select class="form-select" name="county" required>
                        <?php foreach (KENYAN_COUNTIES as $county): ?>
                            <option value="<?php echo $county; ?>" <?php echo $clientData['county'] == $county ? 'selected' : ''; ?>>
                                <?php echo $county; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Tax Obligations *</label>
                <div class="row">
                    <?php foreach (TAX_OBLIGATIONS as $key => $value): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tax_obligations[]" value="<?php echo $key; ?>" 
                                       id="edit_tax_<?php echo str_replace(' ', '_', $key); ?>"
                                       <?php echo in_array($key, $clientData['tax_obligations']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edit_tax_<?php echo str_replace(' ', '_', $key); ?>">
                                    <?php echo $value; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ETIMS Registration Status</label>
                <select class="form-select" name="etims_status">
                    <option value="Not Registered" <?php echo $clientData['etims_status'] == 'Not Registered' ? 'selected' : ''; ?>>Not Registered</option>
                    <option value="Pending" <?php echo $clientData['etims_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Registered" <?php echo $clientData['etims_status'] == 'Registered' ? 'selected' : ''; ?>>Registered</option>
                </select>
            </div>
            
            <div class="text-end">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateClient()">Update Client</button>
            </div>
        </form>
        
        <script>
            function generatePasswordEdit() {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
                let password = '';
                for (let i = 0; i < 8; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                document.getElementById('editClientPassword').value = password;
            }

            function closeEditModal() {
                try {
                    // Close current modal
                    const modal = document.getElementById('editClientModal');
                    if (modal) {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) {
                            bsModal.hide();
                        }
                    }
                    
                    // Close parent modal
                    if (window.parent && window.parent !== window) {
                        const parentModal = window.parent.document.getElementById('editClientModal');
                        if (parentModal) {
                            const parentBsModal = window.parent.bootstrap.Modal.getInstance(parentModal);
                            if (parentBsModal) {
                                parentBsModal.hide();
                            }
                        }
                    }
                    
                    // Remove modal backdrop
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    
                    // Reset body styles
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    
                } catch (e) {
                    console.error('Error closing modal:', e);
                }
            }

            function updateClient() {
                const form = document.getElementById('editClientForm');
                const formData = new FormData(form);
                
                // Add debug logging
                console.log('Updating client with data:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                // Validate required fields
                const requiredFields = ['full_name', 'phone_number', 'kra_pin', 'password', 'email_address', 'id_number', 'client_type', 'county'];
                for (let field of requiredFields) {
                    const input = form.querySelector(`[name="${field}"]`);
                    if (!input || !input.value.trim()) {
                        showMessage(`Please fill in the ${field.replace('_', ' ')} field`, 'error');
                        return;
                    }
                }
                
                // Check if at least one tax obligation is selected
                const taxObligations = form.querySelectorAll('input[name="tax_obligations[]"]:checked');
                if (taxObligations.length === 0) {
                    showMessage('Please select at least one tax obligation', 'error');
                    return;
                }
                
                // Disable the update button to prevent double-clicks
                const updateBtn = document.querySelector('button[onclick="updateClient()"]');
                if (updateBtn) {
                    updateBtn.disabled = true;
                    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                }
                
                // Make the AJAX request
                fetch('ajax/client_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Non-JSON response:', text);
                            throw new Error('Response is not JSON: ' + text.substring(0, 200));
                        });
                    }
                    
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        showMessage('Client updated successfully!', 'success');
                        closeEditModal();
                        
                        // Reload parent page after a short delay
                        setTimeout(() => {
                            if (window.parent && window.parent.location) {
                                window.parent.location.reload();
                            } else {
                                location.reload();
                            }
                        }, 1500);
                    } else {
                        showMessage(data.message || 'Failed to update client', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred: ' + error.message, 'error');
                })
                .finally(() => {
                    // Re-enable the update button
                    if (updateBtn) {
                        updateBtn.disabled = false;
                        updateBtn.innerHTML = 'Update Client';
                    }
                });
            }

            function showMessage(message, type) {
                // Try to use parent window's toast function
                if (window.parent && window.parent.showToast) {
                    window.parent.showToast(message, type);
                } else {
                    // Fallback to alert
                    if (type === 'success') {
                        alert('✅ ' + message);
                    } else {
                        alert('❌ ' + message);
                    }
                }
            }
        </script>
        <?php
        break;

    case 'update':
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
            exit;
        }
        
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'kra_pin' => strtoupper(trim($_POST['kra_pin'] ?? '')),
            'password' => $_POST['password'] ?? '',
            'tax_obligations' => $_POST['tax_obligations'] ?? [],
            'email_address' => trim($_POST['email_address'] ?? ''),
            'id_number' => trim($_POST['id_number'] ?? ''),
            'client_type' => $_POST['client_type'] ?? '',
            'county' => $_POST['county'] ?? '',
            'etims_status' => $_POST['etims_status'] ?? 'Not Registered'
        ];

        // Validation
        if (empty($data['full_name']) || empty($data['phone_number']) || empty($data['kra_pin']) || 
            empty($data['password']) || empty($data['email_address']) || empty($data['id_number']) || 
            empty($data['client_type']) || empty($data['county'])) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }

        if (empty($data['tax_obligations'])) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one tax obligation']);
            exit;
        }

        try {
            $result = $client->update($id, $data);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Client updated successfully']);
            } else {
                $error = $client->getLastError();
                error_log('Client update error: ' . $error);
                echo json_encode(['success' => false, 'message' => 'Failed to update client: ' . $error]);
            }
        } catch (Exception $e) {
            error_log('Client update exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred while updating the client']);
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? 0;
        $result = $client->delete($id);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Client deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $client->getLastError()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>