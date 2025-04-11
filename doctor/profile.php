<?php
// Doctor Profile Page
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

// Get user and doctor information
$user_id = $_SESSION['user_id'];
$doctor_id = $_SESSION['profile_id'];

// Get doctor details
$sql = "SELECT dp.*, u.*, h.name as hospital_name, d.name as department_name 
        FROM doctor_profiles dp 
        JOIN users u ON dp.user_id = u.user_id
        JOIN hospitals h ON dp.hospital_id = h.hospital_id 
        JOIN departments d ON dp.department_id = d.department_id 
        WHERE dp.doctor_id = ?";
$doctor = fetchRow($sql, "i", [$doctor_id]);

// Process form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $specialization = sanitizeInput($_POST['specialization']);
    $qualification = sanitizeInput($_POST['qualification']);
    $experience_years = (int)sanitizeInput($_POST['experience_years']);
    $consultation_fee = (float)sanitizeInput($_POST['consultation_fee']);
    $bio = sanitizeInput($_POST['bio']);
    
    // Validate form data
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $error = 'Please fill in all required fields';
    } else {
        // Update user data
        $user_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone
        ];
        
        // Update doctor profile data
        $doctor_data = [
            'specialization' => $specialization,
            'qualification' => $qualification,
            'experience_years' => $experience_years,
            'consultation_fee' => $consultation_fee,
            'bio' => $bio
        ];
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
                $error = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
            } elseif ($_FILES['profile_image']['size'] > $max_size) {
                $error = 'File size too large. Maximum size is 2MB.';
            } else {
                $upload_dir = BASE_PATH . '/uploads/profiles/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'doctor_' . $doctor_id . '_' . time() . '_' . $_FILES['profile_image']['name'];
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    $user_data['profile_image'] = '../uploads/profiles/' . $filename;
                } else {
                    $error = 'Failed to upload profile image';
                }
            }
        }
        
        if (empty($error)) {
            // Update user and doctor profile
            $result = updateUserProfile($user_id, $user_data, $doctor_data);
            
            if ($result['status']) {
                $success = 'Profile updated successfully';
                
                // Update session data
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                if (isset($user_data['profile_image'])) {
                    $_SESSION['profile_image'] = $user_data['profile_image'];
                }
                
                // Refresh doctor data
                $sql = "SELECT dp.*, u.*, h.name as hospital_name, d.name as department_name 
                        FROM doctor_profiles dp 
                        JOIN users u ON dp.user_id = u.user_id
                        JOIN hospitals h ON dp.hospital_id = h.hospital_id 
                        JOIN departments d ON dp.department_id = d.department_id 
                        WHERE dp.doctor_id = ?";
                $doctor = fetchRow($sql, "i", [$doctor_id]);
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Process password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long';
    } else {
        // Verify current password
        $sql = "SELECT password FROM users WHERE user_id = ?";
        $result = fetchRow($sql, "i", [$user_id]);
        
        if ($result && password_verify($current_password, $result['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_result = executeNonQuery($sql, "si", [$hashed_password, $user_id]);
            
            if ($update_result) {
                $success = 'Password changed successfully';
            } else {
                $error = 'Failed to update password';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .profile-avatar {
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
            position: relative;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: var(--primary-color);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-normal);
        }
        
        .profile-avatar-edit:hover {
            background-color: var(--primary-color-dark);
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: var(--font-size-xl);
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
        }
        
        .profile-id {
            color: var(--gray-600);
            margin-bottom: var(--spacing-sm);
        }
        
        .profile-details {
            display: flex;
            gap: var(--spacing-lg);
            margin-top: var(--spacing-sm);
        }
        
        .profile-detail {
            display: flex;
            align-items: center;
        }
        
        .profile-detail i {
            margin-right: var(--spacing-xs);
            color: var(--primary-color);
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
        
        .form-row {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-actions {
            margin-top: var(--spacing-lg);
            display: flex;
            gap: var(--spacing-md);
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
                <a href="profile.php" class="sidebar-link active">
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
                <h1>My Profile</h1>
                <p>View and update your profile information</p>
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
            
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if (!empty($doctor['profile_image'])): ?>
                        <img src="<?php echo $doctor['profile_image']; ?>" alt="Doctor Profile">
                    <?php else: ?>
                        <i class="fas fa-user-md"></i>
                    <?php endif; ?>
                    <label for="profile_image_upload" class="profile-avatar-edit" title="Change Profile Picture">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
                <div class="profile-info">
                    <div class="profile-name">Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?></div>
                    <div class="profile-id">Doctor ID: <?php echo $doctor['doctor_unique_id']; ?></div>
                    <div class="profile-details">
                        <div class="profile-detail">
                            <i class="fas fa-hospital"></i>
                            <?php echo $doctor['hospital_name']; ?>
                        </div>
                        <div class="profile-detail">
                            <i class="fas fa-stethoscope"></i>
                            <?php echo $doctor['department_name']; ?>
                        </div>
                        <div class="profile-detail">
                            <i class="fas fa-user-md"></i>
                            <?php echo $doctor['specialization']; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="tab-navigation">
                        <div class="tab-item active" data-tab="personal-info">Personal Information</div>
                        <div class="tab-item" data-tab="professional-info">Professional Information</div>
                        <div class="tab-item" data-tab="change-password">Change Password</div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="file" id="profile_image_upload" name="profile_image" style="display: none;" accept="image/*">
                        
                        <!-- Personal Information Tab -->
                        <div class="tab-content active" id="personal-info">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo $doctor['first_name']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo $doctor['last_name']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo $doctor['email']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $doctor['phone']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Username</div>
                                <div class="info-value"><?php echo $doctor['username']; ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Last Login</div>
                                <div class="info-value"><?php echo date('F d, Y H:i', strtotime($doctor['last_login'])); ?></div>
                            </div>
                        </div>
                        
                        <!-- Professional Information Tab -->
                        <div class="tab-content" id="professional-info">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="specialization" class="form-label">Specialization</label>
                                    <input type="text" id="specialization" name="specialization" class="form-control" value="<?php echo $doctor['specialization']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="qualification" class="form-label">Qualification</label>
                                    <input type="text" id="qualification" name="qualification" class="form-control" value="<?php echo $doctor['qualification']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="experience_years" class="form-label">Years of Experience</label>
                                    <input type="number" id="experience_years" name="experience_years" class="form-control" value="<?php echo $doctor['experience_years']; ?>" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label for="consultation_fee" class="form-label">Consultation Fee</label>
                                    <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" value="<?php echo $doctor['consultation_fee']; ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio" class="form-label">Professional Bio</label>
                                <textarea id="bio" name="bio" class="form-control" rows="5"><?php echo $doctor['bio']; ?></textarea>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">License Number</div>
                                <div class="info-value"><?php echo $doctor['license_number']; ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Hospital</div>
                                <div class="info-value"><?php echo $doctor['hospital_name']; ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo $doctor['department_name']; ?></div>
                            </div>
                        </div>
                        
                        <!-- Change Password Tab -->
                        <div class="tab-content" id="change-password">
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control">
                                    <small class="form-text text-muted">Password must be at least 8 characters long</small>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-actions" id="profile-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
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
            const profileActions = document.getElementById('profile-actions');
            
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
                    
                    // Show/hide profile actions based on active tab
                    if (tabId === 'change-password') {
                        profileActions.style.display = 'none';
                    } else {
                        profileActions.style.display = 'flex';
                    }
                });
            });
            
            // Profile image upload
            const profileImageUpload = document.getElementById('profile_image_upload');
            profileImageUpload.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const profileAvatar = document.querySelector('.profile-avatar');
                        
                        // Remove existing image if any
                        const existingImg = profileAvatar.querySelector('img');
                        if (existingImg) {
                            existingImg.remove();
                        }
                        
                        // Remove icon if any
                        const existingIcon = profileAvatar.querySelector('i:not(.fa-camera)');
                        if (existingIcon) {
                            existingIcon.remove();
                        }
                        
                        // Add new image
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Doctor Profile';
                        profileAvatar.insertBefore(img, profileAvatar.firstChild);
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
</body>
</html>