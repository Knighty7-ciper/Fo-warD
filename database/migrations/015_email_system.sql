-- Email Notification System Migration
-- Creates tables for email queue, templates, and user preferences

CREATE TABLE IF NOT EXISTS email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(500) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    template_id INT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'sending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    error_message TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_recipient (recipient_email)
);

CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    subject VARCHAR(500) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    variables JSON,
    category ENUM('system', 'course', 'assignment', 'quiz', 'message', 'notification', 'marketing') DEFAULT 'system',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email_enabled BOOLEAN DEFAULT TRUE,
    digest_frequency ENUM('none', 'daily', 'weekly') DEFAULT 'none',
    notify_new_message BOOLEAN DEFAULT TRUE,
    notify_assignment_due BOOLEAN DEFAULT TRUE,
    notify_quiz_available BOOLEAN DEFAULT TRUE,
    notify_grade_posted BOOLEAN DEFAULT TRUE,
    notify_course_update BOOLEAN DEFAULT TRUE,
    notify_forum_reply BOOLEAN DEFAULT TRUE,
    notify_announcement BOOLEAN DEFAULT TRUE,
    notify_certificate BOOLEAN DEFAULT TRUE,
    marketing_emails BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_prefs (user_id)
);

CREATE TABLE IF NOT EXISTS email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    queue_id INT,
    user_id INT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    status ENUM('sent', 'failed', 'bounced') NOT NULL,
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id) REFERENCES email_queue(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_sent_at (sent_at)
);

-- Insert default email templates
INSERT INTO email_templates (name, subject, body_html, body_text, variables, category) VALUES
('welcome', 'Welcome to FowarD LMS!', 
'<h1>Welcome {{name}}!</h1><p>Thank you for joining FowarD LMS. Your account has been successfully created.</p><p>You can now log in and start exploring our courses.</p><p><a href="{{login_url}}">Login Now</a></p>',
'Welcome {{name}}! Thank you for joining FowarD LMS. Your account has been successfully created. You can now log in at {{login_url}}',
'["name", "email", "login_url"]', 'system'),

('password_reset', 'Password Reset Request',
'<h1>Password Reset</h1><p>Hi {{name}},</p><p>We received a request to reset your password. Click the link below to reset it:</p><p><a href="{{reset_url}}">Reset Password</a></p><p>This link will expire in 1 hour.</p><p>If you did not request this, please ignore this email.</p>',
'Hi {{name}}, We received a request to reset your password. Visit {{reset_url}} to reset it. This link expires in 1 hour.',
'["name", "reset_url"]', 'system'),

('new_message', 'New Message from {{sender_name}}',
'<h1>New Message</h1><p>Hi {{recipient_name}},</p><p>You have received a new message from <strong>{{sender_name}}</strong>:</p><blockquote>{{message_preview}}</blockquote><p><a href="{{message_url}}">Read Full Message</a></p>',
'Hi {{recipient_name}}, You have a new message from {{sender_name}}. Read it at {{message_url}}',
'["recipient_name", "sender_name", "message_preview", "message_url"]', 'message'),

('assignment_due', 'Assignment Due Soon: {{assignment_title}}',
'<h1>Assignment Reminder</h1><p>Hi {{student_name}},</p><p>This is a reminder that your assignment <strong>{{assignment_title}}</strong> is due on <strong>{{due_date}}</strong>.</p><p><a href="{{assignment_url}}">View Assignment</a></p>',
'Hi {{student_name}}, Reminder: {{assignment_title}} is due on {{due_date}}. View at {{assignment_url}}',
'["student_name", "assignment_title", "due_date", "assignment_url"]', 'assignment'),

('grade_posted', 'Grade Posted for {{item_title}}',
'<h1>New Grade Posted</h1><p>Hi {{student_name}},</p><p>Your grade for <strong>{{item_title}}</strong> has been posted.</p><p>Grade: <strong>{{grade}}</strong></p><p>Feedback: {{feedback}}</p><p><a href="{{grades_url}}">View All Grades</a></p>',
'Hi {{student_name}}, Your grade for {{item_title}} has been posted: {{grade}}. View at {{grades_url}}',
'["student_name", "item_title", "grade", "feedback", "grades_url"]', 'notification'),

('course_enrollment', 'Enrolled in {{course_title}}',
'<h1>Course Enrollment Confirmed</h1><p>Hi {{student_name}},</p><p>You have been successfully enrolled in <strong>{{course_title}}</strong>.</p><p><a href="{{course_url}}">Go to Course</a></p>',
'Hi {{student_name}}, You are now enrolled in {{course_title}}. Access at {{course_url}}',
'["student_name", "course_title", "course_url"]', 'course'),

('quiz_available', 'New Quiz Available: {{quiz_title}}',
'<h1>New Quiz Available</h1><p>Hi {{student_name}},</p><p>A new quiz <strong>{{quiz_title}}</strong> is now available in {{course_title}}.</p><p>Due: {{due_date}}</p><p><a href="{{quiz_url}}">Take Quiz</a></p>',
'Hi {{student_name}}, New quiz available: {{quiz_title}} in {{course_title}}. Due {{due_date}}. Take it at {{quiz_url}}',
'["student_name", "quiz_title", "course_title", "due_date", "quiz_url"]', 'quiz'),

('certificate_issued', 'Certificate Issued for {{course_title}}',
'<h1>Congratulations!</h1><p>Hi {{student_name}},</p><p>You have successfully completed <strong>{{course_title}}</strong> and earned your certificate!</p><p><a href="{{certificate_url}}">Download Certificate</a></p>',
'Congratulations {{student_name}}! You completed {{course_title}}. Download your certificate at {{certificate_url}}',
'["student_name", "course_title", "certificate_url"]', 'certificate'),

('announcement', '{{announcement_title}}',
'<h1>{{announcement_title}}</h1><p>{{announcement_body}}</p><p>Posted by: {{author_name}}</p><p><a href="{{announcement_url}}">View Announcement</a></p>',
'{{announcement_title}} - {{announcement_body}} Posted by {{author_name}}. View at {{announcement_url}}',
'["announcement_title", "announcement_body", "author_name", "announcement_url"]', 'notification');
