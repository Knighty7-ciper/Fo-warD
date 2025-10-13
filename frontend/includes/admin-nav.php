<?php
// Admin navigation bar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="main-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/frontend/admin/dashboard.php">FowarD LMS - Admin</a>
        </div>
        <ul class="nav-menu">
            <li><a href="/frontend/admin/dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="/frontend/admin/user-manager.php" class="<?= $current_page == 'user-manager.php' ? 'active' : '' ?>">Users</a></li>
            <li><a href="/frontend/admin/course-manager.php" class="<?= $current_page == 'course-manager.php' ? 'active' : '' ?>">Courses</a></li>
            <li><a href="/frontend/admin/audit-logs.php" class="<?= $current_page == 'audit-logs.php' ? 'active' : '' ?>">Audit Logs</a></li>
            <li><a href="/frontend/admin/settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>">Settings</a></li>
            <li class="nav-user">
                <span>Admin: <?= htmlspecialchars($user['name']) ?></span>
                <a href="/backend/auth/logout.php" class="btn-logout">Logout</a>
            </li>
        </ul>
    </div>
</nav>
