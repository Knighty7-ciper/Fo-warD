<?php
/**
 * Folder Management Interface
 * Phase 5: Advanced Content Management System
 * Features: Create, organize, and manage file folders with drag-and-drop
 */

require_once '../config/database.php';
require_once '../config/auth.php';

$user = requireAuth();
if ($user['role'] !== 'teacher') {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied']));
}

// Get teacher's courses for the folder creation
$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folder Management - FowarD LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
        }

        .folder-management {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
        }

        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .content-area {
            padding: 30px;
        }

        .breadcrumb-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .breadcrumb {
            margin: 0;
            font-size: 0.9rem;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: var(--dark-color);
            font-weight: 600;
        }

        .toolbar-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
            display: flex;
            justify-content: between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .folder-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .folder-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
        }

        .folder-card.drag-over {
            border-color: var(--secondary-color);
            background: rgba(46, 204, 113, 0.05);
        }

        .folder-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .folder-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.1);
            opacity: 0.1;
        }

        .folder-icon {
            font-size: 3rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .folder-name {
            font-weight: 600;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
            line-height: 1.3;
        }

        .folder-info {
            padding: 20px;
        }

        .folder-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .folder-stats {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .folder-actions {
            display: flex;
            gap: 10px;
        }

        .btn-folder {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-open {
            background: var(--primary-color);
            color: white;
        }

        .btn-edit {
            background: var(--warning-color);
            color: white;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-folder:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .dropzone-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(46, 204, 113, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .dropzone-overlay.show {
            display: flex;
        }

        .new-folder-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: 2px dashed rgba(255,255,255,0.3);
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .new-folder-card:hover {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            transform: translateY(-3px);
        }

        .new-folder-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .search-filter {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-box input {
            padding-left: 40px;
            border-radius: 25px;
            border: 2px solid #e9ecef;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .filter-select {
            min-width: 150px;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="folder-management">
        <div class="container">
            <div class="main-container">
                <!-- Header Section -->
                <div class="header-section">
                    <div class="header-content">
                        <h1 class="mb-0">
                            <i class="bi bi-folder"></i>
                            Folder Management
                        </h1>
                        <p class="mb-0 mt-2 opacity-90">Organize your course materials with folders</p>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="content-area">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb-section">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb" id="breadcrumb">
                                <li class="breadcrumb-item active">Root</li>
                            </ol>
                        </nav>
                    </div>

                    <!-- Toolbar -->
                    <div class="toolbar-section">
                        <div class="search-filter">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search folders...">
                            </div>
                            <select class="form-select filter-select" id="courseFilter">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" onclick="createNewFolder()">
                                <i class="bi bi-plus-circle"></i>
                                New Folder
                            </button>
                            <button class="btn btn-outline-secondary" onclick="refreshFolders()">
                                <i class="bi bi-arrow-clockwise"></i>
                                Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Folders Grid -->
                    <div id="foldersContainer">
                        <div class="folder-grid" id="foldersGrid">
                            <!-- Folders will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dropzone Overlay -->
    <div class="dropzone-overlay" id="dropzoneOverlay">
        <div class="text-center">
            <i class="bi bi-cloud-upload" style="font-size: 4rem; margin-bottom: 20px;"></i>
            <div>Drop files here to upload to current folder</div>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal fade" id="createFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-folder-plus"></i>
                        Create New Folder
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createFolderForm">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="folderName" name="name" placeholder="Folder Name" required>
                            <label for="folderName">Folder Name</label>
                        </div>
                        <div class="form-floating">
                            <select class="form-select" id="folderCourse" name="course_id">
                                <option value="">No Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="folderCourse">Associated Course (Optional)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="folderPublic" name="is_public">
                            <label class="form-check-label" for="folderPublic">
                                Make folder publicly accessible
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="createFolderBtn">
                        <i class="bi bi-folder-plus"></i>
                        Create Folder
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Folder Modal -->
    <div class="modal fade" id="editFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i>
                        Edit Folder
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editFolderForm">
                        <input type="hidden" id="editFolderId" name="id">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="editFolderName" name="name" placeholder="Folder Name" required>
                            <label for="editFolderName">Folder Name</label>
                        </div>
                        <div class="form-floating">
                            <select class="form-select" id="editFolderParent" name="parent_id">
                                <option value="">Root Directory</option>
                            </select>
                            <label for="editFolderParent">Parent Folder</label>
                        </div>
                        <div class="form-floating">
                            <select class="form-select" id="editFolderCourse" name="course_id">
                                <option value="">No Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="editFolderCourse">Associated Course</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editFolderPublic" name="is_public">
                            <label class="form-check-label" for="editFolderPublic">
                                Make folder publicly accessible
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateFolderBtn">
                        <i class="bi bi-save"></i>
                        Update Folder
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        // Global state
        let currentParentId = null;
        let currentFolders = [];
        let breadcrumb = [{ id: null, name: 'Root' }];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadFolders();
            setupEventListeners();
            setupDragAndDrop();
        });

        function setupEventListeners() {
            // Search functionality
            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    loadFolders();
                }, 500);
            });

            // Course filter
            document.getElementById('courseFilter').addEventListener('change', loadFolders);

            // Create folder form
            document.getElementById('createFolderBtn').addEventListener('click', createFolder);
            document.getElementById('updateFolderBtn').addEventListener('click', updateFolder);

            // Clear forms when modals are hidden
            document.getElementById('createFolderModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('createFolderForm').reset();
            });

            document.getElementById('editFolderModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('editFolderForm').reset();
            });
        }

        function setupDragAndDrop() {
            // Handle drag and drop for file uploads
            let dragCounter = 0;

            document.addEventListener('dragenter', function(e) {
                e.preventDefault();
                dragCounter++;
                if (dragCounter === 1) {
                    showDropzoneOverlay();
                }
            });

            document.addEventListener('dragleave', function(e) {
                e.preventDefault();
                dragCounter--;
                if (dragCounter === 0) {
                    hideDropzoneOverlay();
                }
            });

            document.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            document.addEventListener('drop', function(e) {
                e.preventDefault();
                dragCounter = 0;
                hideDropzoneOverlay();
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    uploadFilesToCurrentFolder(files);
                }
            });
        }

        function showDropzoneOverlay() {
            document.getElementById('dropzoneOverlay').classList.add('show');
        }

        function hideDropzoneOverlay() {
            document.getElementById('dropzoneOverlay').classList.remove('show');
        }

        async function loadFolders() {
            try {
                const params = new URLSearchParams({
                    parent_id: currentParentId || '',
                    course_id: document.getElementById('courseFilter').value,
                    q: document.getElementById('searchInput').value
                });

                const response = await axios.get(`/api/folders.php?${params}`, {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
                });
                
                if (response.data.success) {
                    currentFolders = response.data.data;
                    renderFolders();
                    updateBreadcrumb();
                }
            } catch (error) {
                console.error('Failed to load folders:', error);
                showToast('error', 'Failed to load folders');
            }
        }

        function renderFolders() {
            const container = document.getElementById('foldersGrid');
            
            if (currentFolders.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-folder-x"></i>
                            <h4>No folders found</h4>
                            <p>Create your first folder to get started!</p>
                        </div>
                    </div>
                `;
                return;
            }

            // Create folder cards
            const folderCards = currentFolders.map(folder => `
                <div class="folder-card" data-folder-id="${folder.id}" ondblclick="openFolder(${folder.id})">
                    <div class="folder-header">
                        <div class="folder-icon">
                            <i class="bi bi-folder${folder.has_subfolders ? '-open' : ''}"></i>
                        </div>
                        <div class="folder-name" title="${folder.name}">${truncateText(folder.name, 20)}</div>
                    </div>
                    <div class="folder-info">
                        <div class="folder-meta">
                            <div class="folder-stats">
                                <div class="stat-item">
                                    <i class="bi bi-file-earmark"></i>
                                    <span>${folder.file_count}</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-folder"></i>
                                    <span>${folder.subfolder_count}</span>
                                </div>
                            </div>
                            ${folder.course_title ? `<small class="text-muted">${folder.course_title}</small>` : ''}
                        </div>
                        <div class="folder-actions">
                            <button class="btn-folder btn-open" onclick="openFolder(${folder.id})">
                                <i class="bi bi-arrow-right"></i> Open
                            </button>
                            ${folder.can_edit ? `
                                <button class="btn-folder btn-edit" onclick="editFolder(${folder.id})">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            ` : ''}
                            ${folder.can_delete ? `
                                <button class="btn-folder btn-delete" onclick="deleteFolder(${folder.id})">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');

            // Add new folder card
            const newFolderCard = `
                <div class="new-folder-card" onclick="createNewFolder()">
                    <div class="new-folder-icon">
                        <i class="bi bi-folder-plus"></i>
                    </div>
                    <h5>Create New Folder</h5>
                    <p class="mb-0">Click to create a new folder</p>
                </div>
            `;

            container.innerHTML = folderCards + newFolderCard;
        }

        function updateBreadcrumb() {
            const breadcrumbContainer = document.getElementById('breadcrumb');
            breadcrumbContainer.innerHTML = breadcrumb.map((item, index) => {
                if (index === breadcrumb.length - 1) {
                    return `<li class="breadcrumb-item active">${item.name}</li>`;
                } else {
                    return `<li class="breadcrumb-item"><a href="#" onclick="navigateToFolder('${item.id || ''}')">${item.name}</a></li>`;
                }
            }).join('');
        }

        function navigateToFolder(folderId) {
            if (folderId === '' || folderId === 'null' || folderId === null) {
                currentParentId = null;
                breadcrumb = [{ id: null, name: 'Root' }];
            } else {
                const folder = findFolderInBreadcrumb(folderId);
                if (folder) {
                    const index = breadcrumb.findIndex(item => item.id == folderId);
                    breadcrumb = breadcrumb.slice(0, index + 1);
                    currentParentId = folderId;
                }
            }
            loadFolders();
        }

        function findFolderInBreadcrumb(folderId) {
            for (let folder of currentFolders) {
                if (folder.id == folderId) {
                    return folder;
                }
            }
            return null;
        }

        async function openFolder(folderId) {
            const folder = currentFolders.find(f => f.id == folderId);
            if (!folder || folder.subfolder_count === 0) {
                // Navigate to folder if it has content or is a leaf node
                currentParentId = folderId;
                
                // Add to breadcrumb if not already there
                const exists = breadcrumb.some(item => item.id == folderId);
                if (!exists) {
                    breadcrumb.push({ id: folderId, name: folder.name });
                }
                
                loadFolders();
            }
        }

        function createNewFolder() {
            const modal = new bootstrap.Modal(document.getElementById('createFolderModal'));
            document.getElementById('folderName').value = '';
            document.getElementById('folderCourse').value = '';
            document.getElementById('folderPublic').checked = false;
            modal.show();
        }

        async function createFolder() {
            const form = document.getElementById('createFolderForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            data.parent_id = currentParentId;

            try {
                const response = await axios.post('/api/folders.php', data, {
                    headers: { 
                        'Authorization': 'Bearer ' + localStorage.getItem('token'),
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.data.success) {
                    showToast('success', 'Folder created successfully');
                    bootstrap.Modal.getInstance(document.getElementById('createFolderModal')).hide();
                    loadFolders();
                } else {
                    showToast('error', response.data.error || 'Failed to create folder');
                }
            } catch (error) {
                showToast('error', 'Failed to create folder');
            }
        }

        async function editFolder(folderId) {
            try {
                const response = await axios.get(`/api/folders.php?action=get&id=${folderId}`, {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
                });
                
                if (response.data.success) {
                    const folder = response.data.data;
                    
                    // Load parent folders for selection
                    await loadParentFolders();
                    
                    const modal = new bootstrap.Modal(document.getElementById('editFolderModal'));
                    document.getElementById('editFolderId').value = folder.id;
                    document.getElementById('editFolderName').value = folder.name;
                    document.getElementById('editFolderParent').value = folder.parent_id || '';
                    document.getElementById('editFolderCourse').value = folder.course_id || '';
                    document.getElementById('editFolderPublic').checked = folder.is_public;
                    modal.show();
                }
            } catch (error) {
                showToast('error', 'Failed to load folder details');
            }
        }

        async function loadParentFolders() {
            try {
                const response = await axios.get('/api/folders.php?action=tree', {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
                });
                
                if (response.data.success) {
                    const folders = response.data.data;
                    const select = document.getElementById('editFolderParent');
                    
                    // Clear existing options except the first one
                    select.innerHTML = '<option value="">Root Directory</option>';
                    
                    // Add folder options
                    addFolderOptions(select, folders, 0);
                }
            } catch (error) {
                console.error('Failed to load parent folders:', error);
            }
        }

        function addFolderOptions(select, folders, level) {
            folders.forEach(folder => {
                const option = document.createElement('option');
                option.value = folder.id;
                option.textContent = '  '.repeat(level) + folder.name;
                select.appendChild(option);
                
                if (folder.children && folder.children.length > 0) {
                    addFolderOptions(select, folder.children, level + 1);
                }
            });
        }

        async function updateFolder() {
            const form = document.getElementById('editFolderForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Convert empty string to null for parent_id
            if (data.parent_id === '') {
                data.parent_id = null;
            }

            try {
                const response = await axios.put('/api/folders.php', data, {
                    headers: { 
                        'Authorization': 'Bearer ' + localStorage.getItem('token'),
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.data.success) {
                    showToast('success', 'Folder updated successfully');
                    bootstrap.Modal.getInstance(document.getElementById('editFolderModal')).hide();
                    loadFolders();
                } else {
                    showToast('error', response.data.error || 'Failed to update folder');
                }
            } catch (error) {
                showToast('error', 'Failed to update folder');
            }
        }

        async function deleteFolder(folderId) {
            if (!confirm('Are you sure you want to delete this folder? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await axios.delete('/api/folders.php', {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') },
                    data: { id: folderId }
                });
                
                if (response.data.success) {
                    showToast('success', 'Folder deleted successfully');
                    loadFolders();
                } else {
                    showToast('error', response.data.error || 'Failed to delete folder');
                }
            } catch (error) {
                showToast('error', 'Failed to delete folder');
            }
        }

        function refreshFolders() {
            loadFolders();
        }

        function uploadFilesToCurrentFolder(files) {
            // This would integrate with the file upload functionality
            // For now, we'll show a message
            showToast('info', `Ready to upload ${files.length} file(s) to current folder`);
        }

        function truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        }

        function showToast(type, message) {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} position-fixed`;
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>