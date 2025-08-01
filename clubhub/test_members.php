<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in";
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$club_id = isset($_SESSION['club_id']) ? $_SESSION['club_id'] : 0;

echo "<h1>User Info</h1>";
echo "User ID: " . $user_id . "<br>";
echo "User Type: " . $user_type . "<br>";
echo "Club ID: " . $club_id . "<br>";

if ($user_type === 'club_leader' && $club_id > 0) {
    // Get club members
    $members = $conn->prepare("
        SELECT cm.*, u.name as member_name, cr.role_name, cr.role_type 
        FROM club_members cm 
        JOIN users u ON cm.user_id = u.id 
        JOIN club_roles cr ON cm.role_id = cr.id 
        WHERE cm.club_id = ? AND cm.active_status = 1
        ORDER BY cr.role_type DESC, cr.role_name
    ");
    $members->bind_param("i", $club_id);
    $members->execute();
    $club_members = $members->get_result();
    
    echo "<h2>Club Members</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Type</th><th>Delete Link</th></tr>";
    
    while ($member = $club_members->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $member['id'] . "</td>";
        echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
        echo "<td>" . htmlspecialchars($member['role_name']) . "</td>";
        echo "<td>" . htmlspecialchars($member['role_type']) . "</td>";
        echo "<td><a href='delete_member.php?id=" . $member['id'] . "'>Delete</a></td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>You are not a club leader or no club is assigned.</p>";
}
?>
