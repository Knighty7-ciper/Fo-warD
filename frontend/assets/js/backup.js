let currentRestoreId = null

document.addEventListener("DOMContentLoaded", () => {
  loadBackups()
  loadSettings()
  setupForms()
})

async function loadBackups() {
  try {
    const response = await fetch("../../backend/api/backup.php?action=list")
    const data = await response.json()

    if (data.backups) {
      displayBackups(data.backups)
      updateStats(data.backups)
    }
  } catch (error) {
    console.error("[v0] Error loading backups:", error)
    showError("Failed to load backups")
  }
}

function displayBackups(backups) {
  const tbody = document.getElementById("backupsTableBody")

  if (backups.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No backups yet. Create your first backup!</td></tr>'
    return
  }

  tbody.innerHTML = backups
    .map(
      (backup) => `
        <tr>
            <td>${backup.filename}</td>
            <td><span class="backup-type">${backup.backup_type}</span></td>
            <td>${backup.file_size_formatted}</td>
            <td><span class="backup-status ${backup.status}">${backup.status.replace("_", " ")}</span></td>
            <td>${new Date(backup.created_at).toLocaleString()}</td>
            <td>${backup.created_by_name || "System"}</td>
            <td>
                <div class="backup-actions">
                    ${backup.status === "completed" ? `<button class="btn btn-sm btn-secondary" onclick="downloadBackup(${backup.id})">Download</button>` : ""}
                    ${backup.status === "completed" ? `<button class="btn btn-sm btn-primary" onclick="showRestore(${backup.id}, '${backup.filename}')">Restore</button>` : ""}
                    <button class="btn btn-sm btn-danger" onclick="deleteBackup(${backup.id})">Delete</button>
                </div>
            </td>
        </tr>
    `,
    )
    .join("")
}

function updateStats(backups) {
  const completed = backups.filter((b) => b.status === "completed")

  document.getElementById("totalBackups").textContent = completed.length

  if (completed.length > 0) {
    const latest = completed[0]
    const date = new Date(latest.created_at)
    document.getElementById("lastBackup").textContent = date.toLocaleDateString()

    const totalSize = completed.reduce((sum, b) => sum + Number.parseInt(b.file_size), 0)
    document.getElementById("totalSize").textContent = formatBytes(totalSize)
  } else {
    document.getElementById("lastBackup").textContent = "Never"
    document.getElementById("totalSize").textContent = "0 B"
  }
}

async function loadSettings() {
  try {
    const response = await fetch("../../backend/api/backup.php?action=settings")
    const data = await response.json()

    if (data.settings) {
      populateSettings(data.settings)
    }
  } catch (error) {
    console.error("[v0] Error loading settings:", error)
  }
}

function populateSettings(settings) {
  document.getElementById("autoBackupEnabled").checked = settings.auto_backup_enabled
  document.getElementById("backupFrequency").value = settings.backup_frequency
  document.getElementById("backupTime").value = settings.backup_time.substring(0, 5)
  document.getElementById("retentionDays").value = settings.retention_days
  document.getElementById("maxBackups").value = settings.max_backups
  document.getElementById("includeUploads").checked = settings.include_uploads
  document.getElementById("notificationEmail").value = settings.notification_email || ""
}

function setupForms() {
  document.getElementById("settingsForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await saveSettings()
  })
}

async function createBackup() {
  if (!confirm("Create a new backup now? This may take a few moments.")) {
    return
  }

  try {
    const response = await fetch("../../backend/api/backup.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "create",
        backup_type: "manual",
      }),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess(`Backup created successfully: ${data.filename} (${data.file_size})`)
      loadBackups()
    } else {
      showError(data.error || "Failed to create backup")
    }
  } catch (error) {
    console.error("[v0] Error creating backup:", error)
    showError("Failed to create backup")
  }
}

function downloadBackup(backupId) {
  window.location.href = `../../backend/api/backup.php?action=download&id=${backupId}`
}

function showRestore(backupId, filename) {
  currentRestoreId = backupId
  document.getElementById("restoreFilename").textContent = filename
  showModal("restoreModal")
}

async function confirmRestore() {
  if (!currentRestoreId) {
    return
  }

  try {
    const response = await fetch("../../backend/api/backup.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "restore",
        backup_id: currentRestoreId,
      }),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Database restored successfully. Please refresh the page.")
      closeModal("restoreModal")
      setTimeout(() => {
        window.location.reload()
      }, 2000)
    } else {
      showError(data.error || "Failed to restore backup")
    }
  } catch (error) {
    console.error("[v0] Error restoring backup:", error)
    showError("Failed to restore backup")
  }
}

async function deleteBackup(backupId) {
  if (!confirm("Are you sure you want to delete this backup? This action cannot be undone.")) {
    return
  }

  try {
    const response = await fetch(`../../backend/api/backup.php?id=${backupId}`, {
      method: "DELETE",
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Backup deleted successfully")
      loadBackups()
    } else {
      showError(data.error || "Failed to delete backup")
    }
  } catch (error) {
    console.error("[v0] Error deleting backup:", error)
    showError("Failed to delete backup")
  }
}

function showSettings() {
  showModal("settingsModal")
}

async function saveSettings() {
  const settings = {
    action: "update_settings",
    auto_backup_enabled: document.getElementById("autoBackupEnabled").checked,
    backup_frequency: document.getElementById("backupFrequency").value,
    backup_time: document.getElementById("backupTime").value + ":00",
    retention_days: Number.parseInt(document.getElementById("retentionDays").value),
    max_backups: Number.parseInt(document.getElementById("maxBackups").value),
    include_uploads: document.getElementById("includeUploads").checked,
    notification_email: document.getElementById("notificationEmail").value,
  }

  try {
    const response = await fetch("../../backend/api/backup.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(settings),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Backup settings saved successfully")
      closeModal("settingsModal")
    } else {
      showError(data.error || "Failed to save settings")
    }
  } catch (error) {
    console.error("[v0] Error saving settings:", error)
    showError("Failed to save settings")
  }
}

function formatBytes(bytes, precision = 2) {
  const units = ["B", "KB", "MB", "GB", "TB"]
  let i = 0

  while (bytes > 1024 && i < units.length - 1) {
    bytes /= 1024
    i++
  }

  return bytes.toFixed(precision) + " " + units[i]
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
