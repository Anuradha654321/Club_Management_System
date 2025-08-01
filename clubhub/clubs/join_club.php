<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require student login
requireStudent();

$user_id = getCurrentUserId();
$error = '';
$success = '';

// Get all clubs
$query = "SELECT * FROM clubs ORDER BY name";
$result = $conn->query($query);
$clubs = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
}

// Get clubs already joined by the student
$stmt = $conn->prepare("SELECT club_id FROM club_members WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$joined_club_ids = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $joined_club_ids[] = $row['club_id'];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $club_id = sanitize($_POST['club_id']);
    $role = sanitize($_POST['role']);
    $join_date = date('Y-m-d');
    
    // Validate inputs
    if (empty($club_id) || empty($role)) {
        $error = 'Please select a club and role.';
    } else if (in_array($club_id, $joined_club_ids)) {
        $error = 'You have already joined this club.';
    } else {
        // Insert new club membership
        $stmt = $conn->prepare("INSERT INTO club_members (user_id, club_id, role, join_date, active_status) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("iiss", $user_id, $club_id, $role, $join_date);
        
        if ($stmt->execute()) {
            // Get club name for success message
            $stmt = $conn->prepare("SELECT name FROM clubs WHERE id = ?");
            $stmt->bind_param("i", $club_id);
            $stmt->execute();
            $club_name = $stmt->get_result()->fetch_assoc()['name'];
            
            $success = "You have successfully joined $club_name as $role.";
            
            // Refresh joined club IDs
            $stmt = $conn->prepare("SELECT club_id FROM club_members WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $joined_club_ids = [];
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $joined_club_ids[] = $row['club_id'];
                }
            }
        } else {
            $error = 'Failed to join club. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Club - College Club Management System</title>
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
        <section class="join-club-section">
            <div class="container">
                <div class="card" style="max-width: 800px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Join a Club</h2>
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
                                    <a href="student_dashboard.php" class="btn btn-sm btn-primary">Back to Dashboard</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form action="join_club.php" method="POST" data-validate="true">
                            <div class="form-group">
                                <label for="club_id" class="form-label">Select Club</label>
                                <select id="club_id" name="club_id" class="form-select" required>
                                    <option value="">Select a club to join</option>
                                    <?php foreach ($clubs as $club): ?>
                                        <?php if (!in_array($club['id'], $joined_club_ids)): ?>
                                            <option value="<?php echo $club['id']; ?>"><?php echo $club['name']; ?> (<?php echo $club['category']; ?>)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">Select Role</label>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="">Select your role</option>
                                    <option value="Member">Member</option>
                                    <option value="President">President</option>
                                    <option value="Secretary">Secretary</option>
                                    <option value="Treasurer">Treasurer</option>
                                    <option value="Vice President">Vice President</option>
                                    <option value="Joint Secretary">Joint Secretary</option>
                                </select>
                                <div class="form-text">
                                    Note: Leadership roles (President, Secretary, etc.) may require approval from club administrators.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Join Club</button>
                                <a href="student_dashboard.php" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                        
                        <div class="mt-4">
                            <h3>Already Joined Clubs</h3>
                            <?php if (count($joined_club_ids) > 0): ?>
                                <ul class="joined-clubs-list">
                                    <?php 
                                    foreach ($joined_club_ids as $joined_id) {
                                        foreach ($clubs as $club) {
                                            if ($club['id'] == $joined_id) {
                                                echo '<li>' . $club['name'] . ' (' . $club['category'] . ')</li>';
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                </ul>
                            <?php else: ?>
                                <p>You haven't joined any clubs yet.</p>
                            <?php endif; ?>
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
