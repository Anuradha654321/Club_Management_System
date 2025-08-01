<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Get all clubs
$query = "SELECT * FROM clubs ORDER BY name";
$result = $conn->query($query);
$clubs = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
}

// Get upcoming events
$query = "SELECT e.*, c.name as club_name 
          FROM events e 
          JOIN clubs c ON e.club_id = c.id 
          WHERE e.status = 'upcoming' 
          ORDER BY e.event_date ASC 
          LIMIT 5";
$result = $conn->query($query);
$events = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Club Management System</title>
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
                    <a href="index.php" class="active">Home</a>
                    <a href="clubs.php">Clubs</a>
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
        
        <div class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Discover & Join College Clubs</h1>
                    <p>Connect with like-minded students, participate in events, and enhance your college experience.</p>
                    <?php if (!isLoggedIn()): ?>
                        <div class="hero-buttons">
                            <a href="register.php" class="btn btn-primary">Get Started</a>
                            <a href="clubs.php" class="btn btn-secondary">Explore Clubs</a>
                        </div>
                    <?php else: ?>
                        <div class="hero-buttons">
                            <a href="clubs.php" class="btn btn-primary">Explore Clubs</a>
                            <a href="events.php" class="btn btn-secondary">Upcoming Events</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main>
        <!-- Featured Clubs Section -->
        <section class="featured-clubs">
            <div class="container">
                <h2 class="section-title">Featured Clubs</h2>
                <div class="club-cards">
                    <?php foreach ($clubs as $club): ?>
                        <div class="club-card">
                            <div class="club-logo">
                                <?php if ($club['logo']): ?>
                                    <img src="<?php echo $club['logo']; ?>" alt="<?php echo $club['name']; ?> Logo">
                                <?php else: ?>
                                    <div class="placeholder-logo"><?php echo substr($club['name'], 0, 1); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="club-info">
                                <h3><?php echo $club['name']; ?></h3>
                                <p class="club-category"><?php echo $club['category']; ?></p>
                                <p class="club-description"><?php echo substr($club['description'], 0, 100) . '...'; ?></p>
                                <a href="club_details.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-outline">Learn More</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="view-all">
                    <a href="clubs.php" class="btn btn-secondary">View All Clubs</a>
                </div>
            </div>
        </section>

        <!-- Upcoming Events Section -->
        <section class="upcoming-events">
            <div class="container">
                <h2 class="section-title">Upcoming Events</h2>
                <?php if (count($events) > 0): ?>
                    <div class="event-cards">
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <div class="event-date">
                                    <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                </div>
                                <div class="event-info">
                                    <h3><?php echo $event['title']; ?></h3>
                                    <p class="event-club"><?php echo $event['club_name']; ?></p>
                                    <p class="event-location"><i class="fas fa-map-marker-alt"></i> <?php echo $event['location']; ?></p>
                                    <p class="event-time"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?></p>
                                    <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline">Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="view-all">
                        <a href="events.php" class="btn btn-secondary">View All Events</a>
                    </div>
                <?php else: ?>
                    <div class="no-events">
                        <p>No upcoming events at the moment. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Benefits Section -->
        <section class="benefits">
            <div class="container">
                <h2 class="section-title">Why Join College Clubs?</h2>
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Build Connections</h3>
                        <p>Connect with like-minded students and build a valuable network for your future.</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h3>Develop Skills</h3>
                        <p>Gain practical experience and develop skills that complement your academic learning.</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h3>Enhance Resume</h3>
                        <p>Add valuable extracurricular activities to your resume and stand out to employers.</p>
                    </div>
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3>Have Fun</h3>
                        <p>Enjoy your college experience by participating in fun and engaging activities.</p>
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
