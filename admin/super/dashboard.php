<?php
// Super Admin Dashboard
session_start();

// Define base path for includes
define('BASE_PATH', dirname(dirname(__DIR__)));

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is logged in and is a super admin
if (!isLoggedIn() || !hasRole('super_admin')) {
    header('Location: ../../login.php');
    exit;
}

// Get admin information
$user_id = $_SESSION['user_id'];
$admin_id = $_SESSION['profile_id'];

// Get total hospitals count
$sql = "SELECT COUNT(*) as total FROM hospitals";
$hospitals_count = fetchRow($sql)['total'];

// Get total doctors count
$sql = "SELECT COUNT(*) as total FROM doctor_profiles";
$doctors_count = fetchRow($sql)['total'];

// Get total patients count
$sql = "SELECT COUNT(*) as total FROM patient_profiles";
$patients_count = fetchRow($sql)['total'];

// Get total appointments count
$sql = "SELECT COUNT(*) as total FROM appointments";
$appointments_count = fetchRow($sql)['total'];

// Get hospitals list
$sql = "SELECT h.*, 
        (SELECT COUNT(*) FROM departments WHERE hospital_id = h.hospital_id) as departments_count,
        (SELECT COUNT(*) FROM doctor_profiles WHERE hospital_id = h.hospital_id) as doctors_count,
        (SELECT COUNT(*) FROM appointments WHERE hospital_id = h.hospital_id) as appointments_count
        FROM hospitals h
        ORDER BY h.name ASC";
$hospitals = fetchRows($sql);

// Get recent appointments across all hospitals
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        CONCAT(du.first_name, ' ', du.last_name) as doctor_name,
        h.name as hospital_name,
        d.name as department_name
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN doctor_profiles dp ON a.doctor_id = dp.doctor_id
        JOIN users du ON dp.user_id = du.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10";
$recent_appointments = fetchRows($sql);

// Get pending reviews that need moderation
$sql = "SELECT r.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        CONCAT(du.first_name, ' ', du.last_name) as doctor_name,
        h.name as hospital_name
        FROM reviews r
        JOIN patient_profiles pp ON r.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN doctor_profiles dp ON r.doctor_id = dp.doctor_id
        JOIN users du ON dp.user_id = du.user_id
        JOIN hospitals h ON r.hospital_id = h.hospital_id
        WHERE r.is_approved = 0
        ORDER BY r.created_at DESC
        LIMIT 5";
$pending_reviews = fetchRows($sql);

// Get appointment statistics by hospital
$sql = "SELECT h.name as hospital_name, COUNT(*) as appointment_count
        FROM appointments a
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        GROUP BY a.hospital_id
        ORDER BY appointment_count DESC
        LIMIT 5";
$hospital_stats = fetchRows($sql);

// Get appointment statistics by status
$sql = "SELECT status, COUNT(*) as count
        FROM appointments
        GROUP BY status";
$status_stats = fetchRows($sql);

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
    <title>Super Admin Dashboard - Hospital Management Platform</title>
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
                <a href="hospitals.php" class="sidebar-link">
                    <i class="fas fa-hospital"></i> Hospitals
                </a>
            </li>
            <li class="sidebar-item">
                <a href="admins.php" class="sidebar-link">
                    <i class="fas fa-user-shield"></i> Admins
                </a>
            </li>
            <li class="sidebar-item">
                <a href="doctors.php" class="sidebar-link">
                    <i class="fas fa-user-md"></i> Doctors
                </a>
            </li>
            <li class="sidebar-item">
                <a href="patients.php" class="sidebar-link">
                    <i class="fas fa-user-injured"></i> Patients
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reviews.php" class="sidebar-link">
                    <i class="fas fa-star"></i> Reviews
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reports.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i> Reports
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
                <h1>Super Admin Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
            </div>
            
            <div class="stats-cards">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary">
                            <div class="stat-card-body">
                                <div class="stat-card-icon">
                                    <i class="fas fa-hospital"></i>
                                </div>
                                <div class="stat-card-content">
                                    <div class="stat-card-value"><?php echo $hospitals_count; ?></div>
                                    <div class="stat-card-title">Hospitals</div>
                                </div>
                            </div>
                            <div class="stat-card-footer">
                                <a href="hospitals.php" class="stat-card-link">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success">
                            <div class="stat-card-body">
                                <div class="stat-card-icon">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div class="stat-card-content">
                                    <div class="stat-card-value"><?php echo $doctors_count; ?></div>
                                    <div class="stat-card-title">Doctors</div>
                                </div>
                            </div>
                            <div class="stat-card-footer">
                                <a href="doctors.php" class="stat-card-link">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info">
                            <div class="stat-card-body">
                                <div class="stat-card-icon">
                                    <i class="fas fa-user-injured"></i>
                                </div>
                                <div class="stat-card-content">
                                    <div class="stat-card-value"><?php echo $patients_count; ?></div>
                                    <div class="stat-card-title">Patients</div>
                                </div>
                            </div>
                            <div class="stat-card-footer">
                                <a href="patients.php" class="stat-card-link">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning">
                            <div class="stat-card-body">
                                <div class="stat-card-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-card-content">
                                    <div class="stat-card-value"><?php echo $appointments_count; ?></div>
                                    <div class="stat-card-title">Appointments</div>
                                </div>
                            </div>
                            <div class="stat-card-footer">
                                <a href="reports.php" class="stat-card-link">View Details <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3>Hospitals Overview</h3>
                            <div class="card-header-actions">
                                <a href="hospitals.php" class="btn btn-sm btn-primary">Manage Hospitals</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($hospitals) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Hospital Name</th>
                                                <th>Location</th>
                                                <th>Departments</th>
                                                <th>Doctors</th>
                                                <th>Appointments</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hospitals as $hospital): ?>
                                                <tr>
                                                    <td><?php echo $hospital['name']; ?></td>
                                                    <td><?php echo $hospital['city'] . ', ' . $hospital['state']; ?></td>
                                                    <td><?php echo $hospital['departments_count']; ?></td>
                                                    <td><?php echo $hospital['doctors_count']; ?></td>
                                                    <td><?php echo $hospital['appointments_count']; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo ($hospital['status'] == 'active') ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($hospital['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No hospitals found.
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
                                                <th>Hospital</th>
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
                                                    <td><?php echo $appointment['hospital_name']; ?></td>
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
                            <h3>Hospital Performance</h3>
                        </div>
                        <div class="card-body">
                            <?php if (count($hospital_stats) > 0): ?>
                                <canvas id="hospitalChart"></canvas>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No hospital statistics available.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Pending Reviews</h3>
                            <div class="card-header-actions">
                                <a href="reviews.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($pending_reviews) > 0): ?>
                                <div class="pending-reviews">
                                    <?php foreach ($pending_reviews as $review): ?>
                                        <div class="review-item">
                                            <div class="review-header">
                                                <div class="review-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo ($i <= $review['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="review-date">
                                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="review-content">
                                                <?php echo $review['review_text']; ?>
                                            </div>
                                            <div class="review-meta">
                                                <div><strong>Patient:</strong> <?php echo $review['patient_name']; ?></div>
                                                <div><strong>Doctor:</strong> <?php echo $review['doctor_name']; ?></div>
                                                <div><strong>Hospital:</strong> <?php echo $review['hospital_name']; ?></div>
                                            </div>
                                            <div class="review-actions">
                                                <a href="reviews.php?action=approve&id=<?php echo $review['review_id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <a href="reviews.php?action=reject&id=<?php echo $review['review_id']; ?>" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No pending reviews.
                                </div>
                            <?php endif; ?>
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
        
        <?php if (count($hospital_stats) > 0): ?>
        // Hospital statistics chart
        const hospitalCtx = document.getElementById('hospitalChart').getContext('2d');
        const hospitalChart = new Chart(hospitalCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($hospital_stats, 'hospital_name')); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode(array_column($hospital_stats, 'appointment_count')); ?>,
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