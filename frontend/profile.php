<?php
session_start();
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/config/auth.php';

Auth::requireAuth();

$current_user = Auth::getUser();
$viewing_user_id = isset($_GET['id']) ? intval($_GET['id']) : $current_user['id'];

$page_title = 'User Profile - Forward LMS';
$body_class = 'profile-page';
$additional_css = ['/frontend/assets/css/profile.css'];
$additional_js = ['/frontend/assets/js/profile.js'];

require_once __DIR__ . '/../shared/templates/header.php';
?>

<div class="profile-container">
    <div class="profile-header" id="profileHeader">
        <div class="profile-cover"></div>
        <div class="profile-info">
            <div class="profile-avatar-wrapper">
                <img src="/frontend/assets/images/default-avatar.png" alt="Profile Avatar" class="profile-avatar" id="profileAvatar">
            </div>
            <div class="profile-details">
                <h1 class="profile-name" id="profileName">Loading...</h1>
                <p class="profile-role" id="profileRole"></p>
                <p class="profile-location" id="profileLocation"></p>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-value" id="coursesCount">0</span>
                        <span class="stat-label">Courses</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value" id="certificatesCount">0</span>
                        <span class="stat-label">Certificates</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value" id="pointsCount">0</span>
                        <span class="stat-label">Points</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value" id="followersCount">0</span>
                        <span class="stat-label">Followers</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value" id="followingCount">0</span>
                        <span class="stat-label">Following</span>
                    </div>
                </div>
            </div>
            <div class="profile-actions" id="profileActions">
                 Actions will be loaded dynamically 
            </div>
        </div>
    </div>

    <div class="profile-content">
        <nav class="profile-tabs">
            <button class="tab-btn active" data-tab="about">About</button>
            <button class="tab-btn" data-tab="activity">Activity</button>
            <button class="tab-btn" data-tab="courses">Courses</button>
            <button class="tab-btn" data-tab="certificates">Certificates</button>
        </nav>

        <div class="tab-content active" id="aboutTab">
            <div class="profile-section">
                <h2>About</h2>
                <p class="profile-bio" id="profileBio">No bio available.</p>
            </div>

            <div class="profile-section" id="contactSection">
                <h2>Contact Information</h2>
                <div class="contact-info" id="contactInfo">
                     Contact info will be loaded dynamically 
                </div>
            </div>

            <div class="profile-section">
                <h2>Skills</h2>
                <div class="skills-list" id="skillsList">
                    <p class="empty-message">No skills added yet.</p>
                </div>
            </div>

            <div class="profile-section">
                <h2>Education</h2>
                <div class="education-list" id="educationList">
                    <p class="empty-message">No education history added yet.</p>
                </div>
            </div>

            <div class="profile-section">
                <h2>Experience</h2>
                <div class="experience-list" id="experienceList">
                    <p class="empty-message">No work experience added yet.</p>
                </div>
            </div>
        </div>

        <div class="tab-content" id="activityTab">
            <div class="profile-section">
                <h2>Recent Activity</h2>
                <div class="activity-feed" id="activityFeed">
                    <p class="empty-message">No recent activity.</p>
                </div>
            </div>
        </div>

        <div class="tab-content" id="coursesTab">
            <div class="profile-section">
                <h2>Enrolled Courses</h2>
                <div class="courses-grid" id="coursesGrid">
                    <p class="empty-message">No courses enrolled yet.</p>
                </div>
            </div>
        </div>

        <div class="tab-content" id="certificatesTab">
            <div class="profile-section">
                <h2>Certificates</h2>
                <div class="certificates-grid" id="certificatesGrid">
                    <p class="empty-message">No certificates earned yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const VIEWING_USER_ID = <?php echo $viewing_user_id; ?>;
    const CURRENT_USER_ID = <?php echo $current_user['id']; ?>;
</script>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>
