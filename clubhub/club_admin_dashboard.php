<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and is club admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'club_admin') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current academic year
$current_month = date('m');
$current_year = date('Y');
$academic_year_start = ($current_month >= 6) ? $current_year : $current_year - 1;
$academic_year_end = $academic_year_start + 1;
$start_date = $academic_year_start . '-06-01';
$end_date = $academic_year_end . '-05-31';

// Get club information for the club admin
$stmt = $conn->prepare("
    SELECT 
        c.*,
        COUNT(DISTINCT cm.id) as member_count,
        COUNT(DISTINCT e.id) as event_count,
        COUNT(DISTINCT er.id) as report_count
    FROM clubs c
    LEFT JOIN club_members cm ON c.id = cm.club_id AND cm.active_status = 1
    LEFT JOIN events e ON c.id = e.club_id 
        AND e.event_date BETWEEN ? AND ?
    LEFT JOIN event_reports er ON e.id = er.event_id
    WHERE c.club_admin_id = ?
    GROUP BY c.id
");
$stmt->bind_param("ssi", $start_date, $end_date, $user_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

if (!$club) {
    die("No club found for this admin");
}

// Get recent reports with detailed event information
$stmt = $conn->prepare("
    SELECT 
        er.*,
        ev.title as event_name,
        ev.event_date,
        ev.event_time,
        ev.location,
        ev.description as event_description,
        u.name as submitted_by_name,
        (SELECT COUNT(*) FROM event_participation ep WHERE ep.event_id = ev.id AND ep.status = 'approved') as participant_count,
        ev.max_participants,
        er.report_file
    FROM event_reports er
    JOIN events ev ON er.event_id = ev.id
    JOIN users u ON er.uploaded_by = u.id
    WHERE ev.club_id = ?
    ORDER BY er.upload_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $club['id']);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get club members
$stmt = $conn->prepare("
    SELECT 
        u.*,
        cr.role_name as role
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cm.active_status = 1
    ORDER BY u.name
");
$stmt->bind_param("i", $club['id']);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Admin Dashboard - <?php echo htmlspecialchars($club['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Club Admin Dashboard - <?php echo htmlspecialchars($club['name']); ?></h1>
                <p>Academic Year: <?php echo $academic_year_start . '-' . $academic_year_end; ?></p>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Club Overview Card -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Club Overview</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($club['category']); ?></p>
                        <p><strong>Total Members:</strong> <?php echo $club['member_count']; ?></p>
                        <p><strong>Events This Year:</strong> <?php echo $club['event_count']; ?></p>
                        <p><strong>Reports Submitted:</strong> <?php echo $club['report_count']; ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php echo $club['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $club['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Recent Reports Card -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Reports</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event Name</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Participants</th>
                                        <th>Submitted By</th>
                                        <th>Report</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($report['event_name']); ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars($report['event_description']); ?></div>
                                        </td>
                                        <td>
                                            <?php echo date('Y-m-d', strtotime($report['event_date'])); ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars($report['event_time']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['location']); ?></td>
                                        <td>
                                            <?php echo $report['participant_count']; ?> / 
                                            <?php echo $report['max_participants'] ? $report['max_participants'] : 'Unlimited'; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($report['submitted_by_name']); ?>
                                            <div class="small text-muted">
                                                <?php echo date('Y-m-d', strtotime($report['upload_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($report['report_file']): ?>
                                                <a href="view_club_report.php?id=<?php echo $report['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-file-alt"></i> View Report
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No report file</span>
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
        </div>

        <!-- Club Members Card -->
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h3>Club Members</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['role']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td>
                                            <!-- Removed View button -->
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 