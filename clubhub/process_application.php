<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and is club leader
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'club_leader') {
    header('Location: login.php');
    exit();
}

$club_id = $_SESSION['club_id'];
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = intval($_POST['application_id']);
    $action = sanitize($_POST['action']); // 'approve' or 'reject'
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get application details
        $stmt = $conn->prepare("
            SELECT ma.*, cr.role_name, cr.role_type 
            FROM membership_applications ma
            JOIN club_roles cr ON ma.role_id = cr.id
            WHERE ma.id = ? AND ma.club_id = ?
        ");
        $stmt->bind_param("ii", $application_id, $club_id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        
        if (!$application) {
            throw new Exception('Application not found.');
        }
        
        if ($action === 'approve') {
            // Check if user is already a member of the club
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM club_members 
                WHERE club_id = ? AND user_id = ? AND active_status = 1
            ");
            $stmt->bind_param("ii", $club_id, $application['user_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                // If already a member, just update their role
                $stmt = $conn->prepare("
                    UPDATE club_members 
                    SET role_id = ?
                    WHERE club_id = ? AND user_id = ? AND active_status = 1
                ");
                $stmt->bind_param("iii", $application['role_id'], $club_id, $application['user_id']);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update member role.');
                }
            } else {
                // If not a member, add them as new member
                $stmt = $conn->prepare("
                    INSERT INTO club_members (user_id, club_id, role_id, join_date) 
                    VALUES (?, ?, ?, CURDATE())
                ");
                $stmt->bind_param("iii", $application['user_id'], $club_id, $application['role_id']);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to add member.');
                }
            }
            
            // Check if role is already taken (for executive positions)
            if ($application['role_type'] === 'executive_body') {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM club_members cm
                    JOIN users u ON cm.user_id = u.id
                    WHERE cm.club_id = ? AND cm.role_id = ? 
                    AND cm.active_status = 1 
                    AND cm.user_id != ?
                ");
                $stmt->bind_param("iii", $club_id, $application['role_id'], $application['user_id']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception('This executive position is already filled by another member.');
                }
            }
            
            // Update user type to club_leader if it's an executive body position
            if ($application['role_type'] === 'executive_body') {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET user_type = 'club_leader', club_id = ? 
                    WHERE id = ?
                ");
                $stmt->bind_param("ii", $club_id, $application['user_id']);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update user type.');
                }
            }
        }
        
        // Update application status
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $conn->prepare("
            UPDATE membership_applications 
            SET application_status = ?, processed_by = ?, processed_date = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $status, $user_id, $application_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update application status.');
        }
        
        $conn->commit();
        $_SESSION['success'] = "Application successfully " . $status . ".";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: club_dashboard.php');
    exit();
}
?> 