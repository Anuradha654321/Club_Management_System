<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is admin or club leader
if (!isAdmin() && !isClubLeader()) {
    header('Location: login.php');
    exit();
}

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Get event details and reports
$stmt = $conn->prepare("
    SELECT e.*, c.name as club_name, 
           er.id as report_id, er.report_file, er.upload_date,
           u.name as uploaded_by_name
    FROM events e
    JOIN clubs c ON e.club_id = c.id
    LEFT JOIN event_reports er ON e.id = er.event_id
    LEFT JOIN users u ON er.uploaded_by = u.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: admin_dashboard.php');
    exit();
}

// Store the first row to get event details
$first_row = $result->fetch_assoc();
$event_details = [
    'id' => $first_row['id'],
    'title' => $first_row['title'],
    'club_id' => $first_row['club_id'],
    'club_name' => $first_row['club_name'],
    'event_date' => $first_row['event_date'],
    'status' => $first_row['status']
];

// Security check for club leader
if (isClubLeader() && $_SESSION['club_id'] !== $event_details['club_id']) {
    header('Location: club_dashboard.php');
    exit();
}

// Get all reports for this event
$reports = [];
if ($first_row['report_id']) {
    $reports[] = [
        'id' => $first_row['report_id'],
        'file' => $first_row['report_file'],
        'upload_date' => $first_row['upload_date'],
        'uploaded_by' => $first_row['uploaded_by_name']
    ];
}

while ($row = $result->fetch_assoc()) {
    if ($row['report_id']) {
        $reports[] = [
            'id' => $row['report_id'],
            'file' => $row['report_file'],
            'upload_date' => $row['upload_date'],
            'uploaded_by' => $row['uploaded_by_name']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Reports - <?php echo htmlspecialchars($event_details['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2>Event Reports</h2>
                        <a href="<?php echo isAdmin() ? 'admin_dashboard.php' : 'club_dashboard.php'; ?>" 
                           class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                    <div class="card-body">
                        <div class="event-details mb-4">
                            <h3><?php echo htmlspecialchars($event_details['title']); ?></h3>
                            <p><strong>Club:</strong> <?php echo htmlspecialchars($event_details['club_name']); ?></p>
                            <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($event_details['event_date'])); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $event_details['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($event_details['status']); ?>
                                </span>
                            </p>
                        </div>

                        <?php if (!empty($reports)): ?>
                            <h4>Uploaded Reports</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Uploaded By</th>
                                            <th>Upload Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($report['uploaded_by']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($report['upload_date'])); ?></td>
                                                <td>
                                                    <a href="view_file.php?file=<?php echo urlencode($report['file']); ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        View Report
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No reports have been uploaded for this event yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 