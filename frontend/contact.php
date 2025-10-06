<?php
$page_title = 'Contact Us - Forward LMS';
$body_class = 'contact-page';
require_once __DIR__ . '/../shared/templates/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Contact Us</h1>
        <p>We'd love to hear from you</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-form-wrapper">
                <h2>Send us a message</h2>
                <form id="contact-form" class="contact-form">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="5" required class="form-control"></textarea>
                    </div>

                    <div id="error-message" class="error-message" style="display: none;"></div>
                    <div id="success-message" class="success-message" style="display: none;"></div>

                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>

            <div class="contact-info">
                <h2>Get in Touch</h2>

                <div class="info-item">
                    <h3>📧 Email</h3>
                    <p>support@forward.local</p>
                </div>

                <div class="info-item">
                    <h3>📞 Phone</h3>
                    <p>+1 (555) 123-4567</p>
                </div>

                <div class="info-item">
                    <h3>🏢 Address</h3>
                    <p>123 Learning Street<br>Education City, EC 12345</p>
                </div>

                <div class="info-item">
                    <h3>🕒 Support Hours</h3>
                    <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday - Sunday: Closed</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('contact-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const successDiv = document.getElementById('success-message');
    successDiv.textContent = 'Thank you for your message! We\'ll get back to you soon.';
    successDiv.style.display = 'block';
    this.reset();
});
</script>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>
