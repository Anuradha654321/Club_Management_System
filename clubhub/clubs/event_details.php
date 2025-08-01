<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: events.php');
    exit;
}

$event_id = sanitize($_GET['id']);

// Get event details
$stmt = $conn->prepare("
    SELECT e.*, c.name as club_name, c.id as club_id, c.category as club_category
    FROM events e
    JOIN clubs c ON e.club_id = c.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: events.php');
    exit;
}

$event = $result->fetch_assoc();

// Get event media
$stmt = $conn->prepare("
    SELECT * FROM event_media
    WHERE event_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$media_items = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $media_items[] = $row;
    }
}

// Get participant count
$stmt = $conn->prepare("SELECT COUNT(*) as participant_count FROM event_participation WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$count_result = $stmt->get_result()->fetch_assoc();
$participant_count = $count_result['participant_count'];

// Check if current user is enrolled
$is_enrolled = false;
$participation = null;

if (isLoggedIn() && isStudent()) {
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT * FROM event_participation WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $is_enrolled = true;
        $participation = $result->fetch_assoc();
    }
}

// Handle success/error messages
$success_message = '';
$error_message = '';

if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['message'])) {
    $success_message = $_GET['message'];
}

if (isset($_GET['error']) && $_GET['error'] == 1 && isset($_GET['message'])) {
    $error_message = $_GET['message'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $event['title']; ?> - College Club Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .event-header {
            background-color: var(--primary-color);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .event-title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
        }
        
        .event-meta-item i {
            margin-right: 0.5rem;
        }
        
        .event-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
        }
        
        .event-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
            margin-left: 1rem;
        }
        
        .event-status.upcoming {
            background-color: #e0e7ff;
            color: #4f46e5;
        }
        
        .event-status.ongoing {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .event-status.past {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .event-details-card {
            margin-bottom: 2rem;
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .media-item {
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .media-preview {
            height: 150px;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .media-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .media-preview .file-icon {
            font-size: 3rem;
            color: var(--gray-color);
        }
        
        .media-info {
            padding: 0.75rem;
        }
        
        .media-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .media-type.photo {
            background-color: #e0e7ff;
            color: #4f46e5;
        }
        
        .media-type.report {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .media-type.other {
            background-color: #fef3c7;
            color: #92400e;
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
                    <a href="clubs.php">Clubs</a>
                    <a href="events.php" class="active">Events</a>
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
        <!-- Event Header -->
        <section class="event-header">
            <div class="container">
                <div class="event-header-content">
                    <h1 class="event-title">
                        <?php echo $event['title']; ?>
                        <span class="event-status <?php echo $event['status']; ?>">
                            <?php echo ucfirst($event['status']); ?>
                        </span>
                    </h1>
                    <p class="event-club">
                        Organized by <a href="club_details.php?id=<?php echo $event['club_id']; ?>" class="text-white"><?php echo $event['club_name']; ?></a>
                        <span class="badge badge-primary"><?php echo $event['club_category']; ?></span>
                    </p>
                    
                    <div class="event-meta">
                        <div class="event-meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date('F d, Y', strtotime($event['event_date'])); ?></span>
                        </div>
                        <div class="event-meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $event['event_time']; ?></span>
                        </div>
                        <div class="event-meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo $event['location']; ?></span>
                        </div>
                        <div class="event-meta-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $participant_count; ?> participants</span>
                        </div>
                    </div>
                    
                    <div class="event-actions">
                        <?php if (isStudent()): ?>
                            <?php if ($is_enrolled): ?>
                                <span class="badge badge-success">Enrolled</span>
                                
                                <?php if ($event['status'] === 'past'): ?>
                                    <?php if (!$participation['proof_file'] || $participation['status'] === 'rejected'): ?>
                                        <a href="upload_proof.php?id=<?php echo $participation['id']; ?>" class="btn btn-primary">Upload Proof</a>
                                    <?php elseif ($participation['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Proof Pending Approval</span>
                                    <?php elseif ($participation['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Participation Approved</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php elseif ($event['status'] !== 'past' && $event['enrollment_open']): ?>
                                <a href="enroll_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">Enroll Now</a>
                            <?php elseif ($event['status'] !== 'past' && !$event['enrollment_open']): ?>
                                <span class="badge badge-danger">Enrollment Closed</span>
                            <?php endif; ?>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="login.php" class="btn btn-primary">Login to Enroll</a>
                        <?php endif; ?>
                        
                        <a href="club_details.php?id=<?php echo $event['club_id']; ?>" class="btn btn-outline btn-light">View Club</a>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="event-details-section">
            <div class="container">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Event Description -->
                <div class="card event-details-card">
                    <div class="card-header">
                        <h2 class="card-title">Event Description</h2>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br($event['description']); ?></p>
                        
                        <?php if ($event['max_participants']): ?>
                            <div class="alert alert-info mt-3">
                                <p><strong>Limited Capacity:</strong> This event has a maximum capacity of <?php echo $event['max_participants']; ?> participants.</p>
                                <p><strong>Current Enrollment:</strong> <?php echo $participant_count; ?> / <?php echo $event['max_participants']; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Event Media -->
                <?php if (count($media_items) > 0): ?>
                    <div class="card event-details-card">
                        <div class="card-header">
                            <h2 class="card-title">Event Media</h2>
                        </div>
                        <div class="card-body">
                            <div class="media-grid">
                                <?php foreach ($media_items as $media): ?>
                                    <div class="media-item">
                                        <div class="media-preview">
                                            <?php 
                                            $fileExtension = strtolower(pathinfo($media['file_path'], PATHINFO_EXTENSION));
                                            if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])):
                                            ?>
                                                <img src="<?php echo $media['file_path']; ?>" alt="Event Media">
                                            <?php else: ?>
                                                <div class="file-icon">
                                                    <i class="far fa-file-alt"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="media-info">
                                            <div class="media-type <?php echo $media['media_type']; ?>">
                                                <?php echo ucfirst($media['media_type']); ?>
                                            </div>
                                            <a href="<?php echo $media['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Enrollment Information -->
                <?php if ($event['status'] !== 'past' && $event['enrollment_open']): ?>
                    <div class="card event-details-card">
                        <div class="card-header">
                            <h2 class="card-title">Enrollment Information</h2>
                        </div>
                        <div class="card-body">
                            <p>This event is open for enrollment. Join now to participate!</p>
                            
                            <?php if (!isLoggedIn()): ?>
                                <p>Please <a href="login.php" class="text-primary">login</a> to enroll in this event.</p>
                            <?php elseif (isStudent() && !$is_enrolled): ?>
                                <a href="enroll_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">Enroll Now</a>
                            <?php elseif (isStudent() && $is_enrolled): ?>
                                <p>You are already enrolled in this event.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
