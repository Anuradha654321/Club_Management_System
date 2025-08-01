<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user has permission to process reports
if (!isAdmin() && !isClubAdmin()) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['report_id']) || !isset($_POST['action'])) {
    header('Location: index.php');
    exit();
}

$report_id = intval($_POST['report_id']);
$action = $_POST['action'];
$feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

// Get report details to check permissions
$stmt = $conn->prepare("
    SELECT er.*, e.club_id
    FROM event_reports er
    JOIN events e ON er.event_id = e.id
    WHERE er.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    $_SESSION['error'] = "Report not found.";
    header('Location: index.php');
    exit();
}

// If club admin, check if they have permission for this club
if (isClubAdmin()) {
    $stmt = $conn->prepare("SELECT 1 FROM clubs WHERE id = ? AND club_admin_id = ?");
    $stmt->bind_param("ii", $report['club_id'], $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $_SESSION['error'] = "You don't have permission to process this report.";
        header('Location: index.php');
        exit();
    }
}

// Process the report
$status = ($action === 'approve') ? 'approved' : 'rejected';

$stmt = $conn->prepare("
    UPDATE event_reports 
    SET status = ?, feedback = ?, processed_by = ?, processed_date = NOW() 
    WHERE id = ?
");
$stmt->bind_param("ssii", $status, $feedback, $_SESSION['user_id'], $report_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Report has been " . $status . " successfully.";
} else {
    $_SESSION['error'] = "Failed to process report.";
}

// Redirect back to the report view
header('Location: view_report.php?id=' . $report_id);
exit(); 