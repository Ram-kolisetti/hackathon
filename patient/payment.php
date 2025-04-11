<?php
// Payment Page
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

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: appointments.php?error=Invalid appointment ID');
    exit;
}

$appointment_id = (int)$_GET['id'];

// Get appointment details
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        dp.consultation_fee,
        h.name as hospital_name,
        d.name as department_name
        FROM appointments a
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users u ON dp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.appointment_id = ? AND a.patient_id = ?";

$appointment = fetchRow($sql, "ii", [$appointment_id, $patient_id]);

// If appointment not found or doesn't belong to this patient
if (!$appointment) {
    header('Location: appointments.php?error=Appointment not found or unauthorized access');
    exit;
}

// Check if payment is already completed
if ($appointment['payment_status'] == 'completed') {
    header('Location: appointment_details.php?id=' . $appointment_id . '&success=Payment already completed');
    exit;
}

$success = '';
$error = '';

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $payment_method = sanitizeInput($_POST['payment_method']);
    $transaction_id = 'TXN' . time() . rand(1000, 9999); // Generate a random transaction ID
    
    // In a real application, you would integrate with a payment gateway here
    // For this demo, we'll simulate a successful payment
    
    // Insert payment record
    $sql = "INSERT INTO payments (appointment_id, patient_id, amount, payment_method, transaction_id, status) 
            VALUES (?, ?, ?, ?, ?, 'completed')";
    $payment_id = insertData($sql, "iidsss", [
        $appointment_id, $patient_id, $appointment['consultation_fee'], $payment_method, $transaction_id
    ]);
    
    if ($payment_id) {
        // Update appointment payment status
        $sql = "UPDATE appointments SET payment_status = 'completed' WHERE appointment_id = ?";
        $result = executeNonQuery($sql, "i", [$appointment_id]);
        
        if ($result) {
            // Create notification for patient
            $sql = "INSERT INTO notifications (user_id, title, message) 
                    VALUES (?, 'Payment Successful', 'Your payment of ₹" . $appointment['consultation_fee'] . " for appointment on " . 
                    date('d M Y', strtotime($appointment['appointment_date'])) . " has been completed successfully.')";
            insertData($sql, "i", [$user_id]);
            
            // Get doctor's user_id
            $sql = "SELECT user_id FROM doctor_profiles WHERE doctor_id = ?";
            $doctor = fetchRow($sql, "i", [$appointment['doctor_id']]);
            
            if ($doctor) {
                // Create notification for doctor
                $sql = "INSERT INTO notifications (user_id, title, message) 
                        VALUES (?, 'Payment Received', 'Payment for appointment on " . 
                        date('d M Y', strtotime($appointment['appointment_date'])) . " has been received.')";
                insertData($sql, "i", [$doctor['user_id']]);
            }
            
            $success = 'Payment completed successfully!';
            
            // Redirect to appointment details page after a short delay
            header("Refresh: 3; URL=appointment_details.php?id=$appointment_id&success=Payment completed successfully");
        } else {
            $error = 'Failed to update appointment status';
        }
    } else {
        $error = 'Failed to process payment';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .payment-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            transition: transform 0.3s ease;
        }
        
        .payment-step:hover {
            transform: translateY(-2px);
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--gray-300);
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .payment-method {
            flex: 1;
            min-width: 150px;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .payment-method:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .payment-method.selected {
            border-color: var(--primary-color);
            background-color: rgba(var(--primary-rgb), 0.05);
            transform: scale(1.02);
        }
        
        .payment-method-icon {
            font-size: 2rem;
            margin-bottom: var(--spacing-sm);
            color: var(--gray-600);
            transition: color 0.3s ease;
        }
        
        .payment-method:hover .payment-method-icon,
        .payment-method.selected .payment-method-icon {
            color: var(--primary-color);
        }
        
        .payment-success {
            animation: successFadeIn 0.5s ease-in-out;
        }
        
        @keyframes successFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .payment-header {
            margin-bottom: var(--spacing-lg);
            text-align: center;
        }
        
        .payment-header h1 {
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }
        
        .payment-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-xl);
            position: relative;
        }
        
        .payment-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--gray-300);
            z-index: 1;
        }
        
        .payment-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--gray-300);
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            transition: all var(--transition-normal);
        }
        
        .step-text {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            text-align: center;
            transition: all var(--transition-normal);
        }
        
        .payment-step.completed .step-number {
            background-color: var(--success-color);
            color: white;
        }
        
        .payment-step.completed .step-text {
            color: var(--success-color);
            font-weight: 600;
        }
        
        .payment-step.active .step-number {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .payment-step.active .step-text {
            color: var(--secondary-color);
            font-weight: 600;
        }
        
        .appointment-summary {
            background-color: var(--gray-100);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .summary-title {
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--primary-color);
        }
        
        .summary-item {
            display: flex;
            margin-bottom: var(--spacing-sm);
        }
        
        .summary-label {
            width: 150px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .summary-value {
            flex: 1;
        }
        
        .payment-amount {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--primary-color);
            text-align: center;
            margin: var(--spacing-lg) 0;
        }
        
        .payment-methods {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .payment-method {
            flex: 1;
            min-width: 150px;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-normal);
        }
        
        .payment-method:hover {
            border-color: var(--secondary-color);
        }
        
        .payment-method.selected {
            border-color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .payment-method-icon {
            font-size: 2rem;
            margin-bottom: var(--spacing-sm);
            color: var(--gray-600);
        }
        
        .payment-method.selected .payment-method-icon {
            color: var(--secondary-color);
        }
        
        .payment-method-name {
            font-weight: 500;
        }
        
        .payment-actions {
            text-align: center;
            margin-top: var(--spacing-lg);
        }
        
        .payment-success {
            text-align: center;
            padding: var(--spacing-lg);
        }
        
        .payment-success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: var(--spacing-md);
        }
        
        .payment-success-message {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: var(--spacing-md);
        }
        
        .payment-success-details {
            margin-bottom: var(--spacing-lg);
        }
        
        @media (max-width: 768px) {
            .payment-steps {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-md);
            }
            
            .payment-steps::before {
                display: none;
            }
            
            .payment-step {
                flex-direction: row;
                width: 100%;
            }
            
            .step-number {
                margin-bottom: 0;
                margin-right: var(--spacing-sm);
                width: 40px;
                height: 40px;
            }
            
            .payment-methods {
                flex-direction: column;
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
            <div class="payment-container">
                <div class="payment-header">
                    <h1>Payment</h1>
                    <p>Complete your payment to confirm your appointment</p>
                </div>
                
                <div class="payment-steps">
                    <div class="payment-step completed">
                        <div class="step-number">1</div>
                        <div class="step-text">Book Appointment</div>
                    </div>
                    <div class="payment-step active">
                        <div class="step-number">2</div>
                        <div class="step-text">Make Payment</div>
                    </div>
                    <div class="payment-step">
                        <div class="step-number">3</div>
                        <div class="step-text">Confirmation</div>
                    </div>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="payment-success">
                        <div class="payment-success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="payment-success-message">
                            <?php echo $success; ?>
                        </div>
                        <div class="payment-success-details">
                            <p>You will be redirected to the appointment details page shortly.</p>
                            <p>If you are not redirected automatically, <a href="appointment_details.php?id=<?php echo $appointment_id; ?>">click here</a>.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="appointment-summary">
                        <div class="summary-title">Appointment Summary</div>
                        <div class="summary-item">
                            <div class="summary-label">Doctor:</div>
                            <div class="summary-value"><?php echo $appointment['doctor_name']; ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Hospital:</div>
                            <div class="summary-value"><?php echo $appointment['hospital_name']; ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Department:</div>
                            <div class="summary-value"><?php echo $appointment['department_name']; ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Date & Time:</div>
                            <div class="summary-value">
                                <?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?> at 
                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-amount">
                        Total Amount: ₹<?php echo number_format($appointment['consultation_fee'], 2); ?>
                    </div>
                    
                    <form method="POST" action="" id="paymentForm">
                        <input type="hidden" name="payment_method" id="payment_method" value="">
                        
                        <div class="form-group">
                            <label class="form-label">Select Payment Method</label>
                            <div class="payment-methods">
                                <div class="payment-method" data-method="upi">
                                    <div class="payment-method-icon">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="payment-method-name">UPI</div>
                                </div>
                                <div class="payment-method" data-method="card">
                                    <div class="payment-method-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="payment-method-name">Credit/Debit Card</div>
                                </div>
                                <div class="payment-method" data-method="net_banking">
                                    <div class="payment-method-icon">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="payment-method-name">Net Banking</div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="upi_details" class="payment-details d-none">
                            <div class="form-group">
                                <label for="upi_id" class="form-label">UPI ID</label>
                                <input type="text" id="upi_id" class="form-control" placeholder="yourname@upi">
                            </div>
                        </div>
                        
                        <div id="card_details" class="payment-details d-none">
                            <div class="form-group">
                                <label for="card_number" class="form-label">Card Number</label>
                                <input type="text" id="card_number" class="form-control" placeholder="1234 5678 9012 3456">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expiry_date" class="form-label">Expiry Date</label>
                                    <input type="text" id="expiry_date" class="form-control" placeholder="MM/YY">
                                </div>
                                <div class="form-group">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <input type="text" id="cvv" class="form-control" placeholder="123">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="card_name" class="form-label">Name on Card</label>
                                <input type="text" id="card_name" class="form-control" placeholder="John Doe">
                            </div>
                        </div>
                        
                        <div id="netbanking_details" class="payment-details d-none">
                            <div class="form-group">
                                <label for="bank" class="form-label">Select Bank</label>
                                <select id="bank" class="form-select">
                                    <option value="" disabled selected>Select Bank</option>
                                    <option value="sbi">State Bank of India</option>
                                    <option value="hdfc">HDFC Bank</option>
                                    <option value="icici">ICICI Bank</option>
                                    <option value="axis">Axis Bank</option>
                                    <option value="kotak">Kotak Mahindra Bank</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="payment-actions">
                            <button type="submit" name="payment_method" id="payButton" class="btn btn-primary btn-lg" disabled>
                                <i class="fas fa-lock"></i> Pay ₹<?php echo number_format($appointment['consultation_fee'], 2); ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('.payment-method');
            const payButton = document.getElementById('payButton');
            const selectedPaymentMethod = document.getElementById('payment_method');
            const upiDetails = document.getElementById('upi_details');
            const cardDetails = document.getElementById('card_details');
            const netbankingDetails = document.getElementById('netbanking_details');
            
            // Payment method selection
            paymentMethods.forEach(function(method) {
                method.addEventListener('click', function() {
                    // Remove selected class from all methods
                    paymentMethods.forEach(function(m) {
                        m.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked method
                    this.classList.add('selected');
                    
                    // Get selected method
                    const methodType = this.getAttribute('data-method');
                    selectedPaymentMethod.value = methodType;
                    
                    // Show relevant payment details
                    upiDetails.classList.add('d-none');
                    cardDetails.classList.add('d-none');
                    netbankingDetails.classList.add('d-none');
                    
                    if (methodType === 'upi') {
                        upiDetails.classList.remove('d-none');
                    } else if (methodType === 'card') {
                        cardDetails.classList.remove('d-none');
                    } else if (methodType === 'net_banking') {
                        netbankingDetails.classList.remove('d-none');
                    }
                    
                    // Enable pay button
                    payButton.disabled = false;
                });
            });
            
            // Form submission
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    // In a real application, you would validate the payment details here
                    // For this demo, we'll just simulate a successful payment
                    
                    // Show loading state
                    payButton.disabled = true;
                    payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                });
            }
        });
    </script>
</body>
</html>