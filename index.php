<?php
// This is a comprehensive implementation of the Calyda Accounts Internal Record Management System
// The system includes all files mentioned in the requirements document

// ============================================
// ROOT DIRECTORY FILES
// ============================================

// FILE: index.php - Cover Page with Login Panel
?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
define('SYSTEM_ACCESS', true);
define('ROOT_PATH', __DIR__);
require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'classes/User.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = new User();
    $loginSuccess = $user->login($username, $password);

    if ($loginSuccess) {
        $currentUser = $user->getCurrentUser();
        $_SESSION['user_id'] = $currentUser['id'];
        $_SESSION['username'] = $currentUser['username'];
        $_SESSION['full_name'] = $currentUser['full_name'];
        $_SESSION['user_role'] = $currentUser['role'];
        $_SESSION['login_time'] = time();

        $message = 'Login successful! Redirecting...';
        $messageType = 'success';

        header("Refresh:1.5; url=dashboard.php");
    } else {
        $message = $user->getLastError();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calyda Accounts - Internal Record Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            height: 600px;
            max-width: 1200px;
        }
        .brand-section {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 2rem;
        }
        .login-section {
            padding: 2rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
        }
        .form-control {
            border-radius: 25px;
            padding: 12px 20px;
            border: 2px solid #e9ecef;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="login-container row g-0">
                    <!-- Brand Section -->
                    <div class="col-md-6 brand-section d-flex flex-column justify-content-center">
                        <div class="text-center">
                            <i class="fas fa-building fa-4x mb-4"></i>
                            <h2 class="fw-bold mb-3">ACCOUNTS</h2>
                            <h5 class="mb-4">Internal Record Management System</h5>
                            <p class="mb-0">Streamlined client registration and VAT record management for accounting professionals</p>
                            
                        </div>
                    </div>
                    
                    <!-- Login Section -->
                    <div class="col-md-6 login-section">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold text-primary">Welcome Back</h3>
                            <p class="text-muted">Please sign in to your account</p>
                        </div>
                        
                        <div id="alert-container">
                            <?php if ($message): ?>
                                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <form id="loginForm" method="POST" action="index.php">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username/Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" id="username" name="username" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control border-start-0" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-login text-white w-100 fw-bold">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">© 2025 Accounts. All rights reserved.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
