<?php
// Payment History Page
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include required files
require_once(BASE_PATH . '/includes/auth.php');
require_once(BASE_PATH . '/includes/payment_functions.php');

// Check if user is logged in and is a patient
if (!isLoggedIn() || !hasRole('patient')) {
    header('Location: ../login.php');
    exit;
}

// Get patient information
$user_id = $_SESSION['user_id'];
$patient_id = $_SESSION['profile_id'];

// Get payment history
$payments = getPaymentHistory($patient_id, 20);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-history-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .payment-item {
            border-left: 4px solid var(--primary-color);
            padding: var(--spacing-md);
            background-color: var(--gray-100);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-md);
            transition: transform var(--transition-normal);
        }
        
        .payment-item:hover {
            transform: translateY(-2px);
        }
        
        .payment-item.completed {
            border-left-color: var(--success-color);
        }
        
        .payment-item.refunded {
            border-left-color: var(--warning-color);
        }
        
        .payment-item.failed {
            border-left-color: var(--error-color);
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .payment-amount {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .payment-date {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }
        
        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .detail-group {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-bottom: var(--spacing-xs);
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .payment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius-full);
            font-size: var(--font-size-sm);
            font-weight: 500;
        }
        
        .status-completed {
            background-color: var(--success-color-light);
            color: var(--success-color-dark);
        }
        
        .status-refunded {
            background-color: var(--warning-color-light);
            color: var(--warning-color-dark);
        }
        
        .status-failed {
            background-color: var(--error-color-light);
            color: var(--error-color-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--spacing-xl);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-400);
            margin-bottom: var(--spacing-md);
        }
        
        .empty-state p {
            color: var(--gray-600);
            margin-bottom: var(--spacing-md);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">MedConnect</a>
        </div>
        
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a href="appointments.php" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="sidebar-item">
                <a href="payment_history.php" class="sidebar-link active">
                    <i class="fas fa-credit-card"></i> Payments
                </a>
            </li>
            <li class="sidebar-item">
                <a href="medical_history.php" class="sidebar-link">
                    <i class="fas fa-file-medical"></i> Medical History
                </a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php" class="sidebar-link">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </li>
            <li class="sidebar-divider"></li>
            <li class="sidebar-item">
                <a href="../logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <div class="content-wrapper">
        <nav class="navbar">
            <button id="sidebarToggle" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-nav ml-auto">
                <div class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['first_name']; ?>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container">
            <div class="payment-history-container">
                <h2 class="mb-4">Payment History</h2>
                
                <?php if (count($payments) > 0): ?>
                    <div class="payment-list">
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-item <?php echo $payment['status']; ?>">
                                <div class="payment-header">
                                    <div class="payment-amount">
                                        <?php echo formatCurrency($payment['amount']); ?>
                                    </div>
                                    <div class="payment-date">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?>
                                    </div>
                                </div>
                                
                                <div class="payment-details">
                                    <div class="detail-group">
                                        <span class="detail-label">Transaction ID</span>
                                        <span class="detail-value"><?php echo $payment['transaction_id']; ?></span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <span class="detail-label">Payment Method</span>
                                        <span class="detail-value">
                                            <?php
                                            $method_icons = [
                                                'upi' => 'fas fa-mobile-alt',
                                                'card' => 'fas fa-credit-card',
                                                'net_banking' => 'fas fa-university'
                                            ];
                                            $icon = $method_icons[$payment['payment_method']] ?? 'fas fa-money-bill';
                                            echo "<i class='{$icon}'></i> " . ucfirst($payment['payment_method']);
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <span class="detail-label">Status</span>
                                        <span class="payment-status status-<?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <span class="detail-label">Doctor</span>
                                        <span class="detail-value"><?php echo $payment['doctor_name']; ?></span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <span class="detail-label">Hospital</span>
                                        <span class="detail-value"><?php echo $payment['hospital_name']; ?></span>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <span class="detail-label">Appointment</span>
                                        <span class="detail-value">
                                            <?php
                                            echo date('d M Y', strtotime($payment['appointment_date'])) . ' at ' .
                                                 date('h:i A', strtotime($payment['appointment_time']));
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <p>No payment records found.</p>
                        <a href="appointments.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book an Appointment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>