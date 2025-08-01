<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';  // Default XAMPP MySQL username
$db_pass = '';      // Default XAMPP MySQL password
$db_name = 'club_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?> 