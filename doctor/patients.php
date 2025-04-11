<?php
// Doctor Patients Page
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
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query based on filters
$query_params = ["i" => [$doctor_id]];
$where_clauses = ["a.doctor_id = ?"];

if (!empty($search)) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR pp.patient_unique_id LIKE ?)";
    $query_params[0] .= "ssss";
    $search_term = "%$search%";
    $query_params[] = $search_term;
    $query_params[] = $search_term;
    $query_params[] = $search_term;
    $query_params[] = $search_term;
}

$where_clause = implode(" AND ", $where_clauses);

// Get patients with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT DISTINCT pp.patient_id, pp.patient_unique_id, pp.date_of_birth, pp.gender, pp.blood_group,
        u.first_name, u.last_name, u.email, u.phone,
        (SELECT COUNT(*) FROM appointments WHERE patient_id = pp.patient_id AND doctor_id = ?) as appointment_count,
        (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = pp.patient_id AND doctor_id = ?) as last_visit
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        WHERE $where_clause
        ORDER BY last_visit DESC
        LIMIT $offset, $limit";

// Add the two additional parameters for the subqueries
$query_params[0] .= "ii";
$query_params[] = $doctor_id;
$query_params[] = $doctor_id;

$patients = fetchRows($sql, ...$query_params);

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT pp.patient_id) as total 
              FROM appointments a
              JOIN patient_profiles pp ON a.patient_id = pp.patient_id
              JOIN users u ON pp.user_id = u.user_id
              WHERE $where_clause";
$total_result = fetchRow($count_sql, ...$query_params);
$total_patients = $total_result['total'];
$total_pages = ceil($total_patients / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Doctor Dashboard</title>
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
                <a href="patients.php" class="sidebar-link active">
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
                <h1>Patients</h1>
                <p>View and manage your patients</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Search Patients</h3>
                </div>
                <div class="card-body">
                    <form action="" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" id="search" name="search" class="form-control" value="<?php echo $search; ?>" placeholder="Search by name or patient ID">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="patients.php" class="btn btn-secondary">
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
                    <h3>Patient List</h3>
                    <div class="card-header-actions">
                        <span class="badge badge-primary"><?php echo $total_patients; ?> Patients Found</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($patients) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Name</th>
                                        <th>Age/Gender</th>
                                        <th>Contact</th>
                                        <th>Last Visit</th>
                                        <th>Total Visits</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr>
                                            <td><?php echo $patient['patient_unique_id']; ?></td>
                                            <td><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></td>
                                            <td>
                                                <?php echo calculateAge($patient['date_of_birth']); ?> years / 
                                                <?php echo ucfirst($patient['gender']); ?>
                                                <?php if (!empty($patient['blood_group'])): ?>
                                                    <span class="badge badge-danger"><?php echo $patient['blood_group']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-envelope text-muted"></i> <?php echo $patient['email']; ?><br>
                                                <i class="fas fa-phone text-muted"></i> <?php echo $patient['phone']; ?>
                                            </td>
                                            <td>
                                                <?php echo !empty($patient['last_visit']) ? date('M d, Y', strtotime($patient['last_visit'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $patient['appointment_count']; ?> visits</span>
                                            </td>
                                            <td>
                                                <a href="patient_details.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-info" title="View Patient Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="patient_records.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-primary" title="View Medical Records">
                                                    <i class="fas fa-file-medical"></i>
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
                                    <a href="?page=<?php echo ($page - 1); ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?>" class="pagination-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo ($page + 1); ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No patients found matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>

<?php
// Helper function to calculate age from date of birth
function calculateAge($dob) {
    $today = new DateTime();
    $birthdate = new DateTime($dob);
    $interval = $today->diff($birthdate);
    return $interval->y;
}
?>