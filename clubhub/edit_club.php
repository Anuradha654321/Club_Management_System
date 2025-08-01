<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and is either admin or club leader
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'club_leader')) {
    header('Location: login.php');
    exit();
}

// Get club ID from URL or session for club leader
$club_id = 0;
if ($_SESSION['user_type'] === 'club_leader' && isset($_SESSION['club_id'])) {
    $club_id = $_SESSION['club_id'];
} else if (isset($_GET['id'])) {
    $club_id = intval($_GET['id']);
}

// Verify club exists and user has permission
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

// Redirect if club doesn't exist or user doesn't have permission
if (!$club || ($_SESSION['user_type'] === 'club_leader' && $_SESSION['club_id'] != $club_id)) {
    header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admin_dashboard.php' : 'club_leader_dashboard.php'));
    exit();
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $category = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($category)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE clubs 
                SET name = ?, category = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssii", $name, $category, $description, $is_active, $club_id);
            
            if ($stmt->execute()) {
                $success = 'Club updated successfully.';
                // Refresh club data
                $stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
                $stmt->bind_param("i", $club_id);
                $stmt->execute();
                $club = $stmt->get_result()->fetch_assoc();
            } else {
                throw new Exception('Failed to update club.');
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get available categories
$categories = ['Department', 'College', 'Others'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Club - <?php echo htmlspecialchars($club['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2>Edit Club: <?php echo htmlspecialchars($club['name']); ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Club Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($club['name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>" 
                                            <?php echo ($club['category'] === $cat) ? 'selected' : ''; ?>>
                                            <?php echo $cat; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4"><?php echo htmlspecialchars($club['description']); ?></textarea>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                                       <?php echo $club['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Update Club</button>
                                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                    <a href="club_details.php?id=<?php echo $club_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                                <?php else: ?>
                                    <a href="club_leader_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                                <?php endif; ?>
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