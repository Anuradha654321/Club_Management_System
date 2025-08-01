<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isStudent()) {
        header('Location: student_dashboard.php');
    } else if (isClubLeader()) {
        header('Location: club_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

// Get all clubs for club leader registration
$query = "SELECT id, name FROM clubs ORDER BY name";
$result = $conn->query($query);
$clubs = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize($_POST['user_type']);
    $student_id = isset($_POST['student_id']) ? sanitize($_POST['student_id']) : null;
    $club_id = isset($_POST['club_id']) ? sanitize($_POST['club_id']) : null;
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($user_type)) {
        $error = 'Please fill in all required fields.';
    } else if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else if ($user_type === 'student' && empty($student_id)) {
        $error = 'Student ID is required for student registration.';
    } else if ($user_type === 'club_leader' && empty($club_id)) {
        $error = 'Please select a club.';
    } else {
        // Register user
        $result = registerUser($name, $email, $password, $user_type, $student_id);
        
        if ($result['success']) {
            // If club leader, update user with club_id
            if ($user_type === 'club_leader' && $result['user_id']) {
                $stmt = $conn->prepare("UPDATE users SET club_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $club_id, $result['user_id']);
                $stmt->execute();
            }
            
            $success = $result['message'] . ' You can now login.';
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - College Club Management System</title>
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
                    <a href="login.php">Login</a>
                    <a href="register.php" class="active btn btn-primary">Register</a>
                </div>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="register-section">
            <div class="container">
                <div class="card" style="max-width: 600px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Register</h2>
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
                                    <a href="login.php" class="btn btn-sm btn-primary">Login Now</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form action="register.php" method="POST" data-validate="true">
                                <div class="form-group">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" id="name" name="name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user_type" class="form-label">Register as</label>
                                    <select id="user_type" name="user_type" class="form-select" required>
                                        <option value="">Select role</option>
                                        <option value="student">Student</option>
                                        <option value="club_leader">Club Leader</option>
                                    </select>
                                </div>
                                
                                <div id="student-fields" class="form-group" style="display: none;">
                                    <label for="student_id" class="form-label">Student ID</label>
                                    <input type="text" id="student_id" name="student_id" class="form-control">
                                </div>
                                
                                <div id="club-fields" class="form-group" style="display: none;">
                                    <label for="club_id" class="form-label">Select Club</label>
                                    <select id="club_id" name="club_id" class="form-select">
                                        <option value="">Select club</option>
                                        <?php foreach ($clubs as $club): ?>
                                            <option value="<?php echo $club['id']; ?>"><?php echo $club['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        Note: Club leader registrations require approval from administrators.
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-3">
                                <p>Already have an account? <a href="login.php" class="text-primary">Login here</a></p>
                            </div>
                        <?php endif; ?>
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
    <script>
        // Show/hide fields based on user type
        document.getElementById('user_type').addEventListener('change', function() {
            const studentFields = document.getElementById('student-fields');
            const clubFields = document.getElementById('club-fields');
            
            if (this.value === 'student') {
                studentFields.style.display = 'block';
                clubFields.style.display = 'none';
            } else if (this.value === 'club_leader') {
                studentFields.style.display = 'none';
                clubFields.style.display = 'block';
            } else {
                studentFields.style.display = 'none';
                clubFields.style.display = 'none';
            }
        });
    </script>
</body>
</html>
