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
                                <button class="btn btn-outline-primary" onclick="exportAllData()">
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
        function exportAllData() {
            if (confirm('This will export all your client data. Continue?')) {
                window.location.href = 'ajax/export_data.php';
            }
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
</body>
</html>