<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'club_admin', 'club_leader'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$report_id = intval($_GET['id']);

// Get report details
$stmt = $conn->prepare("
    SELECT er.*, ev.club_id, ev.title as event_name
    FROM event_reports er
    JOIN events ev ON er.event_id = ev.id
    WHERE er.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    header('Location: index.php');
    exit();
}

// Check if user has permission to view this report
if ($_SESSION['user_type'] === 'club_admin') {
    $stmt = $conn->prepare("SELECT 1 FROM clubs WHERE id = ? AND club_admin_id = ?");
    $stmt->bind_param("ii", $report['club_id'], $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        header('Location: index.php');
        exit();
    }
}

// Get the file path
$file_path = $report['report_file'];

// Check if file exists
if (!file_exists($file_path)) {
    die("Report file not found.");
}

// Get file extension
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// Set appropriate headers based on file type
switch ($file_extension) {
    case 'pdf':
        header('Content-Type: application/pdf');
        break;
    case 'doc':
        header('Content-Type: application/msword');
        break;
    case 'docx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        break;
    default:
        header('Content-Type: application/octet-stream');
}

// Set content disposition to inline for viewing in browser
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');

// Output the file
readfile($file_path);
exit(); 