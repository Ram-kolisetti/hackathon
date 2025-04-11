<?php
require_once('../config/database.php');
require_once('../includes/auth.php');

// Ensure user is logged in and is a patient
if (!isLoggedIn() || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit();
}

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$patient_id = $_SESSION['user_id'];

// Fetch appointment details with related information
$query = "SELECT 
            a.*, 
            h.name as hospital_name,
            d.name as department_name,
            CONCAT(doc.first_name, ' ', doc.last_name) as doctor_name,
            doc_profile.consultation_fee,
            doc_profile.specialization
          FROM appointments a
          JOIN hospitals h ON a.hospital_id = h.hospital_id
          JOIN departments d ON a.department_id = d.department_id
          JOIN users doc ON a.doctor_id = doc.user_id
          JOIN doctor_profiles doc_profile ON doc.user_id = doc_profile.user_id
          WHERE a.appointment_id = ? AND a.patient_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $appointment_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

// If appointment not found or doesn't belong to current patient
if (!$appointment) {
    header('Location: appointments.php');
    exit();
}

// Fetch medical record if exists
$medical_record = null;
if ($appointment['status'] == 'completed') {
    $query = "SELECT * FROM medical_records WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_record = $result->fetch_assoc();
}

// Fetch payment details if exists
$payment = null;
$query = "SELECT * FROM payments WHERE appointment_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - MedConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">MedConnect</a>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="appointments.php" class="sidebar-link active">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="book_appointment.php" class="sidebar-link">
                <i class="fas fa-plus-circle"></i> Book Appointment
            </a>
            <a href="medical_history.php" class="sidebar-link">
                <i class="fas fa-file-medical"></i> Medical History
            </a>
            <a href="profile.php" class="sidebar-link">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="../logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Navbar -->
        <nav class="navbar">
            <button id="sidebarToggle" class="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-user">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                </a>
            </div>
        </nav>

        <!-- Content -->
        <div class="content">
            <div class="container">
                <h2 class="page-title">Appointment Details</h2>

                <div class="card">
                    <div class="card-body">
                        <div class="appointment-details">
                            <h3>Appointment Information</h3>
                            <div class="detail-row">
                                <span class="label">Hospital:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['hospital_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Department:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['department_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Doctor:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['doctor_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Specialization:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['specialization']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Date:</span>
                                <span class="value"><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Time:</span>
                                <span class="value"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Status:</span>
                                <span class="value status-badge <?php echo strtolower($appointment['status']); ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Consultation Fee:</span>
                                <span class="value">$<?php echo number_format($appointment['consultation_fee'], 2); ?></span>
                            </div>
                            <?php if ($appointment['symptoms']): ?>
                            <div class="detail-row">
                                <span class="label">Symptoms:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['symptoms']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($medical_record): ?>
                        <div class="medical-record-section mt-4">
                            <h3>Medical Record</h3>
                            <div class="detail-row">
                                <span class="label">Diagnosis:</span>
                                <span class="value"><?php echo htmlspecialchars($medical_record['diagnosis']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Prescription:</span>
                                <span class="value"><?php echo nl2br(htmlspecialchars($medical_record['prescription'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Notes:</span>
                                <span class="value"><?php echo nl2br(htmlspecialchars($medical_record['notes'])); ?></span>
                            </div>
                            <?php if ($medical_record['follow_up_date']): ?>
                            <div class="detail-row">
                                <span class="label">Follow-up Date:</span>
                                <span class="value"><?php echo date('F j, Y', strtotime($medical_record['follow_up_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($payment): ?>
                        <div class="payment-section mt-4">
                            <h3>Payment Information</h3>
                            <div class="detail-row">
                                <span class="label">Amount:</span>
                                <span class="value">$<?php echo number_format($payment['amount'], 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Payment Method:</span>
                                <span class="value"><?php echo ucfirst($payment['payment_method']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Status:</span>
                                <span class="value status-badge <?php echo strtolower($payment['status']); ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Transaction ID:</span>
                                <span class="value"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                            </div>
                        </div>
                        <?php elseif ($appointment['status'] != 'cancelled'): ?>
                        <div class="payment-section mt-4">
                            <h3>Payment Required</h3>
                            <p>Please complete the payment to confirm your appointment.</p>
                            <a href="payment.php?id=<?php echo $appointment_id; ?>" class="btn btn-primary">Make Payment</a>
                        </div>
                        <?php endif; ?>

                        <div class="actions mt-4">
                            <a href="appointments.php" class="btn btn-secondary">Back to Appointments</a>
                            <?php if ($appointment['status'] == 'scheduled'): ?>
                                <a href="appointments.php?id=<?php echo $appointment_id; ?>&action=cancel" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                    Cancel Appointment
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>