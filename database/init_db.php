<?php
// Database Initialization Script
// This script creates the database and initializes it with schema and sample data

// Database connection parameters
$host = 'localhost';
$user = 'root'; // Change in production
$pass = ''; // Change in production

// Connect to MySQL without selecting a database
try {
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to MySQL successfully.<br>";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS hospital_management";
    if ($conn->query($sql) === TRUE) {
        echo "Database created successfully or already exists.<br>";
    } else {
        echo "Error creating database: " . $conn->error . "<br>";
        exit;
    }
    
    // Close the connection
    $conn->close();
    
    // Read and execute the schema SQL file
    $schemaFile = file_get_contents(__DIR__ . '/schema.sql');
    if ($schemaFile === false) {
        echo "Error reading schema file.<br>";
        exit;
    }
    
    // Connect to the database
    $conn = new mysqli($host, $user, $pass, 'hospital_management');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Execute schema SQL
    if ($conn->multi_query($schemaFile)) {
        echo "Database schema created successfully.<br>";
        
        // Clear results to execute next query
        while ($conn->more_results() && $conn->next_result()) {
            // Consume all results
            if ($res = $conn->store_result()) {
                $res->free();
            }
        }
    } else {
        echo "Error creating schema: " . $conn->error . "<br>";
        exit;
    }
    
    // Read and execute the sample data SQL file
    $dataFile = file_get_contents(__DIR__ . '/init_data.sql');
    if ($dataFile === false) {
        echo "Error reading data file.<br>";
        exit;
    }
    
    // Execute sample data SQL
    if ($conn->multi_query($dataFile)) {
        echo "Sample data inserted successfully.<br>";
    } else {
        echo "Error inserting sample data: " . $conn->error . "<br>";
        exit;
    }
    
    echo "<br>Database initialization completed successfully!<br>";
    echo "<a href='../index.php'>Go to Homepage</a>";
    
    // Close the connection
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}