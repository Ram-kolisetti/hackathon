<?php
// Create/Edit Medical Record Page
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
if (!isset($_GET['appointment_id']) || empty($_GET['appointment_id'])) {
    header('Location: appointments.php?error=Invalid appointment ID');
    exit;
}

$appointment_id = (int)$_GET['appointment_id'];

// Get appointment details
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as patient_name,
        pp.patient_unique_id,
        pp.patient_id,
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

// Check if the appointment is completed
if ($appointment['status'] != 'completed') {
    // Update appointment status to completed
    $update_sql = "UPDATE appointments SET status = 'completed' WHERE appointment_id = ?";
    executeNonQuery($update_sql, "i", [$appointment_id]);
    
    // Create notification for patient
    $get_user_sql = "SELECT user_id FROM patient_profiles WHERE patient_id = ?";
    $patient = fetchRow($get_user_sql, "i", [$appointment['patient_id']]);
    
    if ($patient) {
        $notification_sql = "INSERT INTO notifications (user_id, title, message) 
                          VALUES (?, 'Appointment Completed', 'Your appointment on " . 
                          date('d M Y', strtotime($appointment['appointment_date'])) . " has been marked as completed.')";
        insertData($notification_sql, "i", [$patient['user_id']]);
    }
}

// Check if there's an existing medical record
$sql = "SELECT * FROM medical_records WHERE appointment_id = ?";
$medical_record = fetchRow($sql, "i", [$appointment_id]);

$success = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $diagnosis = sanitizeInput($_POST['diagnosis']);
    $prescription = sanitizeInput($_POST['prescription']);
    $notes = sanitizeInput($_POST['notes']);
    $follow_up_date = !empty($_POST['follow_up_date']) ? sanitizeInput($_POST['follow_up_date']) : null;
    
    // Validate form data
    if (empty($diagnosis)) {
        $error = 'Diagnosis is required';
    } else {
        if ($medical_record) {
            // Update existing record
            $sql = "UPDATE medical_records 
                    SET diagnosis = ?, prescription = ?, notes = ?, follow_up_date = ?, updated_at = NOW() 
                    WHERE record_id = ?";
            $result = executeNonQuery($sql, "ssssi", [
                $diagnosis, $prescription, $notes, $follow_up_date, $medical_record['record_id']
            ]);
            
            if ($result) {
                $success = 'Medical record updated successfully';
            } else {
                $error = 'Failed to update medical record';
            }
        } else {
            // Create new record
            $sql = "INSERT INTO medical_records (appointment_id, patient_id, doctor_id, diagnosis, prescription, notes, follow_up_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $record_id = insertData($sql, "iiissss", [
                $appointment_id, $appointment['patient_id'], $doctor_id, $diagnosis, $prescription, $notes, $follow_up_date
            ]);
            
            if ($record_id) {
                // Create notification for patient
                $get_user_sql = "SELECT user_id FROM patient_profiles WHERE patient_id = ?";
                $patient = fetchRow($get_user_sql, "i", [$appointment['patient_id']]);
                
                if ($patient) {
                    $notification_sql = "INSERT INTO notifications (user_id, title, message) 
                                      VALUES (?, 'Medical Record Created', 'Your medical record for the appointment on " . 
                                      date('d M Y', strtotime($appointment['appointment_date'])) . " has been created.')";
                    insertData($notification_sql, "i", [$patient['user_id']]);
                }
                
                $success = 'Medical record created successfully';
                
                // Refresh the page to get the updated record
                header("Location: create_record.php?appointment_id=$appointment_id&success=$success");
                exit;
            } else {
                $error = 'Failed to create medical record';
            }
        }
    }
}

// Get success/error messages from URL
if (isset($_GET['success']) && empty($success)) {
    $success = sanitizeInput($_GET['success']);
}

if (isset($_GET['error']) && empty($error)) {
    $error = sanitizeInput($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $medical_record ? 'Edit' : 'Create'; ?> Medical Record - Doctor Dashboard</title>
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
                <h1><?php echo $medical_record ? 'Edit' : 'Create'; ?> Medical Record</h1>
                <p>
                    <a href="appointment_details.php?id=<?php echo $appointment_id; ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Appointment
                    </a>
                </p>
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
                    <h3>Appointment Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Patient:</strong> <?php echo $appointment['patient_name']; ?> (<?php echo $appointment['patient_unique_id']; ?>)</p>
                            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></p>
                            <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Hospital:</strong> <?php echo $appointment['hospital_name']; ?></p>
                            <p><strong>Department:</strong> <?php echo $appointment['department_name']; ?></p>
                            <p><strong>Status:</strong> <span class="badge badge-success">Completed</span></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($appointment['symptoms'])): ?>
                        <div class="mt-3">
                            <h4>Patient Symptoms</h4>
                            <div class="symptoms-box">
                                <?php echo nl2br($appointment['symptoms']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3><?php echo $medical_record ? 'Edit' : 'Create'; ?> Medical Record</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="diagnosis" class="form-label">Diagnosis <span class="text-danger">*</span></label>
                            <textarea id="diagnosis" name="diagnosis" class="form-control" rows="4" required><?php echo $medical_record ? $medical_record['diagnosis'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="prescription" class="form-label">Prescription</label>
                            <textarea id="prescription" name="prescription" class="form-control" rows="4"><?php echo $medical_record ? $medical_record['prescription'] : ''; ?></textarea>
                            <small class="form-text text-muted">Enter each medication on a new line with dosage and instructions.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo $medical_record ? $medical_record['notes'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="follow_up_date" class="form-label">Follow-up Date</label>
                            <input type="date" id="follow_up_date" name="follow_up_date" class="form-control" value="<?php echo $medical_record && $medical_record['follow_up_date'] ? date('Y-m-d', strtotime($medical_record['follow_up_date'])) : ''; ?>">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $medical_record ? 'Update' : 'Save'; ?> Medical Record
                            </button>
                            <a href="appointment_details.php?id=<?php echo $appointment_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>