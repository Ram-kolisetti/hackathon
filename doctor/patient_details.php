<?php
// Patient Details Page
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

// Check if patient ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: patients.php?error=Invalid patient ID');
    exit;
}

$patient_id = (int)$_GET['id'];

// Get patient details
$sql = "SELECT pp.*, u.first_name, u.last_name, u.email, u.phone, u.profile_image
        FROM patient_profiles pp
        JOIN users u ON pp.user_id = u.user_id
        WHERE pp.patient_id = ?";

$patient = fetchRow($sql, "i", [$patient_id]);

// If patient not found
if (!$patient) {
    header('Location: patients.php?error=Patient not found');
    exit;
}

// Check if this doctor has treated this patient
$sql = "SELECT COUNT(*) as count FROM appointments 
        WHERE doctor_id = ? AND patient_id = ?";
$result = fetchRow($sql, "ii", [$doctor_id, $patient_id]);

if ($result['count'] == 0) {
    header('Location: patients.php?error=Unauthorized access to patient data');
    exit;
}

// Get patient's appointments with this doctor
$sql = "SELECT a.*, 
        h.name as hospital_name,
        d.name as department_name,
        (SELECT COUNT(*) FROM medical_records WHERE appointment_id = a.appointment_id) as has_record
        FROM appointments a
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.patient_id = ? AND a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$appointments = fetchRows($sql, "ii", [$patient_id, $doctor_id]);

// Get patient's medical records with this doctor
$sql = "SELECT mr.*, a.appointment_date, a.appointment_time
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.appointment_id
        WHERE mr.patient_id = ? AND mr.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$medical_records = fetchRows($sql, "ii", [$patient_id, $doctor_id]);

// Get patient's documents
$sql = "SELECT d.* 
        FROM documents d
        JOIN appointments a ON d.appointment_id = a.appointment_id
        WHERE d.patient_id = ? AND a.doctor_id = ?
        ORDER BY d.upload_date DESC";

$documents = fetchRows($sql, "ii", [$patient_id, $doctor_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .patient-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .patient-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--gray-600);
            overflow: hidden;
        }
        
        .patient-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .patient-info {
            flex: 1;
        }
        
        .patient-name {
            font-size: var(--font-size-xl);
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
        }
        
        .patient-id {
            color: var(--gray-600);
            margin-bottom: var(--spacing-sm);
        }
        
        .patient-badges {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
        }
        
        .patient-contact {
            display: flex;
            gap: var(--spacing-lg);
        }
        
        .patient-contact-item {
            display: flex;
            align-items: center;
        }
        
        .patient-contact-item i {
            margin-right: var(--spacing-xs);
            color: var(--primary-color);
        }
        
        .patient-stats {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .stat-card {
            flex: 1;
            background-color: white;
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
            text-align: center;
        }
        
        .stat-value {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }
        
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid var(--gray-300);
            margin-bottom: var(--spacing-md);
        }
        
        .tab-item {
            padding: var(--spacing-sm) var(--spacing-md);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all var(--transition-normal);
        }
        
        .tab-item:hover {
            color: var(--primary-color);
        }
        
        .tab-item.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-row {
            display: flex;
            margin-bottom: var(--spacing-sm);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .info-label {
            width: 200px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .info-value {
            flex: 1;
        }
        
        .appointment-item {
            background-color: white;
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .appointment-date {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .appointment-details {
            margin-bottom: var(--spacing-sm);
            color: var(--gray-700);
        }
        
        .appointment-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-sm);
        }
        
        .record-item {
            background-color: white;
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .record-date {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .record-content {
            margin-bottom: var(--spacing-sm);
        }
        
        .record-label {
            font-weight: 500;
            margin-bottom: var(--spacing-xs);
            color: var(--gray-700);
        }
        
        .record-text {
            background-color: var(--gray-100);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: var(--spacing-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-sm);
        }
        
        .document-icon {
            font-size: var(--font-size-xl);
            margin-right: var(--spacing-sm);
            color: var(--primary-color);
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-weight: 500;
        }
        
        .document-meta {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        
        .document-actions {
            margin-left: var(--spacing-sm);
        }
    </style>
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
                <h1>Patient Details</h1>
                <p>
                    <a href="patients.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Patients
                    </a>
                </p>
            </div>
            
            <div class="patient-header">
                <div class="patient-avatar">
                    <?php if (!empty($patient['profile_image'])): ?>
                        <img src="<?php echo $patient['profile_image']; ?>" alt="Patient Profile">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="patient-info">
                    <div class="patient-name"><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></div>
                    <div class="patient-id">Patient ID: <?php echo $patient['patient_unique_id']; ?></div>
                    <div class="patient-badges">
                        <span class="badge badge-info"><?php echo ucfirst($patient['gender']); ?></span>
                        <span class="badge badge-secondary"><?php echo calculateAge($patient['date_of_birth']); ?> years</span>
                        <?php if (!empty($patient['blood_group'])): ?>
                            <span class="badge badge-danger"><?php echo $patient['blood_group']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="patient-contact">
                        <div class="patient-contact-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo $patient['email']; ?>
                        </div>
                        <div class="patient-contact-item">
                            <i class="fas fa-phone"></i>
                            <?php echo $patient['phone']; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="patient-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($appointments); ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($medical_records); ?></div>
                    <div class="stat-label">Medical Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php 
                        $completed = 0;
                        foreach ($appointments as $appointment) {
                            if ($appointment['status'] == 'completed') {
                                $completed++;
                            }
                        }
                        echo $completed;
                        ?>
                    </div>
                    <div class="stat-label">Completed Visits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($documents); ?></div>
                    <div class="stat-label">Documents</div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <div class="tab-navigation">
                        <div class="tab-item active" data-tab="personal-info">Personal Information</div>
                        <div class="tab-item" data-tab="appointments">Appointments</div>
                        <div class="tab-item" data-tab="medical-records">Medical Records</div>
                        <div class="tab-item" data-tab="documents">Documents</div>
                    </div>
                    
                    <!-- Personal Information Tab -->
                    <div class="tab-content active" id="personal-info">
                        <div class="info-row">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Patient ID</div>
                            <div class="info-value"><?php echo $patient['patient_unique_id']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($patient['date_of_birth'])); ?> (<?php echo calculateAge($patient['date_of_birth']); ?> years)</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Gender</div>
                            <div class="info-value"><?php echo ucfirst($patient['gender']); ?></div>
                        </div>
                        <?php if (!empty($patient['blood_group'])): ?>
                            <div class="info-row">
                                <div class="info-label">Blood Group</div>
                                <div class="info-value"><?php echo $patient['blood_group']; ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo $patient['email']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo $patient['phone']; ?></div>
                        </div>
                        <?php if (!empty($patient['address'])): ?>
                            <div class="info-row">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo $patient['address']; ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['city']) || !empty($patient['state']) || !empty($patient['zip_code'])): ?>
                            <div class="info-row">
                                <div class="info-label">City/State/Zip</div>
                                <div class="info-value">
                                    <?php echo !empty($patient['city']) ? $patient['city'] : ''; ?>
                                    <?php echo !empty($patient['state']) ? ', ' . $patient['state'] : ''; ?>
                                    <?php echo !empty($patient['zip_code']) ? ' ' . $patient['zip_code'] : ''; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['emergency_contact_name']) || !empty($patient['emergency_contact_phone'])): ?>
                            <div class="info-row">
                                <div class="info-label">Emergency Contact</div>
                                <div class="info-value">
                                    <?php echo !empty($patient['emergency_contact_name']) ? $patient['emergency_contact_name'] : ''; ?>
                                    <?php echo !empty($patient['emergency_contact_phone']) ? ' - ' . $patient['emergency_contact_phone'] : ''; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['allergies'])): ?>
                            <div class="info-row">
                                <div class="info-label">Allergies</div>
                                <div class="info-value"><?php echo nl2br($patient['allergies']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Appointments Tab -->
                    <div class="tab-content" id="appointments">
                        <?php if (count($appointments) > 0): ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="appointment-item">
                                    <div class="appointment-header">
                                        <div class="appointment-date">
                                            <?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?> at 
                                            <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                        <div>
                                            <span class="badge badge-<?php 
                                                echo ($appointment['status'] == 'scheduled') ? 'primary' : 
                                                    (($appointment['status'] == 'completed') ? 'success' : 
                                                        (($appointment['status'] == 'cancelled') ? 'danger' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="appointment-details">
                                        <div><strong>Hospital:</strong> <?php echo $appointment['hospital_name']; ?></div>
                                        <div><strong>Department:</strong> <?php echo $appointment['department_name']; ?></div>
                                        <?php if (!empty($appointment['symptoms'])): ?>
                                            <div class="mt-2"><strong>Symptoms:</strong></div>
                                            <div class="symptoms-box"><?php echo nl2br($appointment['symptoms']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="appointment-actions">
                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <?php if ($appointment['status'] == 'completed' && $appointment['has_record'] > 0): ?>
                                            <a href="view_record.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-file-medical"></i> View Medical Record
                                            </a>
                                        <?php elseif ($appointment['status'] == 'completed' && $appointment['has_record'] == 0): ?>
                                            <a href="create_record.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-plus"></i> Create Medical Record
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No appointments found for this patient.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Medical Records Tab -->
                    <div class="tab-content" id="medical-records">
                        <?php if (count($medical_records) > 0): ?>
                            <?php foreach ($medical_records as $record): ?>
                                <div class="record-item">
                                    <div class="record-header">
                                        <div class="record-date">
                                            <?php echo date('F d, Y', strtotime($record['appointment_date'])); ?> at 
                                            <?php echo date('h:i A', strtotime($record['appointment_time'])); ?>
                                        </div>
                                        <div>
                                            <a href="view_record.php?id=<?php echo $record['record_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View Full Record
                                            </a>
                                        </div>
                                    </div>
                                    <div class="record-content">
                                        <div class="record-label">Diagnosis</div>
                                        <div class="record-text"><?php echo nl2br($record['diagnosis']); ?></div>
                                        
                                        <?php if (!empty($record['prescription'])): ?>
                                            <div class="record-label">Prescription</div>
                                            <div class="record-text"><?php echo nl2br($record['prescription']); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($record['follow_up_date'])): ?>
                                            <div><strong>Follow-up Date:</strong> <?php echo date('F d, Y', strtotime($record['follow_up_date'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No medical records found for this patient.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Documents Tab -->
                    <div class="tab-content" id="documents">
                        <?php if (count($documents) > 0): ?>
                            <?php foreach ($documents as $document): ?>
                                <div class="document-item">
                                    <div class="document-icon">
                                        <?php 
                                        $extension = pathinfo($document['file_name'], PATHINFO_EXTENSION);
                                        $icon = 'file';
                                        
                                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                            $icon = 'file-image';
                                        } elseif (in_array($extension, ['pdf'])) {
                                            $icon = 'file-pdf';
                                        } elseif (in_array($extension, ['doc', 'docx'])) {
                                            $icon = 'file-word';
                                        }
                                        ?>
                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="document-info">
                                        <div class="document-name"><?php echo $document['file_name']; ?></div>
                                        <div class="document-meta">
                                            <?php echo formatFileSize($document['file_size']); ?> • 
                                            <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="document-actions">
                                        <a href="../uploads/documents/<?php echo $document['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No documents found for this patient.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab navigation
            const tabItems = document.querySelectorAll('.tab-item');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabItems.forEach(function(tab) {
                        tab.classList.remove('active');
                    });
                    
                    // Hide all tab contents
                    tabContents.forEach(function(content) {
                        content.classList.remove('active');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
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

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}