<?php
$page_title = 'Help & Support - Forward LMS';
$body_class = 'help-page';

require_once __DIR__ . '/../shared/templates/header.php';
?>

<div class="help-container">
    <!-- Hero Section -->
    <div class="help-hero">
        <h1>How can we help you?</h1>
        <p>Find answers to common questions and get the support you need</p>
        
        <div class="search-section">
            <div class="search-box">
                <input type="text" id="help-search" placeholder="Search for help articles...">
                <button class="search-btn" onclick="searchHelp()">Search</button>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="action-grid">
            <a href="/frontend/courses.php" class="action-card">
                <div class="action-icon">📚</div>
                <h3>Browse Courses</h3>
                <p>Explore our course catalog</p>
            </a>
            
            <a href="/frontend/register.php" class="action-card">
                <div class="action-icon">👤</div>
                <h3>Create Account</h3>
                <p>Join our learning community</p>
            </a>
            
            <a href="/frontend/login.php" class="action-card">
                <div class="action-icon">🔑</div>
                <h3>Login</h3>
                <p>Access your account</p>
            </a>
            
            <a href="/frontend/contact.php" class="action-card">
                <div class="action-icon">💬</div>
                <h3>Contact Support</h3>
                <p>Get personalized help</p>
            </a>
        </div>
    </div>

    <!-- Help Categories -->
    <div class="help-categories">
        <h2>Browse by Category</h2>
        <div class="category-grid">
            <div class="category-card" onclick="showCategory('getting-started')">
                <div class="category-icon">🚀</div>
                <h3>Getting Started</h3>
                <p>Learn the basics of using Forward LMS</p>
                <span class="article-count">5 articles</span>
            </div>
            
            <div class="category-card" onclick="showCategory('courses')">
                <div class="category-icon">📖</div>
                <h3>Courses & Learning</h3>
                <p>Everything about courses and learning</p>
                <span class="article-count">8 articles</span>
            </div>
            
            <div class="category-card" onclick="showCategory('account')">
                <div class="category-icon">👥</div>
                <h3>Account & Profile</h3>
                <p>Manage your account settings</p>
                <span class="article-count">6 articles</span>
            </div>
            
            <div class="category-card" onclick="showCategory('payments')">
                <div class="category-icon">💳</div>
                <h3>Payments & Billing</h3>
                <p>Billing, payments, and refunds</p>
                <span class="article-count">4 articles</span>
            </div>
            
            <div class="category-card" onclick="showCategory('technical')">
                <div class="category-icon">⚙️</div>
                <h3>Technical Issues</h3>
                <p>Troubleshooting and technical help</p>
                <span class="article-count">7 articles</span>
            </div>
            
            <div class="category-card" onclick="showCategory('instructors')">
                <div class="category-icon">🎓</div>
                <h3>For Instructors</h3>
                <p>Creating and managing courses</p>
                <span class="article-count">6 articles</span>
            </div>
        </div>
    </div>

    <!-- Featured Articles -->
    <div class="featured-articles">
        <h2>Featured Help Articles</h2>
        <div class="article-grid">
            <article class="help-article" onclick="showArticle('reset-password')">
                <h4>How to reset your password</h4>
                <p>Learn how to reset your password if you've forgotten it or want to change it for security reasons.</p>
                <div class="article-meta">
                    <span class="category">Account</span>
                    <span class="read-time">2 min read</span>
                </div>
            </article>
            
            <article class="help-article" onclick="showArticle('enroll-course')">
                <h4>How to enroll in a course</h4>
                <p>Step-by-step guide on how to browse, preview, and enroll in courses on our platform.</p>
                <div class="article-meta">
                    <span class="category">Courses</span>
                    <span class="read-time">3 min read</span>
                </div>
            </article>
            
            <article class="help-article" onclick="showArticle('track-progress')">
                <h4>Tracking your learning progress</h4>
                <p>Understand how to monitor your progress through courses and view your achievements.</p>
                <div class="article-meta">
                    <span class="category">Learning</span>
                    <span class="read-time">4 min read</span>
                </div>
            </article>
            
            <article class="help-article" onclick="showArticle('download-certificate')">
                <h4>Downloading your certificates</h4>
                <p>Learn how to download, share, and verify your course completion certificates.</p>
                <div class="article-meta">
                    <span class="category">Certificates</span>
                    <span class="read-time">2 min read</span>
                </div>
            </article>
        </div>
    </div>

    <!-- Contact Support -->
    <div class="contact-support">
        <div class="support-content">
            <h2>Still need help?</h2>
            <p>Our support team is here to assist you with any questions or issues you may have.</p>
            
            <div class="support-options">
                <a href="/frontend/contact.php" class="support-option">
                    <div class="option-icon">📧</div>
                    <div class="option-content">
                        <h4>Email Support</h4>
                        <p>Get detailed help via email</p>
                        <span class="response-time">Response within 24 hours</span>
                    </div>
                </a>
                
                <a href="/frontend/contact.php" class="support-option">
                    <div class="option-icon">💬</div>
                    <div class="option-content">
                        <h4>Contact Form</h4>
                        <p>Send us a message directly</p>
                        <span class="response-time">Response within 12 hours</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Article Modal -->
<div id="article-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="article-title">Article Title</h3>
            <button class="modal-close" onclick="closeArticleModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="article-content">
                <p>Article content will be loaded here...</p>
            </div>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div id="category-modal" class="modal" style="display: none;">
    <div class="modal-content large">
        <div class="modal-header">
            <h3 id="category-title">Category Name</h3>
            <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="category-articles">
                <p>Loading articles...</p>
            </div>
        </div>
    </div>
</div>

<style>
.help-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Hero Section */
.help-hero {
    text-align: center;
    margin-bottom: 4rem;
    padding: 3rem 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
}

.help-hero h1 {
    font-size: 3rem;
    margin-bottom: 1rem;
    font-weight: 700;
}

.help-hero p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.search-section {
    max-width: 600px;
    margin: 0 auto;
}

.search-box {
    display: flex;
    gap: 0.5rem;
    background: white;
    border-radius: 12px;
    padding: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.search-box input {
    flex: 1;
    padding: 1rem;
    border: none;
    font-size: 1rem;
    outline: none;
    color: #374151;
}

.search-btn {
    padding: 1rem 2rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-btn:hover {
    background: #2563eb;
}

/* Quick Actions */
.quick-actions {
    margin-bottom: 4rem;
}

.quick-actions h2 {
    text-align: center;
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 2rem;
    font-weight: 600;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.action-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 2rem;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s;
    text-align: center;
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
    border-color: #3b82f6;
}

.action-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.action-card h3 {
    font-size: 1.25rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.action-card p {
    color: #64748b;
    margin: 0;
}

/* Help Categories */
.help-categories {
    margin-bottom: 4rem;
}

.help-categories h2 {
    text-align: center;
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 2rem;
    font-weight: 600;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.category-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 2rem;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    transform: scaleY(0);
    transition: transform 0.2s;
}

.category-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    border-color: #3b82f6;
}

.category-card:hover::before {
    transform: scaleY(1);
}

.category-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.category-card h3 {
    font-size: 1.25rem;
    color: #1e293b;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.category-card p {
    color: #64748b;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.article-count {
    background: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Featured Articles */
.featured-articles {
    margin-bottom: 4rem;
}

.featured-articles h2 {
    text-align: center;
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 2rem;
    font-weight: 600;
}

.article-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.help-article {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s;
}

.help-article:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    border-color: #3b82f6;
}

.help-article h4 {
    font-size: 1.1rem;
    color: #1e293b;
    margin-bottom: 0.75rem;
    font-weight: 600;
    line-height: 1.3;
}

.help-article p {
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.article-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
}

.category {
    background: #f1f5f9;
    color: #475569;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}

.read-time {
    color: #64748b;
}

/* Contact Support */
.contact-support {
    background: #f8fafc;
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
}

.support-content h2 {
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 1rem;
    font-weight: 600;
}

.support-content p {
    color: #64748b;
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.support-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    max-width: 800px;
    margin: 0 auto;
}

.support-option {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 2rem;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    text-align: left;
}

.support-option:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    border-color: #3b82f6;
}

.option-icon {
    font-size: 2rem;
    width: 60px;
    text-align: center;
}

.option-content h4 {
    font-size: 1.1rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.option-content p {
    color: #64748b;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.response-time {
    color: #10b981;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Modals */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-content.large {
    max-width: 1000px;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    padding: 0.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.modal-close:hover {
    background: #f1f5f9;
}

.modal-body {
    padding: 2rem;
}

#category-articles {
    display: grid;
    gap: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .help-container {
        padding: 1rem;
    }
    
    .help-hero {
        padding: 2rem 1rem;
    }
    
    .help-hero h1 {
        font-size: 2rem;
    }
    
    .search-box {
        flex-direction: column;
        gap: 1rem;
    }
    
    .action-grid,
    .category-grid,
    .article-grid {
        grid-template-columns: 1fr;
    }
    
    .support-options {
        grid-template-columns: 1fr;
    }
    
    .support-option {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .help-hero h1 {
        font-size: 1.75rem;
    }
    
    .quick-actions h2,
    .help-categories h2,
    .featured-articles h2,
    .support-content h2 {
        font-size: 1.5rem;
    }
    
    .contact-support {
        padding: 2rem 1rem;
    }
    
    .modal-content,
    .modal-content.large {
        width: 95%;
    }
    
    .modal-body {
        padding: 1rem;
    }
}

/* Animation for smooth transitions */
.help-article,
.category-card,
.action-card,
.support-option {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Focus states for accessibility */
.category-card:focus,
.help-article:focus,
.action-card:focus,
.support-option:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

.search-box input:focus,
.search-btn:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}
</style>

<script>
// Help article content
const helpArticles = {
    'reset-password': {
        title: 'How to Reset Your Password',
        content: `
            <h3>Resetting Your Password</h3>
            <p>If you've forgotten your password or want to change it for security reasons, follow these steps:</p>
            
            <h4>Option 1: Using the Login Page</h4>
            <ol>
                <li>Go to the <a href="/frontend/login.php">login page</a></li>
                <li>Click on "Forgot password?" link below the login form</li>
                <li>Enter your registered email address</li>
                <li>Check your email for a password reset link</li>
                <li>Click the link and follow the instructions to create a new password</li>
            </ol>
            
            <h4>Option 2: From Your Account Settings</h4>
            <ol>
                <li>Log in to your account</li>
                <li>Go to <a href="/frontend/settings.php">Account Settings</a></li>
                <li>Navigate to the "Security & Password" section</li>
                <li>Click "Change Password"</li>
                <li>Enter your current password and new password</li>
                <li>Click "Change Password" to save</li>
            </ol>
            
            <h4>Tips for a Strong Password</h4>
            <ul>
                <li>Use at least 8 characters</li>
                <li>Include a mix of uppercase and lowercase letters</li>
                <li>Add numbers and special characters</li>
                <li>Avoid using personal information</li>
                <li>Don't reuse passwords from other accounts</li>
            </ul>
            
            <p><strong>Need help?</strong> If you're still having trouble, <a href="/frontend/contact.php">contact our support team</a>.</p>
        `
    },
    
    'enroll-course': {
        title: 'How to Enroll in a Course',
        content: `
            <h3>Enrolling in Courses</h3>
            <p>Enrolling in courses is easy! Here's a step-by-step guide:</p>
            
            <h4>Step 1: Browse Courses</h4>
            <p>Start by visiting our <a href="/frontend/courses.php">course catalog</a> to see all available courses. You can filter by:</p>
            <ul>
                <li>Category (e.g., Technology, Business, Design)</li>
                <li>Level (Beginner, Intermediate, Advanced)</li>
                <li>Price (Free or Paid)</li>
                <li>Duration and other criteria</li>
            </ul>
            
            <h4>Step 2: Find a Course</h4>
            <p>Use the search bar to find specific courses or browse through categories. You can also see course ratings, student reviews, and instructor information.</p>
            
            <h4>Step 3: Preview Course</h4>
            <p>Click on a course to see:</p>
            <ul>
                <li>Course description and learning objectives</li>
                <li>Course curriculum and lessons</li>
                <li>Instructor information</li>
                <li>Student reviews and ratings</li>
                <li>Price and enrollment options</li>
            </ul>
            
            <h4>Step 4: Enroll</h4>
            <p>Once you're ready to enroll:</p>
            <ol>
                <li>Click the "Enroll Now" button</li>
                <li>For free courses, enrollment is instant</li>
                <li>For paid courses, complete the payment process</li>
                <li>You'll receive a confirmation email</li>
                <li>The course will appear in your dashboard</li>
            </ol>
            
            <h4>Accessing Your Course</h4>
            <p>After enrollment:</p>
            <ul>
                <li>Go to your <a href="/frontend/student/dashboard.php">Student Dashboard</a></li>
                <li>Find your enrolled courses</li>
                <li>Click "Continue Learning" to start</li>
                <li>Track your progress as you complete lessons</li>
            </ul>
            
            <p><strong>Need help?</strong> If you have trouble enrolling, <a href="/frontend/contact.php">contact our support team</a>.</p>
        `
    },
    
    'track-progress': {
        title: 'Tracking Your Learning Progress',
        content: `
            <h3>Monitoring Your Progress</h3>
            <p>Forward LMS makes it easy to track your learning progress across all your enrolled courses:</p>
            
            <h4>Dashboard Overview</h4>
            <p>Your <a href="/frontend/student/dashboard.php">Student Dashboard</a> shows:</p>
            <ul>
                <li>Overall completion percentage</li>
                <li>Number of courses in progress</li>
                <li>Completed courses and certificates</li>
                <li>Learning streaks and achievements</li>
                <li>Recent activity and upcoming deadlines</li>
            </ul>
            
            <h4>Course Progress</h4>
            <p>Within each course, you can see:</p>
            <ul>
                <li>Lesson completion status</li>
                <li>Overall course progress percentage</li>
                <li>Time spent learning</li>
                <li>Quiz and assignment scores</li>
                <li>Next recommended lesson</li>
            </ul>
            
            <h4>Progress Indicators</h4>
            <p>Look for these visual indicators:</p>
            <ul>
                <li><strong>Green checkmark:</strong> Completed lessons</li>
                <li><strong>Blue play button:</strong> In-progress lessons</li>
                <li><strong>Gray lock:</strong> Locked lessons (prerequisites not met)</li>
                <li><strong>Progress bars:</strong> Show completion percentage</li>
            </ul>
            
            <h4>Achievements and Certificates</h4>
            <p>As you progress, you'll earn:</p>
            <ul>
                <li>Completion badges for finishing lessons</li>
                <li>Milestone achievements for course progress</li>
                <li>Certificates of completion</li>
                <li>Learning streaks for consistent study</li>
            </ul>
            
            <h4>Tips for Staying on Track</h4>
            <ul>
                <li>Set learning goals and deadlines</li>
                <li>Study regularly to maintain streaks</li>
                <li>Take notes and review frequently</li>
                <li>Complete quizzes to reinforce learning</li>
                <li>Participate in course discussions</li>
            </ul>
            
            <p><strong>Need help?</strong> For questions about progress tracking, <a href="/frontend/contact.php">contact our support team</a>.</p>
        `
    },
    
    'download-certificate': {
        title: 'Downloading Your Certificates',
        content: `
            <h3>Accessing Your Certificates</h3>
            <p>Once you complete a course, you can download and share your certificate of completion:</p>
            
            <h4>Where to Find Certificates</h4>
            <p>Your certificates are available in multiple places:</p>
            <ul>
                <li><a href="/frontend/certificates.php">Certificates Page</a> - View all your certificates</li>
                <li>Course completion page - Immediate download after course completion</li>
                <li>Student dashboard - Quick access to recent certificates</li>
            </ul>
            
            <h4>Downloading Certificates</h4>
            <ol>
                <li>Go to the <a href="/frontend/certificates.php">Certificates page</a></li>
                <li>Find the certificate you want to download</li>
                <li>Click the "View Certificate" button</li>
                <li>Use your browser's print function (Ctrl+P) to save as PDF</li>
                <li>Or right-click and "Save As" to download the image</li>
            </ol>
            
            <h4>Sharing Certificates</h4>
            <p>You can share your achievements:</p>
            <ul>
                <li><strong>LinkedIn:</strong> Add to your LinkedIn profile</li>
                <li><strong>Email:</strong> Send directly to employers or contacts</li>
                <li><strong>Social Media:</strong> Share your achievement on social platforms</li>
                <li><strong>Verification:</strong> Share the verification code for authenticity</li>
            </ul>
            
            <h4>Certificate Verification</h4>
            <p>Each certificate includes:</p>
            <ul>
                <li>Your name and course title</li>
                <li>Date of completion</li>
                <li>Unique verification code</li>
                <li>Forward LMS official branding</li>
                <li>Instructor signature (where applicable)</li>
            </ul>
            
            <h4>Troubleshooting</h4>
            <p>If you can't see or download your certificate:</p>
            <ul>
                <li>Ensure you've completed all course requirements</li>
                <li>Check that the course is fully marked as complete</li>
                <li>Try a different browser or clear your cache</li>
                <li>Contact support if the issue persists</li>
            </ul>
            
            <p><strong>Need help?</strong> For certificate-related issues, <a href="/frontend/contact.php">contact our support team</a>.</p>
        `
    }
};

// Search functionality
function searchHelp() {
    const searchTerm = document.getElementById('help-search').value;
    if (searchTerm.trim()) {
        // In a real implementation, this would search through articles
        alert(`Searching for: "${searchTerm}"\n\nThis would show relevant help articles.`);
    }
}

// Article modal
function showArticle(articleId) {
    const article = helpArticles[articleId];
    if (article) {
        document.getElementById('article-title').textContent = article.title;
        document.getElementById('article-content').innerHTML = article.content;
        document.getElementById('article-modal').style.display = 'flex';
    }
}

function closeArticleModal() {
    document.getElementById('article-modal').style.display = 'none';
}

// Category modal
function showCategory(categoryId) {
    const categories = {
        'getting-started': {
            title: 'Getting Started',
            articles: [
                'How to create an account',
                'Navigating the platform',
                'Setting up your profile',
                'Understanding the dashboard',
                'First steps after enrollment'
            ]
        },
        'courses': {
            title: 'Courses & Learning',
            articles: [
                'How to browse and find courses',
                'Enrolling in courses',
                'Understanding course structure',
                'Taking notes and bookmarks',
                'Quizzes and assignments',
                'Getting the most from courses',
                'Course discussions and forums',
                'Downloading resources'
            ]
        },
        'account': {
            title: 'Account & Profile',
            articles: [
                'Resetting your password',
                'Updating your profile',
                'Managing notification preferences',
                'Privacy settings',
                'Account security',
                'Deleting your account'
            ]
        },
        'payments': {
            title: 'Payments & Billing',
            articles: [
                'Understanding pricing',
                'Payment methods accepted',
                'Processing payments',
                'Refund policy',
                'Invoice and receipts',
                'Tax information'
            ]
        },
        'technical': {
            title: 'Technical Issues',
            articles: [
                'Browser compatibility',
                'Troubleshooting video playback',
                'Downloading course materials',
                'Mobile app issues',
                'Internet connection problems',
                'Account access issues',
                'Performance optimization'
            ]
        },
        'instructors': {
            title: 'For Instructors',
            articles: [
                'Creating your first course',
                'Uploading course content',
                'Managing students',
                'Grading and feedback',
                'Course analytics',
                'Monetization options'
            ]
        }
    };
    
    const category = categories[categoryId];
    if (category) {
        document.getElementById('category-title').textContent = category.title;
        
        const articlesHtml = category.articles
            .map(article => `<div class="article-item" onclick="showArticle('${categoryId}-${article.toLowerCase().replace(/\s+/g, '-')}')">${article}</div>`)
            .join('');
        
        document.getElementById('category-articles').innerHTML = articlesHtml;
        document.getElementById('category-modal').style.display = 'flex';
    }
}

function closeCategoryModal() {
    document.getElementById('category-modal').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('article-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeArticleModal();
    }
});

document.getElementById('category-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCategoryModal();
    }
});

// Allow Enter key in search box
document.getElementById('help-search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchHelp();
    }
});
</script>

<style>
.article-item {
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 0.5rem;
}

.article-item:hover {
    background: #f8fafc;
    border-color: #3b82f6;
}

.modal-content h3,
.modal-content h4 {
    color: #1e293b;
    margin-bottom: 1rem;
}

.modal-content p {
    color: #374151;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.modal-content ol,
.modal-content ul {
    color: #374151;
    line-height: 1.6;
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.modal-content li {
    margin-bottom: 0.5rem;
}

.modal-content a {
    color: #3b82f6;
    text-decoration: none;
}

.modal-content a:hover {
    text-decoration: underline;
}
</style>

<?php
require_once __DIR__ . '/../shared/templates/footer.php';
?>