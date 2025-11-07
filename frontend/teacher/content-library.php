<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$user = requireAuth();
if ($user['role'] !== 'teacher') {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied']));
}

// Get teacher's courses for the filter dropdown
$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Library - FowarD LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
        }

        .content-library {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
        }

        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .content-area {
            padding: 30px;
        }

        .filter-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }

        .upload-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
        }

        .upload-section h3 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropzone {
            border: 3px dashed rgba(255,255,255,0.5);
            border-radius: 15px;
            padding: 40px 20px;
            text-align: center;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .dropzone:hover {
            border-color: rgba(255,255,255,0.8);
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }

        .dropzone.dz-drag-hover {
            border-color: #fff;
            background: rgba(255,255,255,0.2);
        }

        .upload-progress {
            display: none;
            margin-top: 20px;
        }

        .upload-item {
            background: rgba(255,255,255,0.9);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            color: #333;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .file-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .file-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .file-preview {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .file-preview i {
            font-size: 4rem;
            color: white;
            opacity: 0.8;
        }

        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-preview.video::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 0;
            height: 0;
            border-left: 30px solid white;
            border-top: 20px solid transparent;
            border-bottom: 20px solid transparent;
            opacity: 0.8;
        }

        .file-info {
            padding: 20px;
        }

        .file-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark-color);
            line-height: 1.3;
        }

        .file-meta {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }

        .file-size {
            background: var(--light-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-right: 5px;
        }

        .file-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action {
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

        .btn-view {
            background: var(--primary-color);
            color: white;
        }

        .btn-edit {
            background: var(--warning-color);
            color: white;
        }

        .btn-download {
            background: var(--secondary-color);
            color: white;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .search-box {
            position: relative;
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

        .file-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
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

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .form-floating {
            margin-bottom: 15px;
        }

        .folder-selector {
            max-height: 200px;
            overflow-y: auto;
        }

        .folder-item {
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .folder-item:hover {
            background: #f8f9fa;
        }

        .folder-item.selected {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="content-library">
        <div class="container">
            <div class="main-container">
                <!-- Header Section -->
                <div class="header-section">
                    <div class="header-content">
                        <h1 class="mb-0">
                            <i class="bi bi-collection"></i>
                            Content Library
                        </h1>
                        <p class="mb-0 mt-2 opacity-90">Manage your course materials and resources</p>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number" id="totalFiles">0</div>
                                <div class="stat-label">Total Files</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="totalSize">0 MB</div>
                                <div class="stat-label">Storage Used</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="totalDownloads">0</div>
                                <div class="stat-label">Downloads</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="content-area">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search files...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="courseFilter">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="typeFilter">
                                    <option value="">All Types</option>
                                    <option value="video">Videos</option>
                                    <option value="document">Documents</option>
                                    <option value="image">Images</option>
                                    <option value="audio">Audio</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="folderFilter">
                                    <option value="">All Folders</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="sortBy">
                                    <option value="created_at">Newest First</option>
                                    <option value="original_filename">Name A-Z</option>
                                    <option value="file_size">Size</option>
                                    <option value="downloads">Most Downloaded</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-outline-primary w-100" onclick="refreshFiles()">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Section -->
                    <div class="upload-section">
                        <h3>
                            <i class="bi bi-cloud-upload"></i>
                            Upload Files
                        </h3>
                        <form class="dropzone" id="fileDropzone">
                            <div class="dz-message">
                                <i class="bi bi-cloud-upload" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                <h4>Drop files here or click to upload</h4>
                                <p class="mb-0">Supports videos, documents, images, and audio files (Max 100MB)</p>
                            </div>
                        </form>
                        
                        <div class="upload-progress" id="uploadProgress">
                            <h5>Uploading Files...</h5>
                            <div id="uploadList"></div>
                        </div>
                    </div>

                    <!-- Files Grid -->
                    <div id="filesContainer">
                        <div class="file-grid" id="filesGrid">
                            <!-- Files will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- File Details Modal -->
    <div class="modal fade" id="fileDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-earmark"></i>
                        File Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="fileDetailsContent">
                    <!-- File details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editFileBtn">
                        <i class="bi bi-pencil"></i>
                        Edit Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit File Modal -->
    <div class="modal fade" id="editFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i>
                        Edit File Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editFileForm">
                        <input type="hidden" id="editFileId" name="id">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="editFileName" name="original_filename" placeholder="File Name">
                            <label for="editFileName">File Name</label>
                        </div>
                        <div class="form-floating">
                            <textarea class="form-control" id="editFileDescription" name="description" placeholder="Description" style="height: 100px"></textarea>
                            <label for="editFileDescription">Description</label>
                        </div>
                        <div class="form-floating">
                            <input type="text" class="form-control" id="editFileTags" name="tags" placeholder="Tags (comma separated)">
                            <label for="editFileTags">Tags</label>
                        </div>
                        <div class="form-floating">
                            <select class="form-select" id="editFileCourse" name="course_id">
                                <option value="">No Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="editFileCourse">Associated Course</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editFilePublic" name="is_public">
                            <label class="form-check-label" for="editFilePublic">
                                Make file publicly available
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveFileBtn">
                        <i class="bi bi-save"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        // Initialize Dropzone
        Dropzone.autoDiscover = false;
        const dropzone = new Dropzone("#fileDropzone", {
            url: "/api/files.php",
            method: "post",
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('token')
            },
            maxFilesize: 100, // MB
            acceptedFiles: ".mp4,.webm,.ogg,.avi,.mov,.pdf,.doc,.docx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png,.gif,.svg,.webp,.mp3,.wav,.ogg,.m4a",
            uploadMultiple: true,
            parallelUploads: 3,
            addRemoveLinks: true,
            previewsContainer: "#uploadList",
            
            init: function() {
                this.on("sending", function(file, xhr, formData) {
                    document.getElementById('uploadProgress').style.display = 'block';
                    
                    // Add form data
                    formData.append('description', document.getElementById('editFileDescription')?.value || '');
                    formData.append('tags', document.getElementById('editFileTags')?.value || '');
                    formData.append('is_public', document.getElementById('editFilePublic')?.checked || false);
                });
                
                this.on("uploadprogress", function(file, progress) {
                    const progressBar = file.previewElement.querySelector('.progress-bar');
                    if (progressBar) {
                        progressBar.style.width = progress + '%';
                    }
                });
                
                this.on("success", function(file, response) {
                    if (response.success) {
                        showToast('success', 'File uploaded successfully!');
                        refreshFiles();
                    } else {
                        showToast('error', response.error || 'Upload failed');
                    }
                });
                
                this.on("error", function(file, message) {
                    showToast('error', message || 'Upload failed');
                });
                
                this.on("queuecomplete", function() {
                    setTimeout(() => {
                        document.getElementById('uploadProgress').style.display = 'none';
                        this.removeAllFiles();
                    }, 2000);
                });
            }
        });

        // Global state
        let currentFiles = [];
        let currentFolders = [];
        let currentPage = 1;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadFileStats();
            loadFiles();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search functionality
            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    loadFiles();
                }, 500);
            });

            // Filter changes
            ['courseFilter', 'typeFilter', 'folderFilter', 'sortBy'].forEach(id => {
                document.getElementById(id).addEventListener('change', loadFiles);
            });

            // Edit file form
            document.getElementById('saveFileBtn').addEventListener('click', saveFileChanges);
        }

        async function loadFileStats() {
            try {
                const response = await axios.get('/api/files.php?action=stats', {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
                });
                
                if (response.data.success) {
                    const stats = response.data.data;
                    document.getElementById('totalFiles').textContent = stats.total_files;
                    document.getElementById('totalSize').textContent = stats.formatted_total_size;
                    
                    // Calculate total downloads from files data
                    document.getElementById('totalDownloads').textContent = '0'; // Will be updated with file load
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        async function loadFiles(page = 1) {
            try {
                const params = new URLSearchParams({
                    page: page,
                    limit: 20,
                    course_id: document.getElementById('courseFilter').value,
                    type: document.getElementById('typeFilter').value,
                    folder_id: document.getElementById('folderFilter').value,
                    q: document.getElementById('searchInput').value
                });

                const response = await axios.get(`/api/files.php?${params}`, {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
                });
                
                if (response.data.success) {
                    currentFiles = response.data.data.files;
                    currentFolders = response.data.data.folders;
                    
                    renderFiles();
                    updateFolderFilter();
                    updateStats();
                }
            } catch (error) {
                console.error('Failed to load files:', error);
                showToast('error', 'Failed to load files');
            }
        }

        function renderFiles() {
            const container = document.getElementById('filesGrid');
            
            if (currentFiles.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-folder"></i>
                            <h4>No files found</h4>
                            <p>Upload your first file to get started!</p>
                        </div>
                    </div>
                `;
                return;
            }

            container.innerHTML = currentFiles.map(file => `
                <div class="file-card" data-file-id="${file.id}">
                    <div class="file-preview ${file.file_category}">
                        ${renderFilePreview(file)}
                        <div class="file-type-badge">${file.file_category.toUpperCase()}</div>
                    </div>
                    <div class="file-info">
                        <div class="file-name" title="${file.original_filename}">
                            ${truncateFileName(file.original_filename)}
                        </div>
                        <div class="file-meta">
                            <span class="file-size">${file.formatted_size}</span>
                            <span>${formatDate(file.upload_date)}</span>
                        </div>
                        <div class="file-actions">
                            <button class="btn-action btn-view" onclick="viewFile(${file.id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                            ${file.can_edit ? `
                                <button class="btn-action btn-edit" onclick="editFile(${file.id})">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            ` : ''}
                            <button class="btn-action btn-download" onclick="downloadFile(${file.id})">
                                <i class="bi bi-download"></i> Download
                            </button>
                            ${file.can_delete ? `
                                <button class="btn-action btn-delete" onclick="deleteFile(${file.id})">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function renderFilePreview(file) {
            if (file.file_category === 'image') {
                return `<img src="/api/files.php?action=download&id=${file.id}" alt="${file.original_filename}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">`;
            }
            
            const icons = {
                video: 'bi-play-circle',
                document: 'bi-file-earmark-text',
                image: 'bi-image',
                audio: 'bi-music-note',
                other: 'bi-file-earmark'
            };
            
            return `<i class="bi ${icons[file.file_category] || icons.other}"></i>`;
        }

        function truncateFileName(filename, maxLength = 25) {
            if (filename.length <= maxLength) return filename;
            const extension = filename.split('.').pop();
            const nameWithoutExt = filename.substring(0, filename.lastIndexOf('.'));
            const maxNameLength = maxLength - extension.length - 4; // Account for "..." and "."
            return nameWithoutExt.substring(0, maxNameLength) + '...' + extension;
        }

        function updateFolderFilter() {
            const folderFilter = document.getElementById('folderFilter');
            const currentValue = folderFilter.value;
            
            folderFilter.innerHTML = '<option value="">All Folders</option>';
            currentFolders.forEach(folder => {
                const option = document.createElement('option');
                option.value = folder.id;
                option.textContent = folder.name;
                folderFilter.appendChild(option);
            });
            
            folderFilter.value = currentValue;
        }

        function updateStats() {
            const totalDownloads = currentFiles.reduce((sum, file) => sum + (file.downloads || 0), 0);
            document.getElementById('totalDownloads').textContent = totalDownloads;
        }

        function refreshFiles() {
            loadFiles(currentPage);
            loadFileStats();
        }

        async function viewFile(fileId) {
            try {
                const response = await axios.get(`/api/files.php?action=get&id=${fileId}`, {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
                });
                
                if (response.data.success) {
                    showFileDetails(response.data.data);
                }
            } catch (error) {
                showToast('error', 'Failed to load file details');
            }
        }

        function showFileDetails(file) {
            const modal = new bootstrap.Modal(document.getElementById('fileDetailsModal'));
            const content = document.getElementById('fileDetailsContent');
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="file-preview ${file.file_category} mb-3">
                            ${renderFilePreview(file)}
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h4>${file.original_filename}</h4>
                        <p><strong>Size:</strong> ${file.formatted_size}</p>
                        <p><strong>Type:</strong> ${file.file_category}</p>
                        <p><strong>Uploaded:</strong> ${formatDate(file.upload_date)}</p>
                        ${file.course_title ? `<p><strong>Course:</strong> ${file.course_title}</p>` : ''}
                        ${file.description ? `<p><strong>Description:</strong> ${file.description}</p>` : ''}
                        ${file.tags ? `<p><strong>Tags:</strong> ${file.tags}</p>` : ''}
                        <p><strong>Downloads:</strong> ${file.downloads || 0}</p>
                        <p><strong>Privacy:</strong> ${file.is_public ? 'Public' : 'Private'}</p>
                    </div>
                </div>
            `;
            
            modal.show();
        }

        function editFile(fileId) {
            const file = currentFiles.find(f => f.id === fileId);
            if (!file) return;
            
            const modal = new bootstrap.Modal(document.getElementById('editFileModal'));
            document.getElementById('editFileId').value = file.id;
            document.getElementById('editFileName').value = file.original_filename;
            document.getElementById('editFileDescription').value = file.description || '';
            document.getElementById('editFileTags').value = file.tags || '';
            document.getElementById('editFileCourse').value = file.course_id || '';
            document.getElementById('editFilePublic').checked = file.is_public;
            
            modal.show();
        }

        async function saveFileChanges() {
            const form = document.getElementById('editFileForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await axios.put('/api/files.php', data, {
                    headers: { 
                        'Authorization': 'Bearer ' + localStorage.getItem('token'),
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.data.success) {
                    showToast('success', 'File updated successfully');
                    bootstrap.Modal.getInstance(document.getElementById('editFileModal')).hide();
                    refreshFiles();
                } else {
                    showToast('error', response.data.error || 'Update failed');
                }
            } catch (error) {
                showToast('error', 'Failed to update file');
            }
        }

        function downloadFile(fileId) {
            window.open(`/api/files.php?action=download&id=${fileId}`, '_blank');
        }

        async function deleteFile(fileId) {
            if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await axios.delete('/api/files.php', {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') },
                    data: { id: fileId }
                });
                
                if (response.data.success) {
                    showToast('success', 'File deleted successfully');
                    refreshFiles();
                } else {
                    showToast('error', response.data.error || 'Delete failed');
                }
            } catch (error) {
                showToast('error', 'Failed to delete file');
            }
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showToast(type, message) {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
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