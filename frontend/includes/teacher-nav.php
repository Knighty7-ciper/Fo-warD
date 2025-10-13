<?php
// Teacher navigation bar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="main-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/frontend/teacher/dashboard.php">FowarD LMS</a>
        </div>
        <ul class="nav-menu">
            <li><a href="/frontend/teacher/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="/frontend/teacher/courses.php" class="<?= $current_page == 'courses.php' ? 'active' : '' ?>">My Courses</a></li>
            <li><a href="/frontend/teacher/content-library.php" class="<?= $current_page == 'content-library.php' ? 'active' : '' ?>">Content Library</a></li>
            <li><a href="/frontend/teacher/schedule.php" class="<?= $current_page == 'schedule.php' ? 'active' : '' ?>">Schedule</a></li>
            <li><a href="/frontend/teacher/gradebook.php" class="<?= $current_page == 'gradebook.php' ? 'active' : '' ?>">Gradebook</a></li>
            <li class="nav-user">
                <span>Welcome, <?= htmlspecialchars($user['name']) ?></span>
                <a href="/backend/auth/logout.php" class="btn-logout">Logout</a>
            </li>
        </ul>
    </div>
</nav>
