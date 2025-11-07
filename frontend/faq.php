<?php
$page_title = 'Frequently Asked Questions - Forward LMS';
$body_class = 'faq-page';

require_once __DIR__ . '/../shared/templates/header.php';
?>

<div class="faq-container">
    <div class="faq-header">
        <h1>Frequently Asked Questions</h1>
        <p>Find quick answers to the most common questions about Forward LMS</p>
    </div>

    <div class="faq-search">
        <div class="search-box">
            <input type="text" id="faq-search" placeholder="Search FAQ...">
            <button class="search-btn" onclick="searchFAQ()">Search</button>
        </div>
    </div>

    <div class="faq-categories">
        <div class="category-tabs">
            <button class="tab-btn active" onclick="showCategory('all')">All Questions</button>
            <button class="tab-btn" onclick="showCategory('account')">Account & Login</button>
            <button class="tab-btn" onclick="showCategory('courses')">Courses & Learning</button>
            <button class="tab-btn" onclick="showCategory('payments')">Payments & Billing</button>
            <button class="tab-btn" onclick="showCategory('technical')">Technical Support</button>
            <button class="tab-btn" onclick="showCategory('instructors')">For Instructors</button>
        </div>
    </div>

    <div class="faq-content">
        <!-- Account & Login -->
        <div class="faq-category" id="account" style="display: none;">
            <h2>Account & Login</h2>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    How do I create an account?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Creating an account is easy! Click the "Sign Up" button in the top right corner, fill out the registration form with your details, and verify your email address. You'll be ready to start learning in just a few minutes.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    I forgot my password. How can I reset it?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Go to the <a href="/frontend/login.php">login page</a> and click "Forgot password?" Enter your email address, and we'll send you a secure link to reset your password. Check your spam folder if you don't see the email within a few minutes.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Can I change my email address?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Yes! Go to your <a href="/frontend/settings.php">account settings</a> and update your email address. You'll need to verify the new email address before the change takes effect.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    How do I delete my account?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>You can deactivate or delete your account from the Account Management section in your <a href="/frontend/settings.php">settings</a>. Please note that account deletion is permanent and you'll lose access to all your course progress and certificates.</p>
                </div>
            </div>
        </div>

        <!-- Courses & Learning -->
        <div class="faq-category" id="courses" style="display: none;">
            <h2>Courses & Learning</h2>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    How do I enroll in a course?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Browse our <a href="/frontend/courses.php">course catalog</a>, find a course you're interested in, click on it to view details, and then click "Enroll Now". For free courses, enrollment is instant. For paid courses, you'll complete the payment process first.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Are certificates provided upon course completion?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Yes! Upon successfully completing a course, you'll receive a certificate of completion. You can view and download your certificates from the <a href="/frontend/certificates.php">Certificates page</a>. Certificates include a unique verification code for authenticity.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Can I access courses offline?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>While most course content requires an internet connection, some downloadable resources like PDFs and slides can be saved for offline viewing. Video lessons and interactive content need to be accessed online.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    How long do I have to complete a course?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Once enrolled, you have lifetime access to course materials. Take your time to learn at your own pace. There are no deadlines or time limits.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Can I get a refund if I'm not satisfied?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Yes, we offer a 30-day money-back guarantee. If you're not satisfied with a course, contact our support team within 30 days of purchase for a full refund, provided you haven't completed more than 25% of the course.</p>
                </div>
            </div>
        </div>

        <!-- Payments & Billing -->
        <div class="faq-category" id="payments" style="display: none;">
            <h2>Payments & Billing</h2>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    What payment methods do you accept?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>We accept all major credit cards (Visa, MasterCard, American Express, Discover), PayPal, and other secure payment methods. All transactions are processed securely through encrypted connections.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Is my payment information secure?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Absolutely! We use industry-standard SSL encryption to protect your payment information. We never store your credit card details on our servers. All payments are processed by secure, PCI-compliant payment processors.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Can I get an invoice for my purchase?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Yes, you'll receive an email receipt immediately after purchase. If you need a formal invoice, contact our support team with your order details, and we'll provide one for you.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Do you offer student discounts?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>We occasionally run promotional campaigns with special pricing. Subscribe to our newsletter or follow our social media to stay updated on discounts and special offers.</p>
                </div>
            </div>
        </div>

        <!-- Technical Support -->
        <div class="faq-category" id="technical" style="display: none;">
            <h2>Technical Support</h2>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    What browsers are supported?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>We support the latest versions of Chrome, Firefox, Safari, and Edge. For the best experience, we recommend using a modern browser with JavaScript enabled and a stable internet connection.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Videos won't play. What should I do?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Try these steps: 1) Refresh the page, 2) Clear your browser cache, 3) Disable browser extensions temporarily, 4) Try a different browser, 5) Check your internet connection. If issues persist, contact our support team.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    Can I access the platform on mobile devices?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Yes! Our platform is fully responsive and works great on smartphones and tablets. You can learn on the go using any mobile browser.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    My progress isn't saving. What should I do?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Ensure you're logged in and have a stable internet connection. Your progress should save automatically. If it's not working, try refreshing the page or logging out and back in. Contact support if the issue continues.</p>
                </div>
            </div>
        </div>

        <!-- For Instructors -->
        <div class="faq-category" id="instructors" style="display: none;">
            <h2>For Instructors</h2>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    How do I become an instructor?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Contact our team to express your interest in becoming an instructor. We'll review your credentials and teaching experience. Once approved, you'll get access to our instructor dashboard to create and manage courses.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    How much can I earn as an instructor?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Instructor earnings depend on course sales and our revenue sharing model. Typically, instructors earn 70% of net course sales. We also offer performance bonuses for top-performing courses.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    What tools are available for course creation?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Our instructor dashboard provides tools for creating courses, uploading videos, adding quizzes, managing student progress, and analyzing course performance. We also offer course creation templates and best practices.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleAnswer(this)">
                    How do I get paid?
                    <span class="faq-icon">+</span>
                </button>
                <div class="faq-answer">
                    <p>Instructor payments are processed monthly via bank transfer or PayPal. You'll receive detailed statements showing course sales, earnings, and any applicable fees.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Still need help? -->
    <div class="faq-contact">
        <h2>Still need help?</h2>
        <p>Can't find what you're looking for? Our support team is here to help.</p>
        <div class="contact-options">
            <a href="/frontend/contact.php" class="contact-btn">
                <span class="btn-icon">💬</span>
                Contact Support
            </a>
            <a href="/frontend/help.php" class="contact-btn">
                <span class="btn-icon">📚</span>
                Browse Help Articles
            </a>
        </div>
    </div>
</div>

<style>
.faq-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.faq-header {
    text-align: center;
    margin-bottom: 3rem;
}

.faq-header h1 {
    font-size: 2.5rem;
    color: #1e293b;
    margin-bottom: 1rem;
    font-weight: 700;
}

.faq-header p {
    font-size: 1.1rem;
    color: #64748b;
}

.faq-search {
    margin-bottom: 2rem;
}

.search-box {
    display: flex;
    gap: 0.5rem;
    max-width: 500px;
    margin: 0 auto;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
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

.faq-categories {
    margin-bottom: 3rem;
}

.category-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: center;
    background: white;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.tab-btn:hover,
.tab-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.faq-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border: 1px solid #e2e8f0;
    margin-bottom: 3rem;
}

.faq-category {
    display: none;
}

.faq-category.active {
    display: block;
}

.faq-category h2 {
    color: #1e293b;
    font-size: 1.75rem;
    margin-bottom: 2rem;
    font-weight: 600;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.faq-item {
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.faq-question {
    width: 100%;
    padding: 1.5rem;
    background: #f8fafc;
    border: none;
    text-align: left;
    cursor: pointer;
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.2s;
}

.faq-question:hover {
    background: #f1f5f9;
}

.faq-icon {
    font-size: 1.5rem;
    color: #3b82f6;
    font-weight: 300;
    transition: transform 0.2s;
}

.faq-item.active .faq-icon {
    transform: rotate(45deg);
}

.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: white;
}

.faq-item.active .faq-answer {
    max-height: 500px;
}

.faq-answer p {
    padding: 1.5rem;
    color: #374151;
    line-height: 1.6;
    margin: 0;
}

.faq-answer a {
    color: #3b82f6;
    text-decoration: none;
}

.faq-answer a:hover {
    text-decoration: underline;
}

.faq-contact {
    text-align: center;
    background: #f8fafc;
    border-radius: 16px;
    padding: 3rem 2rem;
}

.faq-contact h2 {
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 1rem;
    font-weight: 600;
}

.faq-contact p {
    color: #64748b;
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.contact-options {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.contact-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: white;
    color: #374151;
    text-decoration: none;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s;
}

.contact-btn:hover {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
    transform: translateY(-2px);
}

.btn-icon {
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .faq-container {
        padding: 1rem;
    }
    
    .faq-header h1 {
        font-size: 2rem;
    }
    
    .search-box {
        flex-direction: column;
        gap: 1rem;
    }
    
    .category-tabs {
        flex-direction: column;
    }
    
    .tab-btn {
        text-align: center;
    }
    
    .faq-content {
        padding: 1rem;
    }
    
    .contact-options {
        flex-direction: column;
        align-items: center;
    }
    
    .contact-btn {
        width: 200px;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .faq-header h1 {
        font-size: 1.75rem;
    }
    
    .faq-contact h2 {
        font-size: 1.5rem;
    }
    
    .faq-question {
        font-size: 1rem;
        padding: 1rem;
    }
    
    .faq-answer p {
        padding: 1rem;
    }
}

/* Animation for smooth transitions */
.faq-item {
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
</style>

<script>
// FAQ functionality
function toggleAnswer(button) {
    const faqItem = button.parentElement;
    const isActive = faqItem.classList.contains('active');
    
    // Close all other FAQ items in the same category
    const allFaqItems = faqItem.parentElement.querySelectorAll('.faq-item');
    allFaqItems.forEach(item => {
        if (item !== faqItem) {
            item.classList.remove('active');
        }
    });
    
    // Toggle current item
    if (isActive) {
        faqItem.classList.remove('active');
    } else {
        faqItem.classList.add('active');
    }
}

function showCategory(category) {
    // Update tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Show/hide categories
    const categories = document.querySelectorAll('.faq-category');
    categories.forEach(cat => {
        cat.classList.remove('active');
        cat.style.display = 'none';
    });
    
    if (category === 'all') {
        // Show all categories
        categories.forEach(cat => {
            cat.style.display = 'block';
        });
    } else {
        // Show specific category
        const targetCategory = document.getElementById(category);
        if (targetCategory) {
            targetCategory.style.display = 'block';
            targetCategory.classList.add('active');
        }
    }
}

function searchFAQ() {
    const searchTerm = document.getElementById('faq-search').value.toLowerCase();
    if (searchTerm.trim()) {
        // In a real implementation, this would search through FAQ content
        alert(`Searching for: "${searchTerm}"\n\nThis would filter and highlight relevant FAQ items.`);
    }
}

// Allow Enter key in search box
document.getElementById('faq-search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchFAQ();
    }
});

// Initialize with all questions visible
document.addEventListener('DOMContentLoaded', function() {
    showCategory('all');
});
</script>

<?php
require_once __DIR__ . '/../shared/templates/footer.php';
?>