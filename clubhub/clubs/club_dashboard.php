<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();

// Get club information
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

// Get club members
$stmt = $conn->prepare("
    SELECT cm.*, u.name, u.email, u.student_id
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.club_id = ?
    ORDER BY cm.role, u.name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();
$members = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

// Get club events
$stmt = $conn->prepare("
    SELECT * FROM events
    WHERE club_id = ?
    ORDER BY event_date DESC
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();
$events = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

// Get pending participation requests
$stmt = $conn->prepare("
    SELECT ep.*, e.title as event_title, u.name as student_name, u.student_id
    FROM event_participation ep
    JOIN events e ON ep.event_id = e.id
    JOIN users u ON ep.user_id = u.id
    WHERE e.club_id = ? AND ep.status = 'pending'
    ORDER BY e.event_date DESC
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();
$pending_participations = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_participations[] = $row;
    }
}

// Get executive body
$stmt = $conn->prepare("
    SELECT ce.*, u.name as user_name
    FROM club_executives ce
    LEFT JOIN users u ON ce.user_id = u.id
    WHERE ce.club_id = ?
    ORDER BY ce.start_date DESC
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();
$executives = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $executives[] = $row;
    }
}

// Count stats
$total_members = count($members);
$total_events = count($events);
$upcoming_events = array_filter($events, function($event) {
    return $event['status'] === 'upcoming';
});
$upcoming_count = count($upcoming_events);
$pending_count = count($pending_participations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Dashboard - College Club Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="container">
                <div class="logo">
                    <a href="index.php">College Club Hub</a>
                </div>
                <div class="nav-links">
                    <a href="index.php">Home</a>
                    <a href="clubs.php">Clubs</a>
                    <a href="events.php">Events</a>
                    <a href="club_dashboard.php" class="active">Club Dashboard</a>
                    <a href="logout.php" class="btn btn-outline">Logout</a>
                </div>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1 class="dashboard-title"><?php echo $club['name']; ?> Dashboard</h1>
                <div>
                    <a href="create_event.php" class="btn btn-primary">Create Event</a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-title">Total Members</div>
                    <div class="stat-value"><?php echo $total_members; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Events</div>
                    <div class="stat-value"><?php echo $total_events; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Upcoming Events</div>
                    <div class="stat-value"><?php echo $upcoming_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Pending Approvals</div>
                    <div class="stat-value"><?php echo $pending_count; ?></div>
                </div>
            </div>

            <!-- Dashboard Tabs -->
            <div class="tabs-container">
                <div class="tabs">
                    <div class="tab active" data-tab="events">Events</div>
                    <div class="tab" data-tab="members">Members</div>
                    <div class="tab" data-tab="participations">Participation Requests</div>
                    <div class="tab" data-tab="executives">Executive Body</div>
                    <div class="tab" data-tab="minutes">Meeting Minutes</div>
                </div>

                <!-- Events Tab -->
                <div class="tab-content active" data-tab="events">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Club Events</h2>
                            <a href="create_event.php" class="btn btn-sm btn-primary">Create Event</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($events) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Date</th>
                                                <th>Location</th>
                                                <th>Status</th>
                                                <th>Enrollment</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($events as $event): ?>
                                                <tr>
                                                    <td><?php echo $event['title']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                                    <td><?php echo $event['location']; ?></td>
                                                    <td>
                                                        <?php if ($event['status'] === 'upcoming'): ?>
                                                            <span class="badge badge-primary">Upcoming</span>
                                                        <?php elseif ($event['status'] === 'ongoing'): ?>
                                                            <span class="badge badge-success">Ongoing</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Past</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($event['enrollment_open']): ?>
                                                            <span class="badge badge-success">Open</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Closed</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                                        <a href="manage_event_media.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">Media</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>No events created yet.</p>
                                    <a href="create_event.php" class="btn btn-primary mt-2">Create Event</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Members Tab -->
                <div class="tab-content" data-tab="members">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Club Members</h2>
                            <a href="add_member.php" class="btn btn-sm btn-primary">Add Member</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($members) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Student ID</th>
                                                <th>Role</th>
                                                <th>Join Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($members as $member): ?>
                                                <tr>
                                                    <td><?php echo $member['name']; ?></td>
                                                    <td><?php echo $member['email']; ?></td>
                                                    <td><?php echo $member['student_id']; ?></td>
                                                    <td><?php echo $member['role']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                                    <td>
                                                        <?php if ($member['active_status']): ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                                        <a href="remove_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this member?')">Remove</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>No members in this club yet.</p>
                                    <a href="add_member.php" class="btn btn-primary mt-2">Add Member</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Participation Requests Tab -->
                <div class="tab-content" data-tab="participations">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Pending Participation Requests</h2>
                        </div>
                        <div class="card-body">
                            <?php if (count($pending_participations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Student</th>
                                                <th>Student ID</th>
                                                <th>Proof</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_participations as $participation): ?>
                                                <tr>
                                                    <td><?php echo $participation['event_title']; ?></td>
                                                    <td><?php echo $participation['student_name']; ?></td>
                                                    <td><?php echo $participation['student_id']; ?></td>
                                                    <td>
                                                        <?php if ($participation['proof_file']): ?>
                                                            <a href="<?php echo $participation['proof_file']; ?>" target="_blank" class="btn btn-sm btn-outline">View Proof</a>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">No Proof</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="approve_participation.php?id=<?php echo $participation['id']; ?>" class="btn btn-sm btn-success">Approve</a>
                                                        <a href="reject_participation.php?id=<?php echo $participation['id']; ?>" class="btn btn-sm btn-danger">Reject</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>No pending participation requests.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Executive Body Tab -->
                <div class="tab-content" data-tab="executives">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Executive Body</h2>
                            <a href="add_executive.php" class="btn btn-sm btn-primary">Add Executive</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($executives) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Role</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($executives as $executive): ?>
                                                <tr>
                                                    <td><?php echo $executive['user_id'] ? $executive['user_name'] : $executive['name']; ?></td>
                                                    <td><?php echo $executive['role']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($executive['start_date'])); ?></td>
                                                    <td><?php echo $executive['end_date'] ? date('M d, Y', strtotime($executive['end_date'])) : 'Present'; ?></td>
                                                    <td>
                                                        <a href="edit_executive.php?id=<?php echo $executive['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                                        <a href="remove_executive.php?id=<?php echo $executive['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this executive?')">Remove</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>No executives added yet.</p>
                                    <a href="add_executive.php" class="btn btn-primary mt-2">Add Executive</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Meeting Minutes Tab -->
                <div class="tab-content" data-tab="minutes">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Meeting Minutes</h2>
                            <a href="upload_minutes.php" class="btn btn-sm btn-primary">Upload Minutes</a>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get meeting minutes
                            $stmt = $conn->prepare("
                                SELECT mm.*, u.name as uploaded_by_name
                                FROM meeting_minutes mm
                                JOIN users u ON mm.uploaded_by = u.id
                                WHERE mm.club_id = ?
                                ORDER BY mm.meeting_date DESC
                            ");
                            $stmt->bind_param("i", $club_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $minutes = [];
                            
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $minutes[] = $row;
                                }
                            }
                            ?>
                            
                            <?php if (count($minutes) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Meeting Date</th>
                                                <th>Uploaded By</th>
                                                <th>Upload Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($minutes as $minute): ?>
                                                <tr>
                                                    <td><?php echo $minute['title']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($minute['meeting_date'])); ?></td>
                                                    <td><?php echo $minute['uploaded_by_name']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($minute['created_at'])); ?></td>
                                                    <td>
                                                        <a href="<?php echo $minute['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                                        <a href="delete_minutes.php?id=<?php echo $minute['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete these minutes?')">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>No meeting minutes uploaded yet.</p>
                                    <a href="upload_minutes.php" class="btn btn-primary mt-2">Upload Minutes</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h2>College Club Hub</h2>
                    <p>Connecting students through clubs and activities.</p>
                </div>
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="clubs.php">Clubs</a></li>
                        <li><a href="events.php">Events</a></li>
                        <li><a href="club_dashboard.php">Club Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-envelope"></i> support@collegeclubhub.com</p>
                    <p><i class="fas fa-phone"></i> +1 (123) 456-7890</p>
                    <p><i class="fas fa-map-marker-alt"></i> 123 College Street, City, State</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> College Club Hub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
