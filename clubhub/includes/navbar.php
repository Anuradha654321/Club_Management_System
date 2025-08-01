<?php
if (!function_exists('isLoggedIn')) {
    require_once 'functions.php';
    require_once 'auth.php';
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">College Club Hub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="clubs.php">Clubs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="events.php">Events</a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                        </li>
                    <?php elseif (isClubLeader()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="club_leader_dashboard.php">Club Dashboard</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="student_dashboard.php">My Dashboard</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php 
                            $user = getUserDetails($_SESSION['user_id']);
                            echo htmlspecialchars($user['name']);
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 