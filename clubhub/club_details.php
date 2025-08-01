<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Get club ID from URL
$club_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify club exists
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

if (!$club) {
    header('Location: index.php');
    exit();
}

// Get club members count
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN cr.role_type = 'executive_body' THEN 1 END) as executive_count,
        COUNT(CASE WHEN cr.role_type = 'club_member' THEN 1 END) as member_count
    FROM club_members cm
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cm.active_status = 1
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$member_counts = $stmt->get_result()->fetch_assoc();

// Get executive body members
$stmt = $conn->prepare("
    SELECT u.name, u.email, u.student_id, u.branch, u.year, u.section, cr.role_name
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cr.role_type = 'executive_body'
    ORDER BY cr.role_name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$executives = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get regular members
$stmt = $conn->prepare("
    SELECT u.name, u.email, u.student_id, u.branch, u.year, u.section, cr.role_name
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cr.role_type = 'club_member'
    ORDER BY u.name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$regular_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent events
$stmt = $conn->prepare("
    SELECT e.*, 
           COUNT(DISTINCT ep.id) as participant_count,
           COUNT(DISTINCT er.id) as report_count
    FROM events e
    LEFT JOIN event_participation ep ON e.id = ep.event_id AND ep.status = 'approved'
    LEFT JOIN event_reports er ON e.id = er.event_id
    WHERE e.club_id = ?
    GROUP BY e.id
    ORDER BY e.event_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$recent_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($club['name']); ?> - Club Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2><?php echo htmlspecialchars($club['name']); ?></h2>
                        <?php if (isAdmin()): ?>
                            <div>
                                <a href="edit_club.php?id=<?php echo $club_id; ?>" class="btn btn-primary">Edit Club</a>
                                <a href="admin/club_activity_report.php?club_id=<?php echo $club_id; ?>" class="btn btn-success">Activity Report</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h3>Club Information</h3>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($club['category']); ?></p>
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($club['description']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $club['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $club['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </p>
                                <p><strong>Executive Body Members:</strong> <?php echo $member_counts['executive_count']; ?></p>
                                <p><strong>Regular Members:</strong> <?php echo $member_counts['member_count']; ?></p>
                            </div>
                            
                            <div class="col-md-6">
                                <h3>Executive Body</h3>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Role</th>
                                                <th>Branch</th>
                                                <th>Year</th>
                                                <th>Section</th>
                                                <?php if (isAdmin()): ?>
                                                    <th>Email</th>
                                                    <th>Student ID</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($executives as $exec): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($exec['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($exec['role_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($exec['branch']); ?></td>
                                                    <td><?php echo htmlspecialchars($exec['year']); ?></td>
                                                    <td><?php echo htmlspecialchars($exec['section']); ?></td>
                                                    <?php if (isAdmin()): ?>
                                                        <td><?php echo htmlspecialchars($exec['email']); ?></td>
                                                        <td><?php echo htmlspecialchars($exec['student_id']); ?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <?php if (isAdmin() || isClubLeader()): ?>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h3>Regular Members</h3>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Branch</th>
                                                <th>Year</th>
                                                <th>Section</th>
                                                <?php if (isAdmin()): ?>
                                                    <th>Email</th>
                                                    <th>Student ID</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($regular_members as $member): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($member['branch']); ?></td>
                                                    <td><?php echo htmlspecialchars($member['year']); ?></td>
                                                    <td><?php echo htmlspecialchars($member['section']); ?></td>
                                                    <?php if (isAdmin()): ?>
                                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                        <td><?php echo htmlspecialchars($member['student_id']); ?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h3>Recent Events</h3>
                                <?php if (!empty($recent_events)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Event</th>
                                                    <th>Date</th>
                                                    <th>Participants</th>
                                                    <th>Reports</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_events as $event): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                                        <td><?php echo $event['participant_count']; ?></td>
                                                        <td><?php echo $event['report_count']; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $event['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                                <?php echo ucfirst($event['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p>No recent events.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
