<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;

if (empty($club_id)) {
    echo json_encode(['error' => 'Club ID is required']);
    exit;
}

try {
    // Get taken roles for the club
    $stmt = $conn->prepare("
        SELECT DISTINCT cr.role_name
        FROM club_members cm
        JOIN club_roles cr ON cm.role_id = cr.id
        WHERE cr.club_id = ? 
        AND cr.role_name IN ('President', 'Vice President')
        AND cm.active_status = 1
    ");
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $taken_roles = [];
    while ($row = $result->fetch_assoc()) {
        $taken_roles[] = $row['role_name'];
    }

    echo json_encode([
        'success' => true,
        'taken_roles' => $taken_roles
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Failed to check role availability: ' . $e->getMessage()
    ]);
} 