<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();
$error = '';

// Check if participation ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: club_dashboard.php');
    exit;
}

$participation_id = sanitize($_GET['id']);

// Check if participation belongs to an event from this club
$stmt = $conn->prepare("
    SELECT ep.*, e.title as event_title, e.club_id, u.name as student_name
    FROM event_participation ep
    JOIN events e ON ep.event_id = e.id
    JOIN users u ON ep.user_id = u.id
    WHERE ep.id = ? AND e.club_id = ?
");
$stmt->bind_param("ii", $participation_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: club_dashboard.php');
    exit;
}

$participation = $result->fetch_assoc();

// Process approval
if ($participation['status'] === 'pending') {
    $stmt = $conn->prepare("UPDATE event_participation SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $participation_id);
    
    if ($stmt->execute()) {
        // Redirect back to dashboard with success message
        header('Location: club_dashboard.php?tab=participations&success=1&message=Participation+approved+successfully');
        exit;
    } else {
        // Redirect back to dashboard with error message
        header('Location: club_dashboard.php?tab=participations&error=1&message=Failed+to+approve+participation');
        exit;
    }
} else {
    // Redirect back to dashboard with error message
    header('Location: club_dashboard.php?tab=participations&error=1&message=Participation+is+not+in+pending+status');
    exit;
}
?>
