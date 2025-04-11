<?php
// Payment Confirmation Page
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is logged in and is a patient
if (!isLoggedIn() || !hasRole('patient')) {
    header('Location: ../login.php');
    exit;
}

// Get patient information
$user_id = $_SESSION['user_id'];
$patient_id = $_SESSION['profile_id'];

// Check if transaction ID and appointment ID are provided
if (!isset($_GET['transaction_id']) || !isset($_GET['appointment_id'])) {
    header('Location: appointments.php?error=Invalid payment confirmation');
    exit;
}

$transaction_id = sanitizeInput($_GET['transaction_id']);
$appointment_id = (int)$_GET['appointment_id'];

// Get appointment and payment details
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        dp.consultation_fee,
        h.name as hospital_name,
        d.name as department_name,
        p.amount,
        p.payment_date,
        p.payment_method,
        p.transaction_id
        FROM appointments a
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users u ON dp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        JOIN payments p ON a.appointment_id = p.appointment_id
        WHERE a.appointment_id = ? AND a.patient_id = ? AND p.transaction_id = ?";

$details = fetchRow($sql, "iis", [$appointment_id, $patient_id, $transaction_id]);

// If details not found or don't match
if (!$details) {
    header('Location: appointments.php?error=Payment confirmation details not found');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-lg);
        }

        .confirmation-header {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--gray-200);
        }

        .confirmation-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: var(--spacing-md);
        }

        .confirmation-title {
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }

        .confirmation-details {
            margin-top: var(--spacing-lg);
        }

        .detail-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--gray-200);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-bottom: 4px;
        }

        .detail-value {
            font-weight: 500;
            color: var(--gray-900);
        }

        .confirmation-actions {
            margin-top: var(--spacing-xl);
            text-align: center;
        }

        @media (max-width: 768px) {
            .detail-group {
                grid-template-columns: 1fr;
            }
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
                <a href="book_appointment.php" class="sidebar-link">
                    <i class="fas fa-plus-circle"></i> Book Appointment
                </a>
            </li>
            <li class="sidebar-item">
                <a href="medical_history.php" class="sidebar-link">
                    <i class="fas fa-file-medical"></i> Medical History
                </a>
            </li>
            <li class="sidebar-item">
                <a href="hospitals.php" class="sidebar-link">
                    <i class="fas fa-hospital"></i> Hospitals
                </a>
            </li>
            <li class="sidebar-item">
                <a href="doctors.php" class="sidebar-link">
                    <i class="fas fa-user-md"></i> Doctors
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
            <div class="confirmation-container">
                <div class="confirmation-header">
                    <div class="confirmation-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="confirmation-title">Payment Successful!</h1>
                    <p>Your appointment has been confirmed and payment has been processed successfully.</p>
                </div>
                
                <div class="confirmation-details">
                    <div class="detail-group">
                        <div class="detail-item">
                            <span class="detail-label">Transaction ID</span>
                            <span class="detail-value"><?php echo $details['transaction_id']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Payment Date</span>
                            <span class="detail-value"><?php echo date('d M Y, h:i A', strtotime($details['payment_date'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-item">
                            <span class="detail-label">Hospital</span>
                            <span class="detail-value"><?php echo $details['hospital_name']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Department</span>
                            <span class="detail-value"><?php echo $details['department_name']; ?></span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-item">
                            <span class="detail-label">Doctor</span>
                            <span class="detail-value"><?php echo $details['doctor_name']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Appointment Date & Time</span>
                            <span class="detail-value">
                                <?php echo date('d M Y', strtotime($details['appointment_date'])) . ' at ' . 
                                      date('h:i A', strtotime($details['appointment_time'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-item">
                            <span class="detail-label">Payment Method</span>
                            <span class="detail-value"><?php echo ucfirst($details['payment_method']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Amount Paid</span>
                            <span class="detail-value">₹<?php echo number_format($details['amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="confirmation-actions">
                    <a href="appointments.php" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> View My Appointments
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>