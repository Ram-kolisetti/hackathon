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
$error = '';
$success = '';

// Fetch appointment details
$query = "SELECT 
            a.*, 
            h.name as hospital_name,
            d.name as department_name,
            CONCAT(doc.first_name, ' ', doc.last_name) as doctor_name,
            doc_profile.available_days,
            doc_profile.available_time_start,
            doc_profile.available_time_end
          FROM appointments a
          JOIN hospitals h ON a.hospital_id = h.hospital_id
          JOIN departments d ON a.department_id = d.department_id
          JOIN users doc ON a.doctor_id = doc.user_id
          JOIN doctor_profiles doc_profile ON doc.user_id = doc_profile.user_id
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_date = $_POST['appointment_date'];
    $new_time = $_POST['appointment_time'];
    
    // Validate new date and time
    $available_days = explode(',', $appointment['available_days']);
    $day_of_week = date('D', strtotime($new_date));
    
    if (!in_array($day_of_week, $available_days)) {
        $error = 'Selected day is not available for this doctor.';
    } else {
        // Check if the time is within doctor's available hours
        $selected_time = strtotime($new_time);
        $start_time = strtotime($appointment['available_time_start']);
        $end_time = strtotime($appointment['available_time_end']);
        
        if ($selected_time < $start_time || $selected_time > $end_time) {
            $error = 'Selected time is outside doctor\'s available hours.';
        } else {
            // Check if the slot is available
            $check_query = "SELECT COUNT(*) as count FROM appointments 
                           WHERE doctor_id = ? AND appointment_date = ? 
                           AND appointment_time = ? AND status = 'scheduled' 
                           AND appointment_id != ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('issi', $appointment['doctor_id'], $new_date, $new_time, $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            
            if ($count > 0) {
                $error = 'This time slot is already booked. Please choose another time.';
            } else {
                // Update appointment
                $update_query = "UPDATE appointments 
                                SET appointment_date = ?, appointment_time = ? 
                                WHERE appointment_id = ? AND patient_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('ssii', $new_date, $new_time, $appointment_id, $patient_id);
                
                if ($stmt->execute()) {
                    $success = 'Appointment rescheduled successfully!';
                    // Redirect after a brief delay
                    header("Refresh: 2; URL=appointment_details.php?id=" . $appointment_id);
                } else {
                    $error = 'Failed to reschedule appointment. Please try again.';
                }
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
    <title>Reschedule Appointment - MedConnect</title>
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
                <h2 class="page-title">Reschedule Appointment</h2>

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
                            <h3>Current Appointment Details</h3>
                            <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                            <p><strong>Hospital:</strong> <?php echo htmlspecialchars($appointment['hospital_name']); ?></p>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($appointment['department_name']); ?></p>
                            <p><strong>Current Date:</strong> <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></p>
                            <p><strong>Current Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                        </div>

                        <form method="POST" action="" id="rescheduleForm">
                            <div class="form-group">
                                <label for="appointment_date">New Date:</label>
                                <input type="date" id="appointment_date" name="appointment_date" 
                                       class="form-control" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="appointment_time">New Time:</label>
                                <input type="time" id="appointment_time" name="appointment_time" 
                                       class="form-control"
                                       required>
                            </div>

                            <div class="doctor-schedule-info mt-3">
                                <p><strong>Available Days:</strong> <?php echo str_replace(',', ', ', $appointment['available_days']); ?></p>
                                <p><strong>Available Hours:</strong> 
                                   <?php echo date('g:i A', strtotime($appointment['available_time_start'])); ?> - 
                                   <?php echo date('g:i A', strtotime($appointment['available_time_end'])); ?>
                                </p>
                            </div>

                            <div class="form-actions mt-4">
                                <button type="submit" class="btn btn-primary">Reschedule Appointment</button>
                                <a href="appointment_details.php?id=<?php echo $appointment_id; ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        document.getElementById('appointment_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const availableDays = '<?php echo $appointment["available_days"]; ?>'.split(',');
            const dayOfWeek = selectedDate.toLocaleDateString('en-US', { weekday: 'short' });
            
            if (!availableDays.includes(dayOfWeek)) {
                alert('Selected day is not available. Please choose from available days: ' + availableDays.join(', '));
                this.value = '';
            }
        });

        document.getElementById('appointment_time').addEventListener('change', function() {
            const selectedTime = this.value;
            const startTime = '<?php echo $appointment["available_time_start"]; ?>';
            const endTime = '<?php echo $appointment["available_time_end"]; ?>';
            
            if (selectedTime < startTime || selectedTime > endTime) {
                alert('Selected time is outside available hours. Please choose between ' + 
                      startTime + ' and ' + endTime);
                this.value = '';
            }
        });
    </script>
</body>
</html>