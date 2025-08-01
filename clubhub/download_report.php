<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is admin or club leader
if (!isAdmin() && !isClubLeader()) {
    header('Location: login.php');
    exit();
}

// Get report ID
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get report details
$stmt = $conn->prepare("
    SELECT er.*, e.club_id, e.title as event_title
    FROM event_reports er
    JOIN events e ON er.event_id = e.id
    WHERE er.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    header('Location: admin_dashboard.php');
    exit();
}

// Security check for club leader
if (isClubLeader() && $_SESSION['club_id'] !== $report['club_id']) {
    header('Location: club_dashboard.php');
    exit();
}

// File path
$file_path = 'uploads/reports/' . $report['report_file'];

// Check if file exists
if (!file_exists($file_path)) {
    die('File not found.');
}

// Get file info
$file_info = pathinfo($file_path);
$file_extension = strtolower($file_info['extension']);

// Set appropriate content type based on file extension
$content_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt' => 'text/plain'
];

$content_type = isset($content_types[$file_extension]) 
    ? $content_types[$file_extension] 
    : 'application/octet-stream';

// Prevent caching
header('Cache-Control: private');
header('Pragma: public');

// Set headers for download
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . basename($report['report_file']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Content-Transfer-Encoding: binary');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($file_path);
exit(); 