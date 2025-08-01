<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$club_id = $_SESSION['club_id'];
$user_id = $_SESSION['user_id'];

// Get club details
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

// Redirect if club not found
if (!$club) {
    $_SESSION['error'] = "Club not found.";
    header('Location: index.php');
    exit();
}

// Get executive body members
$stmt = $conn->prepare("
    SELECT u.*, cr.role_name, cr.role_type
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cr.role_type = 'executive_body' AND cm.active_status = 1
    ORDER BY cr.role_name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$executive_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get club members
$stmt = $conn->prepare("
    SELECT u.*, cr.role_name, cr.role_type
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.club_id = ? AND cr.role_type = 'club_member' AND cm.active_status = 1
    ORDER BY cr.role_name, u.name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get pending applications
$stmt = $conn->prepare("
    SELECT 
        ma.id,
        ma.user_id,
        ma.club_id,
        ma.role_id,
        ma.application_status,
        ma.applied_date,
        u.name,
        u.email,
        u.student_id,
        cr.role_name,
        cr.role_type
    FROM membership_applications ma
    LEFT JOIN users u ON ma.user_id = u.id
    LEFT JOIN club_roles cr ON ma.role_id = cr.id
    WHERE ma.club_id = ? AND ma.application_status = 'pending'
    ORDER BY ma.applied_date DESC
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$pending_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get event enrollments
$stmt = $conn->prepare("
    SELECT 
        ep.*,
        e.title as event_title,
        e.event_date,
        u.name as student_name,
        u.email as student_email,
        u.student_id
    FROM event_participation ep
    JOIN events e ON ep.event_id = e.id
    JOIN users u ON ep.user_id = u.id
    WHERE e.club_id = ? AND ep.status = 'pending'
    ORDER BY e.event_date ASC, ep.created_at DESC
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$pending_enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get club events
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
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get club roles
$stmt = $conn->prepare("
    SELECT * FROM club_roles 
    WHERE club_id = ? 
    ORDER BY role_type, role_name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_members = count($executive_members) + count($club_members);
$total_events = count($events);
$upcoming_events = array_filter($events, function($event) {
    return $event['status'] === 'upcoming';
});
$pending_count = count($pending_applications);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Dashboard - <?php echo htmlspecialchars($club['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2><?php echo htmlspecialchars($club['name']); ?></h2>
                <div>
                    <a href="manage_club_roles.php" class="btn btn-primary">Manage Club Roles</a>
                    <a href="edit_club.php?id=<?php echo $club_id; ?>" class="btn btn-secondary">Edit Club</a>
                </div>
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

        <!-- Executive Body -->
        <div class="card mb-4">
                        <div class="card-header">
                <h2>Executive Body</h2>
                        </div>
                        <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
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

        <!-- Club Members -->
        <div class="card mb-4">
                        <div class="card-header">
                <h2>Club Members</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Student ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($club_members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td><?php echo htmlspecialchars($member['role_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo htmlspecialchars($member['student_id']); ?></td>
                                <td>
                                    <a href="delete_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this club member?');">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                        </div>
                    </div>
                </div>

        <!-- Pending Applications -->
        <div class="card mb-4">
                        <div class="card-header">
                <h2>Pending Applications</h2>
                        </div>
                        <div class="card-body">
                <?php if (empty($pending_applications)): ?>
                    <p>No pending applications at this time.</p>
                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Type</th>
                                    <th>Applied Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                <?php foreach ($pending_applications as $application): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong><?php echo htmlspecialchars($application['name'] ?? 'N/A'); ?></strong>
                                            <small class="text-muted">ID: <?php echo htmlspecialchars($application['student_id'] ?? 'N/A'); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($application['role_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $application['role_type'] === 'executive_body' ? 'bg-primary' : 'bg-info'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $application['role_type'] ?? 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $application['applied_date'] ? date('M d, Y', strtotime($application['applied_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php if ($application['id']): ?>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $application['id']; ?>">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $application['id']; ?>">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>

                                        <!-- Approve Modal -->
                                        <div class="modal fade" id="approveModal<?php echo $application['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Application</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to approve the application from <strong><?php echo htmlspecialchars($application['name']); ?></strong>?</p>
                                                        <div class="alert alert-info">
                                                            <strong>Student Details:</strong><br>
                                                            ID: <?php echo htmlspecialchars($application['student_id']); ?><br>
                                                            Email: <?php echo htmlspecialchars($application['email']); ?><br>
                                                            Role: <?php echo htmlspecialchars($application['role_name']); ?>
                                                        </div>
                                                        <form action="process_application.php" method="POST">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="remarks<?php echo $application['id']; ?>" class="form-label">Remarks (optional)</label>
                                                                <textarea class="form-control" id="remarks<?php echo $application['id']; ?>" name="remarks" rows="2"></textarea>
                                                            </div>
                                                            <button type="submit" name="action" value="approve" class="btn btn-success">Confirm Approval</button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal<?php echo $application['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Application</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to reject the application from <strong><?php echo htmlspecialchars($application['name']); ?></strong>?</p>
                                                        <form action="process_application.php" method="POST">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="reject_remarks<?php echo $application['id']; ?>" class="form-label">Rejection Reason (required)</label>
                                                                <textarea class="form-control" id="reject_remarks<?php echo $application['id']; ?>" name="remarks" rows="2" required></textarea>
                                                            </div>
                                                            <button type="submit" name="action" value="reject" class="btn btn-danger">Confirm Rejection</button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                    </div>
                </div>

        <!-- Events Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Events</h2>
                <a href="create_event.php" class="btn btn-primary">Create Event</a>
                        </div>
                        <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Participants</th>
                                <th>Reports</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $event['status'] === 'upcoming' ? 'primary' : 
                                            ($event['status'] === 'ongoing' ? 'success' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $event['participant_count']; ?></td>
                                <td><?php echo $event['report_count']; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="upload_report.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-upload"></i> Report
                                        </a>
                                    </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                        </div>
                    </div>
                </div>

        <!-- Event Enrollments Section -->
        <div class="card mb-4">
                        <div class="card-header">
                <h2>Pending Event Enrollments</h2>
                        </div>
                        <div class="card-body">
                <?php if (empty($pending_enrollments)): ?>
                    <p>No pending event enrollments at this time.</p>
                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                    <th>Event</th>
                                    <th>Student</th>
                                    <th>Details</th>
                                    <th>Applied Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                <?php foreach ($pending_enrollments as $enrollment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enrollment['event_title']); ?></strong><br>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($enrollment['event_date'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong><?php echo htmlspecialchars($enrollment['student_name']); ?></strong>
                                            <small class="text-muted">ID: <?php echo htmlspecialchars($enrollment['student_id']); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($enrollment['student_email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($enrollment['proof_file']): ?>
                                            <a href="<?php echo htmlspecialchars($enrollment['proof_file']); ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                                <i class="fas fa-file-alt"></i> View Proof
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">No Proof Uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveEnrollment<?php echo $enrollment['id']; ?>">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectEnrollment<?php echo $enrollment['id']; ?>">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>

                                        <!-- Approve Enrollment Modal -->
                                        <div class="modal fade" id="approveEnrollment<?php echo $enrollment['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Event Enrollment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to approve the enrollment of <strong><?php echo htmlspecialchars($enrollment['student_name']); ?></strong> for the event <strong><?php echo htmlspecialchars($enrollment['event_title']); ?></strong>?</p>
                                                        <div class="alert alert-info">
                                                            <strong>Student Details:</strong><br>
                                                            ID: <?php echo htmlspecialchars($enrollment['student_id']); ?><br>
                                                            Email: <?php echo htmlspecialchars($enrollment['student_email']); ?>
                                                        </div>
                                                        <form action="process_enrollment.php" method="POST">
                                                            <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="remarks<?php echo $enrollment['id']; ?>" class="form-label">Remarks (optional)</label>
                                                                <textarea class="form-control" id="remarks<?php echo $enrollment['id']; ?>" name="remarks" rows="2"></textarea>
                                                            </div>
                                                            <button type="submit" name="action" value="approve" class="btn btn-success">Confirm Approval</button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Enrollment Modal -->
                                        <div class="modal fade" id="rejectEnrollment<?php echo $enrollment['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Event Enrollment</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to reject the enrollment of <strong><?php echo htmlspecialchars($enrollment['student_name']); ?></strong> for the event <strong><?php echo htmlspecialchars($enrollment['event_title']); ?></strong>?</p>
                                                        <form action="process_enrollment.php" method="POST">
                                                            <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="reject_remarks<?php echo $enrollment['id']; ?>" class="form-label">Rejection Reason (required)</label>
                                                                <textarea class="form-control" id="reject_remarks<?php echo $enrollment['id']; ?>" name="remarks" rows="2" required></textarea>
                                                            </div>
                                                            <button type="submit" name="action" value="reject" class="btn btn-danger">Confirm Rejection</button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
            </div>
            </div>
        </div>
</body>
</html>
