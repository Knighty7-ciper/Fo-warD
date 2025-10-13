<?php
session_start();
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/config/auth.php';

Auth::requireAuth();

$current_user = Auth::getUser();

$page_title = 'Edit Profile - Forward LMS';
$body_class = 'profile-edit-page';
$additional_css = ['/frontend/assets/css/profile-edit.css'];
$additional_js = ['/frontend/assets/js/profile-edit.js'];

require_once __DIR__ . '/../shared/templates/header.php';
?>

<div class="edit-profile-container">
    <header class="page-header">
        <h1>Edit Profile</h1>
        <a href="/frontend/profile.php" class="btn btn-secondary">View Profile</a>
    </header>

    <div class="edit-sections">
         Basic Information 
        <section class="edit-section">
            <h2>Basic Information</h2>
            <form id="basicInfoForm" class="profile-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" disabled>
                        <small>Email cannot be changed</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="City, Country">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select...</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer_not_to_say">Prefer not to say</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </section>

         Avatar Upload 
        <section class="edit-section">
            <h2>Profile Picture</h2>
            <div class="avatar-upload-section">
                <div class="current-avatar">
                    <img src="/frontend/assets/images/default-avatar.png" alt="Current Avatar" id="currentAvatar">
                </div>
                <form id="avatarUploadForm" class="avatar-form">
                    <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('avatarInput').click()">Choose Image</button>
                    <button type="submit" class="btn btn-primary" id="uploadAvatarBtn" style="display: none;">Upload</button>
                    <small>Max size: 5MB. Formats: JPG, PNG, GIF, WebP</small>
                </form>
            </div>
        </section>

         Social Links 
        <section class="edit-section">
            <h2>Social Links</h2>
            <form id="socialLinksForm" class="profile-form">
                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="url" id="website" name="website" placeholder="https://yourwebsite.com">
                </div>
                <div class="form-group">
                    <label for="linkedin">LinkedIn</label>
                    <input type="url" id="linkedin" name="linkedin" placeholder="https://linkedin.com/in/username">
                </div>
                <div class="form-group">
                    <label for="twitter">Twitter</label>
                    <input type="url" id="twitter" name="twitter" placeholder="https://twitter.com/username">
                </div>
                <div class="form-group">
                    <label for="github">GitHub</label>
                    <input type="url" id="github" name="github" placeholder="https://github.com/username">
                </div>
                <button type="submit" class="btn btn-primary">Save Social Links</button>
            </form>
        </section>

         Skills 
        <section class="edit-section">
            <h2>Skills</h2>
            <div class="skills-manager">
                <form id="addSkillForm" class="inline-form">
                    <input type="text" name="skill_name" placeholder="Skill name" required>
                    <select name="proficiency_level">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                        <option value="expert">Expert</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Add Skill</button>
                </form>
                <div class="skills-list" id="editSkillsList"></div>
            </div>
        </section>

         Education 
        <section class="edit-section">
            <h2>Education</h2>
            <button type="button" class="btn btn-secondary" onclick="showAddEducationModal()">Add Education</button>
            <div class="education-list" id="editEducationList"></div>
        </section>

         Experience 
        <section class="edit-section">
            <h2>Work Experience</h2>
            <button type="button" class="btn btn-secondary" onclick="showAddExperienceModal()">Add Experience</button>
            <div class="experience-list" id="editExperienceList"></div>
        </section>

         Privacy Settings 
        <section class="edit-section">
            <h2>Privacy & Notifications</h2>
            <form id="privacyForm" class="profile-form">
                <div class="form-group">
                    <label for="profile_visibility">Profile Visibility</label>
                    <select id="profile_visibility" name="profile_visibility">
                        <option value="public">Public - Anyone can view</option>
                        <option value="connections_only">Connections Only</option>
                        <option value="private">Private - Only me</option>
                    </select>
                </div>
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" id="email_notifications" name="email_notifications">
                        <span>Email Notifications</span>
                    </label>
                </div>
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" id="push_notifications" name="push_notifications">
                        <span>Push Notifications</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </section>
    </div>
</div>

 Add Education Modal 
<div id="addEducationModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addEducationModal')">&times;</span>
        <h2>Add Education</h2>
        <form id="addEducationForm">
            <div class="form-group">
                <label for="edu_institution">Institution *</label>
                <input type="text" id="edu_institution" name="institution" required>
            </div>
            <div class="form-group">
                <label for="edu_degree">Degree *</label>
                <input type="text" id="edu_degree" name="degree" required>
            </div>
            <div class="form-group">
                <label for="edu_field">Field of Study *</label>
                <input type="text" id="edu_field" name="field_of_study" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edu_start">Start Date</label>
                    <input type="date" id="edu_start" name="start_date">
                </div>
                <div class="form-group">
                    <label for="edu_end">End Date</label>
                    <input type="date" id="edu_end" name="end_date">
                </div>
            </div>
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" id="edu_current" name="is_current">
                    <span>Currently studying here</span>
                </label>
            </div>
            <div class="form-group">
                <label for="edu_description">Description</label>
                <textarea id="edu_description" name="description" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Education</button>
        </form>
    </div>
</div>

 Add Experience Modal 
<div id="addExperienceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addExperienceModal')">&times;</span>
        <h2>Add Work Experience</h2>
        <form id="addExperienceForm">
            <div class="form-group">
                <label for="exp_company">Company *</label>
                <input type="text" id="exp_company" name="company" required>
            </div>
            <div class="form-group">
                <label for="exp_position">Position *</label>
                <input type="text" id="exp_position" name="position" required>
            </div>
            <div class="form-group">
                <label for="exp_location">Location</label>
                <input type="text" id="exp_location" name="location">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="exp_start">Start Date</label>
                    <input type="date" id="exp_start" name="start_date">
                </div>
                <div class="form-group">
                    <label for="exp_end">End Date</label>
                    <input type="date" id="exp_end" name="end_date">
                </div>
            </div>
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" id="exp_current" name="is_current">
                    <span>Currently working here</span>
                </label>
            </div>
            <div class="form-group">
                <label for="exp_description">Description</label>
                <textarea id="exp_description" name="description" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Experience</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../shared/templates/footer.php'; ?>
