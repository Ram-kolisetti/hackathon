<?php
require_once('../includes/auth.php');
require_once('../config/database.php');

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$page_title = 'Medical History';

// Fetch medical records
$sql = "SELECT mr.*, d.name as doctor_name, h.name as hospital_name 
        FROM medical_records mr 
        JOIN doctors d ON mr.doctor_id = d.id 
        JOIN hospitals h ON d.hospital_id = h.id 
        WHERE mr.patient_id = ? 
        ORDER BY mr.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);

// Include header
include_once('../includes/header.php');
?>

<div class="container my-4">
    <h2 class="mb-4">Medical History</h2>
    
    <?php if (empty($records)): ?>
    <div class="alert alert-info">
        No medical records found.
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($records as $record): ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($record['diagnosis']); ?></h5>
                    <h6 class="card-subtitle mb-2 text-muted">
                        Dr. <?php echo htmlspecialchars($record['doctor_name']); ?>
                        <small class="text-muted d-block">
                            <?php echo htmlspecialchars($record['hospital_name']); ?>
                        </small>
                    </h6>
                    <p class="card-text">
                        <strong>Symptoms:</strong><br>
                        <?php echo nl2br(htmlspecialchars($record['symptoms'])); ?>
                    </p>
                    <p class="card-text">
                        <strong>Treatment:</strong><br>
                        <?php echo nl2br(htmlspecialchars($record['treatment'])); ?>
                    </p>
                    <p class="card-text">
                        <strong>Prescription:</strong><br>
                        <?php echo nl2br(htmlspecialchars($record['prescription'])); ?>
                    </p>
                    <div class="text-muted">
                        <small>Date: <?php echo date('M d, Y', strtotime($record['created_at'])); ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include_once('../includes/footer.php'); ?>