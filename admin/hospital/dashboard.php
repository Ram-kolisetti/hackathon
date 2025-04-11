<?php
// Hospital Admin Dashboard
session_start();

// Define base path for includes
define('BASE_PATH', dirname(dirname(__DIR__)));

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is logged in and is a hospital admin
if (!isLoggedIn() || !hasRole('hospital_admin')) {
    header('Location: ../../login.php');
    exit;
}

// Get admin information
$user_id = $_SESSION['user_id'];
$admin_id = $_SESSION['profile_id'];
$hospital_id = $_SESSION['hospital_id'];

// Get hospital details
$sql = "SELECT * FROM hospitals WHERE hospital_id = ?";
$hospital = fetchRow($sql, "i", [$hospital_id]);

// Get total doctors count
$sql = "SELECT COUNT(*) as total FROM doctor_profiles WHERE hospital_id = ?";
$doctors_count = fetchRow($sql, "i", [$hospital_id])['total'];

// Get total departments count
$sql = "SELECT COUNT(*) as total FROM departments WHERE hospital_id = ?";
$departments_count = fetchRow($sql, "i", [$hospital_id])['total'];

// Get total appointments count
$sql = "SELECT COUNT(*) as total FROM appointments WHERE hospital_id = ?";
$appointments_count = fetchRow($sql, "i", [$hospital_id])['total'];

// Get total patients count (unique patients who have had appointments at this hospital)
$sql = "SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE hospital_id = ?";
$patients_count = fetchRow($sql, "i", [$hospital_id])['total'];

// Get today's appointments
$today = date('Y-m-d');
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        CONCAT(du.first_name, ' ', du.last_name) as doctor_name,
        d.name as department_name
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users du ON dp.user_id = du.user_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.hospital_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_time ASC";
$today_appointments = fetchRows($sql, "is", [$hospital_id, $today]);

// Get recent appointments
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        CONCAT(du.first_name, ' ', du.last_name) as doctor_name,
        d.name as department_name
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users du ON dp.user_id = du.user_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.hospital_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10";
$recent_appointments = fetchRows($sql, "i", [$hospital_id]);

// Get appointment statistics by department
$sql = "SELECT d.name as department_name, COUNT(*) as appointment_count
        FROM appointments a
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.hospital_id = ?
        GROUP BY a.department_id
        ORDER BY appointment_count DESC";
$department_stats = fetchRows($sql, "i", [$hospital_id]);

// Get appointment statistics by status
$sql = "SELECT status, COUNT(*) as count
        FROM appointments
        WHERE hospital_id = ?
        GROUP BY status";
$status_stats = fetchRows($sql, "i", [$hospital_id]);

// Format status stats for chart
$status_labels = [];
$status_data = [];
$status_colors = [
    'scheduled' => '#007bff',
    'completed' => '#28a745',
    'cancelled' => '#dc3545',
    'missed' => '#ffc107'
];

foreach ($status_stats as $stat) {
    $status_labels[] = ucfirst($stat['status']);
    $status_data[] = $stat['count'];
}

// Get notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$notifications = fetchRows($sql, "i", [$user_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Admin Dashboard - Hospital Management Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
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
                <a href="doctors.php" class="sidebar-link">
                    <i class="fas fa-user-md"></i> Doctors
                </a>
            </li>
            <li class="sidebar-item">
                <a href="departments.php" class="sidebar-link">
                    <i class="fas fa-hospital"></i> Departments
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
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reviews.php" class="sidebar-link">
                    <i class="fas fa-star"></i> Reviews
                </a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php" class="sidebar-link">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </li>
            <li class="sidebar-divider"></li>
            <li class="sidebar-item">
                <a href="../../logout.php" class="sidebar-link">
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
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container">
            <div class="page-header">
                <h1>Hospital Admin Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
            </div>
            
            <div class="hospital-info-card">
                <div class="row">
                    <div class="col-md-2">
                        <div class="hospital-logo">
                            <?php if (!empty($hospital['logo_url'])): ?>
                                <img src="<?php echo $hospital['logo_url']; ?>" alt="Hospital Logo">
                            <?php else: ?>
                                <i class="fas fa-hospital"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <h3><?php echo $hospital['name']; ?></h3>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo $hospital['address']; ?>, <?php echo $hospital['city']; ?>, <?php echo $hospital['state']; ?> <?php echo $hospital['zip_code']; ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo $hospital['phone']; ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo $hospital['email']; ?></p>
                    </div>
                    <div class="col-md-5">
                        <div class="hospital-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $doctors_count; ?></div>
                                <div class="stat-label">Doctors</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $departments_count; ?></div>
                                <div class="stat-label">Departments</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $patients_count; ?></div>
                                <div class="stat-label">Patients</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $appointments_count; ?></div>
                                <div class="stat-label">Appointments</div>
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
                                                <th>Doctor</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($today_appointments as $appointment): ?>
                                                <tr>
                                                    <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                                    <td><?php echo $appointment['patient_name']; ?></td>
                                                    <td><?php echo $appointment['doctor_name']; ?></td>
                                                    <td><?php echo $appointment['department_name']; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            echo ($appointment['status'] == 'scheduled') ? 'primary' : 
                                                                (($appointment['status'] == 'completed') ? 'success' : 
                                                                    (($appointment['status'] == 'cancelled') ? 'danger' : 'warning')); 
                                                        ?>">
                                                            <?php echo ucfirst($appointment['status']); ?>
                                                        </span>
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
                            <h3>Recent Appointments</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($recent_appointments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Patient</th>
                                                <th>Doctor</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_appointments as $appointment): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                                        <small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                                    </td>
                                                    <td><?php echo $appointment['patient_name']; ?></td>
                                                    <td><?php echo $appointment['doctor_name']; ?></td>
                                                    <td><?php echo $appointment['department_name']; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            echo ($appointment['status'] == 'scheduled') ? 'primary' : 
                                                                (($appointment['status'] == 'completed') ? 'success' : 
                                                                    (($appointment['status'] == 'cancelled') ? 'danger' : 'warning')); 
                                                        ?>">
                                                            <?php echo ucfirst($appointment['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No appointments found.
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
                            <h3>Department Statistics</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($department_stats) > 0): ?>
                                <canvas id="departmentChart"></canvas>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No department statistics available.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Quick Links</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-links">
                                <a href="doctors.php" class="quick-link">
                                    <i class="fas fa-user-md"></i>
                                    <span>Manage Doctors</span>
                                </a>
                                <a href="departments.php" class="quick-link">
                                    <i class="fas fa-hospital"></i>
                                    <span>Manage Departments</span>
                                </a>
                                <a href="appointments.php" class="quick-link">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>View Appointments</span>
                                </a>
                                <a href="reports.php" class="quick-link">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>Generate Reports</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/main.js"></script>
    <script>
        // Appointment statistics chart
        const statusCtx = document.getElementById('appointmentChart').getContext('2d');
        const appointmentChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: [
                        '#007bff',
                        '#28a745',
                        '#dc3545',
                        '#ffc107'
                    ],
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
        
        <?php if (count($department_stats) > 0): ?>
        // Department statistics chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        const departmentChart = new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($department_stats, 'department_name')); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode(array_column($department_stats, 'appointment_count')); ?>,
                    backgroundColor: '#3498db',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
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