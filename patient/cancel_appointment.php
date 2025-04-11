<?php
require_once('../config/database.php');
require_once('../includes/auth.php');

// Ensure user is logged in and is a patient
if (!isLoggedIn() || $_SESSION['role_id'] != 1) {
    header('Location: ../login.php');
    exit();
}

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$patient_id = $_SESSION['user_id'];

// Fetch appointment details
$query = "SELECT 
            a.*, 
            h.name as hospital_name,
            d.name as department_name,
            CONCAT(doc.first_name, ' ', doc.last_name) as doctor_name
          FROM appointments a
          JOIN hospitals h ON a.hospital_id = h.hospital_id
          JOIN departments d ON a.department_id = d.department_id
          JOIN users doc ON a.doctor_id = doc.user_id
          WHERE a.appointment_id = ? AND a.patient_id = ? AND a.status = 'scheduled'";

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

$error = '';
$success = '';

// Handle cancellation confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_cancel'])) {
    $cancellation_reason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : '';
    
    // Update appointment status
    $update_query = "UPDATE appointments 
                    SET status = 'cancelled', 
                        cancellation_reason = ?,
                        cancelled_at = CURRENT_TIMESTAMP 
                    WHERE appointment_id = ? AND patient_id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('sii', $cancellation_reason, $appointment_id, $patient_id);
    
    if ($stmt->execute()) {
        $success = 'Appointment cancelled successfully!';
        // Redirect after a brief delay
        header("Refresh: 2; URL=appointments.php");
    } else {
        $error = 'Failed to cancel appointment. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment - MedConnect</title>
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
                <h2 class="page-title">Cancel Appointment</h2>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="appointment-info mb-4">
                            <h3>Appointment Details</h3>
                            <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                            <p><strong>Hospital:</strong> <?php echo htmlspecialchars($appointment['hospital_name']); ?></p>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($appointment['department_name']); ?></p>
                            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></p>
                            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                        </div>

                        <?php if (!$success): ?>
                        <form method="POST" action="" id="cancellationForm">
                            <div class="form-group">
                                <label for="cancellation_reason">Reason for Cancellation:</label>
                                <textarea id="cancellation_reason" name="cancellation_reason" 
                                          class="form-control" rows="3" required
                                          placeholder="Please provide a reason for cancelling this appointment"></textarea>
                            </div>

                            <div class="form-actions mt-4">
                                <button type="submit" name="confirm_cancel" class="btn btn-danger"
                                        onclick="return confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')">
                                    Confirm Cancellation
                                </button>
                                <a href="appointment_details.php?id=<?php echo $appointment_id; ?>" class="btn btn-secondary">
                                    Back to Appointment
                                </a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>