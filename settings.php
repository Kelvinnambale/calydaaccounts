<?php
// FILE: settings.php - User Settings Page
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', __DIR__);

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/constants.php';
require_once 'config/database.php';

// Get current user info
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
$message = '';
$messageType = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                if (password_verify($_POST['current_password'], $user['password'])) {
                    if ($_POST['new_password'] === $_POST['confirm_password']) {
                        $newPasswordHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        if ($stmt->execute([$newPasswordHash, $_SESSION['user_id']])) {
                            $message = 'Password changed successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update password. Please try again.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'New passwords do not match.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Current password is incorrect.';
                    $messageType = 'error';
                }
                break;
                
            case 'update_profile':
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$_POST['full_name'], $_POST['email'], $_SESSION['user_id']])) {
                    $_SESSION['full_name'] = $_POST['full_name'];
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = 'Failed to update profile. Please try again.';
                    $messageType = 'error';
                }
                break;
                
            case 'update_preferences':
                $preferences = json_encode([
                    'notifications' => isset($_POST['notifications']) ? 1 : 0,
                    'email_reports' => isset($_POST['email_reports']) ? 1 : 0,
                    'dashboard_theme' => $_POST['dashboard_theme'] ?? 'light',
                    'items_per_page' => (int)$_POST['items_per_page'] ?? 10
                ]);
                
                $stmt = $db->prepare("UPDATE users SET preferences = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$preferences, $_SESSION['user_id']])) {
                    $message = 'Preferences updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update preferences. Please try again.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get user preferences
$preferences = json_decode($user['preferences'] ?? '{}', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Calyda Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/style.css">
    <style>
        body { background-color: #f8f9fa; }
        .main-content { padding: 20px; }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }
        .settings-section {
            border-left: 4px solid #667eea;
            padding-left: 20px;
            margin-bottom: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
        
        .activity-item {
            padding: 15px;
            border-left: 3px solid #667eea;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-item i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                left: -250px;
                width: 250px;
                z-index: 1000;
                transition: left 0.3s;
            }
            .sidebar.show { left: 0; }
            .main-content { margin-left: 0; }
        }
            /* Quick Actions Button Styles */
.quick-actions-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    transition: all 0.3s ease;
    animation: pulse 2s infinite;
}

.quick-actions-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(0,0,0,0.2);
}

@keyframes pulse {
    0% { box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); }
    50% { box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6); }
    100% { box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); }
}

.quick-actions-menu {
    position: fixed;
    bottom: 100px;
    right: 30px;
    z-index: 999;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    padding: 15px;
    min-width: 250px;
    transform: translateY(20px);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.quick-actions-menu.show {
    transform: translateY(0);
    opacity: 1;
    visibility: visible;
}

.quick-action-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    margin: 5px 0;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}

.quick-action-item:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transform: translateX(5px);
}

.quick-action-item i {
    margin-right: 12px;
    width: 20px;
    font-size: 16px;
}

.quick-action-item span {
    font-size: 14px;
    font-weight: 500;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .quick-actions-btn {
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
    }
    
    .quick-actions-menu {
        bottom: 80px;
        right: 20px;
        min-width: 200px;
    }
}
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

            <div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
                <div class="sidebar-header">
                    <button class="sidebar-close" onclick="closeSidebar()">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <div class="logo-container">
                        <div class="logo-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <div class="logo-text">Calyda Accounts</div>
                            <div class="company-tagline">Professional Tax Solutions</div>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <div class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="clients.php">
                            <i class="fas fa-users"></i>Client Management
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="vat_management.php">
                            <i class="fas fa-receipt"></i>VAT Management
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i>Reports
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link active" href="settings.php">
                            <i class="fas fa-cog"></i>Settings
                        </a>
                    </div>
                    <div class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </a>
                    </div>
                </nav>

                <div class="system-status">
                    <div class="uptime-display">
                        <div class="uptime-title">System Uptime</div>
                        <div class="uptime-value">
                            <div class="status-indicator"></div>
                            <span id="systemUptime">Loading...</span>
                        </div>
                    </div>

                    <div class="user-info d-flex align-items-center">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <div class="user-role">Tax Professional</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Mobile Menu Toggle -->
                <div class="d-md-none mb-3">
                    <button class="btn btn-primary" type="button" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i> Menu
                    </button>
                </div>

                <!-- Settings Header -->
                <div class="card">
                    <div class="settings-header">
                        <h2><i class="fas fa-cog me-2"></i>Settings</h2>
                        <p class="mb-0">Manage your account settings and preferences</p>
                    </div>
                </div>

                <!-- Display Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Account Statistics -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <i class="fas fa-user-clock"></i>
                        <h4>Account Age</h4>
                        <p><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></p>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-sign-in-alt"></i>
                        <h4>Last Login</h4>
                        <p><?php echo date('M j, Y g:i A', strtotime($user['last_login'] ?? $user['created_at'])); ?></p>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-shield-alt"></i>
                        <h4>Account Status</h4>
                        <p><span class="badge bg-success">Active</span></p>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-key"></i>
                        <h4>Password</h4>
                        <p><span class="badge bg-info">Secure</span></p>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                            
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('currentPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" id="newPassword" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('newPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="generateSecurePassword()">
                                            <i class="fas fa-random"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength mt-2" id="passwordStrength"></div>
                                    <small class="text-muted">Password must be at least 8 characters long</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirmPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="mt-2"></div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>

                
                <!-- Data Management -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-database me-2"></i>Data Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <h6>Export Data</h6>
                                <p class="text-muted">Download all your client data</p>
                                <button class="btn btn-outline-primary" onclick="openExportModal()">
                                    <i class="fas fa-download me-2"></i>Export CSV
                                </button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <h6>Backup Settings</h6>
                                <p class="text-muted">Create a backup of your settings</p>
                                <button class="btn btn-outline-info" onclick="backupSettings()">
                                    <i class="fas fa-cloud-download-alt me-2"></i>Backup
                                </button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <h6>Clear Cache</h6>
                                <p class="text-muted">Clear system cache and temporary files</p>
                                <button class="btn btn-outline-warning" onclick="clearCache()">
                                    <i class="fas fa-broom me-2"></i>Clear Cache
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6>Reset All Settings</h6>
                                <p class="text-muted">Reset all preferences to default values</p>
                                <button class="btn btn-outline-danger" onclick="resetSettings()">
                                    <i class="fas fa-undo me-2"></i>Reset Settings
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6>Delete Account</h6>
                                <p class="text-muted">Permanently delete your account and all data</p>
                                <button class="btn btn-danger" onclick="deleteAccount()">
                                    <i class="fas fa-trash me-2"></i>Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="./js/script.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('show');
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function generateSecurePassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*';
            let password = '';
            
            // Ensure at least one of each type
            password += 'ABCDEFGHJKLMNPQRSTUVWXYZ'.charAt(Math.floor(Math.random() * 25));
            password += 'abcdefghijkmnpqrstuvwxyz'.charAt(Math.floor(Math.random() * 25));
            password += '23456789'.charAt(Math.floor(Math.random() * 8));
            password += '!@#$%&*'.charAt(Math.floor(Math.random() * 7));
            
            // Fill the rest randomly
            for (let i = 4; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            // Shuffle the password
            password = password.split('').sort(() => Math.random() - 0.5).join('');
            
            document.getElementById('newPassword').value = password;
            checkPasswordStrength(password);
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.style.width = (strength * 20) + '%';
            
            if (strength < 3) {
                strengthBar.className = 'password-strength strength-weak';
            } else if (strength < 5) {
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'password-strength strength-strong';
            }
        }

        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check"></i> Passwords match</small>';
                } else {
                    matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times"></i> Passwords do not match</small>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        }

        // Event listeners
        document.getElementById('newPassword').addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });

        document.getElementById('confirmPassword').addEventListener('input', checkPasswordMatch);

        // Data management functions
        let filterOptions = {};

// Initialize export modal
function initializeExportModal() {
    // Load filter options
    fetch('ajax/export_handler.php?action=get_filter_options')
        .then(response => response.json())
        .then(data => {
            filterOptions = data;
            populateFilterDropdowns();
        })
        .catch(error => {
            console.error('Error loading filter options:', error);
            showToast('Error loading filter options', 'error');
        });
    
    // Add event listeners for export type changes
    document.querySelectorAll('input[name="export_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            toggleFilterSections(this.value);
            updateExportPreview();
        });
    });
    
    // Add change listeners to all filter inputs
    document.querySelectorAll('#exportForm input, #exportForm select').forEach(input => {
        input.addEventListener('change', updateExportPreview);
    });
}

function populateFilterDropdowns() {
    // Populate client types
    const clientTypeSelects = ['clientType', 'combinedClientType'];
    clientTypeSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            filterOptions.client_types.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = type;
                select.appendChild(option);
            });
        }
    });
    
    // Populate counties
    const countySelects = ['county', 'combinedCounty'];
    countySelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            filterOptions.counties.forEach(county => {
                const option = document.createElement('option');
                option.value = county;
                option.textContent = county;
                select.appendChild(option);
            });
        }
    });
    
    // Populate ETIMS statuses
    const etimsSelect = document.getElementById('etimsStatus');
    if (etimsSelect) {
        filterOptions.etims_statuses.forEach(status => {
            const option = document.createElement('option');
            option.value = status;
            option.textContent = status;
            etimsSelect.appendChild(option);
        });
    }
    
    // Populate VAT clients
    const vatClientSelect = document.getElementById('vatClient');
    if (vatClientSelect) {
        filterOptions.vat_clients.forEach(client => {
            const option = document.createElement('option');
            option.value = client.id;
            option.textContent = client.full_name;
            vatClientSelect.appendChild(option);
        });
    }
    
    // Populate years
    const yearSelect = document.getElementById('recordYear');
    if (yearSelect) {
        filterOptions.years.forEach(year => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            yearSelect.appendChild(option);
        });
    }
}

function toggleFilterSections(exportType) {
    const clientFilters = document.getElementById('clientFilters');
    const vatFilters = document.getElementById('vatFilters');
    const combinedFilters = document.getElementById('combinedFilters');
    
    // Hide all sections first
    clientFilters.style.display = 'none';
    vatFilters.style.display = 'none';
    combinedFilters.style.display = 'none';
    
    // Show relevant sections
    switch(exportType) {
        case 'clients':
            clientFilters.style.display = 'block';
            break;
        case 'vat_records':
            vatFilters.style.display = 'block';
            break;
        case 'client_with_vat':
            combinedFilters.style.display = 'block';
            break;
    }
}

function updateExportPreview() {
    const exportType = document.querySelector('input[name="export_type"]:checked').value;
    const preview = document.getElementById('exportPreview');
    
    let previewText = '';
    
    switch(exportType) {
        case 'clients':
            previewText = '<strong>Clients Export:</strong> Basic client information including name, KRA PIN, contact details, and registration info.';
            break;
        case 'vat_records':
            previewText = '<strong>VAT Records Export:</strong> Detailed VAT records with client information, monthly returns, and VAT calculations.';
            break;
        case 'client_with_vat':
            previewText = '<strong>Combined Report:</strong> Client information with VAT summaries, totals, and latest record dates.';
            break;
    }
    
    // Add filter summary
    const activeFilters = getActiveFilters();
    if (activeFilters.length > 0) {
        previewText += '<br><small class="text-muted">Active filters: ' + activeFilters.join(', ') + '</small>';
    }
    
    preview.innerHTML = previewText;
}

function getActiveFilters() {
    const filters = [];
    const exportType = document.querySelector('input[name="export_type"]:checked').value;
    
    // Check relevant filters based on export type
    if (exportType === 'clients') {
        const clientType = document.getElementById('clientType').value;
        const county = document.getElementById('county').value;
        const etimsStatus = document.getElementById('etimsStatus').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        const hasVat = document.getElementById('hasVat').value;
        
        if (clientType) filters.push(`Client Type: ${clientType}`);
        if (county) filters.push(`County: ${county}`);
        if (etimsStatus) filters.push(`ETIMS: ${etimsStatus}`);
        if (dateFrom) filters.push(`From: ${dateFrom}`);
        if (dateTo) filters.push(`To: ${dateTo}`);
        if (hasVat) filters.push(`VAT: ${hasVat === 'yes' ? 'Only' : 'Excluded'}`);
    } else if (exportType === 'vat_records') {
        const clientId = document.getElementById('vatClient').value;
        const year = document.getElementById('recordYear').value;
        const month = document.getElementById('recordMonth').value;
        
        if (clientId) {
            const clientName = document.getElementById('vatClient').options[document.getElementById('vatClient').selectedIndex].text;
            filters.push(`Client: ${clientName}`);
        }
        if (year) filters.push(`Year: ${year}`);
        if (month) filters.push(`Month: ${document.getElementById('recordMonth').options[document.getElementById('recordMonth').selectedIndex].text}`);
    } else if (exportType === 'client_with_vat') {
        const county = document.getElementById('combinedCounty').value;
        const clientType = document.getElementById('combinedClientType').value;
        const hasRecords = document.getElementById('hasRecords').value;
        
        if (county) filters.push(`County: ${county}`);
        if (clientType) filters.push(`Type: ${clientType}`);
        if (hasRecords) filters.push(`Records: ${hasRecords === 'yes' ? 'With' : 'Without'}`);
    }
    
    return filters;
}

function processExport() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    
    // Show loading state
    const exportBtn = document.querySelector('#exportModal .btn-primary');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';
    exportBtn.disabled = true;
    
    // Create a temporary form for submission
    const tempForm = document.createElement('form');
    tempForm.method = 'POST';
    tempForm.action = 'ajax/export_handler.php';
    tempForm.style.display = 'none';
    
    // Add all form data to temp form
    for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        tempForm.appendChild(input);
    }
    
    document.body.appendChild(tempForm);
    tempForm.submit();
    document.body.removeChild(tempForm);
    
    // Reset button after a delay
    setTimeout(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
        showToast('Export completed successfully!', 'success');
    }, 2000);
}

// Initialize when modal is shown
document.getElementById('exportModal').addEventListener('shown.bs.modal', function () {
    initializeExportModal();
});

// Function to open export modal (called from your existing code)
function openExportModal() {
    new bootstrap.Modal(document.getElementById('exportModal')).show();
}

        function backupSettings() {
            if (confirm('Create a backup of your current settings?')) {
                showToast('Settings backup created successfully!', 'success');
            }
        }

        function clearCache() {
            if (confirm('Clear system cache? This may temporarily slow down the system.')) {
                showToast('Cache cleared successfully!', 'success');
            }
        }

        function resetSettings() {
            if (confirm('Reset all settings to default values? This action cannot be undone.')) {
                showToast('Settings reset to default values!', 'success');
                setTimeout(() => location.reload(), 1500);
            }
        }

        function deleteAccount() {
            const confirmation = prompt('Type "DELETE" to confirm account deletion:');
            if (confirmation === 'DELETE') {
                if (confirm('Are you absolutely sure? This will permanently delete your account and all data.')) {
                    showToast('Account deletion initiated. You will be logged out shortly.', 'error');
                    setTimeout(() => window.location.href = 'logout.php', 3000);
                }
            }
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            let alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            
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
    <script>
let quickActionsOpen = false;

function toggleQuickActions() {
    const menu = document.getElementById('quickActionsMenu');
    const icon = document.getElementById('quickActionsIcon');
    
    if (quickActionsOpen) {
        menu.classList.remove('show');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-plus');
        quickActionsOpen = false;
    } else {
        menu.classList.add('show');
        icon.classList.remove('fa-plus');
        icon.classList.add('fa-times');
        quickActionsOpen = true;
    }
}

// Close menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('quickActionsMenu');
    const btn = document.getElementById('quickActionsBtn');
    
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
        if (quickActionsOpen) {
            toggleQuickActions();
        }
    }
});

// Quick Action Functions
function openAddClientModal() {
    toggleQuickActions();
    new bootstrap.Modal(document.getElementById('addClientModal')).show();
}

function openVATManagement() {
    toggleQuickActions();
    window.location.href = 'vat_management.php';
}

function generateReport() {
    toggleQuickActions();
    window.location.href = './reports.php';
}

function bulkImport() {
    toggleQuickActions();
    // Add bulk import functionality
    showToast('Bulk import feature coming soon!', 'info');
}

function exportData() {
    toggleQuickActions();
    // Add export functionality
    const currentDate = new Date().toISOString().split('T')[0];
    const filename = `clients_export_${currentDate}.csv`;
    
    // Create CSV content
    let csvContent = "Full Name,KRA PIN,Phone,Email,Client Type,County,ETIMS Status,Registration Date\n";
    
    // You can get the clients data from your PHP and add to CSV
    // For now, showing a placeholder
    showToast('Export functionality will be implemented based on your data structure', 'info');
}

function openSettings() {
    toggleQuickActions();
    window.location.href = '../settings.php';
}

// Update the existing showToast function to handle 'info' type
function showToast(message, type) {
    const toast = document.createElement('div');
    let alertClass = 'alert-primary';
    
    switch(type) {
        case 'success':
            alertClass = 'alert-success';
            break;
        case 'error':
            alertClass = 'alert-danger';
            break;
        case 'info':
            alertClass = 'alert-info';
            break;
        default:
            alertClass = 'alert-primary';
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
<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-download me-2"></i>Export Data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" method="POST">
                    <!-- Export Type Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Export Type</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_type" id="export_clients" value="clients" checked>
                                    <label class="form-check-label" for="export_clients">
                                        <i class="fas fa-users text-primary me-1"></i>
                                        <strong>Clients Only</strong>
                                        <small class="d-block text-muted">Basic client information</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_type" id="export_vat" value="vat_records">
                                    <label class="form-check-label" for="export_vat">
                                        <i class="fas fa-receipt text-success me-1"></i>
                                        <strong>VAT Records</strong>
                                        <small class="d-block text-muted">VAT records with client info</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_type" id="export_combined" value="client_with_vat">
                                    <label class="form-check-label" for="export_combined">
                                        <i class="fas fa-chart-line text-warning me-1"></i>
                                        <strong>Combined Report</strong>
                                        <small class="d-block text-muted">Clients with VAT summaries</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Client Filters -->
                    <div id="clientFilters" class="filter-section">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-filter me-2"></i>Client Filters
                        </h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Client Type</label>
                                <select class="form-select" name="filters[client_type]" id="clientType">
                                    <option value="">All Types</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">County</label>
                                <select class="form-select" name="filters[county]" id="county">
                                    <option value="">All Counties</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ETIMS Status</label>
                                <select class="form-select" name="filters[etims_status]" id="etimsStatus">
                                    <option value="">All Statuses</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Registration Date From</label>
                                <input type="date" class="form-control" name="filters[date_from]" id="dateFrom">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Registration Date To</label>
                                <input type="date" class="form-control" name="filters[date_to]" id="dateTo">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Has VAT Obligation</label>
                                <select class="form-select" name="filters[has_vat]" id="hasVat">
                                    <option value="">All Clients</option>
                                    <option value="yes">VAT Clients Only</option>
                                    <option value="no">Non-VAT Clients Only</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- VAT Filters -->
                    <div id="vatFilters" class="filter-section" style="display: none;">
                        <h6 class="text-success mb-3">
                            <i class="fas fa-receipt me-2"></i>VAT Record Filters
                        </h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Select Client</label>
                                <select class="form-select" name="filters[client_id]" id="vatClient">
                                    <option value="">All VAT Clients</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Record Year</label>
                                <select class="form-select" name="filters[record_year]" id="recordYear">
                                    <option value="">All Years</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Record Month</label>
                                <select class="form-select" name="filters[record_month]" id="recordMonth">
                                    <option value="">All Months</option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Record Date From</label>
                                <input type="date" class="form-control" name="filters[date_from]" id="vatDateFrom">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Record Date To</label>
                                <input type="date" class="form-control" name="filters[date_to]" id="vatDateTo">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min Sales Amount</label>
                                <input type="number" class="form-control" name="filters[min_amount]" id="minAmount" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Sales Amount</label>
                                <input type="number" class="form-control" name="filters[max_amount]" id="maxAmount" min="0" step="0.01">
                            </div>
                        </div>
                    </div>

                    <!-- Combined Report Filters -->
                    <div id="combinedFilters" class="filter-section" style="display: none;">
                        <h6 class="text-warning mb-3">
                            <i class="fas fa-chart-line me-2"></i>Combined Report Filters
                        </h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">County</label>
                                <select class="form-select" name="filters[county]" id="combinedCounty">
                                    <option value="">All Counties</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Client Type</label>
                                <select class="form-select" name="filters[client_type]" id="combinedClientType">
                                    <option value="">All Types</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Has VAT Records</label>
                                <select class="form-select" name="filters[has_records]" id="hasRecords">
                                    <option value="">All Clients</option>
                                    <option value="yes">With VAT Records</option>
                                    <option value="no">Without VAT Records</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Export Preview -->
                    <div class="mt-4">
                        <h6 class="text-info mb-3">
                            <i class="fas fa-eye me-2"></i>Export Preview
                        </h6>
                        <div class="alert alert-info">
                            <div id="exportPreview">
                                <strong>Ready to export:</strong> Client data with selected filters
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="processExport()">
                    <i class="fas fa-download me-2"></i>Export CSV
                </button>
            </div>
        </div>
    </div>
</div>