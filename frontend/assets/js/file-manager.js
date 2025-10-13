let currentFolderId = null
let currentFiles = []
let currentFolders = []
let selectedFileId = null
let currentView = "grid"

document.addEventListener("DOMContentLoaded", () => {
  loadFiles()
  setupDragAndDrop()
})

function loadFiles(folderId = null) {
  currentFolderId = folderId

  let url = "/backend/api/files.php?action=list"
  if (folderId) {
    url += `&folder_id=${folderId}`
  } else {
    url += `&folder_id=0`
  }

  fetch(url)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        currentFiles = data.files
        currentFolders = data.folders
        renderFiles()
        updateBreadcrumb()
      }
    })
    .catch((err) => console.error("Error loading files:", err))
}

function renderFiles() {
  const grid = document.getElementById("fileGrid")
  grid.className = `file-grid ${currentView === "list" ? "list-view" : ""}`

  let html = ""

  // Render folders first
  currentFolders.forEach((folder) => {
    html += `<div class="folder-item" onclick="navigateToFolder(${folder.id})">`
    html += '<i class="fas fa-folder folder-icon"></i>'
    html += `<div class="file-name">${folder.name}</div>`
    html += `<div class="file-meta">Folder</div>`
    html += "</div>"
  })

  // Render files
  currentFiles.forEach((file) => {
    const icon = getFileIcon(file.file_type)
    const size = formatFileSize(file.file_size)

    html += `<div class="file-item" onclick="showFileDetails(${file.id})">`
    html += `<i class="fas ${icon.icon} file-icon ${icon.class}"></i>`
    html += `<div class="file-name" title="${file.original_filename}">${file.original_filename}</div>`
    html += `<div class="file-meta">${size}</div>`
    html += "</div>"
  })

  if (html === "") {
    html = '<p class="text-center text-muted">No files or folders found</p>'
  }

  grid.innerHTML = html
}

function getFileIcon(type) {
  const icons = {
    pdf: { icon: "fa-file-pdf", class: "pdf" },
    doc: { icon: "fa-file-word", class: "doc" },
    docx: { icon: "fa-file-word", class: "doc" },
    xls: { icon: "fa-file-excel", class: "doc" },
    xlsx: { icon: "fa-file-excel", class: "doc" },
    ppt: { icon: "fa-file-powerpoint", class: "doc" },
    pptx: { icon: "fa-file-powerpoint", class: "doc" },
    jpg: { icon: "fa-file-image", class: "image" },
    jpeg: { icon: "fa-file-image", class: "image" },
    png: { icon: "fa-file-image", class: "image" },
    gif: { icon: "fa-file-image", class: "image" },
    mp4: { icon: "fa-file-video", class: "video" },
    avi: { icon: "fa-file-video", class: "video" },
    mov: { icon: "fa-file-video", class: "video" },
    mp3: { icon: "fa-file-audio", class: "audio" },
    wav: { icon: "fa-file-audio", class: "audio" },
    zip: { icon: "fa-file-archive", class: "" },
    rar: { icon: "fa-file-archive", class: "" },
  }

  return icons[type] || { icon: "fa-file", class: "" }
}

function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes"
  const k = 1024
  const sizes = ["Bytes", "KB", "MB", "GB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i]
}

function navigateToFolder(folderId) {
  loadFiles(folderId)
}

function updateBreadcrumb() {
  // Simplified breadcrumb - in production, you'd fetch the full path
  const breadcrumb = document.getElementById("breadcrumb")
  breadcrumb.innerHTML = '<a href="#" onclick="navigateToFolder(null)">Home</a>'
}

function switchView(view) {
  currentView = view
  document.querySelectorAll(".view-btn").forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.view === view)
  })
  renderFiles()
}

function showUploadModal() {
  document.getElementById("uploadModal").style.display = "flex"
  loadFoldersForSelect()
}

function closeUploadModal() {
  document.getElementById("uploadModal").style.display = "none"
  document.getElementById("uploadForm").reset()
  document.getElementById("fileList").innerHTML = ""
}

function loadFoldersForSelect() {
  const select = document.getElementById("uploadFolder")
  select.innerHTML = '<option value="">Root</option>'

  currentFolders.forEach((folder) => {
    const option = document.createElement("option")
    option.value = folder.id
    option.textContent = folder.name
    select.appendChild(option)
  })
}

function setupDragAndDrop() {
  const uploadArea = document.getElementById("uploadArea")

  uploadArea.addEventListener("dragover", (e) => {
    e.preventDefault()
    uploadArea.classList.add("drag-over")
  })

  uploadArea.addEventListener("dragleave", () => {
    uploadArea.classList.remove("drag-over")
  })

  uploadArea.addEventListener("drop", (e) => {
    e.preventDefault()
    uploadArea.classList.remove("drag-over")
    handleFileSelect({ target: { files: e.dataTransfer.files } })
  })
}

let selectedFiles = []

function handleFileSelect(event) {
  selectedFiles = Array.from(event.target.files)
  renderFileList()
}

function renderFileList() {
  const fileList = document.getElementById("fileList")
  let html = ""

  selectedFiles.forEach((file, index) => {
    html += '<div class="file-list-item">'
    html += '<div class="file-list-item-name">'
    html += `<i class="fas fa-file"></i>`
    html += `<span>${file.name}</span>`
    html += "</div>"
    html += `<span class="file-list-item-size">${formatFileSize(file.size)}</span>`
    html += `<button type="button" class="file-list-item-remove" onclick="removeFile(${index})">`
    html += '<i class="fas fa-times"></i>'
    html += "</button>"
    html += "</div>"
  })

  fileList.innerHTML = html
}

function removeFile(index) {
  selectedFiles.splice(index, 1)
  renderFileList()
}

function uploadFile(e) {
  e.preventDefault()

  if (selectedFiles.length === 0) {
    alert("Please select at least one file")
    return
  }

  const formData = new FormData()
  selectedFiles.forEach((file) => {
    formData.append("file", file)
  })

  formData.append("description", document.getElementById("uploadDescription").value)
  formData.append("tags", document.getElementById("uploadTags").value)
  formData.append("folder_id", document.getElementById("uploadFolder").value)
  formData.append("is_public", document.getElementById("uploadPublic").checked)

  fetch("/backend/api/files.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        closeUploadModal()
        loadFiles(currentFolderId)
        alert(data.message)
      } else {
        alert("Error: " + data.error)
      }
    })
    .catch((err) => {
      console.error("Error uploading file:", err)
      alert("Failed to upload file")
    })
}

function showCreateFolderModal() {
  document.getElementById("createFolderModal").style.display = "flex"
}

function closeCreateFolderModal() {
  document.getElementById("createFolderModal").style.display = "none"
}

function createFolder(e) {
  e.preventDefault()

  const folderData = {
    name: document.getElementById("folderName").value,
    parent_id: currentFolderId,
    is_public: document.getElementById("folderPublic").checked,
  }

  fetch("/backend/api/folders.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(folderData),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        closeCreateFolderModal()
        loadFiles(currentFolderId)
        alert(data.message)
      } else {
        alert("Error: " + data.error)
      }
    })
}

function showFileDetails(fileId) {
  selectedFileId = fileId

  fetch(`/backend/api/files.php?id=${fileId}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const file = data.file

        document.getElementById("fileDetailsName").textContent = file.original_filename
        document.getElementById("fileDetailsType").textContent = file.file_type.toUpperCase()
        document.getElementById("fileDetailsSize").textContent = formatFileSize(file.file_size)
        document.getElementById("fileDetailsDate").textContent = new Date(file.created_at).toLocaleString()
        document.getElementById("fileDetailsUploader").textContent = file.uploader_name
        document.getElementById("fileDetailsDownloads").textContent = file.downloads

        if (file.description) {
          document.getElementById("fileDetailsDesc").textContent = file.description
          document.getElementById("fileDetailsDescRow").style.display = "flex"
        } else {
          document.getElementById("fileDetailsDescRow").style.display = "none"
        }

        if (file.tags) {
          document.getElementById("fileDetailsTags").textContent = file.tags
          document.getElementById("fileDetailsTagsRow").style.display = "flex"
        } else {
          document.getElementById("fileDetailsTagsRow").style.display = "none"
        }

        // Show preview for images
        const preview = document.getElementById("filePreview")
        if (["jpg", "jpeg", "png", "gif"].includes(file.file_type)) {
          preview.innerHTML = `<img src="${file.file_path}" alt="${file.original_filename}">`
        } else {
          const icon = getFileIcon(file.file_type)
          preview.innerHTML = `<i class="fas ${icon.icon}"></i>`
        }

        document.getElementById("fileDetailsModal").style.display = "flex"
      }
    })
}

function closeFileDetailsModal() {
  document.getElementById("fileDetailsModal").style.display = "none"
  selectedFileId = null
}

function downloadFileAction() {
  if (selectedFileId) {
    window.location.href = `/backend/api/files.php?action=download&id=${selectedFileId}`
  }
}

function deleteFileConfirm() {
  if (!selectedFileId) return

  if (confirm("Are you sure you want to delete this file?")) {
    fetch("/backend/api/files.php", {
      method: "DELETE",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: selectedFileId }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          closeFileDetailsModal()
          loadFiles(currentFolderId)
          alert(data.message)
        } else {
          alert("Error: " + data.error)
        }
      })
  }
}

function searchFiles() {
  const query = document.getElementById("fileSearch").value

  if (query.length < 2) {
    loadFiles(currentFolderId)
    return
  }

  fetch(`/backend/api/files.php?action=search&q=${encodeURIComponent(query)}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        currentFiles = data.files
        currentFolders = []
        renderFiles()
      }
    })
}

function filterFiles() {
  // Simplified filtering - in production, you'd filter on the server
  renderFiles()
}

function sortFiles() {
  // Simplified sorting - in production, you'd sort on the server
  renderFiles()
}
