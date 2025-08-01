<?php
session_start();
require_once 'db.php';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is a student
function isStudent() {
    return isLoggedIn() && $_SESSION['user_type'] === 'student';
}

// Function to check if user is a club leader
function isClubLeader() {
    return isLoggedIn() && $_SESSION['user_type'] === 'club_leader';
}

// Function to get current user ID
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

// Function to get current user type
function getCurrentUserType() {
    return isLoggedIn() ? $_SESSION['user_type'] : null;
}

// Function to get current user's club ID (for club leaders)
function getCurrentClubId() {
    return (isLoggedIn() && $_SESSION['user_type'] === 'club_leader') ? $_SESSION['club_id'] : null;
}

// Function to register a new user
function registerUser($name, $email, $password, $user_type, $student_id = null) {
    global $conn;
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Email already exists'];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, user_type, student_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $user_type, $student_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $conn->insert_id];
    } else {
        return ['success' => false, 'message' => 'Registration failed: ' . $conn->error];
    }
}

// Function to login a user
function loginUser($email, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, name, password, user_type, club_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_type'] = $user['user_type'];
            
            if ($user['user_type'] === 'club_leader' && $user['club_id']) {
                $_SESSION['club_id'] = $user['club_id'];
            }
            
            return ['success' => true, 'message' => 'Login successful', 'user_type' => $user['user_type']];
        } else {
            return ['success' => false, 'message' => 'Invalid password'];
        }
    } else {
        return ['success' => false, 'message' => 'User not found'];
    }
}

// Function to logout a user
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    return ['success' => true, 'message' => 'Logout successful'];
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Function to redirect if not a student
function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        header('Location: index.php');
        exit;
    }
}

// Function to redirect if not a club leader
function requireClubLeader() {
    requireLogin();
    if (!isClubLeader()) {
        header('Location: index.php');
        exit;
    }
}
?>
