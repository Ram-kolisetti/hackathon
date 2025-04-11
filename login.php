<?php
// Login page for the Hospital Management Platform
session_start();

// Define base path for includes
define('BASE_PATH', __DIR__);

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to appropriate dashboard based on role
    switch ($_SESSION['role']) {
        case 'patient':
            header('Location: patient/dashboard.php');
            break;
        case 'doctor':
            header('Location: doctor/dashboard.php');
            break;
        case 'hospital_admin':
            header('Location: admin/hospital/dashboard.php');
            break;
        case 'super_admin':
            header('Location: admin/super/dashboard.php');
            break;
        default:
            // If role is not recognized, destroy session
            session_destroy();
    }
    exit;
}

$error = '';
$success = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password']; // Don't sanitize password before verification
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username/email and password';
        } else {
            $result = loginUser($username, $password);
            
            if ($result['status']) {
                // Start user session
                startUserSession($result['user']);
                
                // Redirect to appropriate dashboard
                switch ($result['user']['role_name']) {
                    case 'patient':
                        header('Location: patient/dashboard.php');
                        break;
                    case 'doctor':
                        header('Location: doctor/dashboard.php');
                        break;
                    case 'hospital_admin':
                        header('Location: admin/hospital/dashboard.php');
                        break;
                    case 'super_admin':
                        header('Location: admin/super/dashboard.php');
                        break;
                }
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .role-selector {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .role-selector .role-btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            border: 2px solid var(--primary-color);
            border-radius: 5px;
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .role-selector .role-btn.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .role-selector .role-btn i {
            margin-right: 8px;
        }
        
        .role-info {
            display: none;
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            background-color: #f8f9fa;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .role-info.show {
            display: block;
        }
    </style>
</head>
<body class="login-body">
    <div class="container">
        <div class="info-side">
            <div class="info-content">
                <h1>Welcome to MedConnect</h1>
                <p>Your comprehensive healthcare management solution</p>
                
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-user-md"></i>
                        <span>Doctors: Manage your appointments and patient records</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-hospital-user"></i>
                        <span>Patients: Book appointments and access medical history</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-hospital"></i>
                        <span>Administrators: Oversee hospital operations</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-side">
            <div class="login-header">
                <h2>Login to Your Account</h2>
                <p>Please select your role and enter your credentials</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="role-selector">
                <div class="role-btn" data-role="doctor">
                    <i class="fas fa-user-md"></i>Doctor
                </div>
                <div class="role-btn" data-role="admin">
                    <i class="fas fa-user-shield"></i>Admin
                </div>
            </div>
            
            <div class="role-info" id="doctor-info">
                For doctors: Use your registered email/username and password
            </div>
            <div class="role-info" id="admin-info">
                For administrators: Use your admin credentials provided by the system
            </div>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Username or Email
                    </label>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Enter your username or email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="login" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleButtons = document.querySelectorAll('.role-btn');
        const doctorInfo = document.getElementById('doctor-info');
        const adminInfo = document.getElementById('admin-info');
        
        roleButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                roleButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Show appropriate info
                if (this.dataset.role === 'doctor') {
                    doctorInfo.classList.add('show');
                    adminInfo.classList.remove('show');
                } else {
                    adminInfo.classList.add('show');
                    doctorInfo.classList.remove('show');
                }
            });
        });
    });
    </script>
</body>
</html>