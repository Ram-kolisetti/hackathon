<?php
require_once('../includes/auth.php');
require_once('../config/database.php');

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$page_title = 'Submit Symptoms';
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'];
    $symptoms = trim($_POST['symptoms']);
    $duration = trim($_POST['duration']);
    $severity = trim($_POST['severity']);
    
    // Insert symptoms
    $sql = "INSERT INTO symptoms (patient_id, appointment_id, description, duration, severity, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iisss', 
        $patient_id,
        $appointment_id,
        $symptoms,
        $duration,
        $severity
    );
    
    if ($stmt->execute()) {
        $success_message = 'Symptoms submitted successfully!';
    } else {
        $error_message = 'Error submitting symptoms. Please try again.';
    }
}

// Fetch upcoming appointments
$sql = "SELECT a.*, d.name as doctor_name, h.name as hospital_name 
        FROM appointments a 
        JOIN doctors d ON a.doctor_id = d.id 
        JOIN hospitals h ON d.hospital_id = h.id 
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() 
        ORDER BY a.appointment_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// Include header
include_once('../includes/header.php');
?>

<div class="container my-4">
    <h2 class="mb-4">Submit Symptoms</h2>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <?php echo $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($appointments)): ?>
    <div class="alert alert-info">
        No upcoming appointments found. Please book an appointment first.
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="appointment_id" class="form-label">Select Appointment</label>
                    <select class="form-select" id="appointment_id" name="appointment_id" required>
                        <option value="">Choose appointment...</option>
                        <?php foreach ($appointments as $appointment): ?>
                            <option value="<?php echo $appointment['id']; ?>">
                                <?php 
                                echo date('M d, Y', strtotime($appointment['appointment_date'])) . ' - ' .
                                     'Dr. ' . htmlspecialchars($appointment['doctor_name']) . ' - ' .
                                     htmlspecialchars($appointment['hospital_name']);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="symptoms" class="form-label">Describe Your Symptoms</label>
                    <textarea class="form-control" id="symptoms" name="symptoms" 
                              rows="4" required placeholder="Please describe your symptoms in detail..."></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="duration" class="form-label">Duration of Symptoms</label>
                        <select class="form-select" id="duration" name="duration" required>
                            <option value="">Select duration...</option>
                            <option value="1-2 days">1-2 days</option>
                            <option value="3-7 days">3-7 days</option>
                            <option value="1-2 weeks">1-2 weeks</option>
                            <option value="2-4 weeks">2-4 weeks</option>
                            <option value="1-6 months">1-6 months</option>
                            <option value="6+ months">6+ months</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="severity" class="form-label">Severity Level</label>
                        <select class="form-select" id="severity" name="severity" required>
                            <option value="">Select severity...</option>
                            <option value="Mild">Mild - Noticeable but not interfering with daily activities</option>
                            <option value="Moderate">Moderate - Some interference with daily activities</option>
                            <option value="Severe">Severe - Significant interference with daily activities</option>
                            <option value="Very Severe">Very Severe - Unable to perform daily activities</option>
                        </select>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Submit Symptoms</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include_once('../includes/footer.php'); ?>