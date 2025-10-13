<?php
session_start();
require_once '../../backend/config/db.php';

$page_title = 'Browse Courses';
include '../../shared/templates/header.php';

$db = getDBConnection();

// Get all published courses
$sql = "SELECT c.*, u.first_name, u.last_name, 
        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.status = 'published'
        ORDER BY c.created_at DESC";

$stmt = $db->query($sql);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="page-header">
        <h1>Browse Courses</h1>
        <p>Explore our wide range of courses and start learning today</p>
    </div>
    
    <div class="course-filters">
        <input type="text" id="searchInput" placeholder="Search courses..." class="search-input">
        <select id="categoryFilter" class="filter-select">
            <option value="">All Categories</option>
            <option value="programming">Programming</option>
            <option value="design">Design</option>
            <option value="business">Business</option>
            <option value="marketing">Marketing</option>
            <option value="science">Science</option>
        </select>
        <select id="levelFilter" class="filter-select">
            <option value="">All Levels</option>
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
        </select>
    </div>
    
    <div class="courses-grid" id="coursesGrid">
        <?php foreach ($courses as $course): ?>
            <div class="course-card" data-category="<?= htmlspecialchars($course['category'] ?? '') ?>" 
                 data-level="<?= htmlspecialchars($course['level'] ?? 'beginner') ?>">
                <div class="course-image">
                    <?php if ($course['thumbnail']): ?>
                        <img src="<?= htmlspecialchars($course['thumbnail']) ?>" alt="Course thumbnail">
                    <?php else: ?>
                        <div class="placeholder-image">
                            <span><?= strtoupper(substr($course['title'], 0, 2)) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="course-content">
                    <h3><?= htmlspecialchars($course['title']) ?></h3>
                    <p class="course-instructor">
                        By <?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?>
                    </p>
                    <p class="course-description">
                        <?= htmlspecialchars(substr($course['description'], 0, 120)) ?>...
                    </p>
                    <div class="course-meta">
                        <span class="meta-item">
                            <i class="icon-users"></i> <?= $course['student_count'] ?> students
                        </span>
                        <span class="meta-item">
                            <i class="icon-clock"></i> <?= $course['duration'] ?? '8' ?> weeks
                        </span>
                    </div>
                    <div class="course-footer">
                        <?php if ($course['price'] > 0): ?>
                            <span class="course-price">KSh <?= number_format($course['price']) ?></span>
                        <?php else: ?>
                            <span class="course-price free">Free</span>
                        <?php endif; ?>
                        <a href="view-course.php?id=<?= $course['id'] ?>" class="btn btn-primary">View Course</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.course-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.search-input,
.filter-select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
}

.search-input {
    flex: 1;
    min-width: 250px;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
}

.course-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.course-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.course-image {
    height: 180px;
    overflow: hidden;
}

.course-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.placeholder-image {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.placeholder-image span {
    font-size: 3rem;
    color: white;
    font-weight: bold;
}

.course-content {
    padding: 20px;
}

.course-content h3 {
    margin: 0 0 10px 0;
    font-size: 1.25rem;
    color: #333;
}

.course-instructor {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.course-description {
    color: #555;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 15px;
}

.course-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.meta-item {
    font-size: 0.85rem;
    color: #666;
}

.course-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.course-price {
    font-size: 1.25rem;
    font-weight: bold;
    color: #333;
}

.course-price.free {
    color: #28a745;
}
</style>

<script>
// Search and filter functionality
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const levelFilter = document.getElementById('levelFilter');
const coursesGrid = document.getElementById('coursesGrid');
const courseCards = coursesGrid.querySelectorAll('.course-card');

function filterCourses() {
    const searchTerm = searchInput.value.toLowerCase();
    const category = categoryFilter.value;
    const level = levelFilter.value;
    
    courseCards.forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        const description = card.querySelector('.course-description').textContent.toLowerCase();
        const cardCategory = card.dataset.category;
        const cardLevel = card.dataset.level;
        
        const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
        const matchesCategory = !category || cardCategory === category;
        const matchesLevel = !level || cardLevel === level;
        
        if (matchesSearch && matchesCategory && matchesLevel) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

searchInput.addEventListener('input', filterCourses);
categoryFilter.addEventListener('change', filterCourses);
levelFilter.addEventListener('change', filterCourses);
</script>

<?php include '../../shared/templates/footer.php'; ?>
