let currentTemplateId = null

document.addEventListener("DOMContentLoaded", () => {
  loadTemplates()
  setupForm()
  setupLivePreview()
})

async function loadTemplates() {
  try {
    const response = await fetch("../../backend/api/certificate-templates.php?action=list")
    const data = await response.json()

    if (data.templates) {
      displayTemplates(data.templates)
    }
  } catch (error) {
    console.error("[v0] Error loading templates:", error)
    showError("Failed to load templates")
  }
}

function displayTemplates(templates) {
  const container = document.getElementById("templatesGrid")

  if (templates.length === 0) {
    container.innerHTML = '<p class="empty-state">No templates yet. Create your first template!</p>'
    return
  }

  container.innerHTML = templates
    .map(
      (template) => `
        <div class="template-card">
            <div class="template-preview-mini" style="background-color: ${template.background_color}">
                ${template.title_text}
            </div>
            ${template.is_default ? '<span class="template-badge">Default</span>' : ""}
            <div class="template-info">
                <h3>${template.name}</h3>
                <p>${template.description || "No description"}</p>
                <div class="template-meta">
                    <small>Orientation: ${template.orientation}</small>
                    <small>Created: ${new Date(template.created_at).toLocaleDateString()}</small>
                </div>
            </div>
            <div class="template-actions">
                <button class="btn btn-sm btn-secondary" onclick="editTemplate(${template.id})">Edit</button>
                ${!template.is_default ? `<button class="btn btn-sm btn-danger" onclick="deleteTemplate(${template.id})">Delete</button>` : ""}
            </div>
        </div>
    `,
    )
    .join("")
}

function showCreateTemplate() {
  currentTemplateId = null
  document.getElementById("modalTitle").textContent = "Create Certificate Template"
  document.getElementById("templateForm").reset()
  document.getElementById("templateId").value = ""
  updatePreview()
  showModal("templateModal")
}

async function editTemplate(templateId) {
  try {
    const response = await fetch(`../../backend/api/certificate-templates.php?action=get&id=${templateId}`)
    const data = await response.json()

    if (data.template) {
      currentTemplateId = templateId
      populateForm(data.template)
      document.getElementById("modalTitle").textContent = "Edit Certificate Template"
      updatePreview()
      showModal("templateModal")
    }
  } catch (error) {
    console.error("[v0] Error loading template:", error)
    showError("Failed to load template")
  }
}

function populateForm(template) {
  document.getElementById("templateId").value = template.id
  document.getElementById("templateName").value = template.name
  document.getElementById("templateDescription").value = template.description || ""
  document.getElementById("orientation").value = template.orientation
  document.getElementById("backgroundColor").value = template.background_color
  document.getElementById("borderStyle").value = template.border_style
  document.getElementById("borderColor").value = template.border_color
  document.getElementById("titleText").value = template.title_text
  document.getElementById("titleFontSize").value = template.title_font_size
  document.getElementById("titleColor").value = template.title_color
  document.getElementById("bodyTemplate").value = template.body_template
  document.getElementById("isDefault").checked = template.is_default
}

function setupForm() {
  document.getElementById("templateForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await saveTemplate()
  })
}

function setupLivePreview() {
  const inputs = [
    "orientation",
    "backgroundColor",
    "borderStyle",
    "borderColor",
    "titleText",
    "titleFontSize",
    "titleColor",
    "bodyTemplate",
  ]

  inputs.forEach((id) => {
    const element = document.getElementById(id)
    element.addEventListener("input", updatePreview)
    element.addEventListener("change", updatePreview)
  })
}

function updatePreview() {
  const preview = document.getElementById("certificatePreview")
  const orientation = document.getElementById("orientation").value
  const bgColor = document.getElementById("backgroundColor").value
  const borderStyle = document.getElementById("borderStyle").value
  const borderColor = document.getElementById("borderColor").value
  const titleText = document.getElementById("titleText").value
  const titleSize = document.getElementById("titleFontSize").value
  const titleColor = document.getElementById("titleColor").value
  const bodyTemplate = document.getElementById("bodyTemplate").value

  preview.className = `certificate-preview ${orientation}`
  preview.style.backgroundColor = bgColor

  let borderCSS = ""
  switch (borderStyle) {
    case "simple":
      borderCSS = `border: 2px solid ${borderColor};`
      break
    case "elegant":
      borderCSS = `border: 4px double ${borderColor}; padding: 1rem;`
      break
    case "modern":
      borderCSS = `border: 6px solid ${borderColor}; border-radius: 8px;`
      break
  }

  const bodyText = bodyTemplate
    .replace("{{student_name}}", "<strong>John Doe</strong>")
    .replace("{{course_title}}", "<strong>Sample Course</strong>")
    .replace("{{completion_date}}", new Date().toLocaleDateString())
    .replace("{{certificate_number}}", "CERT-SAMPLE-001")
    .replace("{{instructor_name}}", "Jane Smith")

  preview.innerHTML = `
        <div style="${borderCSS} height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
            <div style="font-size: ${titleSize}px; font-weight: bold; color: ${titleColor}; margin-bottom: 1rem; text-transform: uppercase;">
                ${titleText}
            </div>
            <div style="font-size: 14px; line-height: 1.6; max-width: 80%; margin: 1rem auto;">
                ${bodyText}
            </div>
            <div style="margin-top: 2rem; font-size: 10px; color: #666;">
                Certificate No: CERT-SAMPLE-001
            </div>
        </div>
    `
}

function previewTemplate() {
  updatePreview()
}

async function saveTemplate() {
  const templateData = {
    name: document.getElementById("templateName").value,
    description: document.getElementById("templateDescription").value,
    orientation: document.getElementById("orientation").value,
    background_color: document.getElementById("backgroundColor").value,
    border_style: document.getElementById("borderStyle").value,
    border_color: document.getElementById("borderColor").value,
    title_text: document.getElementById("titleText").value,
    title_font_size: Number.parseInt(document.getElementById("titleFontSize").value),
    title_color: document.getElementById("titleColor").value,
    body_template: document.getElementById("bodyTemplate").value,
    signature_fields: [
      { label: "Instructor", name: "{{instructor_name}}" },
      { label: "Administrator", name: "FowarD LMS" },
    ],
    is_default: document.getElementById("isDefault").checked,
  }

  try {
    const url = "../../backend/api/certificate-templates.php"
    const method = currentTemplateId ? "PUT" : "POST"

    if (currentTemplateId) {
      templateData.id = currentTemplateId
    }

    const response = await fetch(url, {
      method: method,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(templateData),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess(currentTemplateId ? "Template updated successfully" : "Template created successfully")
      closeModal("templateModal")
      loadTemplates()
    } else {
      showError(data.error || "Failed to save template")
    }
  } catch (error) {
    console.error("[v0] Error saving template:", error)
    showError("Failed to save template")
  }
}

async function deleteTemplate(templateId) {
  if (!confirm("Are you sure you want to delete this template?")) {
    return
  }

  try {
    const response = await fetch(`../../backend/api/certificate-templates.php?id=${templateId}`, {
      method: "DELETE",
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Template deleted successfully")
      loadTemplates()
    } else {
      showError(data.error || "Failed to delete template")
    }
  } catch (error) {
    console.error("[v0] Error deleting template:", error)
    showError("Failed to delete template")
  }
}

function showModal(modalId) {
  document.getElementById(modalId).style.display = "flex"
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none"
}

function showSuccess(message) {
  alert(message)
}

function showError(message) {
  alert("Error: " + message)
}
