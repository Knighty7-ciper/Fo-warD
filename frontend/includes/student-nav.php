<?php
// Student navigation bar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="main-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/frontend/student/dashboard.php">FowarD LMS</a>
        </div>
        <ul class="nav-menu">
            <li><a href="/frontend/student/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="/frontend/student/enrolled-courses.php" class="<?= $current_page == 'enrolled-courses.php' ? 'active' : '' ?>">My Courses</a></li>
            <li><a href="/frontend/courses.php" class="<?= $current_page == 'courses.php' ? 'active' : '' ?>">Browse Courses</a></li>
            <li><a href="/frontend/student/certificates.php" class="<?= $current_page == 'certificates.php' ? 'active' : '' ?>">Certificates</a></li>
            <li><a href="/frontend/student/rewards.php" class="<?= $current_page == 'rewards.php' ? 'active' : '' ?>">Rewards</a></li>
            <li><a href="/frontend/student/peer-collaboration.php" class="<?= $current_page == 'peer-collaboration.php' ? 'active' : '' ?>">Discussions</a></li>
            <li class="nav-user">
                <span>Welcome, <?= htmlspecialchars($user['name']) ?></span>
                <a href="/backend/auth/logout.php" class="btn-logout">Logout</a>
            </li>
        </ul>
    </div>
</nav>
