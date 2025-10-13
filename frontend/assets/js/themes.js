let themes = []

document.addEventListener("DOMContentLoaded", () => {
  loadThemes()

  // Update preview on color change
  document.getElementById("primaryColor")?.addEventListener("input", updatePreview)
  document.getElementById("secondaryColor")?.addEventListener("input", updatePreview)
  document.getElementById("accentColor")?.addEventListener("input", updatePreview)
  document.getElementById("fontFamily")?.addEventListener("change", updatePreview)
})

function loadThemes() {
  fetch("/backend/api/themes.php")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        themes = data.themes
        renderThemes()
      }
    })
    .catch((err) => console.error("Error loading themes:", err))
}

function renderThemes() {
  const grid = document.getElementById("themesGrid")
  let html = ""

  themes.forEach((theme) => {
    const isActive = theme.is_active == 1

    html += `<div class="theme-card ${isActive ? "active" : ""}">`
    html += `<div class="theme-preview-header" style="background: ${theme.primary_color};">`
    html += theme.name
    if (isActive) html += '<span class="active-badge">Active</span>'
    html += "</div>"
    html += '<div class="theme-card-body">'
    html += `<h3 class="theme-card-title">${theme.name}</h3>`
    html += `<p class="theme-card-description">${theme.description || "No description"}</p>`
    html += '<div class="theme-colors">'
    html += `<div class="color-swatch" style="background: ${theme.primary_color};" title="Primary"></div>`
    html += `<div class="color-swatch" style="background: ${theme.secondary_color};" title="Secondary"></div>`
    html += `<div class="color-swatch" style="background: ${theme.accent_color};" title="Accent"></div>`
    html += "</div>"
    html += '<div class="theme-card-actions">'

    if (!isActive) {
      html += `<button class="btn btn-primary btn-sm" onclick="activateTheme(${theme.id})">Activate</button>`
    }

    if (!theme.is_default) {
      html += `<button class="btn btn-secondary btn-sm" onclick="editTheme(${theme.id})">Edit</button>`
      if (!isActive) {
        html += `<button class="btn btn-danger btn-sm" onclick="deleteTheme(${theme.id})">Delete</button>`
      }
    }

    html += "</div></div></div>"
  })

  grid.innerHTML = html
}

function showCreateThemeModal() {
  document.getElementById("themeModalTitle").textContent = "Create Theme"
  document.getElementById("themeForm").reset()
  document.getElementById("themeId").value = ""
  updatePreview()
  document.getElementById("themeModal").style.display = "flex"
}

function closeThemeModal() {
  document.getElementById("themeModal").style.display = "none"
}

function editTheme(id) {
  const theme = themes.find((t) => t.id == id)
  if (!theme) return

  document.getElementById("themeModalTitle").textContent = "Edit Theme"
  document.getElementById("themeId").value = theme.id
  document.getElementById("themeName").value = theme.name
  document.getElementById("themeDescription").value = theme.description || ""
  document.getElementById("primaryColor").value = theme.primary_color
  document.getElementById("secondaryColor").value = theme.secondary_color
  document.getElementById("accentColor").value = theme.accent_color
  document.getElementById("fontFamily").value = theme.font_family

  updatePreview()
  document.getElementById("themeModal").style.display = "flex"
}

function saveTheme(e) {
  e.preventDefault()

  const themeData = {
    id: document.getElementById("themeId").value,
    name: document.getElementById("themeName").value,
    description: document.getElementById("themeDescription").value,
    primary_color: document.getElementById("primaryColor").value,
    secondary_color: document.getElementById("secondaryColor").value,
    accent_color: document.getElementById("accentColor").value,
    font_family: document.getElementById("fontFamily").value,
  }

  const method = themeData.id ? "PUT" : "POST"

  fetch("/backend/api/themes.php", {
    method: method,
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(themeData),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        closeThemeModal()
        loadThemes()
        alert(data.message)
      } else {
        alert("Error: " + data.error)
      }
    })
}

function activateTheme(id) {
  if (confirm("Are you sure you want to activate this theme?")) {
    fetch("/backend/api/themes.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ activate: true, id: id }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          loadThemes()
          alert(data.message)
        }
      })
  }
}

function deleteTheme(id) {
  if (confirm("Are you sure you want to delete this theme?")) {
    fetch("/backend/api/themes.php", {
      method: "DELETE",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: id }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          loadThemes()
          alert(data.message)
        } else {
          alert("Error: " + data.error)
        }
      })
  }
}

function updatePreview() {
  const primary = document.getElementById("primaryColor").value
  const secondary = document.getElementById("secondaryColor").value
  const accent = document.getElementById("accentColor").value
  const font = document.getElementById("fontFamily").value

  const preview = document.getElementById("themePreview")
  preview.style.fontFamily = font

  const header = preview.querySelector(".preview-header")
  header.style.background = primary

  const primaryBtn = preview.querySelector(".preview-btn-primary")
  primaryBtn.style.background = primary

  const secondaryBtn = preview.querySelector(".preview-btn-secondary")
  secondaryBtn.style.background = secondary
  secondaryBtn.style.color = "white"
}
