<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'backend/config/db.php';
require_once 'backend/config/auth.php';

$current_user = Auth::getUser();
$is_logged_in = Auth::isAuthenticated();
$user_role = Auth::getUserRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $page_title ?? 'Forward LMS'; ?></title>

    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Favicon with fallback -->
    <link rel="icon" type="image/svg+xml" href="/frontend/assets/images/icons/favicon.svg" onerror="this.href='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzI1NjNlYiIvPgo8dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IndoaXRlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiPjwvdGV4dD4KPHN2Zz4K';">

    <meta name="description" content="Forward LMS - Learning Management System">
    <meta name="keywords" content="learning, education, courses, online learning">
</head>
<body class="<?php echo $body_class ?? ''; ?>">

<header class="main-header">
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/">
                    <img src="/frontend/assets/images/logo.png" alt="Forward LMS" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.marginLeft='0';">
                    <span class="brand-name">Forward LMS</span>
                </a>
            </div>

            // Added global search bar to header 
            <?php if ($is_logged_in): ?>
            <div class="nav-search">
                <form action="/frontend/search.php" method="GET">
                    <input type="text" name="q" placeholder="Search..." class="search-input">
                    <button type="submit" class="search-btn">🔍</button>
                </form>
            </div>
            <?php endif; ?>

            <ul class="nav-menu" id="nav-menu">
                <li><a href="/" class="nav-link">Home</a></li>
                <li><a href="/frontend/courses.php" class="nav-link">Courses</a></li>
                <li><a href="/frontend/about.php" class="nav-link">About</a></li>
                <li><a href="/frontend/contact.php" class="nav-link">Contact</a></li>

                <?php if ($is_logged_in): ?>
                    <?php if ($user_role === 'admin'): ?>
                        <li><a href="/frontend/admin/dashboard.php" class="nav-link">Admin</a></li>
                    <?php elseif ($user_role === 'teacher'): ?>
                        <li><a href="/frontend/teacher/dashboard.php" class="nav-link">Dashboard</a></li>
                    <?php elseif ($user_role === 'student'): ?>
                        <li><a href="/frontend/student/dashboard.php" class="nav-link">Dashboard</a></li>
                    <?php endif; ?>

                    <li class="user-menu">
                        <a href="#" class="nav-link user-dropdown">
                            <?php if (!empty($current_user['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($current_user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($current_user['first_name']); ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/frontend/profile.php">Profile</a></li>
                            <li><a href="/frontend/settings.php">Settings</a></li>
                            <li><hr></li>
                            <li><a href="/backend/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li><a href="/frontend/login.php" class="nav-link">Login</a></li>
                    <li><a href="/frontend/register.php" class="nav-link btn-primary">Sign Up</a></li>
                <?php endif; ?>
            </ul>

            <button class="nav-toggle" id="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>
</header>

<main class="main-content">
