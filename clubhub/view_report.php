<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$report_id = intval($_GET['id']);

// Get report details with club information
$stmt = $conn->prepare("
    SELECT er.*, e.name as event_name, e.club_id, c.name as club_name, u.name as submitted_by_name
    FROM event_reports er
    JOIN events e ON er.event_id = e.id
    JOIN clubs c ON e.club_id = c.id
    JOIN users u ON er.submitted_by = u.id
    WHERE er.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    header('Location: index.php');
    exit();
}

// Check if user has permission to view this report
if (!isAdmin() && !isClubAdmin() && !canAccessClub($report['club_id'])) {
    header('Location: index.php');
    exit();
}

// Get the file path
$file_path = 'uploads/reports/' . $report['file_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report - <?php echo htmlspecialchars($report['event_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Event Report</h1>
            </div>
            <div class="col text-end">
                <a href="download_report.php?id=<?php echo $report_id; ?>" class="btn btn-primary">Download Report</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><?php echo htmlspecialchars($report['event_name']); ?></h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Club:</strong> <?php echo htmlspecialchars($report['club_name']); ?></p>
                        <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($report['submitted_by_name']); ?></p>
                        <p><strong>Submission Date:</strong> <?php echo date('Y-m-d', strtotime($report['submission_date'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php echo $report['status'] === 'approved' ? 'success' : ($report['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($report['status']); ?>
                            </span>
                        </p>
                        <?php if ($report['feedback']): ?>
                        <p><strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($report['feedback'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (file_exists($file_path)): ?>
                <div class="mt-4">
                    <h4>Report Content</h4>
                    <?php if (pathinfo($file_path, PATHINFO_EXTENSION) === 'pdf'): ?>
                    <embed src="<?php echo $file_path; ?>" type="application/pdf" width="100%" height="600px">
                    <?php else: ?>
                    <div class="alert alert-info">
                        This report is in DOC/DOCX format. Please download to view the content.
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mt-4">
                    Report file not found.
                </div>
                <?php endif; ?>

                <?php if ((isAdmin() || isClubAdmin()) && $report['status'] === 'pending'): ?>
                <div class="mt-4">
                    <h4>Review Report</h4>
                    <form action="process_report.php" method="POST">
                        <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Feedback</label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 