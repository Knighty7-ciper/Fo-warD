let selectedFiles = []
const assignmentId = new URLSearchParams(window.location.search).get("id")

document.addEventListener("DOMContentLoaded", () => {
  loadAssignment()
  setupFileUpload()
})

async function loadAssignment() {
  try {
    const response = await fetch(`/backend/api/assignments.php?id=${assignmentId}`)
    const assignment = await response.json()

    const header = document.getElementById("assignment-header")
    const now = new Date()
    const dueDate = new Date(assignment.due_date)
    const isOverdue = dueDate < now
    const isDueSoon = dueDate - now < 86400000 && !isOverdue // Less than 24 hours

    let statusClass = ""
    let statusText = ""

    if (isOverdue) {
      statusClass = "overdue"
      statusText = "Overdue"
    } else if (isDueSoon) {
      statusClass = "due-soon"
      statusText = "Due Soon"
    }

    header.innerHTML = `
            <h1>${escapeHtml(assignment.title)}</h1>
            <p>${escapeHtml(assignment.description)}</p>
            
            ${
              assignment.instructions
                ? `
                <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                    <strong>Instructions:</strong>
                    <p style="margin: 10px 0 0 0;">${escapeHtml(assignment.instructions)}</p>
                </div>
            `
                : ""
            }
            
            <div class="assignment-meta">
                <div class="meta-item">
                    <span class="meta-label">Due Date</span>
                    <span class="meta-value ${statusClass}">${formatDate(assignment.due_date)} ${statusText ? `(${statusText})` : ""}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Max Points</span>
                    <span class="meta-value">${assignment.max_points}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Submission Type</span>
                    <span class="meta-value">${assignment.submission_type.replace("_", " ").toUpperCase()}</span>
                </div>
                ${
                  assignment.allowed_file_types
                    ? `
                    <div class="meta-item">
                        <span class="meta-label">Allowed Files</span>
                        <span class="meta-value">${assignment.allowed_file_types}</span>
                    </div>
                `
                    : ""
                }
            </div>
            
            ${
              assignment.submission
                ? `
                <div class="alert alert-success" style="margin-top: 20px;">
                    <strong>You have already submitted this assignment</strong>
                    <p>Submitted on: ${formatDate(assignment.submission.submitted_at)}</p>
                    ${assignment.submission.grade ? `<p>Grade: ${assignment.submission.grade} / ${assignment.max_points}</p>` : ""}
                </div>
            `
                : ""
            }
        `
  } catch (error) {
    console.error("Error loading assignment:", error)
  }
}

function setupFileUpload() {
  const fileInput = document.getElementById("file-input")
  const uploadArea = document.getElementById("file-upload-area")

  fileInput.addEventListener("change", handleFileSelect)

  // Drag and drop
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

    const files = Array.from(e.dataTransfer.files)
    addFiles(files)
  })

  // Form submission
  document.getElementById("submission-form").addEventListener("submit", submitAssignment)
}

function handleFileSelect(e) {
  const files = Array.from(e.target.files)
  addFiles(files)
}

function addFiles(files) {
  selectedFiles = [...selectedFiles, ...files]
  renderFileList()
}

function removeFile(index) {
  selectedFiles.splice(index, 1)
  renderFileList()
}

function renderFileList() {
  const fileList = document.getElementById("file-list")

  if (selectedFiles.length === 0) {
    fileList.innerHTML = ""
    return
  }

  fileList.innerHTML = selectedFiles
    .map(
      (file, index) => `
        <div class="file-item">
            <div class="file-info">
                <span class="file-icon">📄</span>
                <div class="file-details">
                    <span class="file-name">${escapeHtml(file.name)}</span>
                    <span class="file-size">${formatFileSize(file.size)}</span>
                </div>
            </div>
            <button type="button" class="file-remove" onclick="removeFile(${index})">×</button>
        </div>
    `,
    )
    .join("")
}

async function submitAssignment(e) {
  e.preventDefault()

  if (selectedFiles.length === 0 && !document.querySelector('textarea[name="submission_text"]').value) {
    alert("Please provide either text submission or upload files")
    return
  }

  const formData = new FormData(e.target)

  // Clear existing files and add selected files
  formData.delete("files[]")
  selectedFiles.forEach((file) => {
    formData.append("files[]", file)
  })

  try {
    const response = await fetch("/backend/api/submissions.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (result.success) {
      alert("Assignment submitted successfully!")
      window.location.href = "/frontend/student/assignments.php"
    } else {
      alert("Error: " + (result.error || "Failed to submit assignment"))
    }
  } catch (error) {
    console.error("Error submitting assignment:", error)
    alert("Failed to submit assignment")
  }
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  })
}

function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes"
  const k = 1024
  const sizes = ["Bytes", "KB", "MB", "GB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i]
}

function escapeHtml(text) {
  const div = document.createElement("div")
  div.textContent = text
  return div.innerHTML
}
