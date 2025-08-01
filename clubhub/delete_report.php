<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || 
    ($_SESSION['user_type'] !== 'club_leader' && $_SESSION['user_type'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$report_id) {
    header('Location: club_dashboard.php');
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get report details
    $stmt = $conn->prepare("
        SELECT er.*, e.club_id, e.id as event_id
        FROM event_reports er
        JOIN events e ON er.event_id = e.id
        WHERE er.id = ?
    ");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    
    if (!$report) {
        throw new Exception('Report not found.');
    }
    
    // Check if user has permission to delete this report
    if ($_SESSION['user_type'] === 'club_leader') {
        if ($_SESSION['club_id'] !== $report['club_id'] || $report['uploaded_by'] !== $user_id) {
            throw new Exception('You do not have permission to delete this report.');
        }
    }
    
    // Delete file from filesystem
    if (file_exists($report['file_path'])) {
        if (!unlink($report['file_path'])) {
            throw new Exception('Failed to delete report file.');
        }
    }
    
    // Delete record from database
    $stmt = $conn->prepare("DELETE FROM event_reports WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete report record.');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Redirect back to upload page with success message
    $_SESSION['success_message'] = 'Report deleted successfully.';
    header('Location: upload_report.php?id=' . $report['event_id']);
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: upload_report.php?id=' . $report['event_id']);
    exit();
} 