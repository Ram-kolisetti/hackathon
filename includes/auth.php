<?php
// Authentication functions for the Hospital Management Platform

// Include database connection
require_once(BASE_PATH . '/config/database.php');

/**
 * Register a new user
 * 
 * @param string $username Username
 * @param string $email Email address
 * @param string $password Password (will be hashed)
 * @param string $first_name First name
 * @param string $last_name Last name
 * @param string $phone Phone number
 * @param int $role_id Role ID (from roles table)
 * @return array Array with status and message/user_id
 */
function registerUser($username, $email, $password, $first_name, $last_name, $phone, $role_id) {
    // Check if username or email already exists
    $sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
    $result = executeQuery($sql, "ss", [$username, $email]);
    
    if ($result->num_rows > 0) {
        return ['status' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert the new user
    $sql = "INSERT INTO users (role_id, username, email, password, first_name, last_name, phone) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $user_id = insertData($sql, "issssss", [
        $role_id, $username, $email, $hashed_password, $first_name, $last_name, $phone
    ]);
    
    if ($user_id) {
        return ['status' => true, 'user_id' => $user_id];
    } else {
        return ['status' => false, 'message' => 'Registration failed'];
    }
}

/**
 * Login a user
 * 
 * @param string $username Username or email
 * @param string $password Password
 * @return array Array with status and message/user data
 */
function loginUser($username, $password) {
    // Check if input is email or username
    $is_email = filter_var($username, FILTER_VALIDATE_EMAIL);
    
    if ($is_email) {
        $sql = "SELECT u.user_id, u.username, u.email, u.password, u.first_name, u.last_name, 
                r.role_name, u.status 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.email = ?";
    } else {
        $sql = "SELECT u.user_id, u.username, u.email, u.password, u.first_name, u.last_name, 
                r.role_name, u.status 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.username = ?";
    }
    
    $result = executeQuery($sql, "s", [$username]);
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            return ['status' => false, 'message' => 'Account is not active'];
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Remove password from user data before storing in session
            unset($user['password']);
            
            // Update last login time
            $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
            executeNonQuery($update_sql, "i", [$user['user_id']]);
            
            return ['status' => true, 'user' => $user];
        } else {
            return ['status' => false, 'message' => 'Invalid password'];
        }
    } else {
        return ['status' => false, 'message' => 'User not found'];
    }
}

/**
 * Start a user session
 * 
 * @param array $user User data
 * @return void
 */
function startUserSession($user) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
    
    // Get role-specific profile ID
    switch ($user['role_name']) {
        case 'patient':
            $sql = "SELECT patient_id, patient_unique_id FROM patient_profiles WHERE user_id = ?";
            $profile = fetchRow($sql, "i", [$user['user_id']]);
            if ($profile) {
                $_SESSION['profile_id'] = $profile['patient_id'];
                $_SESSION['unique_id'] = $profile['patient_unique_id'];
            }
            break;
            
        case 'doctor':
            $sql = "SELECT doctor_id, doctor_unique_id, hospital_id, department_id FROM doctor_profiles WHERE user_id = ?";
            $profile = fetchRow($sql, "i", [$user['user_id']]);
            if ($profile) {
                $_SESSION['profile_id'] = $profile['doctor_id'];
                $_SESSION['unique_id'] = $profile['doctor_unique_id'];
                $_SESSION['hospital_id'] = $profile['hospital_id'];
                $_SESSION['department_id'] = $profile['department_id'];
            }
            break;
            
        case 'hospital_admin':
        case 'super_admin':
            $sql = "SELECT admin_id, hospital_id FROM admin_profiles WHERE user_id = ?";
            $profile = fetchRow($sql, "i", [$user['user_id']]);
            if ($profile) {
                $_SESSION['profile_id'] = $profile['admin_id'];
                $_SESSION['hospital_id'] = $profile['hospital_id'];
            }
            break;
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        // Check for session timeout (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            // Session expired, log out user
            logoutUser();
            return false;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    return false;
}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool True if user has role, false otherwise
 */
function hasRole($roles) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // If not logged in, return false
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Convert single role to array
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    // Check if user has any of the specified roles
    return in_array($_SESSION['role'], $roles);
}

/**
 * Logout user
 * 
 * @return void
 */
function logoutUser() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

/**
 * Generate a unique ID for patients or doctors
 * 
 * @param string $type Type of ID ('patient' or 'doctor')
 * @return string Unique ID
 */
function generateUniqueId($type) {
    $prefix = ($type === 'patient') ? 'PAT' : 'DOC';
    
    // Get the latest ID from the database
    if ($type === 'patient') {
        $sql = "SELECT patient_unique_id FROM patient_profiles ORDER BY patient_id DESC LIMIT 1";
    } else {
        $sql = "SELECT doctor_unique_id FROM doctor_profiles ORDER BY doctor_id DESC LIMIT 1";
    }
    
    $result = executeQuery($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_id = $row[$type . '_unique_id'];
        $number = intval(substr($last_id, 3)) + 1;
    } else {
        $number = 1;
    }
    
    // Format the number with leading zeros
    return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
}

/**
 * Reset user password
 * 
 * @param int $user_id User ID
 * @param string $new_password New password
 * @return bool True if successful, false otherwise
 */
function resetPassword($user_id, $new_password) {
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update the password
    $sql = "UPDATE users SET password = ? WHERE user_id = ?";
    $affected = executeNonQuery($sql, "si", [$hashed_password, $user_id]);
    
    return $affected > 0;
}

/**
 * Update user profile
 * 
 * @param int $user_id User ID
 * @param array $data Profile data to update
 * @return bool True if successful, false otherwise
 */
function updateUserProfile($user_id, $data) {
    // Start transaction
    $conn = connectDB();
    $conn->begin_transaction();
    
    try {
        // Update users table
        $user_fields = [];
        $user_values = [];
        $user_types = "";
        
        // Check which fields to update
        $allowed_user_fields = ['first_name', 'last_name', 'phone', 'email', 'profile_image'];
        
        foreach ($allowed_user_fields as $field) {
            if (isset($data[$field])) {
                $user_fields[] = "$field = ?";
                $user_values[] = $data[$field];
                $user_types .= "s";
            }
        }
        
        if (!empty($user_fields)) {
            $user_values[] = $user_id;
            $user_types .= "i";
            
            $sql = "UPDATE users SET " . implode(", ", $user_fields) . " WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($user_types, ...$user_values);
            $stmt->execute();
            $stmt->close();
        }
        
        // Get user role
        $sql = "SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Update role-specific profile
        switch ($user['role_name']) {
            case 'patient':
                // Update patient_profiles table
                $patient_fields = [];
                $patient_values = [];
                $patient_types = "";
                
                $allowed_patient_fields = ['date_of_birth', 'gender', 'blood_group', 'address', 'city', 'state', 'zip_code', 'emergency_contact_name', 'emergency_contact_phone', 'allergies'];
                
                foreach ($allowed_patient_fields as $field) {
                    if (isset($data[$field])) {
                        $patient_fields[] = "$field = ?";
                        $patient_values[] = $data[$field];
                        $patient_types .= "s";
                    }
                }
                
                if (!empty($patient_fields)) {
                    $patient_values[] = $user_id;
                    $patient_types .= "i";
                    
                    $sql = "UPDATE patient_profiles SET " . implode(", ", $patient_fields) . " WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($patient_types, ...$patient_values);
                    $stmt->execute();
                    $stmt->close();
                }
                break;
                
            case 'doctor':
                // Update doctor_profiles table
                $doctor_fields = [];
                $doctor_values = [];
                $doctor_types = "";
                
                $allowed_doctor_fields = ['specialization', 'qualification', 'experience_years', 'consultation_fee', 'available_days', 'available_time_start', 'available_time_end', 'bio'];
                
                foreach ($allowed_doctor_fields as $field) {
                    if (isset($data[$field])) {
                        $doctor_fields[] = "$field = ?";
                        $doctor_values[] = $data[$field];
                        $doctor_types .= "s";
                    }
                }
                
                if (!empty($doctor_fields)) {
                    $doctor_values[] = $user_id;
                    $doctor_types .= "i";
                    
                    $sql = "UPDATE doctor_profiles SET " . implode(", ", $doctor_fields) . " WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($doctor_types, ...$doctor_values);
                    $stmt->execute();
                    $stmt->close();
                }
                break;
        }
        
        // Commit transaction
        $conn->commit();
        $conn->close();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $conn->close();
        return false;
    }
}