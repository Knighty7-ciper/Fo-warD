<?php
require_once '../backend/includes/auth.php';
$user = requireAuth();
$pageTitle = 'File Manager';
include '../shared/templates/header.php';
?>

<link rel="stylesheet" href="assets/css/file-manager.css">

<div class="file-manager-container">
    <div class="file-manager-header">
        <div class="header-left">
            <h1>File Manager</h1>
            <div class="breadcrumb" id="breadcrumb">
                <a href="#" onclick="navigateToFolder(null)">Home</a>
            </div>
        </div>
        <div class="header-right">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="fileSearch" placeholder="Search files..." onkeyup="searchFiles()">
            </div>
            <button class="btn btn-secondary" onclick="showCreateFolderModal()">
                <i class="fas fa-folder-plus"></i> New Folder
            </button>
            <button class="btn btn-primary" onclick="showUploadModal()">
                <i class="fas fa-upload"></i> Upload File
            </button>
        </div>
    </div>

    <div class="file-manager-toolbar">
        <div class="view-options">
            <button class="view-btn active" data-view="grid" onclick="switchView('grid')">
                <i class="fas fa-th"></i>
            </button>
            <button class="view-btn" data-view="list" onclick="switchView('list')">
                <i class="fas fa-list"></i>
            </button>
        </div>
        
        <div class="filter-options">
            <select id="fileTypeFilter" onchange="filterFiles()">
                <option value="">All Types</option>
                <option value="pdf">PDF</option>
                <option value="doc,docx">Documents</option>
                <option value="jpg,jpeg,png,gif">Images</option>
                <option value="mp4,avi,mov">Videos</option>
                <option value="mp3,wav">Audio</option>
                <option value="zip,rar">Archives</option>
            </select>
            
            <select id="sortBy" onchange="sortFiles()">
                <option value="date_desc">Date (Newest)</option>
                <option value="date_asc">Date (Oldest)</option>
                <option value="name_asc">Name (A-Z)</option>
                <option value="name_desc">Name (Z-A)</option>
                <option value="size_desc">Size (Largest)</option>
                <option value="size_asc">Size (Smallest)</option>
            </select>
        </div>
        
        <div class="storage-info">
            <i class="fas fa-hdd"></i>
            <span id="storageUsed">0 MB</span> / <span id="storageTotal">1 GB</span>
        </div>
    </div>

    <div class="file-manager-content">
        <div class="file-grid" id="fileGrid">
             Files and folders will be loaded here 
        </div>
    </div>
</div>

 Upload Modal 
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upload File</h2>
            <button class="close-btn" onclick="closeUploadModal()">&times;</button>
        </div>
        <form id="uploadForm" onsubmit="uploadFile(event)">
            <div class="upload-area" id="uploadArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Drag and drop files here or click to browse</p>
                <input type="file" id="fileInput" multiple onchange="handleFileSelect(event)">
            </div>
            
            <div id="fileList" class="file-list"></div>
            
            <div class="form-group">
                <label for="uploadDescription">Description (Optional)</label>
                <textarea id="uploadDescription" rows="2"></textarea>
            </div>
            
            <div class="form-group">
                <label for="uploadTags">Tags (Optional)</label>
                <input type="text" id="uploadTags" placeholder="Comma separated tags">
            </div>
            
            <div class="form-group">
                <label for="uploadFolder">Folder</label>
                <select id="uploadFolder">
                    <option value="">Root</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" id="uploadPublic">
                    Make file public
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

 Create Folder Modal 
<div id="createFolderModal" class="modal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h2>Create Folder</h2>
            <button class="close-btn" onclick="closeCreateFolderModal()">&times;</button>
        </div>
        <form onsubmit="createFolder(event)">
            <div class="form-group">
                <label for="folderName">Folder Name *</label>
                <input type="text" id="folderName" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" id="folderPublic">
                    Make folder public
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateFolderModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

 File Details Modal 
<div id="fileDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="fileDetailsName"></h2>
            <button class="close-btn" onclick="closeFileDetailsModal()">&times;</button>
        </div>
        <div class="file-details-content">
            <div class="file-preview" id="filePreview"></div>
            
            <div class="file-info">
                <div class="info-row">
                    <strong>Type:</strong>
                    <span id="fileDetailsType"></span>
                </div>
                <div class="info-row">
                    <strong>Size:</strong>
                    <span id="fileDetailsSize"></span>
                </div>
                <div class="info-row">
                    <strong>Uploaded:</strong>
                    <span id="fileDetailsDate"></span>
                </div>
                <div class="info-row">
                    <strong>Uploaded by:</strong>
                    <span id="fileDetailsUploader"></span>
                </div>
                <div class="info-row">
                    <strong>Downloads:</strong>
                    <span id="fileDetailsDownloads"></span>
                </div>
                <div class="info-row" id="fileDetailsDescRow" style="display: none;">
                    <strong>Description:</strong>
                    <span id="fileDetailsDesc"></span>
                </div>
                <div class="info-row" id="fileDetailsTagsRow" style="display: none;">
                    <strong>Tags:</strong>
                    <span id="fileDetailsTags"></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeFileDetailsModal()">Close</button>
            <button class="btn btn-danger" onclick="deleteFileConfirm()" id="deleteFileBtn">
                <i class="fas fa-trash"></i> Delete
            </button>
            <button class="btn btn-primary" onclick="downloadFileAction()">
                <i class="fas fa-download"></i> Download
            </button>
        </div>
    </div>
</div>

<script src="assets/js/file-manager.js"></script>

<?php include '../shared/templates/footer.php'; ?>
