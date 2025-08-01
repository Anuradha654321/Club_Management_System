<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();
$error = '';
$success = '';

// Get club members for selection
$stmt = $conn->prepare("
    SELECT cm.user_id, u.name
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.club_id = ? AND cm.active_status = 1
    ORDER BY u.name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$result = $stmt->get_result();
$club_members = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $club_members[] = $row;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $executive_type = sanitize($_POST['executive_type']);
    $role = sanitize($_POST['role']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : NULL;
    
    if ($executive_type === 'existing') {
        $member_user_id = sanitize($_POST['member_id']);
        $name = ''; // Name will be fetched from user table
        
        // Insert executive with user_id
        $stmt = $conn->prepare("
            INSERT INTO club_executives (club_id, user_id, name, role, start_date, end_date)
            VALUES (?, ?, '', ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $club_id, $member_user_id, $role, $start_date, $end_date);
    } else {
        $name = sanitize($_POST['external_name']);
        $member_user_id = NULL;
        
        // Insert executive without user_id (external person)
        $stmt = $conn->prepare("
            INSERT INTO club_executives (club_id, user_id, name, role, start_date, end_date)
            VALUES (?, NULL, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $club_id, $name, $role, $start_date, $end_date);
    }
    
    if ($stmt->execute()) {
        $success = "Executive added successfully.";
    } else {
        $error = "Failed to add executive. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Executive - College Club Management System</title>
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
        
        .radio-group {
            margin-bottom: 1rem;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .radio-option input {
            margin-right: 0.5rem;
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
        <section class="add-executive-section">
            <div class="container">
                <div class="card" style="max-width: 700px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Add Executive</h2>
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
                                    <a href="club_dashboard.php?tab=executives" class="btn btn-sm btn-primary">Back to Executive List</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form action="add_executive.php" method="POST" id="executiveForm">
                            <div class="form-group">
                                <label class="form-label">Executive Type</label>
                                <div class="radio-group">
                                    <div class="radio-option">
                                        <input type="radio" id="existing_member" name="executive_type" value="existing" checked onchange="toggleExecutiveType()">
                                        <label for="existing_member">Existing Club Member</label>
                                    </div>
                                    <div class="radio-option">
                                        <input type="radio" id="external_person" name="executive_type" value="external" onchange="toggleExecutiveType()">
                                        <label for="external_person">External Person</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="existing_member_section">
                                <div class="form-group">
                                    <label for="member_id" class="form-label">Select Member</label>
                                    <select id="member_id" name="member_id" class="form-select" required>
                                        <option value="">-- Select a Member --</option>
                                        <?php foreach ($club_members as $member): ?>
                                            <option value="<?php echo $member['user_id']; ?>"><?php echo $member['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="external_person_section" style="display: none;">
                                <div class="form-group">
                                    <label for="external_name" class="form-label">Name</label>
                                    <input type="text" id="external_name" name="external_name" class="form-control">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">Role</label>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="">-- Select a Role --</option>
                                    <option value="President">President</option>
                                    <option value="Vice President">Vice President</option>
                                    <option value="Secretary">Secretary</option>
                                    <option value="Joint Secretary">Joint Secretary</option>
                                    <option value="Treasurer">Treasurer</option>
                                    <option value="Technical Head">Technical Head</option>
                                    <option value="Cultural Head">Cultural Head</option>
                                    <option value="Sports Head">Sports Head</option>
                                    <option value="Event Coordinator">Event Coordinator</option>
                                    <option value="Media Coordinator">Media Coordinator</option>
                                    <option value="Public Relations Officer">Public Relations Officer</option>
                                    <option value="Faculty Advisor">Faculty Advisor</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" id="start_date" name="start_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" id="end_date" name="end_date" class="form-control">
                                        <div class="form-text">
                                            Leave empty for current executives.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Add Executive</button>
                                <a href="club_dashboard.php?tab=executives" class="btn btn-outline">Cancel</a>
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

    <script>
        function toggleExecutiveType() {
            const existingMemberRadio = document.getElementById('existing_member');
            const existingMemberSection = document.getElementById('existing_member_section');
            const externalPersonSection = document.getElementById('external_person_section');
            const memberIdInput = document.getElementById('member_id');
            const externalNameInput = document.getElementById('external_name');
            
            if (existingMemberRadio.checked) {
                existingMemberSection.style.display = 'block';
                externalPersonSection.style.display = 'none';
                memberIdInput.setAttribute('required', 'required');
                externalNameInput.removeAttribute('required');
            } else {
                existingMemberSection.style.display = 'none';
                externalPersonSection.style.display = 'block';
                memberIdInput.removeAttribute('required');
                externalNameInput.setAttribute('required', 'required');
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleExecutiveType();
        });
    </script>
</body>
</html>
