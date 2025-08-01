<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();
$error = '';
$success = '';

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: club_dashboard.php?tab=events');
    exit;
}

$event_id = sanitize($_GET['id']);

// Check if event belongs to this club
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND club_id = ?");
$stmt->bind_param("ii", $event_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: club_dashboard.php?tab=events');
    exit;
}

$event = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $event_date = sanitize($_POST['event_date']);
    
    // Process start time with AM/PM
    $event_time_start_hour = sanitize($_POST['event_time_start_hour']);
    $event_time_start_minute = sanitize($_POST['event_time_start_minute']);
    $event_time_start_ampm = sanitize($_POST['event_time_start_ampm']);
    
    // Process end time with AM/PM
    $event_time_end_hour = sanitize($_POST['event_time_end_hour']);
    $event_time_end_minute = sanitize($_POST['event_time_end_minute']);
    $event_time_end_ampm = sanitize($_POST['event_time_end_ampm']);
    
    // Format the time values with leading zeros
    $start_hour = sprintf('%02d', $event_time_start_hour);
    $start_minute = sprintf('%02d', $event_time_start_minute);
    $end_hour = sprintf('%02d', $event_time_end_hour);
    $end_minute = sprintf('%02d', $event_time_end_minute);
    
    // Combine into a single time string (e.g., "10:00 AM - 12:00 PM")
    $event_time = "$start_hour:$start_minute $event_time_start_ampm - $end_hour:$end_minute $event_time_end_ampm";
    
    $location = sanitize($_POST['location']);
    $status = sanitize($_POST['status']);
    $enrollment_open = isset($_POST['enrollment_open']) ? 1 : 0;
    $max_participants = sanitize($_POST['max_participants']);
    
    if (empty($max_participants)) {
        $max_participants = NULL;
    }
    
    // Update event
    $stmt = $conn->prepare("
        UPDATE events 
        SET title = ?, description = ?, event_date = ?, event_time = ?, 
            location = ?, status = ?, enrollment_open = ?, max_participants = ?
        WHERE id = ? AND club_id = ?
    ");
    $stmt->bind_param("ssssssiiis", $title, $description, $event_date, $event_time, 
                      $location, $status, $enrollment_open, $max_participants, $event_id, $club_id);
    
    if ($stmt->execute()) {
        $success = "Event updated successfully.";
        
        // Refresh event data
        $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND club_id = ?");
        $stmt->bind_param("ii", $event_id, $club_id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
    } else {
        $error = "Failed to update event. Please try again.";
    }
}

// Parse the event time for the form
$time_parts = [];
$start_time = '';
$end_time = '';
$start_hour = '12';
$start_minute = '00';
$start_ampm = 'AM';
$end_hour = '12';
$end_minute = '00';
$end_ampm = 'PM';

if (!empty($event['event_time'])) {
    // Try to parse the time format "10:00 AM - 12:00 PM"
    if (preg_match('/(\d{1,2}):(\d{2})\s+(AM|PM)\s+-\s+(\d{1,2}):(\d{2})\s+(AM|PM)/', $event['event_time'], $time_parts)) {
        $start_hour = $time_parts[1];
        $start_minute = $time_parts[2];
        $start_ampm = $time_parts[3];
        $end_hour = $time_parts[4];
        $end_minute = $time_parts[5];
        $end_ampm = $time_parts[6];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - College Club Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
        }
        
        .checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .checkbox input {
            margin-right: 0.5rem;
        }
        
        .hour-minute-group {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .hour-select, .minute-select {
            width: auto;
            min-width: 70px;
            border-radius: 4px;
        }
        
        .time-separator {
            margin: 0 5px;
            font-weight: bold;
        }
        
        .ampm-select {
            width: 80px;
            margin-left: 10px;
        }
        
        .time-input-group {
            display: flex;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
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
        <section class="edit-event-section">
            <div class="container">
                <div class="card" style="max-width: 900px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Edit Event</h2>
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
                        
                        <form action="edit_event.php?id=<?php echo $event_id; ?>" method="POST">
                            <div class="form-group">
                                <label for="title" class="form-label">Event Title</label>
                                <input type="text" id="title" name="title" class="form-control" value="<?php echo $event['title']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Event Description</label>
                                <textarea id="description" name="description" class="form-control" rows="4" required><?php echo $event['description']; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event_date" class="form-label">Event Date</label>
                                        <input type="date" id="event_date" name="event_date" class="form-control" value="<?php echo $event['event_date']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event_time_start" class="form-label">Start Time</label>
                                        <div class="time-input-group">
                                            <div class="hour-minute-group">
                                                <select id="event_time_start_hour" name="event_time_start_hour" class="form-select hour-select" required>
                                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($i == $start_hour) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $i); ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <span class="time-separator">:</span>
                                                <select id="event_time_start_minute" name="event_time_start_minute" class="form-select minute-select" required>
                                                    <?php for ($i = 0; $i < 60; $i += 5): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($i == $start_minute) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $i); ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <select id="event_time_start_ampm" name="event_time_start_ampm" class="form-select ampm-select">
                                                <option value="AM" <?php echo ($start_ampm === 'AM') ? 'selected' : ''; ?>>AM</option>
                                                <option value="PM" <?php echo ($start_ampm === 'PM') ? 'selected' : ''; ?>>PM</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event_time_end" class="form-label">End Time</label>
                                        <div class="time-input-group">
                                            <div class="hour-minute-group">
                                                <select id="event_time_end_hour" name="event_time_end_hour" class="form-select hour-select" required>
                                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($i == $end_hour) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $i); ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <span class="time-separator">:</span>
                                                <select id="event_time_end_minute" name="event_time_end_minute" class="form-select minute-select" required>
                                                    <?php for ($i = 0; $i < 60; $i += 5): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($i == $end_minute) ? 'selected' : ''; ?>><?php echo sprintf('%02d', $i); ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <select id="event_time_end_ampm" name="event_time_end_ampm" class="form-select ampm-select">
                                                <option value="AM" <?php echo ($end_ampm === 'AM') ? 'selected' : ''; ?>>AM</option>
                                                <option value="PM" <?php echo ($end_ampm === 'PM') ? 'selected' : ''; ?>>PM</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="location" class="form-label">Event Location</label>
                                <input type="text" id="location" name="location" class="form-control" value="<?php echo $event['location']; ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Event Status</label>
                                        <select id="status" name="status" class="form-select" required>
                                            <option value="upcoming" <?php echo ($event['status'] === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                            <option value="ongoing" <?php echo ($event['status'] === 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                            <option value="past" <?php echo ($event['status'] === 'past') ? 'selected' : ''; ?>>Past</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="max_participants" class="form-label">Maximum Participants</label>
                                        <input type="number" id="max_participants" name="max_participants" class="form-control" value="<?php echo $event['max_participants']; ?>" min="0">
                                        <div class="form-text">
                                            Leave empty for unlimited participants.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" id="enrollment_open" name="enrollment_open" <?php echo ($event['enrollment_open']) ? 'checked' : ''; ?>>
                                    <label for="enrollment_open">Open for Enrollment</label>
                                </div>
                                <div class="form-text">
                                    If checked, students will be able to enroll in this event.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Update Event</button>
                                <a href="club_dashboard.php?tab=events" class="btn btn-outline">Cancel</a>
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
