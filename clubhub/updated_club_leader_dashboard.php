<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a club leader
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'club_leader') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$club_id = $_SESSION['club_id'];

// Handle role creation
if (isset($_POST['create_role'])) {
    $role_name = $_POST['role_name'];
    $role_type = $_POST['role_type'];
    
    $stmt = $conn->prepare("INSERT INTO club_roles (club_id, role_name, role_type) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $club_id, $role_name, $role_type);
    $stmt->execute();
}

// Handle application approval/rejection
if (isset($_POST['process_application'])) {
    $application_id = $_POST['application_id'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    $stmt = $conn->prepare("UPDATE membership_applications SET application_status = ?, processed_by = ?, processed_date = NOW(), remarks = ? WHERE id = ?");
    $stmt->bind_param("sisi", $status, $user_id, $remarks, $application_id);
    $stmt->execute();
    
    if ($status === 'approved') {
        // Get application details
        $stmt = $conn->prepare("SELECT user_id, club_id, role_id FROM membership_applications WHERE id = ?");
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();
        
        // Add to club_members
        $stmt = $conn->prepare("INSERT INTO club_members (user_id, club_id, role_id, join_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iii", $application['user_id'], $application['club_id'], $application['role_id']);
        $stmt->execute();
    }
}

// Handle member deletion
if (isset($_POST['delete_member'])) {
    $member_id = $_POST['member_id'];
    
    // Verify the member belongs to the club leader's club
    $stmt = $conn->prepare("SELECT * FROM club_members WHERE id = ? AND club_id = ?");
    $stmt->bind_param("ii", $member_id, $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Delete the member
        $stmt = $conn->prepare("DELETE FROM club_members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
    }
}

// Get pending applications
$applications = $conn->prepare("
    SELECT ma.*, u.name as applicant_name, cr.role_name, cr.role_type 
    FROM membership_applications ma 
    JOIN users u ON ma.user_id = u.id 
    JOIN club_roles cr ON ma.role_id = cr.id 
    WHERE ma.club_id = ? AND ma.application_status = 'pending'
    ORDER BY ma.applied_date DESC
");
$applications->bind_param("i", $club_id);
$applications->execute();
$pending_applications = $applications->get_result();

// Get club members
$members = $conn->prepare("
    SELECT cm.*, u.name as member_name, cr.role_name, cr.role_type 
    FROM club_members cm 
    JOIN users u ON cm.user_id = u.id 
    JOIN club_roles cr ON cm.role_id = cr.id 
    WHERE cm.club_id = ? AND cm.active_status = 1
    ORDER BY cr.role_type DESC, cr.role_name
");
$members->bind_param("i", $club_id);
$members->execute();
$club_members = $members->get_result();

// Get club roles
$roles = $conn->prepare("SELECT * FROM club_roles WHERE club_id = ? ORDER BY role_type, role_name");
$roles->bind_param("i", $club_id);
$roles->execute();
$club_roles = $roles->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Leader Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Club Leader Dashboard</h1>
            <a href="edit_club.php" class="btn btn-primary">Edit Club</a>
        </div>
        
        <!-- Create New Role -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Create New Role</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="role_name" class="form-label">Role Name</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="role_type" class="form-label">Role Type</label>
                        <select class="form-select" id="role_type" name="role_type" required>
                            <option value="executive_body">Executive Body</option>
                            <option value="club_member">Club Member</option>
                        </select>
                    </div>
                    <button type="submit" name="create_role" class="btn btn-primary">Create Role</button>
                </form>
            </div>
        </div>

        <!-- Pending Applications -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Pending Applications</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Role</th>
                                <th>Type</th>
                                <th>Applied Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $pending_applications->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['role_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['role_type']); ?></td>
                                <td><?php echo htmlspecialchars($app['applied_date']); ?></td>
                                <td>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <input type="text" name="remarks" placeholder="Remarks" class="form-control form-control-sm d-inline-block w-auto">
                                        <button type="submit" name="process_application" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <input type="text" name="remarks" placeholder="Remarks" class="form-control form-control-sm d-inline-block w-auto">
                                        <button type="submit" name="process_application" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Club Members -->
        <div class="card">
            <div class="card-header">
                <h3>Club Members</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h4>Executive Body</h4>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $club_members->data_seek(0);
                                    while ($member = $club_members->fetch_assoc()):
                                        if ($member['role_type'] === 'executive_body'):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['role_name']); ?></td>
                                        <td>
                                            <a href="delete_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this executive member?');">Delete</a>
                                        </td>
                                    </tr>
                                    <?php 
                                        endif;
                                    endwhile; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h4>Club Members</h4>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $club_members->data_seek(0);
                                    while ($member = $club_members->fetch_assoc()):
                                        if ($member['role_type'] === 'club_member'):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['role_name']); ?></td>
                                        <td>
                                            <a href="delete_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this club member?');">Delete</a>
                                        </td>
                                    </tr>
                                    <?php 
                                        endif;
                                    endwhile; 
                                    ?>
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
