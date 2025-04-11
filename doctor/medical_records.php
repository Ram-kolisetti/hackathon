<?php
// Doctor Medical Records Page
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
$patient_search = isset($_GET['patient']) ? sanitizeInput($_GET['patient']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query based on filters
$query_params = ["i" => [$doctor_id]];
$where_clauses = ["mr.doctor_id = ?"];

if (!empty($patient_search)) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR pp.patient_unique_id LIKE ?)";
    $query_params[0] .= "ssss";
    $search_term = "%$patient_search%";
    $query_params[] = $search_term;
    $query_params[] = $search_term;
    $query_params[] = $search_term;
    $query_params[] = $search_term;
}

if (!empty($date_from)) {
    $where_clauses[] = "a.appointment_date >= ?";
    $query_params[0] .= "s";
    $query_params[] = $date_from;
}

if (!empty($date_to)) {
    $where_clauses[] = "a.appointment_date <= ?";
    $query_params[0] .= "s";
    $query_params[] = $date_to;
}

$where_clause = implode(" AND ", $where_clauses);

// Get medical records with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT mr.*, 
        a.appointment_date, a.appointment_time,
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        pp.patient_unique_id,
        h.name as hospital_name,
        d.name as department_name
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.appointment_id
        JOIN patient_profiles pp ON mr.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE $where_clause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT $offset, $limit";

$medical_records = fetchRows($sql, ...$query_params);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM medical_records mr
              JOIN appointments a ON mr.appointment_id = a.appointment_id
              JOIN patient_profiles pp ON mr.patient_id = pp.patient_id
              JOIN users u ON pp.user_id = u.user_id
              WHERE $where_clause";
$total_result = fetchRow($count_sql, ...$query_params);
$total_records = $total_result['total'];
$total_pages = ceil($total_records / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Doctor Dashboard</title>
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
                <a href="medical_records.php" class="sidebar-link active">
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
                <h1>Medical Records</h1>
                <p>View and manage patient medical records</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Search Records</h3>
                </div>
                <div class="card-body">
                    <form action="" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="patient" class="form-label">Patient Name/ID</label>
                                    <input type="text" id="patient" name="patient" class="form-control" value="<?php echo $patient_search; ?>" placeholder="Search by name or ID">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="medical_records.php" class="btn btn-secondary">
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
                    <h3>Medical Records List</h3>
                    <div class="card-header-actions">
                        <span class="badge badge-primary"><?php echo $total_records; ?> Records Found</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($medical_records) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Patient ID</th>
                                        <th>Diagnosis</th>
                                        <th>Follow-up</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medical_records as $record): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($record['appointment_date'])); ?><br>
                                                <small><?php echo date('h:i A', strtotime($record['appointment_time'])); ?></small>
                                            </td>
                                            <td><?php echo $record['patient_name']; ?></td>
                                            <td><?php echo $record['patient_unique_id']; ?></td>
                                            <td>
                                                <?php 
                                                    $short_diagnosis = strlen($record['diagnosis']) > 50 ? 
                                                        substr($record['diagnosis'], 0, 50) . '...' : 
                                                        $record['diagnosis'];
                                                    echo $short_diagnosis;
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($record['follow_up_date'])): ?>
                                                    <?php echo date('M d, Y', strtotime($record['follow_up_date'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="view_record.php?id=<?php echo $record['record_id']; ?>" class="btn btn-sm btn-info" title="View Record">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="create_record.php?appointment_id=<?php echo $record['appointment_id']; ?>" class="btn btn-sm btn-primary" title="Edit Record">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="print_record.php?id=<?php echo $record['record_id']; ?>" class="btn btn-sm btn-secondary" title="Print Record" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
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
                                    <a href="?page=<?php echo ($page - 1); ?><?php echo (!empty($patient_search)) ? '&patient='.$patient_search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo (!empty($patient_search)) ? '&patient='.$patient_search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?>" class="pagination-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo ($page + 1); ?><?php echo (!empty($patient_search)) ? '&patient='.$patient_search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No medical records found matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>