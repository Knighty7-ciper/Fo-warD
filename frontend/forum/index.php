<?php
$page_title = 'Forum';
require_once __DIR__ . '/../../shared/templates/header.php';

if (!Auth::isAuthenticated()) {
    header('Location: /frontend/login.php');
    exit;
}
?>

<link rel="stylesheet" href="/frontend/assets/css/forum.css">

<div class="forum-container">
    <div class="forum-header">
        <h1>Discussion Forum</h1>
        <a href="/frontend/forum/create-topic.php" class="btn btn-primary">Create New Topic</a>
    </div>
    
    <div class="forum-search">
        <input type="text" id="search-input" placeholder="Search topics..." onkeyup="searchTopics(event)">
    </div>
    
    <div class="forum-categories" id="forum-categories">
        <div class="loading">Loading categories...</div>
    </div>
</div>

<script src="/frontend/assets/js/forum.js"></script>

<?php require_once __DIR__ . '/../../shared/templates/footer.php'; ?>
