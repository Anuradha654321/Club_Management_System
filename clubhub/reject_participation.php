<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();
$error = '';
$success = '';

// Check if participation ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: club_dashboard.php');
    exit;
}

$participation_id = sanitize($_GET['id']);

// Check if participation belongs to an event from this club
$stmt = $conn->prepare("
    SELECT ep.*, e.title as event_title, e.club_id, u.name as student_name
    FROM event_participation ep
    JOIN events e ON ep.event_id = e.id
    JOIN users u ON ep.user_id = u.id
    WHERE ep.id = ? AND e.club_id = ?
");
$stmt->bind_param("ii", $participation_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: club_dashboard.php');
    exit;
}

$participation = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = sanitize($_POST['feedback']);
    
    // Update participation status
    $stmt = $conn->prepare("UPDATE event_participation SET status = 'rejected', feedback = ? WHERE id = ?");
    $stmt->bind_param("si", $feedback, $participation_id);
    
    if ($stmt->execute()) {
        // Redirect back to dashboard with success message
        header('Location: club_dashboard.php?tab=participations&success=1&message=Participation+rejected+successfully');
        exit;
    } else {
        $error = "Failed to reject participation. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reject Participation - College Club Management System</title>
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
                    <a href="club_dashboard.php">Club Dashboard</a>
                    <a href="logout.php" class="btn btn-outline">Logout</a>
                </div>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="reject-participation-section">
            <div class="container">
                <div class="card" style="max-width: 700px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Reject Participation</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="participation-details mb-4">
                            <p><strong>Student:</strong> <?php echo $participation['student_name']; ?></p>
                            <p><strong>Event:</strong> <?php echo $participation['event_title']; ?></p>
                            
                            <?php if ($participation['proof_file']): ?>
                                <p><strong>Proof:</strong> <a href="<?php echo $participation['proof_file']; ?>" target="_blank">View Proof</a></p>
                            <?php else: ?>
                                <p><strong>Proof:</strong> <span class="badge badge-warning">No Proof Submitted</span></p>
                            <?php endif; ?>
                        </div>
                        
                        <form action="reject_participation.php?id=<?php echo $participation_id; ?>" method="POST" data-validate="true">
                            <div class="form-group">
                                <label for="feedback" class="form-label">Rejection Reason</label>
                                <textarea id="feedback" name="feedback" class="form-control" rows="4" required></textarea>
                                <div class="form-text">
                                    Please provide a reason for rejecting this participation. This will be visible to the student.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-danger">Reject Participation</button>
                                <a href="club_dashboard.php?tab=participations" class="btn btn-outline">Cancel</a>
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
