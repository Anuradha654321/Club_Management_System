<?php
// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "club_db";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

// Function to validate file upload
function validateFileUpload($file, $allowedTypes, $maxSize = 5242880) { // 5MB default
    $errors = [];
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = "File size exceeds the maximum limit of " . ($maxSize / 1048576) . "MB.";
    }
    
    // Check file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "Sorry, only " . implode(", ", $allowedTypes) . " files are allowed.";
    }
    
    return $errors;
}
?>
