<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get file path from query parameter
$file = isset($_GET['file']) ? $_GET['file'] : '';

// Basic security check - prevent directory traversal
$file = basename($file);
$filepath = 'uploads/reports/' . $file;

// Verify file exists
if (!file_exists($filepath)) {
    die('File not found');
}

// Get file extension
$extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

// Set content type based on file extension
$content_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain'
];

$content_type = isset($content_types[$extension]) ? $content_types[$extension] : 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($filepath));

// For PDFs and images, display in browser
if (in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif'])) {
    header('Content-Disposition: inline; filename="' . $file . '"');
} else {
    // For other files, prompt download
    header('Content-Disposition: attachment; filename="' . $file . '"');
}

// Output file
readfile($filepath);
exit;
?> 