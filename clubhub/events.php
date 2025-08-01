<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Get all events
$query = "SELECT e.*, c.name as club_name, c.category as club_category
          FROM events e 
          JOIN clubs c ON e.club_id = c.id 
          ORDER BY e.event_date ASC";
$result = $conn->query($query);
$events = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

// Separate events by status
$upcoming_events = array_filter($events, function($event) {
    return $event['status'] === 'upcoming';
});

$ongoing_events = array_filter($events, function($event) {
    return $event['status'] === 'ongoing';
});

$past_events = array_filter($events, function($event) {
    return $event['status'] === 'past';
});

// Get club categories for filter
$query = "SELECT DISTINCT category FROM clubs ORDER BY category";
$result = $conn->query($query);
$categories = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - College Club Management System</title>
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
        <section class="events-section">
            <div class="container">
                <div class="section-header">
                    <h1 class="section-title">College Events</h1>
                    <p class="section-description">Discover and participate in exciting events organized by various clubs.</p>
                </div>

                <div class="filter-bar">
                    <div class="search-box">
                        <input type="text" id="event-search" placeholder="Search events..." class="form-control">
                    </div>
                    <div class="filter-dropdown">
                        <select id="event-filter-status" class="form-select">
                            <option value="all">All Status</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="past">Past</option>
                        </select>
                    </div>
                    <div class="filter-dropdown">
                        <select id="event-filter-category" class="form-select">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="event-section" id="upcoming-events">
                    <h2>Upcoming Events</h2>
                    <?php if (count($upcoming_events) > 0): ?>
                        <div class="event-cards">
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="event-card" data-category="<?php echo $event['club_category']; ?>" data-status="upcoming">
                                    <div class="event-date">
                                        <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h3><?php echo $event['title']; ?></h3>
                                        <p class="event-club"><?php echo $event['club_name']; ?> <span class="badge badge-primary"><?php echo $event['club_category']; ?></span></p>
                                        <p class="event-location"><i class="fas fa-map-marker-alt"></i> <?php echo $event['location']; ?></p>
                                        <p class="event-time"><i class="far fa-clock"></i> <?php echo $event['event_time']; ?></p>
                                        
                                        <div class="event-enrollment">
                                            <?php if ($event['enrollment_open']): ?>
                                                <span class="badge badge-success">Enrollment Open</span>
                                                <?php if ($event['max_participants']): ?>
                                                    <span class="badge badge-info">Limited Seats: <?php echo $event['max_participants']; ?></span>
                                                <?php endif; ?>
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
                    <?php else: ?>
                        <div class="no-events">
                            <p>No upcoming events at the moment. Check back later!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ongoing Events -->
                <div class="event-section" id="ongoing-events">
                    <h2>Ongoing Events</h2>
                    <?php if (count($ongoing_events) > 0): ?>
                        <div class="event-cards">
                            <?php foreach ($ongoing_events as $event): ?>
                                <div class="event-card" data-category="<?php echo $event['club_category']; ?>" data-status="ongoing">
                                    <div class="event-date">
                                        <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h3><?php echo $event['title']; ?></h3>
                                        <p class="event-club"><?php echo $event['club_name']; ?> <span class="badge badge-primary"><?php echo $event['club_category']; ?></span></p>
                                        <p class="event-location"><i class="fas fa-map-marker-alt"></i> <?php echo $event['location']; ?></p>
                                        <p class="event-time"><i class="far fa-clock"></i> <?php echo $event['event_time']; ?></p>
                                        
                                        <div class="event-enrollment">
                                            <?php if ($event['enrollment_open']): ?>
                                                <span class="badge badge-success">Enrollment Open</span>
                                                <?php if ($event['max_participants']): ?>
                                                    <span class="badge badge-info">Limited Seats: <?php echo $event['max_participants']; ?></span>
                                                <?php endif; ?>
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
                    <?php else: ?>
                        <div class="no-events">
                            <p>No ongoing events at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Past Events -->
                <div class="event-section" id="past-events">
                    <h2>Past Events</h2>
                    <?php if (count($past_events) > 0): ?>
                        <div class="event-cards">
                            <?php foreach ($past_events as $event): ?>
                                <div class="event-card past" data-category="<?php echo $event['club_category']; ?>" data-status="past">
                                    <div class="event-date">
                                        <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h3><?php echo $event['title']; ?></h3>
                                        <p class="event-club"><?php echo $event['club_name']; ?> <span class="badge badge-primary"><?php echo $event['club_category']; ?></span></p>
                                        <p class="event-location"><i class="fas fa-map-marker-alt"></i> <?php echo $event['location']; ?></p>
                                        <p class="event-time"><i class="far fa-clock"></i> <?php echo $event['event_time']; ?></p>
                                        
                                        <div class="event-actions">
                                            <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline">View Details</a>
                                            
                                            <?php if (isStudent()): ?>
                                                <?php
                                                // Check if student participated
                                                $user_id = getCurrentUserId();
                                                $stmt = $conn->prepare("SELECT * FROM event_participation WHERE user_id = ? AND event_id = ?");
                                                $stmt->bind_param("ii", $user_id, $event['id']);
                                                $stmt->execute();
                                                $participation = $stmt->get_result()->fetch_assoc();
                                                
                                                if ($participation):
                                                ?>
                                                    <?php if ($participation['status'] === 'approved'): ?>
                                                        <span class="badge badge-success">Participation Approved</span>
                                                    <?php elseif ($participation['status'] === 'rejected'): ?>
                                                        <span class="badge badge-danger">Participation Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Participation Pending</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$participation['proof_file'] || $participation['status'] === 'rejected'): ?>
                                                        <a href="upload_proof.php?id=<?php echo $participation['id']; ?>" class="btn btn-sm btn-primary">Upload Proof</a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-events">
                            <p>No past events to display.</p>
                        </div>
                    <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('event-search');
            const statusFilter = document.getElementById('event-filter-status');
            const categoryFilter = document.getElementById('event-filter-category');
            const eventCards = document.querySelectorAll('.event-card');
            
            // Function to filter events
            function filterEvents() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                const categoryValue = categoryFilter.value;
                
                eventCards.forEach(card => {
                    const title = card.querySelector('h3').textContent.toLowerCase();
                    const club = card.querySelector('.event-club').textContent.toLowerCase();
                    const location = card.querySelector('.event-location').textContent.toLowerCase();
                    const status = card.getAttribute('data-status');
                    const category = card.getAttribute('data-category');
                    
                    const matchesSearch = title.includes(searchTerm) || 
                                        club.includes(searchTerm) || 
                                        location.includes(searchTerm);
                    
                    const matchesStatus = statusValue === 'all' || status === statusValue;
                    const matchesCategory = categoryValue === 'all' || category === categoryValue;
                    
                    if (matchesSearch && matchesStatus && matchesCategory) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Show/hide "no events" message
                document.querySelectorAll('.event-section').forEach(section => {
                    const sectionId = section.getAttribute('id');
                    const visibleCards = section.querySelectorAll('.event-card[style=""]').length;
                    const noEventsMessage = section.querySelector('.no-events');
                    
                    if (visibleCards === 0 && !noEventsMessage) {
                        const message = document.createElement('div');
                        message.className = 'no-events';
                        message.innerHTML = '<p>No events match your filter criteria.</p>';
                        section.appendChild(message);
                    } else if (visibleCards > 0 && noEventsMessage) {
                        noEventsMessage.remove();
                    }
                });
            }
            
            // Add event listeners
            searchInput.addEventListener('input', filterEvents);
            statusFilter.addEventListener('change', filterEvents);
            categoryFilter.addEventListener('change', filterEvents);
        });
    </script>
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
        
        .event-section {
            margin-bottom: 3rem;
        }
        
        .event-section h2 {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .event-enrollment {
            margin-bottom: 1rem;
        }
        
        .event-card.past {
            opacity: 0.8;
        }
        
        .no-events {
            text-align: center;
            padding: 2rem;
            background-color: #f9fafb;
            border-radius: var(--border-radius);
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
        
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
    </style>
</body>
</html>
