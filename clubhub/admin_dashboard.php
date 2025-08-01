<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require admin login
requireAdmin();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get current academic year
$current_month = date('m');
$current_year = date('Y');
$academic_year_start = ($current_month >= 6) ? $current_year : $current_year - 1;
$academic_year_end = $academic_year_start + 1;
$start_date = $academic_year_start . '-06-01';
$end_date = $academic_year_end . '-05-31';

// Get all clubs with their activity counts
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
    GROUP BY c.id
    ORDER BY c.name
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$clubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process club deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_club'])) {
    $club_id = intval($_POST['club_id']);
    $stmt = $conn->prepare("DELETE FROM clubs WHERE id = ?");
    $stmt->bind_param("i", $club_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Club deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete club.";
    }
    header('Location: admin_dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Admin Dashboard</h1>
                <p>Academic Year: <?php echo $academic_year_start . '-' . $academic_year_end; ?></p>
            </div>
            <div class="col text-end">
                <a href="create_club.php" class="btn btn-primary">Create New Club</a>
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
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h3>Clubs Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Club Name</th>
                                        <th>Category</th>
                                        <th>Members</th>
                                        <th>Events This Year</th>
                                        <th>Reports</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clubs as $club): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($club['name']); ?></td>
                                        <td><?php echo htmlspecialchars($club['category']); ?></td>
                                        <td><?php echo $club['member_count']; ?></td>
                                        <td><?php echo $club['event_count']; ?></td>
                                        <td><?php echo $club['report_count']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $club['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $club['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="club_details.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <a href="edit_club.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <a href="admin/club_activity_report.php?club_id=<?php echo $club['id']; ?>" class="btn btn-sm btn-success">Activity Report</a>
                                            <form action="admin_dashboard.php" method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this club?');">
                                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                <button type="submit" name="delete_club" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
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