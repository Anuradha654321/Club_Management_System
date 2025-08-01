<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Get list of clubs
$stmt = $conn->prepare("SELECT id, name FROM clubs WHERE is_active = 1");
$stmt->execute();
$clubs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Define branches, years, and sections
$branches = ['CSE', 'IT', 'ECE', 'EEE', 'MECH', 'CIVIL', 'CSM'];
$years = ['1', '2', '3', '4'];
$sections = ['1', '2', '3', '4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);
    $user_type = trim($_POST['user_type']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';
    $branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
    $year = isset($_POST['year']) ? trim($_POST['year']) : '';
    $section = isset($_POST['section']) ? trim($_POST['section']) : '';

    try {
        // Validate inputs
        if (empty($name) || empty($email) || empty($user_type) || empty($password)) {
            throw new Exception('All fields are required.');
        }

        // Student ID is required only for students and club leaders
        if (($user_type === 'student' || $user_type === 'club_leader') && empty($student_id)) {
            throw new Exception('Student ID is required for students and club leaders.');
        }

        // Validate student/club leader specific fields
        if ($user_type !== 'admin' && $user_type !== 'club_admin') {
            if (empty($branch) || empty($year) || empty($section)) {
                throw new Exception('Branch, Year and Section are required.');
            }
            
            if (!in_array($branch, $branches)) {
                throw new Exception('Invalid branch selected.');
            }
            
            if (!in_array($year, $years)) {
                throw new Exception('Invalid year selected.');
            }
            
            if (!in_array($section, $sections)) {
                throw new Exception('Invalid section selected.');
            }
        }

        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        // Additional validation for club admin
        if ($user_type === 'club_admin') {
            if (empty($club_id)) {
                throw new Exception('Please select a club for club admin registration.');
            }

            // Check if club already has an admin
            $stmt = $conn->prepare("SELECT club_admin_id FROM clubs WHERE id = ?");
            $stmt->bind_param("i", $club_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && $result['club_admin_id']) {
                throw new Exception('This club already has an admin.');
            }
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

        // Create user account
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($user_type === 'club_leader') {
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, student_id, password, user_type, club_id, branch, year, section) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssisss", $name, $email, $student_id, $hashed_password, $user_type, $club_id, $branch, $year, $section);
        } else if ($user_type === 'student') {
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, student_id, password, user_type, branch, year, section) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssssss", $name, $email, $student_id, $hashed_password, $user_type, $branch, $year, $section);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, student_id, password, user_type) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $name, $email, $student_id, $hashed_password, $user_type);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to create user account.');
        }
        $user_id = $conn->insert_id;

        // If club admin, update the club with admin ID
        if ($user_type === 'club_admin') {
            $stmt = $conn->prepare("UPDATE clubs SET club_admin_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $user_id, $club_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to assign club admin.');
            }
        }

        // Commit transaction
        $conn->commit();
        $success = "Registration successful! Please login to continue.";

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Check if admin exists
$admin_exists = false;
$stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE user_type = 'admin'");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$admin_exists = ($result['admin_count'] > 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - College Club Management System</title>
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
                        <h2>Register</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-primary">Login Now</a>
                                </div>
                            </div>
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
                                <input type="text" class="form-control" id="student_id" name="student_id">
                                <div class="form-text">Required for students and club leaders only.</div>
                            </div>

                            <div id="student_details" style="display: none;">
                                <div class="mb-3">
                                    <label for="branch" class="form-label">Branch</label>
                                    <select class="form-select" id="branch" name="branch" required>
                                        <option value="">Select Branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo $branch; ?>"><?php echo $branch; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="year" class="form-label">Year</label>
                                    <select class="form-select" id="year" name="year" required>
                                        <option value="">Select Year</option>
                                        <?php foreach ($years as $year): ?>
                                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="section" class="form-label">Section</label>
                                    <select class="form-select" id="section" name="section" required>
                                        <option value="">Select Section</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo $section; ?>"><?php echo $section; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="user_type" class="form-label">Register as</label>
                                <select class="form-select" id="user_type" name="user_type" required onchange="toggleFields()">
                                    <option value="">Select Role</option>
                                    <option value="student">Student</option>
                                    <option value="club_leader">Club Leader</option>
                                    <?php if (!$admin_exists): ?>
                                        <option value="admin">Admin</option>
                                    <?php endif; ?>
                                    <option value="club_admin">Club Admin</option>
                                </select>
                            </div>

                            <div id="club_selection" style="display: none;">
                                <div class="mb-3">
                                    <label for="club_id" class="form-label">Select Club</label>
                                    <select class="form-select" id="club_id" name="club_id" required>
                                        <option value="">Select Club</option>
                                        <?php foreach ($clubs as $club): ?>
                                            <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="club_leader_fields" style="display: none;">
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
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Register</button>
                            <a href="login.php" class="btn btn-link">Already have an account? Login</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Function to toggle form fields based on user type
    function toggleFields() {
        const userType = document.getElementById('user_type').value;
        const studentDetails = document.getElementById('student_details');
        const clubLeaderFields = document.getElementById('club_leader_fields');
        const clubSelection = document.getElementById('club_selection');
        const branchSelect = document.getElementById('branch');
        const yearSelect = document.getElementById('year');
        const sectionSelect = document.getElementById('section');
        const clubIdSelect = document.getElementById('club_id');
        const roleSelect = document.getElementById('role');
        const studentIdInput = document.getElementById('student_id');

        // Hide all fields first
        studentDetails.style.display = 'none';
        clubLeaderFields.style.display = 'none';
        clubSelection.style.display = 'none';
        
        // Reset required fields
        branchSelect.required = false;
        yearSelect.required = false;
        sectionSelect.required = false;
        clubIdSelect.required = false;
        roleSelect.required = false;
        studentIdInput.required = false;

        if (userType === 'student') {
            studentDetails.style.display = 'block';
            branchSelect.required = true;
            yearSelect.required = true;
            sectionSelect.required = true;
            studentIdInput.required = true;
        } else if (userType === 'club_leader') {
            studentDetails.style.display = 'block';
            clubLeaderFields.style.display = 'block';
            clubSelection.style.display = 'block';
            branchSelect.required = true;
            yearSelect.required = true;
            sectionSelect.required = true;
            clubIdSelect.required = true;
            roleSelect.required = true;
            studentIdInput.required = true;
        } else if (userType === 'club_admin') {
            clubSelection.style.display = 'block';
            clubIdSelect.required = true;
            studentIdInput.required = false;
        } else if (userType === 'admin') {
            studentIdInput.required = false;
        }
    }

    // Add validation for club and role selection
    document.getElementById('club_id').addEventListener('change', async function() {
        const clubId = this.value;
        const roleSelect = document.getElementById('role');
        const userType = document.getElementById('user_type').value;
        
        if (clubId && userType === 'club_leader') {
            try {
                const response = await fetch(`get_available_roles.php?club_id=${clubId}`);
                const data = await response.json();
                
                // Enable/disable role options based on availability
                Array.from(roleSelect.options).forEach(option => {
                    if (option.value) {
                        option.disabled = data.taken_roles.includes(option.value);
                    }
                });
            } catch (error) {
                console.error('Error checking role availability:', error);
            }
        }
    });
    </script>
</body>
</html>
