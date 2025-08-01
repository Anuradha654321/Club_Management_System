<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="../index.php">College Club Hub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>" 
                       href="../admin_dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'clubs.php' ? 'active' : ''; ?>" 
                       href="../clubs.php">Clubs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>" 
                       href="../events.php">Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" 
                       href="../reports.php">Reports</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav> 