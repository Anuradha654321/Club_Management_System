<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require club leader login
requireClubLeader();

$club_id = $_SESSION['club_id'];
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get club details
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

// Handle role creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $role_name = trim($_POST['role_name']);
    $role_type = $_POST['role_type'];
    
    // Validate role name
    if (empty($role_name)) {
        $error = "Role name cannot be empty";
    } else {
        // Check if role already exists
        $stmt = $conn->prepare("SELECT id FROM club_roles WHERE club_id = ? AND role_name = ?");
        $stmt->bind_param("is", $club_id, $role_name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "This role already exists in your club";
        } else {
            // Create new role
            $stmt = $conn->prepare("INSERT INTO club_roles (club_id, role_name, role_type) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $club_id, $role_name, $role_type);
            if ($stmt->execute()) {
                $success = "New role '$role_name' created successfully";
            } else {
                $error = "Failed to create role";
            }
        }
    }
}

// Handle role deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    $role_id = $_POST['role_id'];
    
    // Check if role is being used
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM club_members 
        WHERE role_id = ? AND active_status = 1
    ");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error = "Cannot delete role as it is currently assigned to members";
    } else {
        // Get role details before deletion
        $stmt = $conn->prepare("SELECT role_name FROM club_roles WHERE id = ? AND club_id = ?");
        $stmt->bind_param("ii", $role_id, $club_id);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc();
        
        // Delete role
        $stmt = $conn->prepare("DELETE FROM club_roles WHERE id = ? AND club_id = ?");
        $stmt->bind_param("ii", $role_id, $club_id);
        if ($stmt->execute()) {
            $success = "Role '{$role['role_name']}' deleted successfully";
        } else {
            $error = "Failed to delete role";
        }
    }
}

// Get all roles for this club
$stmt = $conn->prepare("
    SELECT cr.*, 
           COUNT(cm.id) as member_count
    FROM club_roles cr
    LEFT JOIN club_members cm ON cr.id = cm.role_id AND cm.active_status = 1
    WHERE cr.club_id = ?
    GROUP BY cr.id
    ORDER BY cr.role_type, cr.role_name
");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Club Roles - <?php echo htmlspecialchars($club['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Manage Club Roles - <?php echo htmlspecialchars($club['name']); ?></h1>
                    <a href="club_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Create New Role -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>Create New Role</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="row g-3">
                            <div class="col-md-6">
                                <label for="role_name" class="form-label">Role Name</label>
                                <input type="text" class="form-control" id="role_name" name="role_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="role_type" class="form-label">Role Type</label>
                                <select class="form-select" id="role_type" name="role_type" required>
                                    <option value="club_member">Club Member</option>
                                    <option value="executive_body">Executive Body</option>
                                </select>
                                <div class="form-text">
                                    Executive Body roles have additional privileges in managing the club.
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="create_role" class="btn btn-primary">Create Role</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing Roles -->
                <div class="card">
                    <div class="card-header">
                        <h2>Existing Roles</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Role Name</th>
                                        <th>Type</th>
                                        <th>Current Members</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $role['role_type'] === 'executive_body' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo $role['role_type'] === 'executive_body' ? 'Executive Body' : 'Club Member'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $role['member_count']; ?></td>
                                            <td>
                                                <?php if ($role['member_count'] == 0 && !in_array($role['role_name'], ['President', 'Vice President'])): ?>
                                                    <form method="POST" action="" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this role?');">
                                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                                        <button type="submit" name="delete_role" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <?php if (in_array($role['role_name'], ['President', 'Vice President'])): ?>
                                                        <span class="badge bg-info">Default Role</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">In Use</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 