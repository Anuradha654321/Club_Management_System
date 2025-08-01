<?php
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';

// Require student login
requireStudent();

$user_id = getCurrentUserId();

// Get student information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get clubs joined by student
$stmt = $conn->prepare("
    SELECT cm.*, c.name as club_name, c.category, c.logo, cr.role_name as role
    FROM club_members cm
    JOIN clubs c ON cm.club_id = c.id
    JOIN club_roles cr ON cm.role_id = cr.id
    WHERE cm.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$joined_clubs = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $joined_clubs[] = $row;
    }
}

// Get events participated
$stmt = $conn->prepare("
    SELECT ep.*, e.title as event_title, e.event_date, c.name as club_name
    FROM event_participation ep
    JOIN events e ON ep.event_id = e.id
    JOIN clubs c ON e.club_id = c.id
    WHERE ep.user_id = ?
    ORDER BY e.event_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$participated_events = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $participated_events[] = $row;
    }
}

// Count stats
$clubs_count = count($joined_clubs);
$events_count = count($participated_events);
$approved_events = array_filter($participated_events, function($event) {
    return $event['status'] === 'approved';
});
$approved_count = count($approved_events);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - College Club Management System</title>
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
                    <a href="student_dashboard.php" class="active">My Dashboard</a>
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
                <h1 class="dashboard-title">Student Dashboard</h1>
                <div>
                    <a href="join_club.php" class="btn btn-primary">Join New Club</a>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-title">Clubs Joined</div>
                    <div class="stat-value"><?php echo $clubs_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Events Participated</div>
                    <div class="stat-value"><?php echo $events_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Approved Participations</div>
                    <div class="stat-value"><?php echo $approved_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Pending Approvals</div>
                    <div class="stat-value"><?php echo $events_count - $approved_count; ?></div>
                </div>
            </div>

            <!-- Dashboard Tabs -->
            <div class="tabs-container">
                <div class="tabs">
                    <div class="tab active" data-tab="my-clubs">My Clubs</div>
                    <div class="tab" data-tab="my-events">Event Participation</div>
                    <div class="tab" data-tab="club-ratings">Club Ratings</div>
                </div>

                <!-- My Clubs Tab -->
                <div class="tab-content active" data-tab="my-clubs">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">My Club Memberships</h2>
                        </div>
                        <div class="card-body">
                            <?php if (count($joined_clubs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Club</th>
                                                <th>Category</th>
                                                <th>Role</th>
                                                <th>Join Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($joined_clubs as $club): ?>
                                                <tr>
                                                    <td>
                                                        <div style="display: flex; align-items: center;">
                                                            <?php if ($club['logo']): ?>
                                                                <img src="<?php echo $club['logo']; ?>" alt="<?php echo $club['club_name']; ?>" style="width: 30px; height: 30px; margin-right: 10px; border-radius: 50%;">
                                                            <?php else: ?>
                                                                <div style="width: 30px; height: 30px; margin-right: 10px; border-radius: 50%; background-color: #4f46e5; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                                                    <?php echo substr($club['club_name'], 0, 1); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php echo $club['club_name']; ?>
                                                        </div>
                                                    </td>
                                                    <td><span class="badge badge-primary"><?php echo $club['category']; ?></span></td>
                                                    <td><?php echo $club['role']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($club['join_date'])); ?></td>
                                                    <td>
                                                        <?php if ($club['active_status']): ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="club_details.php?id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-outline">View</a>
                                                        <a href="update_membership.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-secondary">Update</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>You haven't joined any clubs yet.</p>
                                    <a href="join_club.php" class="btn btn-primary mt-2">Join a Club</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Event Participation Tab -->
                <div class="tab-content" data-tab="my-events">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">My Event Participation</h2>
                        </div>
                        <div class="card-body">
                            <?php if (count($participated_events) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Club</th>
                                                <th>Date</th>
                                                <th>Proof</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($participated_events as $event): ?>
                                                <tr>
                                                    <td><?php echo $event['event_title']; ?></td>
                                                    <td><?php echo $event['club_name']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                                    <td>
                                                        <?php if ($event['proof_file']): ?>
                                                            <a href="<?php echo $event['proof_file']; ?>" target="_blank" class="btn btn-sm btn-outline">View Proof</a>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Not Uploaded</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($event['status'] === 'approved'): ?>
                                                            <span class="badge badge-success">Approved</span>
                                                        <?php elseif ($event['status'] === 'rejected'): ?>
                                                            <span class="badge badge-danger">Rejected</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="event_details.php?id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-outline">View Event</a>
                                                        <?php if (!$event['proof_file'] || $event['status'] === 'rejected'): ?>
                                                            <a href="upload_proof.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">Upload Proof</a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>You haven't participated in any events yet.</p>
                                    <a href="events.php" class="btn btn-primary mt-2">View Upcoming Events</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Club Ratings Tab -->
                <div class="tab-content" data-tab="club-ratings">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">My Club Ratings</h2>
                        </div>
                        <div class="card-body">
                            <?php if (count($joined_clubs) > 0): ?>
                                <div class="club-ratings-container">
                                    <?php foreach ($joined_clubs as $club): ?>
                                        <?php
                                        // Check if user has already rated this club
                                        $stmt = $conn->prepare("SELECT * FROM club_ratings WHERE user_id = ? AND club_id = ?");
                                        $stmt->bind_param("ii", $user_id, $club['club_id']);
                                        $stmt->execute();
                                        $rating_result = $stmt->get_result();
                                        $existing_rating = $rating_result->num_rows > 0 ? $rating_result->fetch_assoc() : null;
                                        ?>
                                        <div class="club-rating-card">
                                            <div class="club-rating-header">
                                                <h3><?php echo $club['club_name']; ?></h3>
                                                <span class="badge badge-primary"><?php echo $club['category']; ?></span>
                                            </div>
                                            
                                            <?php if ($existing_rating): ?>
                                                <div class="existing-rating">
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <span class="star <?php echo $i <= $existing_rating['rating'] ? 'active' : ''; ?>">★</span>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <p class="rating-feedback"><?php echo $existing_rating['review']; ?></p>
                                                    <a href="rate_club.php?id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-outline">Update Rating</a>
                                                </div>
                                            <?php else: ?>
                                                <div class="no-rating">
                                                    <p>You haven't rated this club yet.</p>
                                                    <a href="rate_club.php?id=<?php echo $club['club_id']; ?>" class="btn btn-sm btn-primary">Rate Now</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>You need to join clubs before you can rate them.</p>
                                    <a href="join_club.php" class="btn btn-primary mt-2">Join a Club</a>
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
                        <li><a href="student_dashboard.php">My Dashboard</a></li>
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
