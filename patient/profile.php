<?php
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include required files
require_once(BASE_PATH . '/includes/auth.php');
require_once(BASE_PATH . '/config/database.php');

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: ../login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$page_title = 'My Profile';
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $blood_group = trim($_POST['blood_group']);
    $allergies = trim($_POST['allergies']);
    $medical_conditions = trim($_POST['medical_conditions']);
    
    // Update patient information
    $sql = "UPDATE patients SET 
            name = ?, 
            email = ?, 
            phone = ?, 
            address = ?, 
            blood_group = ?, 
            allergies = ?, 
            medical_conditions = ? 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssi', 
        $name, 
        $email, 
        $phone, 
        $address, 
        $blood_group, 
        $allergies, 
        $medical_conditions, 
        $patient_id
    );
    
    if ($stmt->execute()) {
        $success_message = 'Profile updated successfully!';
    } else {
        $error_message = 'Error updating profile. Please try again.';
    }
}

// Fetch patient information
$sql = "SELECT * FROM patients WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

// Include header
include_once('../includes/header.php');
?>

<div class="container my-4">
    <h2 class="mb-4">My Profile</h2>
    
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
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="blood_group" class="form-label">Blood Group</label>
                        <select class="form-select" id="blood_group" name="blood_group" required>
                            <?php
                            $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($blood_groups as $bg) {
                                $selected = ($patient['blood_group'] === $bg) ? 'selected' : '';
                                echo "<option value=\"$bg\" $selected>$bg</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" 
                                  rows="3"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" 
                                  rows="3"><?php echo htmlspecialchars($patient['allergies']); ?></textarea>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="medical_conditions" class="form-label">Medical Conditions</label>
                        <textarea class="form-control" id="medical_conditions" name="medical_conditions" 
                                  rows="3"><?php echo htmlspecialchars($patient['medical_conditions']); ?></textarea>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once('../includes/footer.php'); ?>