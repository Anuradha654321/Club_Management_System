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

// Get club categories for filter
$categories = ['IEEE', 'CSI', 'IAPC', 'IESE', 'Department'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubs - College Club Management System</title>
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
        <section class="clubs-section">
            <div class="container">
                <div class="section-header">
                    <h1 class="section-title">Explore Clubs</h1>
                    <p class="section-description">Discover and join clubs that match your interests.</p>
                </div>

                <div class="filter-bar">
                    <div class="search-box">
                        <input type="text" id="club-search" placeholder="Search clubs..." class="form-control">
                    </div>
                    <div class="filter-dropdown">
                        <select id="club-filter" class="form-select">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

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
                                
                                <?php
                                // Get club rating
                                $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM club_ratings WHERE club_id = ?");
                                $stmt->bind_param("i", $club['id']);
                                $stmt->execute();
                                $rating_result = $stmt->get_result()->fetch_assoc();
                                $avg_rating = $rating_result['avg_rating'] ? round($rating_result['avg_rating'], 1) : 0;
                                
                                // Get member count
                                $stmt = $conn->prepare("SELECT COUNT(*) as member_count FROM club_members WHERE club_id = ?");
                                $stmt->bind_param("i", $club['id']);
                                $stmt->execute();
                                $member_result = $stmt->get_result()->fetch_assoc();
                                $member_count = $member_result['member_count'];
                                ?>
                                
                                <div class="club-meta">
                                    <span class="club-rating">
                                        <i class="fas fa-star"></i> <?php echo $avg_rating; ?>
                                    </span>
                                    <span class="club-members">
                                        <i class="fas fa-users"></i> <?php echo $member_count; ?> members
                                    </span>
                                </div>
                                
                                <a href="club_details.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-outline">View Details</a>
                                
                                <?php if (isStudent()): ?>
                                    <?php
                                    // Check if student is already a member
                                    $user_id = getCurrentUserId();
                                    $stmt = $conn->prepare("SELECT * FROM club_members WHERE user_id = ? AND club_id = ?");
                                    $stmt->bind_param("ii", $user_id, $club['id']);
                                    $stmt->execute();
                                    $is_member = $stmt->get_result()->num_rows > 0;
                                    ?>
                                    
                                    <?php if ($is_member): ?>
                                        <span class="badge badge-success">Joined</span>
                                    <?php else: ?>
                                        <a href="join_club.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-primary">Join Club</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
    <style>
        .filter-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .search-box {
            flex: 1;
        }
        
        .filter-dropdown {
            width: 200px;
        }
        
        .club-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--gray-color);
            font-size: 0.875rem;
        }
        
        .club-rating i, .club-members i {
            color: var(--primary-color);
            margin-right: 0.25rem;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .section-description {
            color: var(--gray-color);
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</body>
</html>
