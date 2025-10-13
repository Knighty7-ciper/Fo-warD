<?php
$page_title = 'Search Results';
require_once __DIR__ . '/../shared/templates/header.php';

if (!Auth::isAuthenticated()) {
    header('Location: /frontend/login.php');
    exit;
}

$query = $_GET['q'] ?? '';
?>

<link rel="stylesheet" href="/frontend/assets/css/search.css">

<div class="search-container">
    <div class="search-header">
        <h1>Search Results</h1>
        <div class="search-box">
            <input type="text" id="search-input" placeholder="Search courses, lessons, assignments..." value="<?php echo htmlspecialchars($query); ?>" autofocus>
            <button onclick="performSearch()" class="btn btn-primary">Search</button>
        </div>
    </div>
    
    <div class="search-filters">
        <button class="filter-btn active" data-type="all">All Results</button>
        <button class="filter-btn" data-type="courses">Courses</button>
        <button class="filter-btn" data-type="lessons">Lessons</button>
        <button class="filter-btn" data-type="assignments">Assignments</button>
        <button class="filter-btn" data-type="quizzes">Quizzes</button>
        <button class="filter-btn" data-type="forum">Forum</button>
    </div>
    
    <div class="search-results" id="search-results">
        <?php if (empty($query)): ?>
            <div class="search-suggestions">
                <div class="suggestions-section">
                    <h3>Recent Searches</h3>
                    <div id="recent-searches" class="suggestions-list"></div>
                </div>
                <div class="suggestions-section">
                    <h3>Popular Searches</h3>
                    <div id="popular-searches" class="suggestions-list"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="loading">Searching...</div>
        <?php endif; ?>
    </div>
</div>

<script src="/frontend/assets/js/search.js"></script>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>
