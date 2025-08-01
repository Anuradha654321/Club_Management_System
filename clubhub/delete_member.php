<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a club leader
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'club_leader') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$club_id = $_SESSION['club_id'];
$success = false;
$error = '';

// Process member deletion
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $member_id = $_GET['id'];
    
    // Verify the member belongs to the club leader's club
    $stmt = $conn->prepare("SELECT * FROM club_members WHERE id = ? AND club_id = ?");
    $stmt->bind_param("ii", $member_id, $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Delete the member
        $stmt = $conn->prepare("DELETE FROM club_members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        
        if ($stmt->execute()) {
            $success = true;
            $_SESSION['success'] = "Member has been removed successfully.";
        } else {
            $error = "Failed to remove member. Please try again.";
        }
    } else {
        $error = "Invalid member or you don't have permission to remove this member.";
    }
} else {
    $error = "Invalid member ID.";
}

// Redirect back to club leader dashboard
if ($success) {
    header('Location: club_leader_dashboard.php');
} else {
    $_SESSION['error'] = $error;
    header('Location: club_leader_dashboard.php');
}
exit();
?>
