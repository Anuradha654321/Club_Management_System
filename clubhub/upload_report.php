<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || 
    ($_SESSION['user_type'] !== 'club_leader' && $_SESSION['user_type'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$event_id) {
    header('Location: club_dashboard.php');
    exit();
}

// Get event details
$stmt = $conn->prepare("
    SELECT e.*, c.name as club_name 
    FROM events e
    JOIN clubs c ON e.club_id = c.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    header('Location: club_dashboard.php');
    exit();
}

// Check if user has permission to upload report for this event
if ($_SESSION['user_type'] === 'club_leader' && $_SESSION['club_id'] !== $event['club_id']) {
    header('Location: club_dashboard.php');
    exit();
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded
    if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === 0) {
        // Validate file
        $allowedTypes = ['pdf', 'doc', 'docx'];
        $validation_result = validateFileUpload($_FILES['report_file'], $allowedTypes);
        
        if ($validation_result['success']) {
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Create directory if it doesn't exist
                if (!file_exists('uploads/reports')) {
                    mkdir('uploads/reports', 0777, true);
                }
                
                // Generate unique filename
                $fileExtension = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
                $newFilename = 'event_report_' . $event_id . '_' . time() . '.' . $fileExtension;
                $targetPath = 'uploads/reports/' . $newFilename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['report_file']['tmp_name'], $targetPath)) {
                    // Insert report record
                    $stmt = $conn->prepare("
                        INSERT INTO event_reports 
                        (event_id, report_file, uploaded_by, upload_date) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("isi", $event_id, $targetPath, $user_id);
                    
                    if ($stmt->execute()) {
                        $conn->commit();
                        $success = 'Event report uploaded successfully.';
                    } else {
                        throw new Exception('Failed to save report record.');
                    }
                } else {
                    throw new Exception('Failed to upload file.');
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error: ' . $e->getMessage();
            }
        } else {
            $error = $validation_result['message'];
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

// Get existing reports
$stmt = $conn->prepare("
    SELECT er.*, u.name as uploaded_by_name 
    FROM event_reports er
    JOIN users u ON er.uploaded_by = u.id
    WHERE er.event_id = ?
    ORDER BY er.upload_date DESC
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Event Report - <?php echo htmlspecialchars($event['title']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Upload Event Report</h2>
                        <p class="mb-0">Event: <?php echo htmlspecialchars($event['title']); ?></p>
                        <p class="mb-0">Club: <?php echo htmlspecialchars($event['club_name']); ?></p>
                        <p class="mb-0">Date: <?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form action="upload_report.php?id=<?php echo $event_id; ?>" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="report_file" class="form-label">Upload Report Document</label>
                                <input type="file" id="report_file" name="report_file" class="form-control" required accept=".pdf,.doc,.docx">
                                <div class="form-text">
                                    Accepted file formats: PDF, DOC, DOCX. Maximum file size: 5MB.
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Upload Report</button>
                                <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary">Back to Event</a>
                            </div>
                        </form>

                        <?php if (!empty($reports)): ?>
                            <div class="mt-4">
                                <h3>Uploaded Reports</h3>
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
                                                    <td><?php echo htmlspecialchars($report['uploaded_by_name']); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($report['upload_date'])); ?></td>
                                                    <td>
                                                        <a href="<?php echo htmlspecialchars($report['report_file']); ?>" class="btn btn-sm btn-info" target="_blank">View</a>
                                                        <?php if ($_SESSION['user_type'] === 'admin' || 
                                                                ($_SESSION['user_type'] === 'club_leader' && $report['uploaded_by'] === $user_id)): ?>
                                                            <a href="delete_report.php?id=<?php echo $report['id']; ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('Are you sure you want to delete this report?');">Delete</a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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