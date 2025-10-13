<?php
require_once '../../backend/config/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Preferences - FowarD LMS</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/email-preferences.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="preferences-header">
            <h1>Email Preferences</h1>
            <p>Manage your email notification settings</p>
        </div>

        <div class="preferences-container">
            <form id="preferencesForm">
                <div class="preference-section">
                    <h2>General Settings</h2>
                    
                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="emailEnabled">Enable Email Notifications</label>
                            <p class="preference-description">Receive email notifications for important updates</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="emailEnabled" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="digestFrequency">Email Digest</label>
                            <p class="preference-description">Receive a summary of notifications instead of individual emails</p>
                        </div>
                        <select id="digestFrequency" class="form-select">
                            <option value="none">None - Send immediately</option>
                            <option value="daily">Daily Digest</option>
                            <option value="weekly">Weekly Digest</option>
                        </select>
                    </div>
                </div>

                <div class="preference-section">
                    <h2>Notification Types</h2>
                    
                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyNewMessage">New Messages</label>
                            <p class="preference-description">When you receive a new message</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyNewMessage" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyAssignmentDue">Assignment Reminders</label>
                            <p class="preference-description">Reminders when assignments are due soon</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyAssignmentDue" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyQuizAvailable">Quiz Notifications</label>
                            <p class="preference-description">When new quizzes become available</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyQuizAvailable" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyGradePosted">Grade Notifications</label>
                            <p class="preference-description">When grades are posted</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyGradePosted" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyCourseUpdate">Course Updates</label>
                            <p class="preference-description">When courses you're enrolled in are updated</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyCourseUpdate" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyForumReply">Forum Replies</label>
                            <p class="preference-description">When someone replies to your forum posts</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyForumReply" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyAnnouncement">Announcements</label>
                            <p class="preference-description">Important announcements from instructors and admins</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyAnnouncement" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="notifyCertificate">Certificates</label>
                            <p class="preference-description">When you earn a certificate</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="notifyCertificate" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="preference-section">
                    <h2>Marketing</h2>
                    
                    <div class="preference-item">
                        <div class="preference-info">
                            <label for="marketingEmails">Marketing Emails</label>
                            <p class="preference-description">Receive updates about new features and courses</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="marketingEmails">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/email-preferences.js"></script>
</body>
</html>
