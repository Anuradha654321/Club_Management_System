<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$club_id = $_SESSION['club_id'];
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: club_dashboard.php');
    exit();
}

// Get enrollment ID and action
$enrollment_id = isset($_POST['enrollment_id']) ? intval($_POST['enrollment_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

// Validate inputs
if (empty($enrollment_id) || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = "Invalid request parameters.";
    header('Location: club_dashboard.php');
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get enrollment details and verify it belongs to this club
    $stmt = $conn->prepare("
        SELECT ep.*, e.club_id, e.title as event_title, u.name as student_name
        FROM event_participation ep
        JOIN events e ON ep.event_id = e.id
        JOIN users u ON ep.user_id = u.id
        WHERE ep.id = ? AND e.club_id = ? AND ep.status = 'pending'
    ");
    $stmt->bind_param("ii", $enrollment_id, $club_id);
    $stmt->execute();
    $enrollment = $stmt->get_result()->fetch_assoc();

    if (!$enrollment) {
        throw new Exception('Invalid enrollment or unauthorized access.');
    }

    // Update enrollment status
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    $stmt = $conn->prepare("
        UPDATE event_participation 
        SET status = ?, 
            feedback = ?,
            updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $status, $remarks, $enrollment_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update enrollment status.');
    }

    // Commit transaction
    $conn->commit();

    // Set success message
    $_SESSION['success'] = sprintf(
        "Successfully %s enrollment for %s in event: %s",
        $action === 'approve' ? 'approved' : 'rejected',
        $enrollment['student_name'],
        $enrollment['event_title']
    );

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to dashboard
header('Location: club_dashboard.php');
exit(); 