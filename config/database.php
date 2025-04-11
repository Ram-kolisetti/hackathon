<?php
// Database configuration file

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change in production
define('DB_PASS', ''); // Change in production
define('DB_NAME', 'hospital_management');

// Create database connection
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Function to sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to execute prepared statements
function executeQuery($sql, $types = "", $params = []) {
    $conn = connectDB();
    $stmt = $conn->prepare($sql);
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    
    return $result;
}

// Function to get a single row
function fetchRow($sql, $types = "", $params = []) {
    $result = executeQuery($sql, $types, $params);
    return $result->fetch_assoc();
}

// Function to get multiple rows
function fetchRows($sql, $types = "", $params = []) {
    $result = executeQuery($sql, $types, $params);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to insert data and return the inserted ID
function insertData($sql, $types, $params) {
    $conn = connectDB();
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $insertId = $conn->insert_id;
    $stmt->close();
    $conn->close();
    
    return $insertId;
}

// Function to update or delete data
function executeNonQuery($sql, $types, $params) {
    $conn = connectDB();
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    
    return $affectedRows;
}