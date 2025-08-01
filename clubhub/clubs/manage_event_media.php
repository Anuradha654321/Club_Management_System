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
    header('Location: club_dashboard.php');
    exit;
}

$event_id = sanitize($_GET['id']);
$is_new = isset($_GET['new']) && $_GET['new'] == 1;

// Check if event belongs to the club
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND club_id = ?");
$stmt->bind_param("ii", $event_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: club_dashboard.php');
    exit;
}

$event = $result->fetch_assoc();

// Get existing media
$stmt = $conn->prepare("
    SELECT em.*, u.name as uploaded_by_name
    FROM event_media em
    JOIN users u ON em.uploaded_by = u.id
    WHERE em.event_id = ?
    ORDER BY em.created_at DESC
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$media_items = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $media_items[] = $row;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $media_type = sanitize($_POST['media_type']);
    
    // Check if file was uploaded
    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === 0) {
        // Validate file
        $allowedTypes = [];
        
        if ($media_type === 'photo') {
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        } else if ($media_type === 'report' || $media_type === 'other') {
            $allowedTypes = ['pdf', 'doc', 'docx', 'txt'];
        }
        
        $errors = validateFileUpload($_FILES['media_file'], $allowedTypes);
        
        if (empty($errors)) {
            // Create directory if it doesn't exist
            if (!file_exists('uploads/events')) {
                mkdir('uploads/events', 0777, true);
            }
            
            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
            $newFilename = 'event_' . $event_id . '_' . $media_type . '_' . time() . '.' . $fileExtension;
            $targetPath = 'uploads/events/' . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['media_file']['tmp_name'], $targetPath)) {
                // Insert media record
                $stmt = $conn->prepare("
                    INSERT INTO event_media (event_id, media_type, file_path, uploaded_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("issi", $event_id, $media_type, $targetPath, $user_id);
                
                if ($stmt->execute()) {
                    $success = 'Media uploaded successfully.';
                    
                    // Refresh media list
                    $stmt = $conn->prepare("
                        SELECT em.*, u.name as uploaded_by_name
                        FROM event_media em
                        JOIN users u ON em.uploaded_by = u.id
                        WHERE em.event_id = ?
                        ORDER BY em.created_at DESC
                    ");
                    $stmt->bind_param("i", $event_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $media_items = [];
                    
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $media_items[] = $row;
                        }
                    }
                } else {
                    $error = 'Failed to save media record. Please try again.';
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

// Handle delete request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $media_id = sanitize($_GET['delete']);
    
    // Check if media belongs to the event
    $stmt = $conn->prepare("
        SELECT * FROM event_media 
        WHERE id = ? AND event_id = ?
    ");
    $stmt->bind_param("ii", $media_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $media = $result->fetch_assoc();
        $file_path = $media['file_path'];
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM event_media WHERE id = ?");
        $stmt->bind_param("i", $media_id);
        
        if ($stmt->execute()) {
            // Delete file if exists
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $success = 'Media deleted successfully.';
            
            // Refresh media list
            $stmt = $conn->prepare("
                SELECT em.*, u.name as uploaded_by_name
                FROM event_media em
                JOIN users u ON em.uploaded_by = u.id
                WHERE em.event_id = ?
                ORDER BY em.created_at DESC
            ");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $media_items = [];
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $media_items[] = $row;
                }
            }
        } else {
            $error = 'Failed to delete media. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Event Media - College Club Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .media-item {
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius);
            overflow: hidden;
            position: relative;
        }
        
        .media-preview {
            height: 150px;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .media-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .media-preview .file-icon {
            font-size: 3rem;
            color: var(--gray-color);
        }
        
        .media-info {
            padding: 0.75rem;
        }
        
        .media-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .media-type.photo {
            background-color: #e0e7ff;
            color: #4f46e5;
        }
        
        .media-type.report {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .media-type.other {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .media-date {
            font-size: 0.75rem;
            color: var(--gray-color);
            margin-bottom: 0.5rem;
        }
        
        .media-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .delete-media {
            color: var(--danger-color);
            cursor: pointer;
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
        <section class="manage-media-section">
            <div class="container">
                <div class="card" style="max-width: 900px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Manage Media for: <?php echo $event['title']; ?></h2>
                        <a href="club_dashboard.php" class="btn btn-sm btn-outline">Back to Dashboard</a>
                    </div>
                    <div class="card-body">
                        <?php if ($is_new): ?>
                            <div class="alert alert-success">
                                Event created successfully! You can now add media to this event.
                            </div>
                        <?php endif; ?>
                        
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
                        
                        <div class="event-details mb-4">
                            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($event['event_date'])); ?> at <?php echo date('h:i A', strtotime($event['event_time'])); ?></p>
                            <p><strong>Location:</strong> <?php echo $event['location']; ?></p>
                            <p><strong>Status:</strong> 
                                <?php if ($event['status'] === 'upcoming'): ?>
                                    <span class="badge badge-primary">Upcoming</span>
                                <?php elseif ($event['status'] === 'ongoing'): ?>
                                    <span class="badge badge-success">Ongoing</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Past</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="upload-form">
                            <h3>Upload New Media</h3>
                            <form action="manage_event_media.php?id=<?php echo $event_id; ?>" method="POST" enctype="multipart/form-data" data-validate="true">
                                <div class="form-group">
                                    <label for="media_type" class="form-label">Media Type</label>
                                    <select id="media_type" name="media_type" class="form-select" required>
                                        <option value="photo">Photo</option>
                                        <option value="report">Report</option>
                                        <option value="other">Other Document</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="media_file" class="form-label">Upload File</label>
                                    <input type="file" id="media_file" name="media_file" class="form-control" required>
                                    <div class="form-text">
                                        For photos: JPG, JPEG, PNG, GIF. For documents: PDF, DOC, DOCX, TXT. Maximum file size: 5MB.
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Upload Media</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="existing-media mt-4">
                            <h3>Existing Media</h3>
                            <?php if (count($media_items) > 0): ?>
                                <div class="media-grid">
                                    <?php foreach ($media_items as $media): ?>
                                        <div class="media-item">
                                            <div class="media-preview">
                                                <?php 
                                                $fileExtension = strtolower(pathinfo($media['file_path'], PATHINFO_EXTENSION));
                                                if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])):
                                                ?>
                                                    <img src="<?php echo $media['file_path']; ?>" alt="Event Media">
                                                <?php else: ?>
                                                    <div class="file-icon">
                                                        <i class="far fa-file-alt"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="media-info">
                                                <div class="media-type <?php echo $media['media_type']; ?>">
                                                    <?php echo ucfirst($media['media_type']); ?>
                                                </div>
                                                <div class="media-date">
                                                    Uploaded: <?php echo date('M d, Y', strtotime($media['created_at'])); ?>
                                                </div>
                                                <div class="media-actions">
                                                    <a href="<?php echo $media['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                                    <a href="manage_event_media.php?id=<?php echo $event_id; ?>&delete=<?php echo $media['id']; ?>" class="delete-media" onclick="return confirm('Are you sure you want to delete this media?')">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <p>No media uploaded yet.</p>
                                </div>
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
