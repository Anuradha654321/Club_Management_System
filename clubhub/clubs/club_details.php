<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if club ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: clubs.php');
    exit;
}

$club_id = sanitize($_GET['id']);

// Get club details
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: clubs.php');
    exit;
}

$club = $result->fetch_assoc();

// Get club executives
$stmt = $conn->prepare("
    SELECT ce.*, u.name as user_name
    FROM club_executives ce
    LEFT JOIN users u ON ce.user_id = u.id
    WHERE ce.club_id = ? AND (ce.end_date IS NULL OR ce.end_date >= CURDATE())
    ORDER BY ce.role
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

// Get upcoming events
$stmt = $conn->prepare("
    SELECT * FROM events
    WHERE club_id = ? AND (status = 'upcoming' OR status = 'ongoing')
    ORDER BY event_date ASC
    LIMIT 3
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_events = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
}

// Get club ratings
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count
    FROM club_ratings
    WHERE club_id = ?
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$rating_result = $stmt->get_result()->fetch_assoc();
$avg_rating = $rating_result['avg_rating'] ? round($rating_result['avg_rating'], 1) : 0;
$rating_count = $rating_result['rating_count'];

// Get member count
$stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM club_members WHERE club_id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$member_result = $stmt->get_result()->fetch_assoc();
$member_count = $member_result['member_count'];

// Check if current user is a member
$is_member = false;
$membership = null;

if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT * FROM club_members WHERE user_id = ? AND club_id = ?");
    $stmt->bind_param("ii", $user_id, $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $is_member = true;
        $membership = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $club['name']; ?> - College Club Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .club-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .club-logo-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin-right: 2rem;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
        }
        
        .club-logo-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .club-meta {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }
        
        .club-meta-item {
            display: flex;
            align-items: center;
            color: var(--gray-color);
        }
        
        .club-meta-item i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .club-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
        }
        
        .club-tabs {
            margin-top: 3rem;
        }
        
        .executive-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .executive-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            text-align: center;
        }
        
        .executive-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #e0e7ff;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .executive-role {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .executive-name {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        
        .executive-since {
            font-size: 0.875rem;
            color: var(--gray-color);
        }
        
        .rating-stars {
            color: #f59e0b;
            font-size: 1.25rem;
            margin-right: 0.5rem;
        }
        
        .rating-count {
            color: var(--gray-color);
            font-size: 0.875rem;
        }
    </style>
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
                    <a href="clubs.php" class="active">Clubs</a>
                    <a href="events.php">Events</a>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isStudent()): ?>
                            <a href="student_dashboard.php">My Dashboard</a>
                        <?php elseif (isClubLeader()): ?>
                            <a href="club_dashboard.php">Club Dashboard</a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-outline">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="club-details-section">
            <div class="container">
                <!-- Club Header -->
                <div class="club-header">
                    <div class="club-logo-large">
                        <?php if ($club['logo']): ?>
                            <img src="<?php echo $club['logo']; ?>" alt="<?php echo $club['name']; ?> Logo">
                        <?php else: ?>
                            <?php echo substr($club['name'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <div class="club-info">
                        <h1><?php echo $club['name']; ?></h1>
                        <div class="club-meta">
                            <div class="club-meta-item">
                                <i class="fas fa-tag"></i>
                                <span><?php echo $club['category']; ?></span>
                            </div>
                            <div class="club-meta-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $member_count; ?> members</span>
                            </div>
                            <div class="club-meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Established: <?php echo $club['established_date'] ? date('F Y', strtotime($club['established_date'])) : 'N/A'; ?></span>
                            </div>
                            <div class="club-meta-item">
                                <i class="fas fa-star"></i>
                                <span class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $avg_rating): ?>
                                            ★
                                        <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                            ★
                                        <?php else: ?>
                                            ☆
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </span>
                                <span class="rating-count">(<?php echo $rating_count; ?> ratings)</span>
                            </div>
                        </div>
                        
                        <div class="club-actions">
                            <?php if (isStudent()): ?>
                                <?php if ($is_member): ?>
                                    <a href="student_dashboard.php" class="btn btn-primary">View Membership</a>
                                    <?php if ($membership['active_status']): ?>
                                        <span class="badge badge-success">Active Member</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive Member</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="join_club.php?id=<?php echo $club_id; ?>" class="btn btn-primary">Join Club</a>
                                <?php endif; ?>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="login.php" class="btn btn-primary">Login to Join</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Club Description -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="card-title">About the Club</h2>
                    </div>
                    <div class="card-body">
                        <p><?php echo $club['description']; ?></p>
                    </div>
                </div>
                
                <!-- Club Tabs -->
                <div class="club-tabs tabs-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="executives">Executive Body</div>
                        <div class="tab" data-tab="events">Upcoming Events</div>
                    </div>
                    
                    <!-- Executive Body Tab -->
                    <div class="tab-content active" data-tab="executives">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Executive Body</h2>
                            </div>
                            <div class="card-body">
                                <?php if (count($executives) > 0): ?>
                                    <div class="executive-grid">
                                        <?php foreach ($executives as $executive): ?>
                                            <div class="executive-card">
                                                <div class="executive-avatar">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div class="executive-role"><?php echo $executive['role']; ?></div>
                                                <div class="executive-name"><?php echo $executive['user_id'] ? $executive['user_name'] : $executive['name']; ?></div>
                                                <div class="executive-since">Since: <?php echo date('F Y', strtotime($executive['start_date'])); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4">
                                        <p>No executive body information available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Events Tab -->
                    <div class="tab-content" data-tab="events">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Upcoming Events</h2>
                            </div>
                            <div class="card-body">
                                <?php if (count($upcoming_events) > 0): ?>
                                    <div class="event-cards">
                                        <?php foreach ($upcoming_events as $event): ?>
                                            <div class="event-card">
                                                <div class="event-date">
                                                    <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                                    <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                                </div>
                                                <div class="event-info">
                                                    <h3><?php echo $event['title']; ?></h3>
                                                    <p class="event-location"><i class="fas fa-map-marker-alt"></i> <?php echo $event['location']; ?></p>
                                                    <p class="event-time"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?></p>
                                                    
                                                    <div class="event-enrollment">
                                                        <?php if ($event['enrollment_open']): ?>
                                                            <span class="badge badge-success">Enrollment Open</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Enrollment Closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="event-actions">
                                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline">View Details</a>
                                                        
                                                        <?php if (isStudent() && $event['enrollment_open']): ?>
                                                            <?php
                                                            // Check if student is already enrolled
                                                            $user_id = getCurrentUserId();
                                                            $stmt = $conn->prepare("SELECT * FROM event_participation WHERE user_id = ? AND event_id = ?");
                                                            $stmt->bind_param("ii", $user_id, $event['id']);
                                                            $stmt->execute();
                                                            $is_enrolled = $stmt->get_result()->num_rows > 0;
                                                            ?>
                                                            
                                                            <?php if ($is_enrolled): ?>
                                                                <span class="badge badge-success">Enrolled</span>
                                                            <?php else: ?>
                                                                <a href="enroll_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">Enroll Now</a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="text-center mt-4">
                                        <a href="events.php" class="btn btn-secondary">View All Events</a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4">
                                        <p>No upcoming events scheduled.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
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
