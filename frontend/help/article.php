<?php
$pageTitle = 'Help Article';
include '../../shared/templates/header.php';

$slug = $_GET['slug'] ?? '';
?>

<link rel="stylesheet" href="../assets/css/help.css">

<div class="article-container">
    <div class="article-breadcrumb">
        <a href="/frontend/help/">Help Center</a>
        <i class="fas fa-chevron-right"></i>
        <span id="articleCategory"></span>
    </div>

    <article class="article-content">
        <h1 id="articleTitle"></h1>
        <div class="article-meta">
            <span id="articleDate"></span>
            <span id="articleViews"></span>
        </div>
        <div id="articleBody"></div>
    </article>

    <div class="article-feedback">
        <h3>Was this article helpful?</h3>
        <div class="feedback-buttons">
            <button class="btn btn-success" onclick="submitFeedback(true)">
                <i class="fas fa-thumbs-up"></i> Yes
            </button>
            <button class="btn btn-secondary" onclick="submitFeedback(false)">
                <i class="fas fa-thumbs-down"></i> No
            </button>
        </div>
        <div id="feedbackMessage" style="display: none;"></div>
    </div>

    <div class="related-articles">
        <h3>Related Articles</h3>
        <div id="relatedArticles"></div>
    </div>
</div>

<script>
const articleSlug = '<?php echo htmlspecialchars($slug); ?>';
loadArticle(articleSlug);
</script>
<script src="../assets/js/help.js"></script>

<?php include '../../shared/templates/footer.php'; ?>
