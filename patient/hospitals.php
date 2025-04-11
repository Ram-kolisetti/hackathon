<?php
// Hospitals listing page for patients
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

// Get all hospitals from database
$hospitals = [];
try {
    $db = getDBConnection();
    $query = "SELECT h.id, h.name, h.address, h.phone, h.email, 
              (SELECT COUNT(*) FROM doctors d WHERE d.hospital_id = h.id) as doctor_count
              FROM hospitals h WHERE h.status = 'active' ORDER BY h.name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospitals - MedConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include('../includes/patient-sidebar.php'); ?>
        
        <div class="main-content">
            <h1>Available Hospitals</h1>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="hospitals-grid">
                <?php foreach ($hospitals as $hospital): ?>
                <div class="hospital-card">
                    <div class="hospital-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h2><?php echo htmlspecialchars($hospital['name']); ?></h2>
                    <p class="hospital-address">
                        <i class="fas fa-location-dot"></i>
                        <?php echo htmlspecialchars($hospital['address']); ?>
                    </p>
                    <p class="hospital-contact">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($hospital['phone']); ?>
                    </p>
                    <p class="hospital-email">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($hospital['email']); ?>
                    </p>
                    <p class="doctor-count">
                        <i class="fas fa-user-md"></i>
                        <?php echo $hospital['doctor_count']; ?> Doctors Available
                    </p>
                    <a href="doctors.php?hospital_id=<?php echo $hospital['id']; ?>" class="btn btn-primary">
                        View Doctors
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>