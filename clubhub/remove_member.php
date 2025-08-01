<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();

// Check if member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: club_dashboard.php?tab=members');
    exit;
}

$member_id = sanitize($_GET['id']);

// Check if member belongs to this club
$stmt = $conn->prepare("
    SELECT cm.*, u.name
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.id = ? AND cm.club_id = ?
");
$stmt->bind_param("ii", $member_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: club_dashboard.php?tab=members');
    exit;
}

$member = $result->fetch_assoc();

// Process removal
$stmt = $conn->prepare("DELETE FROM club_members WHERE id = ? AND club_id = ?");
$stmt->bind_param("ii", $member_id, $club_id);

if ($stmt->execute()) {
    // Redirect back to dashboard with success message
    header('Location: club_dashboard.php?tab=members&success=1&message=' . urlencode("Member '{$member['name']}' has been removed from the club."));
    exit;
} else {
    // Redirect back to dashboard with error message
    header('Location: club_dashboard.php?tab=members&error=1&message=' . urlencode("Failed to remove member. Please try again."));
    exit;
}
?>
