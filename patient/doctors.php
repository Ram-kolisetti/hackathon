<?php
// Doctors listing page for patients
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include authentication and database functions
require_once(BASE_PATH . '/includes/auth.php');
require_once(BASE_PATH . '/config/database.php');

// Check if user is logged in and is a patient
if (!isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

// Get hospital ID from query parameter
$hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;

if (!$hospital_id) {
    header('Location: hospitals.php');
    exit;
}

// Get hospital and its doctors from database
$hospital = null;
$doctors = [];
try {
    $db = getDBConnection();
    
    // Get hospital details
    $query = "SELECT * FROM hospitals WHERE id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$hospital_id]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hospital) {
        header('Location: hospitals.php');
        exit;
    }
    
    // Get doctors for this hospital
    $query = "SELECT d.id, d.name, d.specialty, d.experience_years, d.education,
              d.consultation_fee, d.rating,
              (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id 
               AND a.status = 'completed') as total_appointments
              FROM doctors d 
              WHERE d.hospital_id = ? AND d.status = 'active'
              ORDER BY d.rating DESC, d.experience_years DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$hospital_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors at <?php echo htmlspecialchars($hospital['name']); ?> - MedConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include('../includes/patient-sidebar.php'); ?>
        
        <div class="main-content">
            <div class="breadcrumb">
                <a href="hospitals.php">Hospitals</a> &gt; 
                <?php echo htmlspecialchars($hospital['name']); ?>
            </div>
            
            <h1>Doctors at <?php echo htmlspecialchars($hospital['name']); ?></h1>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="doctors-grid">
                <?php foreach ($doctors as $doctor): ?>
                <div class="doctor-card">
                    <div class="doctor-header">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-rating">
                            <?php
                            $rating = round($doctor['rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $rating 
                                    ? '<i class="fas fa-star"></i>' 
                                    : '<i class="far fa-star"></i>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <h2><?php echo htmlspecialchars($doctor['name']); ?></h2>
                    
                    <p class="doctor-specialty">
                        <i class="fas fa-stethoscope"></i>
                        <?php echo htmlspecialchars($doctor['specialty']); ?>
                    </p>
                    
                    <p class="doctor-experience">
                        <i class="fas fa-clock"></i>
                        <?php echo $doctor['experience_years']; ?> Years Experience
                    </p>
                    
                    <p class="doctor-education">
                        <i class="fas fa-graduation-cap"></i>
                        <?php echo htmlspecialchars($doctor['education']); ?>
                    </p>
                    
                    <p class="consultation-fee">
                        <i class="fas fa-dollar-sign"></i>
                        Consultation Fee: $<?php echo number_format($doctor['consultation_fee'], 2); ?>
                    </p>
                    
                    <p class="appointment-count">
                        <i class="fas fa-calendar-check"></i>
                        <?php echo $doctor['total_appointments']; ?> Consultations
                    </p>
                    
                    <a href="book_appointment.php?doctor_id=<?php echo $doctor['id']; ?>" 
                       class="btn btn-primary">
                        Book Appointment
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>