<?php
// View Medical Record Page
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

// Check if record ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: medical_records.php?error=Invalid record ID');
    exit;
}

$record_id = (int)$_GET['id'];

// Get medical record details
$sql = "SELECT mr.*, 
        a.appointment_date, a.appointment_time, a.symptoms,
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
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.appointment_id
        JOIN patient_profiles pp ON mr.patient_id = pp.patient_id
        JOIN users u ON pp.user_id = u.user_id
        JOIN hospitals h ON a.hospital_id = h.hospital_id
        JOIN departments d ON a.department_id = d.department_id
        WHERE mr.record_id = ? AND mr.doctor_id = ?";

$record = fetchRow($sql, "ii", [$record_id, $doctor_id]);

// If record not found or doesn't belong to this doctor
if (!$record) {
    header('Location: medical_records.php?error=Record not found or unauthorized access');
    exit;
}

// Get patient's documents for this appointment
$sql = "SELECT * FROM documents WHERE appointment_id = ? ORDER BY upload_date DESC";
$documents = fetchRows($sql, "i", [$record['appointment_id']]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Medical Record - Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
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
        
        .record-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .record-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            margin-bottom: var(--spacing-md);
            color: var(--primary-color);
            border-bottom: 1px solid var(--gray-300);
            padding-bottom: var(--spacing-sm);
        }
        
        .record-row {
            display: flex;
            margin-bottom: var(--spacing-sm);
        }
        
        .record-label {
            width: 150px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .record-value {
            flex: 1;
        }
        
        .content-box {
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
        
        @media print {
            .sidebar, .navbar, .page-header, .action-buttons, .no-print {
                display: none !important;
            }
            
            .content-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
            
            .record-card, .patient-info-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                margin-bottom: 15px !important;
            }
            
            body {
                background-color: white !important;
            }
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
                <h1>Medical Record</h1>
                <p>
                    <a href="medical_records.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Records
                    </a>
                </p>
            </div>
            
            <div class="record-header">
                <h2>Medical Record #<?php echo $record_id; ?></h2>
                <div class="record-date">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($record['appointment_date'])); ?>
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
                            <div class="patient-name"><?php echo $record['patient_name']; ?></div>
                            <div class="patient-id">Patient ID: <?php echo $record['patient_unique_id']; ?></div>
                            <div>
                                <span class="badge badge-info"><?php echo $record['gender']; ?></span>
                                <span class="badge badge-secondary"><?php echo calculateAge($record['date_of_birth']); ?> years</span>
                                <?php if (!empty($record['blood_group'])): ?>
                                    <span class="badge badge-danger"><?php echo $record['blood_group']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="patient-contact">
                                <div class="patient-contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo $record['patient_email']; ?>
                                </div>
                                <div class="patient-contact-item">
                                    <i class="fas fa-phone"></i>
                                    <?php echo $record['patient_phone']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appointment Details -->
                    <div class="record-card">
                        <div class="record-title">Appointment Details</div>
                        <div class="record-row">
                            <div class="record-label">Date & Time:</div>
                            <div class="record-value">
                                <?php echo date('F d, Y', strtotime($record['appointment_date'])); ?> at 
                                <?php echo date('h:i A', strtotime($record['appointment_time'])); ?>
                            </div>
                        </div>
                        <div class="record-row">
                            <div class="record-label">Hospital:</div>
                            <div class="record-value"><?php echo $record['hospital_name']; ?></div>
                        </div>
                        <div class="record-row">
                            <div class="record-label">Department:</div>
                            <div class="record-value"><?php echo $record['department_name']; ?></div>
                        </div>
                        <?php if (!empty($record['symptoms'])): ?>
                            <div class="record-row">
                                <div class="record-label">Symptoms:</div>
                                <div class="record-value">
                                    <div class="content-box">
                                        <?php echo nl2br($record['symptoms']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($record['allergies'])): ?>
                            <div class="record-row">
                                <div class="record-label">Allergies:</div>
                                <div class="record-value">
                                    <div class="content-box">
                                        <?php echo nl2br($record['allergies']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Medical Record Details -->
                    <div class="record-card">
                        <div class="record-title">Medical Record</div>
                        <div class="record-row">
                            <div class="record-label">Diagnosis:</div>
                            <div class="record-value">
                                <div class="content-box">
                                    <?php echo nl2br($record['diagnosis']); ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($record['prescription'])): ?>
                            <div class="record-row">
                                <div class="record-label">Prescription:</div>
                                <div class="record-value">
                                    <div class="content-box">
                                        <?php echo nl2br($record['prescription']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($record['notes'])): ?>
                            <div class="record-row">
                                <div class="record-label">Notes:</div>
                                <div class="record-value">
                                    <div class="content-box">
                                        <?php echo nl2br($record['notes']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($record['follow_up_date'])): ?>
                            <div class="record-row">
                                <div class="record-label">Follow-up Date:</div>
                                <div class="record-value"><?php echo date('F d, Y', strtotime($record['follow_up_date'])); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="record-row">
                            <div class="record-label">Created:</div>
                            <div class="record-value"><?php echo date('F d, Y H:i', strtotime($record['created_at'])); ?></div>
                        </div>
                        <?php if ($record['updated_at'] != $record['created_at']): ?>
                            <div class="record-row">
                                <div class="record-label">Last Updated:</div>
                                <div class="record-value"><?php echo date('F d, Y H:i', strtotime($record['updated_at'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons no-print">
                            <a href="create_record.php?appointment_id=<?php echo $record['appointment_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Record
                            </a>
                            <button onclick="window.print()" class="btn btn-secondary">
                                <i class="fas fa-print"></i> Print Record
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 no-print">
                    <!-- Patient Documents -->
                    <div class="record-card">
                        <div class="record-title">Patient Documents</div>
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