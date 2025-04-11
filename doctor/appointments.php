<?php
// Doctor Appointments Page
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

// Set default filter values
$date_filter = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query based on filters
$query_params = ["i" => [$doctor_id]];
$where_clauses = ["a.doctor_id = ?"];

if (!empty($date_filter)) {
    $where_clauses[] = "a.appointment_date = ?";
    $query_params[0] .= "s";
    $query_params[] = $date_filter;
}

if (!empty($status_filter)) {
    $where_clauses[] = "a.status = ?";
    $query_params[0] .= "s";
    $query_params[] = $status_filter;
}

$where_clause = implode(" AND ", $where_clauses);

// Get appointments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

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
        WHERE $where_clause
        ORDER BY a.appointment_date DESC, a.appointment_time ASC
        LIMIT $offset, $limit";

$appointments = fetchRows($sql, ...$query_params);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM appointments a WHERE $where_clause";
$total_result = fetchRow($count_sql, ...$query_params);
$total_appointments = $total_result['total'];
$total_pages = ceil($total_appointments / $limit);

// Process appointment status update if requested
if (isset($_GET['id']) && isset($_GET['action'])) {
    $appointment_id = (int)$_GET['id'];
    $action = sanitizeInput($_GET['action']);
    
    // Verify the appointment belongs to this doctor
    $check_sql = "SELECT appointment_id, patient_id FROM appointments WHERE appointment_id = ? AND doctor_id = ?";
    $appointment = fetchRow($check_sql, "ii", [$appointment_id, $doctor_id]);
    
    if ($appointment) {
        $new_status = '';
        $success_message = '';
        
        switch ($action) {
            case 'complete':
                $new_status = 'completed';
                $success_message = 'Appointment marked as completed.';
                break;
            case 'cancel':
                $new_status = 'cancelled';
                $success_message = 'Appointment cancelled successfully.';
                break;
            case 'miss':
                $new_status = 'missed';
                $success_message = 'Appointment marked as missed.';
                break;
        }
        
        if (!empty($new_status)) {
            $update_sql = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
            $result = executeNonQuery($update_sql, "si", [$new_status, $appointment_id]);
            
            if ($result) {
                // Create notification for patient
                $get_user_sql = "SELECT user_id FROM patient_profiles WHERE patient_id = ?";
                $patient = fetchRow($get_user_sql, "i", [$appointment['patient_id']]);
                
                if ($patient) {
                    $notification_sql = "INSERT INTO notifications (user_id, title, message) 
                                      VALUES (?, 'Appointment Update', 'Your appointment has been marked as $new_status.')";
                    insertData($notification_sql, "i", [$patient['user_id']]);
                }
                
                // Redirect to avoid resubmission
                header("Location: appointments.php?success=$success_message");
                exit;
            }
        }
    }
}

// Get success/error messages
$success = isset($_GET['success']) ? sanitizeInput($_GET['success']) : '';
$error = isset($_GET['error']) ? sanitizeInput($_GET['error']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <a href="appointments.php" class="sidebar-link active">
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
                <div class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i> Dr. <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                    </a>
                </div>
            </div>
        </nav>
        
        <div class="container">
            <div class="page-header">
                <h1>Appointments</h1>
                <p>Manage your patient appointments</p>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>Filter Appointments</h3>
                </div>
                <div class="card-body">
                    <form action="" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" id="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status" class="form-label">Status</label>
                                    <select id="status" name="status" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="scheduled" <?php echo ($status_filter == 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="missed" <?php echo ($status_filter == 'missed') ? 'selected' : ''; ?>>Missed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="appointments.php" class="btn btn-secondary">
                                        <i class="fas fa-sync"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Appointment List</h3>
                </div>
                <div class="card-body">
                    <?php if (count($appointments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Patient</th>
                                        <th>Patient ID</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                                <small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                            </td>
                                            <td><?php echo $appointment['patient_name']; ?></td>
                                            <td><?php echo $appointment['patient_unique_id']; ?></td>
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
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($appointment['payment_status'] == 'completed') ? 'success' : 
                                                        (($appointment['payment_status'] == 'refunded') ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($appointment['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($appointment['status'] == 'scheduled'): ?>
                                                    <a href="appointments.php?id=<?php echo $appointment['appointment_id']; ?>&action=complete" class="btn btn-sm btn-success" title="Mark as Completed" onclick="return confirm('Mark this appointment as completed?')">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="appointments.php?id=<?php echo $appointment['appointment_id']; ?>&action=miss" class="btn btn-sm btn-warning" title="Mark as Missed" onclick="return confirm('Mark this appointment as missed?')">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                    <a href="appointments.php?id=<?php echo $appointment['appointment_id']; ?>&action=cancel" class="btn btn-sm btn-danger" title="Cancel Appointment" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($appointment['status'] == 'completed'): ?>
                                                    <a href="create_record.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-primary" title="Create/Edit Medical Record">
                                                        <i class="fas fa-file-medical"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo ($page - 1); ?><?php echo (!empty($date_filter)) ? '&date='.$date_filter : ''; ?><?php echo (!empty($status_filter)) ? '&status='.$status_filter : ''; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo (!empty($date_filter)) ? '&date='.$date_filter : ''; ?><?php echo (!empty($status_filter)) ? '&status='.$status_filter : ''; ?>" class="pagination-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo ($page + 1); ?><?php echo (!empty($date_filter)) ? '&date='.$date_filter : ''; ?><?php echo (!empty($status_filter)) ? '&status='.$status_filter : ''; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No appointments found matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>