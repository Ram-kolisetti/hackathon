<?php
// Doctor Dashboard
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is logged in and is a doctor
if (!isLoggedIn() || !hasRole('doctor')) {
    header('Location: ../login.php');
    exit;
}

// Get doctor information
$user_id = $_SESSION['user_id'];
$doctor_id = $_SESSION['profile_id'];

// Get doctor details
$sql = "SELECT dp.*, h.name as hospital_name, d.name as department_name 
        FROM doctor_profiles dp 
        JOIN hospitals h ON dp.hospital_id = h.hospital_id 
        JOIN departments d ON dp.department_id = d.department_id 
        WHERE dp.doctor_id = ?";
$doctor = fetchRow($sql, "i", [$doctor_id]);

// Get today's appointments
$today = date('Y-m-d');
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        pp.patient_unique_id,
        h.name as hospital_name,
        d.name as department_name
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.doctor_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_time ASC";
$today_appointments = fetchRows($sql, "is", [$doctor_id, $today]);

// Get upcoming appointments
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        pp.patient_unique_id,
        h.name as hospital_name,
        d.name as department_name
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.doctor_id = ? AND a.appointment_date > ?
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5";
$upcoming_appointments = fetchRows($sql, "is", [$doctor_id, $today]);

// Get recent appointments (completed)
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        pp.patient_unique_id,
        h.name as hospital_name,
        d.name as department_name,
        mr.record_id
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        LEFT JOIN medical_records mr ON a.appointment_id = mr.appointment_id
        WHERE a.doctor_id = ? AND a.status = 'completed'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5";
$completed_appointments = fetchRows($sql, "i", [$doctor_id]);

// Get notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$notifications = fetchRows($sql, "i", [$user_id]);

// Get statistics
// Total appointments
$sql = "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?";
$total_appointments = fetchRow($sql, "i", [$doctor_id])['total'];

// Completed appointments
$sql = "SELECT COUNT(*) as completed FROM appointments WHERE doctor_id = ? AND status = 'completed'";
$completed_count = fetchRow($sql, "i", [$doctor_id])['completed'];

// Upcoming appointments
$sql = "SELECT COUNT(*) as upcoming FROM appointments WHERE doctor_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled'";
$upcoming_count = fetchRow($sql, "i", [$doctor_id])['upcoming'];

// Cancelled appointments
$sql = "SELECT COUNT(*) as cancelled FROM appointments WHERE doctor_id = ? AND status = 'cancelled'";
$cancelled_count = fetchRow($sql, "i", [$doctor_id])['cancelled'];

// Average rating
$sql = "SELECT AVG(rating) as avg_rating FROM reviews WHERE doctor_id = ?";
$result = fetchRow($sql, "i", [$doctor_id]);
$avg_rating = $result['avg_rating'] ? round($result['avg_rating'], 1) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">MedConnect</a>
        </div>
        
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="dashboard.php" class="sidebar-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a href="appointments.php" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
            </li>
            <li class="sidebar-item">
                <a href="patients.php" class="sidebar-link">
                    <i class="fas fa-user-injured"></i> Patients
                </a>
            </li>
            <li class="sidebar-item">
                <a href="medical_records.php" class="sidebar-link">
                    <i class="fas fa-file-medical"></i> Medical Records
                </a>
            </li>
            <li class="sidebar-item">
                <a href="schedule.php" class="sidebar-link">
                    <i class="fas fa-clock"></i> My Schedule
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
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" id="notificationsDropdown">
                        <i class="fas fa-bell"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="badge badge-danger"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="notificationsDropdown">
                        <div class="dropdown-header">Notifications</div>
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <a href="#" class="dropdown-item">
                                    <div class="notification-item">
                                        <div class="notification-title"><?php echo $notification['title']; ?></div>
                                        <div class="notification-message"><?php echo $notification['message']; ?></div>
                                        <div class="notification-time"><?php echo date('M d, H:i', strtotime($notification['created_at'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dropdown-item">No new notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i> Dr. <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container">
            <div class="page-header">
                <h1>Doctor Dashboard</h1>
                <p>Welcome back, Dr. <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
            </div>
            
            <div class="doctor-info-card">
                <div class="row">
                    <div class="col-md-2">
                        <div class="doctor-avatar">
                            <?php if (!empty($_SESSION['profile_image'])): ?>
                                <img src="<?php echo $_SESSION['profile_image']; ?>" alt="Doctor Profile">
                            <?php else: ?>
                                <i class="fas fa-user-md"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <h3>Dr. <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></h3>
                        <p class="doctor-id">Doctor ID: <?php echo $_SESSION['unique_id']; ?></p>
                        <p><i class="fas fa-hospital"></i> <?php echo $doctor['hospital_name']; ?></p>
                        <p><i class="fas fa-stethoscope"></i> <?php echo $doctor['department_name']; ?> - <?php echo $doctor['specialization']; ?></p>
                    </div>
                    <div class="col-md-5">
                        <div class="doctor-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $total_appointments; ?></div>
                                <div class="stat-label">Total Appointments</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $completed_count; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $upcoming_count; ?></div>
                                <div class="stat-label">Upcoming</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $avg_rating; ?></div>
                                <div class="stat-label">Rating</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3>Today's Appointments</h3>
                            <div class="card-header-actions">
                                <a href="appointments.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($today_appointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Patient</th>
                                                <th>Patient ID</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($today_appointments as $appointment): ?>
                                                <tr>
                                                    <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                                    <td><?php echo $appointment['patient_name']; ?></td>
                                                    <td><?php echo $appointment['patient_unique_id']; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            echo ($appointment['status'] == 'scheduled') ? 'primary' : 
                                                                (($appointment['status'] == 'completed') ? 'success' : 
                                                                    (($appointment['status'] == 'cancelled') ? 'danger' : 'warning')); 
                                                        ?>">
                                                            <?php echo ucfirst($appointment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($appointment['status'] == 'scheduled'): ?>
                                                            <a href="update_appointment.php?id=<?php echo $appointment['appointment_id']; ?>&action=complete" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="update_appointment.php?id=<?php echo $appointment['appointment_id']; ?>&action=miss" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No appointments scheduled for today.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Upcoming Appointments</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_appointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Patient</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                                        <small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                                    </td>
                                                    <td><?php echo $appointment['patient_name']; ?></td>
                                                    <td><?php echo $appointment['department_name']; ?></td>
                                                    <td>
                                                        <span class="badge badge-primary">Scheduled</span>
                                                    </td>
                                                    <td>
                                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No upcoming appointments.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>Appointment Statistics</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="appointmentChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Recent Medical Records</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($completed_appointments) > 0): ?>
                                <div class="recent-records">
                                    <?php foreach ($completed_appointments as $appointment): ?>
                                        <div class="record-item">
                                            <div class="record-date">
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                            </div>
                                            <div class="record-details">
                                                <div class="record-patient"><?php echo $appointment['patient_name']; ?></div>
                                                <div class="record-department"><?php echo $appointment['department_name']; ?></div>
                                            </div>
                                            <div class="record-actions">
                                                <?php if ($appointment['record_id']): ?>
                                                    <a href="view_record.php?id=<?php echo $appointment['record_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-file-medical"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="create_record.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No recent medical records.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Appointment statistics chart
        const ctx = document.getElementById('appointmentChart').getContext('2d');
        const appointmentChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Upcoming', 'Cancelled'],
                datasets: [{
                    data: [<?php echo $completed_count; ?>, <?php echo $upcoming_count; ?>, <?php echo $cancelled_count; ?>],
                    backgroundColor: ['#28a745', '#007bff', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        });
        
        // Initialize dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            
            dropdownToggles.forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdownMenu = this.nextElementSibling;
                    dropdownMenu.classList.toggle('show');
                });
            });
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.matches('.dropdown-toggle')) {
                    const dropdowns = document.querySelectorAll('.dropdown-menu');
                    dropdowns.forEach(function(dropdown) {
                        if (dropdown.classList.contains('show')) {
                            dropdown.classList.remove('show');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>