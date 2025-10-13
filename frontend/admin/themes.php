<?php
require_once '../../backend/includes/auth.php';
$user = requireAuth();

if ($user['role'] !== 'admin') {
    header('Location: /frontend/index.php');
    exit;
}

$pageTitle = 'Theme Manager';
include '../../shared/templates/header.php';
?>

<link rel="stylesheet" href="../assets/css/themes.css">

<div class="themes-container">
    <div class="themes-header">
        <h1>Theme Manager</h1>
        <button class="btn btn-primary" onclick="showCreateThemeModal()">
            <i class="fas fa-plus"></i> Create Theme
        </button>
    </div>

    <div class="themes-grid" id="themesGrid"></div>
</div>

 Create/Edit Theme Modal 
<div id="themeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="themeModalTitle">Create Theme</h2>
            <button class="close-btn" onclick="closeThemeModal()">&times;</button>
        </div>
        <form id="themeForm" onsubmit="saveTheme(event)">
            <input type="hidden" id="themeId">
            
            <div class="form-group">
                <label for="themeName">Theme Name *</label>
                <input type="text" id="themeName" required>
            </div>

            <div class="form-group">
                <label for="themeDescription">Description</label>
                <textarea id="themeDescription" rows="2"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="primaryColor">Primary Color</label>
                    <input type="color" id="primaryColor" value="#3b82f6">
                </div>

                <div class="form-group">
                    <label for="secondaryColor">Secondary Color</label>
                    <input type="color" id="secondaryColor" value="#10b981">
                </div>

                <div class="form-group">
                    <label for="accentColor">Accent Color</label>
                    <input type="color" id="accentColor" value="#f59e0b">
                </div>
            </div>

            <div class="form-group">
                <label for="fontFamily">Font Family</label>
                <select id="fontFamily">
                    <option value="Inter">Inter</option>
                    <option value="Roboto">Roboto</option>
                    <option value="Open Sans">Open Sans</option>
                    <option value="Lato">Lato</option>
                    <option value="Montserrat">Montserrat</option>
                </select>
            </div>

            <div class="theme-preview">
                <h4>Preview</h4>
                <div class="preview-box" id="themePreview">
                    <div class="preview-header">Header</div>
                    <div class="preview-content">
                        <button class="preview-btn-primary">Primary Button</button>
                        <button class="preview-btn-secondary">Secondary Button</button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeThemeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Theme</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/themes.js"></script>

<?php include '../../shared/templates/footer.php'; ?>
