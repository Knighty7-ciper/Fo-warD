</main>

<footer class="main-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Forward LMS</h3>
                <p>Community-driven online learning platform for students, teachers, and administrators.</p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="icon-facebook"></i></a>
                    <a href="#" aria-label="Twitter"><i class="icon-twitter"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="icon-linkedin"></i></a>
                    <a href="#" aria-label="GitHub"><i class="icon-github"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/frontend/courses.php">Courses</a></li>
                    <li><a href="/frontend/about.php">About Us</a></li>
                    <li><a href="/frontend/contact.php">Contact</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Resources</h4>
                <ul>
                    <li><a href="/frontend/help.php">Help Center</a></li>
                    <li><a href="/frontend/faq.php">FAQ</a></li>
                    <li><a href="/docs/user-manual.pdf" target="_blank">User Manual</a></li>
                    <li><a href="/frontend/terms.php">Terms of Service</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Legal</h4>
                <ul>
                    <li><a href="/frontend/privacy.php">Privacy Policy</a></li>
                    <li><a href="/frontend/terms.php">Terms & Conditions</a></li>
                    <li><a href="/frontend/cookies.php">Cookie Policy</a></li>
                    <li><a href="/frontend/accessibility.php">Accessibility</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Forward LMS. All rights reserved.</p>
            <p>Version 1.0.0 | Built with PHP & Supabase</p>
        </div>
    </div>
</footer>

<?php if (isset($additional_js)): ?>
    <?php foreach ($additional_js as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<script src="/frontend/assets/js/script.js"></script>

</body>
</html>
