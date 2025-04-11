<?php
// Patient Appointments Page
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

// Process appointment actions (cancel)
$success = '';
$error = '';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $appointment_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    // Verify the appointment belongs to this patient
    $sql = "SELECT * FROM appointments WHERE appointment_id = ? AND patient_id = ?";
    $appointment = fetchRow($sql, "ii", [$appointment_id, $patient_id]);
    
    if (!$appointment) {
        $error = 'Appointment not found or unauthorized access';
    } else {
        if ($action === 'cancel' && $appointment['status'] === 'scheduled') {
            // Cancel the appointment
            $sql = "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?";
            $result = executeNonQuery($sql, "i", [$appointment_id]);
            
            if ($result) {
                // Create notification for patient
                $sql = "INSERT INTO notifications (user_id, title, message) 
                        VALUES (?, 'Appointment Cancelled', 'Your appointment on " . 
                        date('d M Y', strtotime($appointment['appointment_date'])) . " has been cancelled.')";
                insertData($sql, "i", [$user_id]);
                
                // Get doctor's user_id
                $sql = "SELECT user_id FROM doctor_profiles WHERE doctor_id = ?";
                $doctor = fetchRow($sql, "i", [$appointment['doctor_id']]);
                
                if ($doctor) {
                    // Create notification for doctor
                    $sql = "INSERT INTO notifications (user_id, title, message) 
                            VALUES (?, 'Appointment Cancelled', 'Patient cancelled appointment on " . 
                            date('d M Y', strtotime($appointment['appointment_date'])) . ".')";
                    insertData($sql, "i", [$doctor['user_id']]);
                }
                
                $success = 'Appointment cancelled successfully';
            } else {
                $error = 'Failed to cancel appointment';
            }
        } else {
            $error = 'Invalid action or appointment status';
        }
    }
}

// Get all appointments for this patient
$sql = "SELECT a.*, h.name as hospital_name, d.name as department_name, 
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        dp.consultation_fee
        FROM appointments a
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users u ON dp.user_id = u.user_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$appointments = fetchRows($sql, "i", [$patient_id]);

// Group appointments by status
$upcoming_appointments = [];
$past_appointments = [];
$cancelled_appointments = [];

foreach ($appointments as $appointment) {
    $appointment_datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
    $now = date('Y-m-d H:i:s');
    
    if ($appointment['status'] === 'cancelled') {
        $cancelled_appointments[] = $appointment;
    } else if ($appointment_datetime > $now && $appointment['status'] === 'scheduled') {
        $upcoming_appointments[] = $appointment;
    } else {
        $past_appointments[] = $appointment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | Hospital Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="sidebar-logo">
                    <img src="../assets/images/logo.png" alt="Hospital Logo">
                    <h5>Hospital Management</h5>
                </div>
                <ul class="nav flex-column sidebar-nav">
                    <li class="nav-item">
                        <a href="dashboard.php" class="sidebar-link">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="appointments.php" class="sidebar-link active">
                            <i class="fas fa-calendar-check"></i> My Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="book_appointment.php" class="sidebar-link">
                            <i class="fas fa-calendar-plus"></i> Book Appointment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="sidebar-link">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../includes/logout.php" class="sidebar-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
                <!-- Navbar -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white">
                    <div class="container-fluid">
                        <button id="sidebarToggle" class="navbar-toggler d-md-none collapsed" type="button">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="d-flex align-items-center ms-auto">
                            <div class="dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="../assets/images/user-avatar.png" class="avatar" alt="User Avatar">
                                    <span class="ms-2"><?php echo $_SESSION['name']; ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="../includes/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Page content -->
                <div class="container-fluid mt-4">
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2 class="page-title">My Appointments</h2>
                                <a href="book_appointment.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Book New Appointment
                                </a>
                            </div>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Appointment Tabs -->
                            <ul class="nav nav-tabs" id="appointmentTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">
                                        Upcoming <span class="badge bg-primary"><?php echo count($upcoming_appointments); ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab">
                                        Past <span class="badge bg-secondary"><?php echo count($past_appointments); ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab">
                                        Cancelled <span class="badge bg-danger"><?php echo count($cancelled_appointments); ?></span>
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="appointmentTabsContent">
                                <!-- Upcoming Appointments -->
                                <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                                    <?php if (empty($upcoming_appointments)): ?>
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle"></i> You don't have any upcoming appointments.
                                            <a href="book_appointment.php" class="alert-link">Book an appointment now</a>.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Doctor</th>
                                                        <th>Hospital</th>
                                                        <th>Department</th>
                                                        <th>Status</th>
                                                        <th>Payment</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></div>
                                                                <div><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                                            </td>
                                                            <td><?php echo $appointment['doctor_name']; ?></td>
                                                            <td><?php echo $appointment['hospital_name']; ?></td>
                                                            <td><?php echo $appointment['department_name']; ?></td>
                                                            <td>
                                                                <span class="badge bg-primary"><?php echo ucfirst($appointment['status']); ?></span>
                                                            </td>
                                                            <td>
                                                                <?php if ($appointment['payment_status'] === 'completed'): ?>
                                                                    <span class="badge bg-success">Paid</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning">Pending</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group">
                                                                    <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <?php if ($appointment['payment_status'] === 'pending'): ?>
                                                                        <a href="payment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-success" title="Make Payment">
                                                                            <i class="fas fa-credit-card"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <a href="appointments.php?id=<?php echo $appointment['appointment_id']; ?>&action=cancel" class="btn btn-sm btn-danger" title="Cancel Appointment" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                                        <i class="fas fa-times"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Past Appointments -->
                                <div class="tab-pane fade" id="past" role="tabpanel">
                                    <?php if (empty($past_appointments)): ?>
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle"></i> You don't have any past appointments.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Doctor</th>
                                                        <th>Hospital</th>
                                                        <th>Department</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($past_appointments as $appointment): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></div>
                                                                <div><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                                            </td>
                                                            <td><?php echo $appointment['doctor_name']; ?></td>
                                                            <td><?php echo $appointment['hospital_name']; ?></td>
                                                            <td><?php echo $appointment['department_name']; ?></td>
                                                            <td>
                                                                <?php if ($appointment['status'] === 'completed'): ?>
                                                                    <span class="badge bg-success">Completed</span>
                                                                <?php elseif ($appointment['status'] === 'missed'): ?>
                                                                    <span class="badge bg-warning">Missed</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary"><?php echo ucfirst($appointment['status']); ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                                    <i class="fas fa-eye"></i> View Details
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Cancelled Appointments -->
                                <div class="tab-pane fade" id="cancelled" role="tabpanel">
                                    <?php if (empty($cancelled_appointments)): ?>
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle"></i> You don't have any cancelled appointments.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Doctor</th>
                                                        <th>Hospital</th>
                                                        <th>Department</th>
                                                        <th>Cancelled On</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cancelled_appointments as $appointment): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></div>
                                                                <div><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                                                            </td>
                                                            <td><?php echo $appointment['doctor_name']; ?></td>
                                                            <td><?php echo $appointment['hospital_name']; ?></td>
                                                            <td><?php echo $appointment['department_name']; ?></td>
                                                            <td><?php echo date('d M Y', strtotime($appointment['updated_at'])); ?></td>
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
            </main>
        </div>
    </div>
    
    <!-- Chatbot -->
    <div class="chatbot-container">
        <div class="chatbot-header">
            <h5><i class="fas fa-robot"></i> Healthcare Assistant</h5>
            <button id="chatbotToggle" class="btn btn-sm"><i class="fas fa-times"></i></button>
        </div>
        <div class="chatbot-body">
            <div class="chat-message bot">
                <div class="chat-bubble">Hello! How can I assist you with your appointments today?</div>
                <div class="chat-time">Now</div>
            </div>
        </div>
        <div class="chatbot-footer">
            <input type="text" class="chatbot-input" placeholder="Type your message...">
            <button class="chatbot-send"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>