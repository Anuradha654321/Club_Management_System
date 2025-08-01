<?php
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require student login
if (!isStudent()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get all clubs
$query = "SELECT * FROM clubs WHERE is_active = 1 ORDER BY name";
$result = $conn->query($query);
$clubs = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
}

// Get clubs already joined by the student
$stmt = $conn->prepare("
    SELECT cm.club_id 
    FROM club_members cm 
    WHERE cm.user_id = ? AND cm.active_status = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$joined_club_ids = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $joined_club_ids[] = $row['club_id'];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
    $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
    
    // Validate inputs
    if (empty($club_id) || empty($role_id)) {
        $error = 'Please select a club and role.';
    } else if (in_array($club_id, $joined_club_ids)) {
        $error = 'You have already joined this club.';
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Verify role exists and belongs to the selected club
            $stmt = $conn->prepare("
                SELECT cr.*, c.name as club_name
                FROM club_roles cr
                JOIN clubs c ON cr.club_id = c.id
                WHERE cr.id = ? AND cr.club_id = ?
            ");
            $stmt->bind_param("ii", $role_id, $club_id);
            $stmt->execute();
            $role = $stmt->get_result()->fetch_assoc();
            
            if (!$role) {
                throw new Exception('Invalid role selected.');
            }
            
            // Check if role is restricted (President or Vice President)
            if (in_array($role['role_name'], ['President', 'Vice President'])) {
                throw new Exception('You cannot apply for President or Vice President positions.');
            }
            
            // For executive roles (Secretary, Joint Secretary, Treasurer), create application
            if ($role['role_type'] === 'executive_body') {
                // Check if already applied
                $stmt = $conn->prepare("
                    SELECT id FROM membership_applications 
                    WHERE user_id = ? AND club_id = ? AND role_id = ? 
                    AND application_status = 'pending'
                ");
                $stmt->bind_param("iii", $user_id, $club_id, $role_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('You have already applied for this position.');
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO membership_applications 
                    (user_id, club_id, role_id, application_status, applied_date) 
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("iii", $user_id, $club_id, $role_id);
                $stmt->execute();
                
                $success = "Your application for {$role['role_name']} position in {$role['club_name']} has been submitted and is pending approval.";
            } else {
                // For regular member roles, create application (all member roles need approval)
                $stmt = $conn->prepare("
                    INSERT INTO membership_applications 
                    (user_id, club_id, role_id, application_status, applied_date) 
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("iii", $user_id, $club_id, $role_id);
                $stmt->execute();
                
                $success = "Your application to join {$role['club_name']} as {$role['role_name']} has been submitted and is pending approval.";
            }
            
            // Commit transaction
            $conn->commit();
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = 'Failed to join club: ' . $e->getMessage();
        }
    }
}

// Get available roles for each club
$available_roles = [];
$stmt = $conn->prepare("
    SELECT cr.*, c.name as club_name
    FROM club_roles cr
    JOIN clubs c ON cr.club_id = c.id
    WHERE cr.club_id IN (SELECT id FROM clubs WHERE is_active = 1)
    AND cr.role_name NOT IN ('President', 'Vice President')
    ORDER BY cr.club_id, cr.role_type, cr.role_name
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if (!isset($available_roles[$row['club_id']])) {
        $available_roles[$row['club_id']] = [
            'club_name' => $row['club_name'],
            'roles' => []
        ];
    }
    $available_roles[$row['club_id']]['roles'][] = $row;
}

// Get pending applications
$stmt = $conn->prepare("
    SELECT ma.*, c.name as club_name, cr.role_name
    FROM membership_applications ma
    JOIN clubs c ON ma.club_id = c.id
    JOIN club_roles cr ON ma.role_id = cr.id
    WHERE ma.user_id = ? AND ma.application_status = 'pending'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Club - College Club Management System</title>
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
                        <h2 class="card-title">Join a Club</h2>
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
                                <div class="mt-2">
                                    <a href="student_dashboard.php" class="btn btn-sm btn-primary">Back to Dashboard</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="club_id" class="form-label">Select Club</label>
                                <select id="club_id" name="club_id" class="form-select" required>
                                    <option value="">Select a club to join</option>
                                    <?php foreach ($clubs as $club): ?>
                                        <?php if (!in_array($club['id'], $joined_club_ids)): ?>
                                            <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['name']); ?> (<?php echo htmlspecialchars($club['category']); ?>)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role_id" class="form-label">Select Role</label>
                                <select id="role_id" name="role_id" class="form-select" required>
                                    <option value="">First select a club</option>
                                </select>
                                <div class="form-text">
                                    Note: All role applications require approval from club administrators.
                                    President and Vice President positions are not available for direct application.
                                    These positions are assigned by the admin.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Apply for Role</button>
                                <a href="student_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                        
                        <?php if (!empty($pending_applications)): ?>
                            <div class="mt-4">
                                <h3>Your Pending Applications</h3>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Club</th>
                                                <th>Role</th>
                                                <th>Applied Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_applications as $app): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($app['club_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($app['role_name']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (count($joined_club_ids) > 0): ?>
                            <div class="mt-4">
                                <h3>Your Club Memberships</h3>
                                <ul class="list-group">
                                    <?php 
                                    foreach ($joined_club_ids as $joined_id) {
                                        foreach ($clubs as $club) {
                                            if ($club['id'] == $joined_id) {
                                                echo '<li class="list-group-item">' . htmlspecialchars($club['name']) . ' (' . htmlspecialchars($club['category']) . ')</li>';
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('club_id').addEventListener('change', function() {
        const clubId = this.value;
        const roleSelect = document.getElementById('role_id');
        roleSelect.innerHTML = '<option value="">Select your role</option>';
        
        if (clubId) {
            const roles = <?php echo json_encode($available_roles); ?>;
            if (roles[clubId]) {
                roles[clubId].roles.forEach(function(role) {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.role_name + 
                        (role.role_type === 'executive_body' ? ' (Executive Position)' : '');
                    roleSelect.appendChild(option);
                });
            }
        }
    });
    </script>
</body>
</html>
