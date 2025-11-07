<?php
$page_title = 'About Us - Forward LMS';
$body_class = 'about-page';
require_once __DIR__ . '/../shared/templates/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>About Forward LMS</h1>
        <p>Education platform for managing courses and students</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="about-content">
            <h2>Our Mission</h2>
            <p>Forward LMS is a learning management system designed to help educators organize and deliver course content to students. It provides tools for course creation, student enrollment, progress tracking, and communication.</p>

            <h2>Key Features</h2>
            <div class="features-list">
                <div class="feature-item">
                    <h3>📚 Course Creation</h3>
                    <p>Create and organize course materials, lessons, and assignments.</p>
                </div>
                <div class="feature-item">
                    <h3>👥 Student Management</h3>
                    <p>Enroll students, track their progress, and manage course access.</p>
                </div>
                <div class="feature-item">
                    <h3>📹 Live Classes</h3>
                    <p>Host online classes and meetings with video conferencing.</p>
                </div>
                <div class="feature-item">
                    <h3>📊 Reports</h3>
                    <p>View student performance data and course analytics.</p>
                </div>
            </div>

            <h2>Our Story</h2>
            <p>Forward LMS is a straightforward learning management system built with PHP and MySQL. It provides the essential tools needed to manage online education without unnecessary complexity.</p>
            <p>The system is designed to be reliable and easy to use for both educators and students.</p>

            <h2>What We Provide</h2>
            <ul>
                <li>Course creation and management tools</li>
                <li>Student enrollment and progress tracking</li>
                <li>Assignment submission and grading</li>
                <li>Communication tools for students and teachers</li>
                <li>Basic reporting and analytics</li>
            </ul>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>
