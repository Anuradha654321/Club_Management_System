<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Require student login
requireStudent();

$user_id = getCurrentUserId();
$error = '';
$success = '';

// Check if participation ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: student_dashboard.php');
    exit;
}

$participation_id = sanitize($_GET['id']);

// Get participation details
$stmt = $conn->prepare("
    SELECT ep.*, e.title as event_title, e.event_date, c.name as club_name
    FROM event_participation ep
    JOIN events e ON ep.event_id = e.id
    JOIN clubs c ON e.club_id = c.id
    WHERE ep.id = ? AND ep.user_id = ?
");
$stmt->bind_param("ii", $participation_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: student_dashboard.php');
    exit;
}

$participation = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === 0) {
        // Validate file
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        $errors = validateFileUpload($_FILES['proof_file'], $allowedTypes);
        
        if (empty($errors)) {
            // Create directory if it doesn't exist
            if (!file_exists('uploads/proofs')) {
                mkdir('uploads/proofs', 0777, true);
            }
            
            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
            $newFilename = 'proof_' . $user_id . '_' . $participation['event_id'] . '_' . time() . '.' . $fileExtension;
            $targetPath = 'uploads/proofs/' . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
                // Update participation record
                $stmt = $conn->prepare("UPDATE event_participation SET proof_file = ?, status = 'pending' WHERE id = ?");
                $stmt->bind_param("si", $targetPath, $participation_id);
                
                if ($stmt->execute()) {
                    $success = 'Proof uploaded successfully. Your participation is pending approval.';
                    
                    // Refresh participation details
                    $stmt = $conn->prepare("
                        SELECT ep.*, e.title as event_title, e.event_date, c.name as club_name
                        FROM event_participation ep
                        JOIN events e ON ep.event_id = e.id
                        JOIN clubs c ON e.club_id = c.id
                        WHERE ep.id = ? AND ep.user_id = ?
                    ");
                    $stmt->bind_param("ii", $participation_id, $user_id);
                    $stmt->execute();
                    $participation = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = 'Failed to update participation record. Please try again.';
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
    <title>Upload Participation Proof - College Club Management System</title>
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
        <section class="upload-proof-section">
            <div class="container">
                <div class="card" style="max-width: 700px; margin: 3rem auto;">
                    <div class="card-header">
                        <h2 class="card-title">Upload Participation Proof</h2>
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
                        
                        <div class="event-details mb-4">
                            <h3><?php echo $participation['event_title']; ?></h3>
                            <p><strong>Club:</strong> <?php echo $participation['club_name']; ?></p>
                            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($participation['event_date'])); ?></p>
                            <p><strong>Current Status:</strong> 
                                <?php if ($participation['status'] === 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($participation['status'] === 'rejected'): ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </p>
                            
                            <?php if ($participation['proof_file']): ?>
                                <div class="current-proof mb-3">
                                    <p><strong>Current Proof:</strong></p>
                                    <?php 
                                    $fileExtension = strtolower(pathinfo($participation['proof_file'], PATHINFO_EXTENSION));
                                    if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])):
                                    ?>
                                        <div class="proof-preview">
                                            <img src="<?php echo $participation['proof_file']; ?>" alt="Proof" style="max-width: 300px; max-height: 200px;">
                                        </div>
                                    <?php else: ?>
                                        <a href="<?php echo $participation['proof_file']; ?>" target="_blank" class="btn btn-sm btn-outline">View Current Proof</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($participation['status'] === 'rejected' || !$participation['proof_file']): ?>
                                <form action="upload_proof.php?id=<?php echo $participation_id; ?>" method="POST" enctype="multipart/form-data" data-validate="true">
                                    <div class="form-group">
                                        <label for="proof_file" class="form-label">Upload Proof</label>
                                        <input type="file" id="proof_file" name="proof_file" class="form-control" required accept=".jpg, .jpeg, .png, .pdf">
                                        <div class="form-text">
                                            Accepted file formats: JPG, JPEG, PNG, PDF. Maximum file size: 5MB.
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Upload Proof</button>
                                        <a href="student_dashboard.php" class="btn btn-outline">Cancel</a>
                                    </div>
                                </form>
                            <?php elseif ($participation['status'] === 'pending'): ?>
                                <div class="alert alert-info">
                                    Your proof has been submitted and is pending approval. You can upload a new proof if needed.
                                </div>
                                <form action="upload_proof.php?id=<?php echo $participation_id; ?>" method="POST" enctype="multipart/form-data" data-validate="true">
                                    <div class="form-group">
                                        <label for="proof_file" class="form-label">Upload New Proof</label>
                                        <input type="file" id="proof_file" name="proof_file" class="form-control" required accept=".jpg, .jpeg, .png, .pdf">
                                        <div class="form-text">
                                            Accepted file formats: JPG, JPEG, PNG, PDF. Maximum file size: 5MB.
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Upload New Proof</button>
                                        <a href="student_dashboard.php" class="btn btn-outline">Cancel</a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    Your participation has been approved. No further action is needed.
                                </div>
                                <a href="student_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
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
</body>
</html>
