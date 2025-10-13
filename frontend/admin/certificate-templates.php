<?php
require_once '../../backend/config/auth.php';
requireLogin();
requireRole(['admin', 'teacher']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Templates - FowarD LMS</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/certificate-templates.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="templates-header">
            <h1>Certificate Templates</h1>
            <button class="btn btn-primary" onclick="showCreateTemplate()">Create Template</button>
        </div>

        <div class="templates-grid" id="templatesGrid">
            <div class="loading">Loading templates...</div>
        </div>
    </div>

    <!-- Template Designer Modal -->
    <div id="templateModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="modalTitle">Create Certificate Template</h2>
                <button class="close-btn" onclick="closeModal('templateModal')">&times;</button>
            </div>
            <div class="template-designer">
                <div class="designer-sidebar">
                    <form id="templateForm">
                        <input type="hidden" id="templateId">
                        
                        <div class="form-group">
                            <label for="templateName">Template Name *</label>
                            <input type="text" id="templateName" required>
                        </div>

                        <div class="form-group">
                            <label for="templateDescription">Description</label>
                            <textarea id="templateDescription" rows="2"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="orientation">Orientation</label>
                            <select id="orientation">
                                <option value="landscape">Landscape</option>
                                <option value="portrait">Portrait</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="backgroundColor">Background Color</label>
                            <input type="color" id="backgroundColor" value="#FFFFFF">
                        </div>

                        <div class="form-group">
                            <label for="borderStyle">Border Style</label>
                            <select id="borderStyle">
                                <option value="none">None</option>
                                <option value="simple">Simple</option>
                                <option value="elegant">Elegant</option>
                                <option value="modern">Modern</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="borderColor">Border Color</label>
                            <input type="color" id="borderColor" value="#000000">
                        </div>

                        <div class="form-group">
                            <label for="titleText">Title Text</label>
                            <input type="text" id="titleText" value="Certificate of Completion">
                        </div>

                        <div class="form-group">
                            <label for="titleFontSize">Title Font Size</label>
                            <input type="number" id="titleFontSize" value="36" min="20" max="60">
                        </div>

                        <div class="form-group">
                            <label for="titleColor">Title Color</label>
                            <input type="color" id="titleColor" value="#000000">
                        </div>

                        <div class="form-group">
                            <label for="bodyTemplate">Body Text *</label>
                            <textarea id="bodyTemplate" rows="4" required>This is to certify that <strong>{{student_name}}</strong> has successfully completed the course <strong>{{course_title}}</strong> on {{completion_date}}.</textarea>
                            <small>Available variables: {{student_name}}, {{course_title}}, {{completion_date}}, {{certificate_number}}, {{instructor_name}}</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="isDefault">
                                Set as Default Template
                            </label>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('templateModal')">Cancel</button>
                            <button type="button" class="btn btn-secondary" onclick="previewTemplate()">Preview</button>
                            <button type="submit" class="btn btn-primary">Save Template</button>
                        </div>
                    </form>
                </div>
                <div class="designer-preview">
                    <h3>Live Preview</h3>
                    <div class="preview-container" id="previewContainer">
                        <div class="certificate-preview" id="certificatePreview"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/certificate-templates.js"></script>
</body>
</html>
