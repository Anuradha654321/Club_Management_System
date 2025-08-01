<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require student login
requireStudent();

$user_id = getCurrentUserId();
$error = '';
$success = '';

// Check if club ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: student_dashboard.php');
    exit;
}

$club_id = sanitize($_GET['id']);

// Check if student is a member of the club
$stmt = $conn->prepare("SELECT * FROM club_members WHERE user_id = ? AND club_id = ?");
$stmt->bind_param("ii", $user_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: student_dashboard.php');
    exit;
}

// Get club details
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

// Check if user has already rated this club
$stmt = $conn->prepare("SELECT * FROM club_ratings WHERE user_id = ? AND club_id = ?");
$stmt->bind_param("ii", $user_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();
$existing_rating = $result->num_rows > 0 ? $result->fetch_assoc() : null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = sanitize($_POST['rating']);
    $feedback = sanitize($_POST['feedback']);
    
    // Validate inputs
    if (empty($rating) || $rating < 1 || $rating > 5) {
        $error = 'Please provide a valid rating (1-5).';
    } else {
        if ($existing_rating) {
            // Update existing rating
            $stmt = $conn->prepare("UPDATE club_ratings SET rating = ?, review = ? WHERE id = ?");
            $stmt->bind_param("isi", $rating, $feedback, $existing_rating['id']);
            
            if ($stmt->execute()) {
                $success = 'Your rating has been updated successfully.';
                
                // Refresh existing rating
                $stmt = $conn->prepare("SELECT * FROM club_ratings WHERE user_id = ? AND club_id = ?");
                $stmt->bind_param("ii", $user_id, $club_id);
                $stmt->execute();
                $existing_rating = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update rating. Please try again.';
            }
        } else {
            // Insert new rating
            $stmt = $conn->prepare("INSERT INTO club_ratings (club_id, user_id, rating, review) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $club_id, $user_id, $rating, $feedback);
            
            if ($stmt->execute()) {
                $success = 'Your rating has been submitted successfully.';
                
                // Get the new rating
                $stmt = $conn->prepare("SELECT * FROM club_ratings WHERE user_id = ? AND club_id = ?");
                $stmt->bind_param("ii", $user_id, $club_id);
                $stmt->execute();
                $existing_rating = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to submit rating. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Club - College Club Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }
        
        .rating-input .star {
            font-size: 2rem;
            color: #d1d5db;
            cursor: pointer;
            transition: color 0.2s ease;
            margin-right: 0.25rem;
        }
        
        .rating-input .star:hover,
        .rating-input .star.hover,
        .rating-input .star.active {
            color: #f59e0b;
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
        <section class="rate-club-section">
            <div class="container">
                <div class="card" style="max-width: 700px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Rate Club: <?php echo $club['name']; ?></h2>
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
                        
                        <div class="club-details mb-4">
                            <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <?php if ($club['logo']): ?>
                                    <img src="<?php echo $club['logo']; ?>" alt="<?php echo $club['name']; ?>" style="width: 60px; height: 60px; margin-right: 1rem; border-radius: 50%;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; margin-right: 1rem; border-radius: 50%; background-color: #4f46e5; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold;">
                                        <?php echo substr($club['name'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin-bottom: 0.25rem;"><?php echo $club['name']; ?></h3>
                                    <span class="badge badge-primary"><?php echo $club['category']; ?></span>
                                </div>
                            </div>
                            <p><?php echo $club['description']; ?></p>
                        </div>
                        
                        <form action="rate_club.php?id=<?php echo $club_id; ?>" method="POST" data-validate="true">
                            <div class="form-group">
                                <label class="form-label">Your Rating</label>
                                <div class="rating-input">
                                    <span class="star" data-value="5">★</span>
                                    <span class="star" data-value="4">★</span>
                                    <span class="star" data-value="3">★</span>
                                    <span class="star" data-value="2">★</span>
                                    <span class="star" data-value="1">★</span>
                                </div>
                                <input type="hidden" name="rating" id="rating-value" value="<?php echo $existing_rating ? $existing_rating['rating'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="feedback" class="form-label">Your Feedback</label>
                                <textarea id="feedback" name="feedback" class="form-control" rows="4"><?php echo $existing_rating ? $existing_rating['review'] : ''; ?></textarea>
                                <div class="form-text">
                                    Please provide your honest feedback about your experience with this club.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary"><?php echo $existing_rating ? 'Update Rating' : 'Submit Rating'; ?></button>
                                <a href="student_dashboard.php" class="btn btn-outline">Cancel</a>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating-input .star');
            const ratingInput = document.getElementById('rating-value');
            
            // Set initial rating if exists
            if (ratingInput.value) {
                const rating = parseInt(ratingInput.value);
                stars.forEach(star => {
                    if (parseInt(star.getAttribute('data-value')) <= rating) {
                        star.classList.add('active');
                    }
                });
            }
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    ratingInput.value = value;
                    
                    // Update UI
                    stars.forEach(s => {
                        if (parseInt(s.getAttribute('data-value')) <= value) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
                
                star.addEventListener('mouseover', function() {
                    const value = this.getAttribute('data-value');
                    
                    // Update UI on hover
                    stars.forEach(s => {
                        if (parseInt(s.getAttribute('data-value')) <= value) {
                            s.classList.add('hover');
                        } else {
                            s.classList.remove('hover');
                        }
                    });
                });
                
                star.addEventListener('mouseout', function() {
                    stars.forEach(s => s.classList.remove('hover'));
                });
            });
        });
    </script>
</body>
</html>
