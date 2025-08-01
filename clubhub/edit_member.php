<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();
$error = '';
$success = '';

// Check if member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: club_dashboard.php?tab=members');
    exit;
}

$member_id = sanitize($_GET['id']);

// Check if member belongs to this club
$stmt = $conn->prepare("
    SELECT cm.*, u.name, u.email, u.student_id
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.id = ? AND cm.club_id = ?
");
$stmt->bind_param("ii", $member_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: club_dashboard.php?tab=members');
    exit;
}

$member = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = sanitize($_POST['role']);
    $active_status = isset($_POST['active_status']) ? 1 : 0;
    
    // Update member information
    $stmt = $conn->prepare("UPDATE club_members SET role = ?, active_status = ? WHERE id = ?");
    $stmt->bind_param("sii", $role, $active_status, $member_id);
    
    if ($stmt->execute()) {
        $success = "Member information updated successfully.";
    } else {
        $error = "Failed to update member information. Please try again.";
    }
    
    // Refresh member data
    $stmt = $conn->prepare("
        SELECT cm.*, u.name, u.email, u.student_id
        FROM club_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.id = ? AND cm.club_id = ?
    ");
    $stmt->bind_param("ii", $member_id, $club_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - College Club Management System</title>
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
        <section class="edit-member-section">
            <div class="container">
                <div class="card" style="max-width: 700px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Edit Member</h2>
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
                            </div>
                        <?php endif; ?>
                        
                        <div class="member-details mb-4">
                            <p><strong>Name:</strong> <?php echo $member['name']; ?></p>
                            <p><strong>Email:</strong> <?php echo $member['email']; ?></p>
                            <p><strong>Student ID:</strong> <?php echo $member['student_id']; ?></p>
                            <p><strong>Join Date:</strong> <?php echo date('F d, Y', strtotime($member['join_date'])); ?></p>
                        </div>
                        
                        <form action="edit_member.php?id=<?php echo $member_id; ?>" method="POST">
                            <div class="form-group">
                                <label for="role" class="form-label">Role</label>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="Member" <?php echo ($member['role'] === 'Member') ? 'selected' : ''; ?>>Member</option>
                                    <option value="President" <?php echo ($member['role'] === 'President') ? 'selected' : ''; ?>>President</option>
                                    <option value="Secretary" <?php echo ($member['role'] === 'Secretary') ? 'selected' : ''; ?>>Secretary</option>
                                    <option value="Treasurer" <?php echo ($member['role'] === 'Treasurer') ? 'selected' : ''; ?>>Treasurer</option>
                                    <option value="Vice President" <?php echo ($member['role'] === 'Vice President') ? 'selected' : ''; ?>>Vice President</option>
                                    <option value="Joint Secretary" <?php echo ($member['role'] === 'Joint Secretary') ? 'selected' : ''; ?>>Joint Secretary</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" id="active_status" name="active_status" <?php echo ($member['active_status']) ? 'checked' : ''; ?>>
                                    <label for="active_status">Active Member</label>
                                </div>
                                <div class="form-text">
                                    Inactive members will not be able to participate in club activities.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Update Member</button>
                                <a href="club_dashboard.php?tab=members" class="btn btn-outline">Cancel</a>
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
