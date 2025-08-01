<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get club ID from URL
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;

// Get club details
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

if (!$club) {
    header('Location: index.php');
    exit();
}

// Get executive body members
$stmt = $conn->prepare("
    SELECT u.*, cr.role_name 
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cr.role_type = 'executive_body'
    ORDER BY cr.role_name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$executive_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get regular members
$stmt = $conn->prepare("
    SELECT u.*, cr.role_name 
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cr.role_type = 'club_member'
    ORDER BY u.name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all events with detailed information
$stmt = $conn->prepare("
    SELECT 
        e.*,
        COUNT(DISTINCT ep_approved.id) as participant_count,
        COUNT(DISTINCT ep_all.id) as enrollment_count,
        COUNT(DISTINCT er.id) as report_count,
        GROUP_CONCAT(DISTINCT er.report_file) as report_files
    FROM events e
    LEFT JOIN event_participation ep_approved ON e.id = ep_approved.event_id AND ep_approved.status = 'approved'
    LEFT JOIN event_participation ep_all ON e.id = ep_all.event_id
    LEFT JOIN event_reports er ON e.id = er.event_id
    WHERE e.club_id = ?
    GROUP BY e.id
    ORDER BY e.event_date DESC
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_events = count($events);
$total_participants = 0;
$total_reports = 0;
foreach ($events as $event) {
    $total_participants += $event['participant_count'];
    $total_reports += $event['report_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($club['name']); ?> - Activity Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">
    <style>
        .report-section { margin-bottom: 2rem; }
        .stats-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .event-report { margin-top: 1rem; }
    </style>
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo htmlspecialchars($club['name']); ?> - Activity Report</h1>
            <button class="btn btn-primary" onclick="window.print()">Generate Report</button>
        </div>

        <!-- Club Overview -->
        <div class="card report-section">
            <div class="card-header">
                <h2>Club Overview</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5>Executive Body Members:</h5>
                            <p class="h3"><?php echo count($executive_members); ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5>Club Members:</h5>
                            <p class="h3"><?php echo count($club_members); ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5>Academic Year:</h5>
                            <p class="h3"><?php echo date('Y').'-'.(date('Y')+1); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Executive Body -->
        <div class="card report-section">
            <div class="card-header">
                <h2>Executive Body</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Student ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($executive_members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td><?php echo htmlspecialchars($member['role_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo htmlspecialchars($member['student_id']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Events and Activities -->
        <div class="card report-section">
            <div class="card-header">
                <h2>Events and Activities</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="eventsTable">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Mode</th>
                                <th>Location</th>
                                <th>Participants</th>
                                <th>Enrollments</th>
                                <th>Status</th>
                                <th>Reports</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($event['event_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($event['event_time'])); ?></td>
                                <td><?php echo htmlspecialchars($event['event_mode'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                                <td><?php echo $event['participant_count']; ?></td>
                                <td><?php echo $event['enrollment_count']; ?></td>
                                <td><?php echo ucfirst($event['status'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($event['report_count'] > 0): ?>
                                        <a href="../view_event_report.php?event_id=<?php echo $event['id']; ?>" 
                                           class="btn btn-info btn-sm">View Report</a>
                                    <?php else: ?>
                                        No reports
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#eventsTable').DataTable({
                order: [[1, 'desc']], // Sort by date by default
                pageLength: 25
            });
        });
    </script>
</body>
</html> 