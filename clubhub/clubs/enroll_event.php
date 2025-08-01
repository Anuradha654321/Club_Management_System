<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require student login
requireStudent();

$user_id = getCurrentUserId();
$error = '';
$success = '';

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: events.php');
    exit;
}

$event_id = sanitize($_GET['id']);

// Get event details
$stmt = $conn->prepare("
    SELECT e.*, c.name as club_name 
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

// Check if enrollment is open
if (!$event['enrollment_open']) {
    header('Location: event_details.php?id=' . $event_id . '&error=1&message=Enrollment+is+closed+for+this+event');
    exit;
}

// Check if event is in the past
if ($event['status'] === 'past') {
    header('Location: event_details.php?id=' . $event_id . '&error=1&message=Cannot+enroll+in+past+events');
    exit;
}

// Check if student is already enrolled
$stmt = $conn->prepare("SELECT * FROM event_participation WHERE user_id = ? AND event_id = ?");
$stmt->bind_param("ii", $user_id, $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header('Location: event_details.php?id=' . $event_id . '&error=1&message=You+are+already+enrolled+in+this+event');
    exit;
}

// Check if max participants limit is reached
if ($event['max_participants']) {
    $stmt = $conn->prepare("SELECT COUNT(*) as participant_count FROM event_participation WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $count_result = $stmt->get_result()->fetch_assoc();
    
    if ($count_result['participant_count'] >= $event['max_participants']) {
        header('Location: event_details.php?id=' . $event_id . '&error=1&message=This+event+has+reached+its+maximum+participant+limit');
        exit;
    }
}

// Process enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Insert participation record
    $stmt = $conn->prepare("INSERT INTO event_participation (event_id, user_id, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param("ii", $event_id, $user_id);
    
    if ($stmt->execute()) {
        $participation_id = $conn->insert_id;
        $success = "You have successfully enrolled in this event. Please upload proof of participation after attending the event.";
        
        // Redirect to event details page after successful enrollment
        header("Location: event_details.php?id=$event_id&success=1&message=Successfully+enrolled+in+the+event");
        exit;
    } else {
        $error = "Failed to enroll in the event. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll in Event - College Club Management System</title>
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
                    <a href="student_dashboard.php">My Dashboard</a>
                    <a href="logout.php" class="btn btn-outline">Logout</a>
                </div>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="enroll-event-section">
            <div class="container">
                <div class="card" style="max-width: 700px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Enroll in Event</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-sm btn-primary">View Event Details</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-details mb-4">
                            <h3><?php echo $event['title']; ?></h3>
                            <p><strong>Club:</strong> <?php echo $event['club_name']; ?></p>
                            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($event['event_date'])); ?> at <?php echo date('h:i A', strtotime($event['event_time'])); ?></p>
                            <p><strong>Location:</strong> <?php echo $event['location']; ?></p>
                            <p><strong>Status:</strong> 
                                <?php if ($event['status'] === 'upcoming'): ?>
                                    <span class="badge badge-primary">Upcoming</span>
                                <?php elseif ($event['status'] === 'ongoing'): ?>
                                    <span class="badge badge-success">Ongoing</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Past</span>
                                <?php endif; ?>
                            </p>
                            
                            <?php if ($event['max_participants']): ?>
                                <?php
                                // Get current participant count
                                $stmt = $conn->prepare("SELECT COUNT(*) as participant_count FROM event_participation WHERE event_id = ?");
                                $stmt->bind_param("i", $event_id);
                                $stmt->execute();
                                $count_result = $stmt->get_result()->fetch_assoc();
                                $participant_count = $count_result['participant_count'];
                                $seats_left = $event['max_participants'] - $participant_count;
                                ?>
                                <p><strong>Seats Available:</strong> <?php echo $seats_left; ?> out of <?php echo $event['max_participants']; ?></p>
                            <?php endif; ?>
                            
                            <div class="event-description">
                                <p><strong>Description:</strong></p>
                                <p><?php echo $event['description']; ?></p>
                            </div>
                        </div>
                        
                        <form action="enroll_event.php?id=<?php echo $event_id; ?>" method="POST">
                            <div class="enrollment-confirmation">
                                <p>By enrolling in this event, you agree to:</p>
                                <ul>
                                    <li>Attend the event on the specified date and time</li>
                                    <li>Follow all rules and guidelines set by the club</li>
                                    <li>Upload proof of participation after the event</li>
                                </ul>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Confirm Enrollment</button>
                                <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
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
    <style>
        .enrollment-confirmation {
            background-color: #f9fafb;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }
        
        .enrollment-confirmation ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            margin-top: 0.5rem;
        }
        
        .enrollment-confirmation li {
            margin-bottom: 0.5rem;
        }
        
        .event-description {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
        }
    </style>
</body>
</html>
