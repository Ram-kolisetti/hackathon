<?php
// Main entry point for the Hospital Management Platform
session_start();

// Define base path for includes
define('BASE_PATH', __DIR__);

// Redirect to appropriate dashboard based on user role or to login page
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'patient':
            header('Location: patient/dashboard.php');
            break;
        case 'doctor':
            header('Location: doctor/dashboard.php');
            break;
        case 'hospital_admin':
            header('Location: admin/hospital/dashboard.php');
            break;
        case 'super_admin':
            header('Location: admin/super/dashboard.php');
            break;
        default:
            // If role is not recognized, destroy session and redirect to login
            session_destroy();
            header('Location: login.php');
    }
    exit;
} else {
    // If not logged in, redirect to login page
    header('Location: login.php');
    exit;
}