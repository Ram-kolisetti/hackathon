<?php
// Logout page for patients
session_start();

// Define base path for includes
define('BASE_PATH', dirname(__DIR__));

// Include authentication functions
require_once(BASE_PATH . '/includes/auth.php');

// Check if user is logged in and is a patient
if (!isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

// Clear all session data
session_unset();
session_destroy();

// Redirect to login page
header('Location: ../login.php');
exit;