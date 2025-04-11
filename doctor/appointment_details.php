<?php
// Doctor Appointment Details Page
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

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: appointments.php?error=Invalid appointment ID');
    exit;
}

$appointment_id = (int)$_GET['id'];

// Get appointment details
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        u.email as patient_email,
        u.phone as patient_phone,
        pp.patient_unique_id,
        pp.date_of_birth,
        pp.gender,
        pp.blood_group,
        pp.allergies,
        h.name as hospital_name,
        d.name as department_name
        FROM appointments a
        JOIN patient_profiles pp ON a.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE a.appointment_id = ? AND a.doctor_id = ?";

$appointment = fetchRow($sql, "ii", [$appointment_id, $doctor_id]);

// If appointment not found or doesn't belong to this doctor
if (!$appointment) {
    header('Location: appointments.php?error=Appointment not found or unauthorized access');
    exit;
}

// Check if there's a medical record for this appointment
$sql = "SELECT * FROM medical_records WHERE appointment_id = ?";
$medical_record = fetchRow($sql, "i", [$appointment_id]);

// Get patient's previous appointments with this doctor
$sql = "SELECT a.*, mr.diagnosis, mr.prescription 
        FROM appointments a
        LEFT JOIN medical_records mr ON a.appointment_id = mr.appointment_id
        WHERE a.patient_id = ? AND a.doctor_id = ? AND a.appointment_id != ? AND a.status = 'completed'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$previous_appointments = fetchRows($sql, "iii", [$appointment['patient_id'], $doctor_id, $appointment_id]);

// Get patient's uploaded documents for this appointment
$sql = "SELECT * FROM documents WHERE appointment_id = ? ORDER BY upload_date DESC";
$documents = fetchRows($sql, "i", [$appointment_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .appointment-status {
            font-size: var(--font-size-lg);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-md);
            display: inline-block;
        }
        
        .patient-info-card {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
        }
        
        .patient-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-xl);
            color: var(--gray-600);
        }
        
        .patient-details {
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
        
        .patient-contact {
            display: flex;
            gap: var(--spacing-lg);
            margin-top: var(--spacing-sm);
        }
        
        .patient-contact-item {
            display: flex;
            align-items: center;
        }
        
        .patient-contact-item i {
            margin-right: var(--spacing-xs);
            color: var(--primary-color);
        }
        
        .appointment-details-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .appointment-details-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            margin-bottom: var(--spacing-md);
            color: var(--primary-color);
            border-bottom: 1px solid var(--gray-300);
            padding-bottom: var(--spacing-sm);
        }
        
        .appointment-detail-row {
            display: flex;
            margin-bottom: var(--spacing-sm);
        }
        
        .appointment-detail-label {
            width: 150px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .appointment-detail-value {
            flex: 1;
        }
        
        .symptoms-box {
            background-color: var(--gray-100);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
            margin-top: var(--spacing-sm);
        }
        
        .action-buttons {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }
        
        .medical-record-form {
            margin-top: var(--spacing-md);
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
        
        .previous-appointment {
            border-left: 3px solid var(--primary-color);
            padding-left: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .previous-appointment-date {
            font-weight: 500;
            margin-bottom: var(--spacing-xs);
        }
        
        .previous-appointment-details {
            font-size: var(--font-size-sm);
            color: var(--gray-700);
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
                <h1>Appointment Details</h1>
                <p>
                    <a href="appointments.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Appointments
                    </a>
                </p>
            </div>
            
            <div class="appointment-header">
                <h2>Appointment #<?php echo $appointment_id; ?></h2>
                <div class="appointment-status bg-<?php 
                    echo ($appointment['status'] == 'scheduled') ? 'primary' : 
                        (($appointment['status'] == 'completed') ? 'success' : 
                            (($appointment['status'] == 'cancelled') ? 'danger' : 'warning')); 
                ?>">
                    <?php echo ucfirst($appointment['status']); ?>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Patient Information -->
                    <div class="patient-info-card">
                        <div class="patient-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="patient-details">
                            <div class="patient-name"><?php echo $appointment['patient_name']; ?></div>
                            <div class="patient-id">Patient ID: <?php echo $appointment['patient_unique_id']; ?></div>
                            <div>
                                <span class="badge badge-info"><?php echo $appointment['gender']; ?></span>
                                <span class="badge badge-secondary"><?php echo calculateAge($appointment['date_of_birth']); ?> years</span>
                                <?php if (!empty($appointment['blood_group'])): ?>
                                    <span class="badge badge-danger"><?php echo $appointment['blood_group']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="patient-contact">
                                <div class="patient-contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo $appointment['patient_email']; ?>
                                </div>
                                <div class="patient-contact-item">
                                    <i class="fas fa-phone"></i>
                                    <?php echo $appointment['patient_phone']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appointment Details -->
                    <div class="appointment-details-card">
                        <div class="appointment-details-title">Appointment Details</div>
                        <div class="appointment-detail-row">
                            <div class="appointment-detail-label">Date & Time:</div>
                            <div class="appointment-detail-value">
                                <?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?> at 
                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                        </div>
                        <div class="appointment-detail-row">
                            <div class="appointment-detail-label">Hospital:</div>
                            <div class="appointment-detail-value"><?php echo $appointment['hospital_name']; ?></div>
                        </div>
                        <div class="appointment-detail-row">
                            <div class="appointment-detail-label">Department:</div>
                            <div class="appointment-detail-value"><?php echo $appointment['department_name']; ?></div>
                        </div>
                        <div class="appointment-detail-row">
                            <div class="appointment-detail-label">Payment Status:</div>
                            <div class="appointment-detail-value">
                                <span class="badge badge-<?php 
                                    echo ($appointment['payment_status'] == 'completed') ? 'success' : 
                                        (($appointment['payment_status'] == 'refunded') ? 'warning' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($appointment['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="appointment-detail-row">
                            <div class="appointment-detail-label">Symptoms:</div>
                            <div class="appointment-detail-value">
                                <div class="symptoms-box">
                                    <?php echo !empty($appointment['symptoms']) ? nl2br($appointment['symptoms']) : 'No symptoms provided'; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($appointment['allergies'])): ?>
                            <div class="appointment-detail-row">
                                <div class="appointment-detail-label">Allergies:</div>
                                <div class="appointment-detail-value">
                                    <div class="symptoms-box">
                                        <?php echo nl2br($appointment['allergies']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['status'] == 'scheduled'): ?>
                            <div class="action-buttons">
                                <a href="appointments.php?id=<?php echo $appointment_id; ?>&action=complete" class="btn btn-success" onclick="return confirm('Mark this appointment as completed?')">
                                    <i class="fas fa-check"></i> Mark as Completed
                                </a>
                                <a href="appointments.php?id=<?php echo $appointment_id; ?>&action=miss" class="btn btn-warning" onclick="return confirm('Mark this appointment as missed?')">
                                    <i class="fas fa-times"></i> Mark as Missed
                                </a>
                                <a href="appointments.php?id=<?php echo $appointment_id; ?>&action=cancel" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                    <i class="fas fa-ban"></i> Cancel Appointment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Medical Record Section -->
                    <?php if ($appointment['status'] == 'completed'): ?>
                        <div class="appointment-details-card">
                            <div class="appointment-details-title">
                                <?php echo $medical_record ? 'Medical Record' : 'Create Medical Record'; ?>
                            </div>
                            
                            <?php if ($medical_record): ?>
                                <div class="appointment-detail-row">
                                    <div class="appointment-detail-label">Diagnosis:</div>
                                    <div class="appointment-detail-value"><?php echo nl2br($medical_record['diagnosis']); ?></div>
                                </div>
                                <div class="appointment-detail-row">
                                    <div class="appointment-detail-label">Prescription:</div>
                                    <div class="appointment-detail-value"><?php echo nl2br($medical_record['prescription']); ?></div>
                                </div>
                                <?php if (!empty($medical_record['notes'])): ?>
                                    <div class="appointment-detail-row">
                                        <div class="appointment-detail-label">Notes:</div>
                                        <div class="appointment-detail-value"><?php echo nl2br($medical_record['notes']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($medical_record['follow_up_date'])): ?>
                                    <div class="appointment-detail-row">
                                        <div class="appointment-detail-label">Follow-up Date:</div>
                                        <div class="appointment-detail-value"><?php echo date('F d, Y', strtotime($medical_record['follow_up_date'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <a href="create_record.php?appointment_id=<?php echo $appointment_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit Medical Record
                                    </a>
                                </div>
                            <?php else: ?>
                                <p>No medical record has been created for this appointment yet.</p>
                                <div class="action-buttons">
                                    <a href="create_record.php?appointment_id=<?php echo $appointment_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Create Medical Record
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <!-- Patient Documents -->
                    <div class="appointment-details-card">
                        <div class="appointment-details-title">Patient Documents</div>
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
                            <p>No documents uploaded for this appointment.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Previous Appointments -->
                    <div class="appointment-details-card">
                        <div class="appointment-details-title">Previous Medical History</div>
                        <?php if (count($previous_appointments) > 0): ?>
                            <?php foreach ($previous_appointments as $prev): ?>
                                <div class="previous-appointment">
                                    <div class="previous-appointment-date">
                                        <?php echo date('F d, Y', strtotime($prev['appointment_date'])); ?>
                                    </div>
                                    <?php if (!empty($prev['diagnosis'])): ?>
                                        <div class="previous-appointment-details">
                                            <strong>Diagnosis:</strong> <?php echo $prev['diagnosis']; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($prev['prescription'])): ?>
                                        <div class="previous-appointment-details">
                                            <strong>Prescription:</strong> <?php echo $prev['prescription']; ?>
                                        </div>
                                    <?php endif; ?>
                                    <a href="appointment_details.php?id=<?php echo $prev['appointment_id']; ?>" class="btn btn-sm btn-link">
                                        View Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No previous appointments with this patient.</p>
                        <?php endif; ?>
                    </div>
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
?>