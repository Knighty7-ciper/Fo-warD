<?php
$pageTitle = 'Help Center';
include '../../shared/templates/header.php';
?>

<link rel="stylesheet" href="../assets/css/help.css">

<div class="help-container">
    <div class="help-hero">
        <h1>How can we help you?</h1>
        <div class="help-search">
            <i class="fas fa-search"></i>
            <input type="text" id="helpSearch" placeholder="Search for help articles..." onkeyup="searchHelp()">
        </div>
    </div>

    <div class="help-categories">
        <h2>Browse by Category</h2>
        <div class="category-grid" id="categoryGrid">
            <div class="category-card" onclick="filterByCategory('Getting Started')">
                <i class="fas fa-rocket"></i>
                <h3>Getting Started</h3>
                <p>Learn the basics of using the platform</p>
            </div>

            <div class="category-card" onclick="filterByCategory('Courses')">
                <i class="fas fa-book"></i>
                <h3>Courses</h3>
                <p>Everything about creating and taking courses</p>
            </div>

            <div class="category-card" onclick="filterByCategory('Assignments')">
                <i class="fas fa-tasks"></i>
                <h3>Assignments</h3>
                <p>Submitting and grading assignments</p>
            </div>

            <div class="category-card" onclick="filterByCategory('Quizzes')">
                <i class="fas fa-question-circle"></i>
                <h3>Quizzes</h3>
                <p>Creating and taking quizzes</p>
            </div>

            <div class="category-card" onclick="filterByCategory('Account')">
                <i class="fas fa-user"></i>
                <h3>Account</h3>
                <p>Managing your account and profile</p>
            </div>

            <div class="category-card" onclick="filterByCategory('Troubleshooting')">
                <i class="fas fa-wrench"></i>
                <h3>Troubleshooting</h3>
                <p>Common issues and solutions</p>
            </div>
        </div>
    </div>

    <div class="help-articles">
        <h2>Popular Articles</h2>
        <div id="articlesList"></div>
    </div>

    <div class="help-contact">
        <div class="contact-card">
            <i class="fas fa-envelope"></i>
            <h3>Still need help?</h3>
            <p>Contact our support team and we'll get back to you within 24 hours</p>
            <a href="/frontend/contact.php" class="btn btn-primary">Contact Support</a>
        </div>
    </div>
</div>

<script src="../assets/js/help.js"></script>

<?php include '../../shared/templates/footer.php'; ?>
