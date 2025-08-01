<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require admin login
requireAdmin();

$error = '';
$success = '';

// Get list of clubs
$stmt = $conn->prepare("SELECT id, name FROM clubs WHERE is_active = 1");
$stmt->execute();
$clubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);
    $club_id = intval($_POST['club_id']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Validate inputs
        if (empty($name) || empty($email) || empty($student_id) || empty($club_id) || empty($role) || empty($password)) {
            throw new Exception('All fields are required.');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        if (!in_array($role, ['President', 'Vice President'])) {
            throw new Exception('Invalid role selected.');
        }

        // Start transaction
        $conn->begin_transaction();

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Email already registered.');
        }

        // Check if student ID already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Student ID already registered.');
        }

        // Check if role is already taken in the club
        $stmt = $conn->prepare("
            SELECT cm.* 
            FROM club_members cm
            JOIN club_roles cr ON cm.role_id = cr.id
            WHERE cr.club_id = ? AND cr.role_name = ? AND cm.active_status = 1
        ");
        $stmt->bind_param("is", $club_id, $role);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("This club already has a $role.");
        }

        // Get role ID
        $stmt = $conn->prepare("
            SELECT id FROM club_roles 
            WHERE club_id = ? AND role_name = ? AND role_type = 'executive_body'
        ");
        $stmt->bind_param("is", $club_id, $role);
        $stmt->execute();
        $role_result = $stmt->get_result()->fetch_assoc();
        
        if (!$role_result) {
            throw new Exception('Invalid role selected.');
        }
        $role_id = $role_result['id'];

        // Create user account
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, student_id, password, user_type, club_id) 
            VALUES (?, ?, ?, ?, 'club_leader', ?)
        ");
        $stmt->bind_param("ssssi", $name, $email, $student_id, $hashed_password, $club_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create user account.');
        }
        $user_id = $conn->insert_id;

        // Add club membership
        $stmt = $conn->prepare("
            INSERT INTO club_members (user_id, club_id, role_id, join_date, active_status) 
            VALUES (?, ?, ?, CURDATE(), 1)
        ");
        $stmt->bind_param("iii", $user_id, $club_id, $role_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create club membership.');
        }

        // Commit transaction
        $conn->commit();
        $success = "Club leader account created successfully!";

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Club Leader - College Club Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2>Register Club Leader</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student ID</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" required>
                            </div>

                            <div class="mb-3">
                                <label for="club_id" class="form-label">Select Club</label>
                                <select class="form-select" id="club_id" name="club_id" required>
                                    <option value="">Select a club</option>
                                    <?php foreach ($clubs as $club): ?>
                                        <option value="<?php echo $club['id']; ?>">
                                            <?php echo htmlspecialchars($club['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">Leadership Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select a role</option>
                                    <option value="President">President</option>
                                    <option value="Vice President">Vice President</option>
                                </select>
                                <div class="form-text">
                                    Note: Each club can have only one President and one Vice President.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Register Club Leader</button>
                            <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 