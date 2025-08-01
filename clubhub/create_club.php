<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $category = sanitize($_POST['category']);
    $established_date = sanitize($_POST['established_date']);
    
    // Validate category is one of the allowed options
    $allowed_categories = ['Department', 'College', 'Others'];
    if (!in_array($category, $allowed_categories)) {
        $error = 'Invalid category selected. Please select a valid category.';
    } else {
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Insert club
        $stmt = $conn->prepare("
            INSERT INTO clubs (name, description, category, established_date) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $name, $description, $category, $established_date);
        
        if ($stmt->execute()) {
            $club_id = $conn->insert_id;
            
            // Add default roles
            $default_roles = [
                ['President', 'executive_body'],
                ['Vice President', 'executive_body'],
                ['Secretary', 'executive_body'],
                ['Joint Secretary', 'executive_body'],
                ['Treasurer', 'executive_body'],
                ['Lead', 'club_member'],
                ['Co-Lead', 'club_member'],
                ['Member', 'club_member']
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO club_roles (club_id, role_name, role_type) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($default_roles as $role) {
                $stmt->bind_param("iss", $club_id, $role[0], $role[1]);
                $stmt->execute();
            }
            
            $conn->commit();
            $_SESSION['success'] = "Club created successfully!";
            header('Location: admin_dashboard.php');
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error creating club: " . $e->getMessage();
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2>Create New Club</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Club Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Department">Department</option>
                                    <option value="College">College</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="established_date" class="form-label">Established Date</label>
                                <input type="date" class="form-control" id="established_date" name="established_date" required>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Create Club</button>
                                <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 