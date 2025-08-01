<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$user_id = getCurrentUserId();
$club_id = getCurrentClubId();

// Get club information
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $meeting_date = sanitize($_POST['meeting_date']);
    
    // Check if file was uploaded
    if (isset($_FILES['minutes_file']) && $_FILES['minutes_file']['error'] === 0) {
        // Validate file
        $allowedTypes = ['pdf', 'doc', 'docx', 'txt'];
        $errors = validateFileUpload($_FILES['minutes_file'], $allowedTypes);
        
        if (empty($errors)) {
            // Create directory if it doesn't exist
            if (!file_exists('uploads/minutes')) {
                mkdir('uploads/minutes', 0777, true);
            }
            
            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['minutes_file']['name'], PATHINFO_EXTENSION));
            $newFilename = 'minutes_' . $club_id . '_' . time() . '.' . $fileExtension;
            $targetPath = 'uploads/minutes/' . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['minutes_file']['tmp_name'], $targetPath)) {
                // Insert minutes record
                $stmt = $conn->prepare("
                    INSERT INTO meeting_minutes (club_id, title, meeting_date, file_path, uploaded_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isssi", $club_id, $title, $meeting_date, $targetPath, $user_id);
                
                if ($stmt->execute()) {
                    $success = 'Meeting minutes uploaded successfully.';
                } else {
                    $error = 'Failed to save meeting minutes record. Please try again.';
                }
            } else {
                $error = 'Failed to upload file. Please try again.';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Meeting Minutes - College Club Management System</title>
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
        <section class="upload-minutes-section">
            <div class="container">
                <div class="card" style="max-width: 700px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Upload Meeting Minutes for <?php echo $club['name']; ?></h2>
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
                                    <a href="club_dashboard.php?tab=minutes" class="btn btn-sm btn-primary">Back to Dashboard</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form action="upload_minutes.php" method="POST" enctype="multipart/form-data" data-validate="true">
                            <div class="form-group">
                                <label for="title" class="form-label">Meeting Title</label>
                                <input type="text" id="title" name="title" class="form-control" required>
                                <div class="form-text">
                                    E.g., "General Body Meeting", "Executive Committee Meeting", etc.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="meeting_date" class="form-label">Meeting Date</label>
                                <input type="date" id="meeting_date" name="meeting_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="minutes_file" class="form-label">Upload Minutes Document</label>
                                <input type="file" id="minutes_file" name="minutes_file" class="form-control" required accept=".pdf, .doc, .docx, .txt">
                                <div class="form-text">
                                    Accepted file formats: PDF, DOC, DOCX, TXT. Maximum file size: 5MB.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Upload Minutes</button>
                                <a href="club_dashboard.php?tab=minutes" class="btn btn-outline">Cancel</a>
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
