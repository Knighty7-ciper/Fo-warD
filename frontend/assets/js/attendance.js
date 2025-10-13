const courseId = new URLSearchParams(window.location.search).get("course_id")
let currentSessionId = null
let attendanceRecords = []

document.addEventListener("DOMContentLoaded", () => {
  loadSessions()
  loadSettings()
  setupTabs()
  setupForms()
})

function setupTabs() {
  const tabBtns = document.querySelectorAll(".tab-btn")
  tabBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const tabName = this.dataset.tab
      switchTab(tabName)
    })
  })
}

function switchTab(tabName) {
  // Update buttons
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.classList.remove("active")
  })
  document.querySelector(`[data-tab="${tabName}"]`).classList.add("active")

  // Update content
  document.querySelectorAll(".tab-content").forEach((content) => {
    content.classList.remove("active")
  })
  document.getElementById(`${tabName}-tab`).classList.add("active")

  // Load data for the tab
  if (tabName === "statistics") {
    loadStatistics()
  } else if (tabName === "report") {
    loadReport()
  }
}

function setupForms() {
  document.getElementById("createSessionForm").addEventListener("submit", handleCreateSession)
  document.getElementById("settingsForm").addEventListener("submit", handleSaveSettings)

  document.getElementById("geofenceEnabled").addEventListener("change", function () {
    document.getElementById("geofenceSettings").style.display = this.checked ? "block" : "none"
  })
}

async function loadSessions() {
  try {
    const response = await fetch(`../../backend/api/attendance.php?action=sessions&course_id=${courseId}`)
    const data = await response.json()

    if (data.sessions) {
      displaySessions(data.sessions)
    }
  } catch (error) {
    console.error("[v0] Error loading sessions:", error)
    showError("Failed to load sessions")
  }
}

function displaySessions(sessions) {
  const container = document.getElementById("sessionsList")

  if (sessions.length === 0) {
    container.innerHTML = '<p class="empty-state">No attendance sessions yet. Create one to get started.</p>'
    return
  }

  container.innerHTML = sessions
    .map((session) => {
      const attendanceRate =
        session.total_marked > 0 ? ((session.present_count / session.total_marked) * 100).toFixed(1) : 0

      return `
            <div class="session-card">
                <div class="session-info">
                    <h3>${formatDate(session.session_date)} - ${session.session_type}</h3>
                    <div class="session-meta">
                        <span>⏰ ${session.session_time}</span>
                        <span>⏱️ ${session.duration_minutes} min</span>
                        ${session.location ? `<span>📍 ${session.location}</span>` : ""}
                    </div>
                </div>
                <div class="session-stats">
                    <div class="stat">
                        <div class="stat-value">${session.present_count}</div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">${attendanceRate}%</div>
                        <div class="stat-label">Rate</div>
                    </div>
                </div>
                <div class="session-actions">
                    <button class="btn btn-sm btn-primary" onclick="markAttendance(${session.id})">Mark Attendance</button>
                    <button class="btn btn-sm btn-secondary" onclick="viewSession(${session.id})">View</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteSession(${session.id})">Delete</button>
                </div>
            </div>
        `
    })
    .join("")
}

async function loadStatistics() {
  try {
    const response = await fetch(`../../backend/api/attendance.php?action=statistics&course_id=${courseId}`)
    const data = await response.json()

    if (data.statistics) {
      displayStatistics(data.statistics)
    }
  } catch (error) {
    console.error("[v0] Error loading statistics:", error)
    showError("Failed to load statistics")
  }
}

function displayStatistics(stats) {
  const container = document.getElementById("statisticsGrid")

  container.innerHTML = `
        <div class="stat-card">
            <h3>Total Sessions</h3>
            <div class="value">${stats.total_sessions || 0}</div>
        </div>
        <div class="stat-card">
            <h3>Total Students</h3>
            <div class="value">${stats.total_students || 0}</div>
        </div>
        <div class="stat-card">
            <h3>Average Attendance</h3>
            <div class="value">${Number.parseFloat(stats.avg_attendance_rate || 0).toFixed(1)}%</div>
        </div>
    `
}

async function loadReport() {
  try {
    const response = await fetch(`../../backend/api/attendance.php?action=report&course_id=${courseId}`)
    const data = await response.json()

    if (data.report) {
      displayReport(data.report)
    }
  } catch (error) {
    console.error("[v0] Error loading report:", error)
    showError("Failed to load report")
  }
}

function displayReport(report) {
  const container = document.getElementById("reportContainer")

  if (report.length === 0) {
    container.innerHTML = '<p class="empty-state">No attendance data available.</p>'
    return
  }

  container.innerHTML = `
        <table class="report-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Total Sessions</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Late</th>
                    <th>Excused</th>
                    <th>Attendance %</th>
                </tr>
            </thead>
            <tbody>
                ${report
                  .map((student) => {
                    const percentage = Number.parseFloat(student.attendance_percentage || 0)
                    let percentageClass = "high"
                    if (percentage < 50) percentageClass = "low"
                    else if (percentage < 75) percentageClass = "medium"

                    return `
                        <tr>
                            <td>${student.name}</td>
                            <td>${student.email}</td>
                            <td>${student.total_sessions}</td>
                            <td>${student.present}</td>
                            <td>${student.absent}</td>
                            <td>${student.late}</td>
                            <td>${student.excused}</td>
                            <td>
                                <span class="attendance-percentage ${percentageClass}">
                                    ${percentage.toFixed(1)}%
                                </span>
                            </td>
                        </tr>
                    `
                  })
                  .join("")}
            </tbody>
        </table>
    `
}

function showCreateSession() {
  document.getElementById("sessionDate").value = new Date().toISOString().split("T")[0]
  document.getElementById("sessionTime").value = new Date().toTimeString().slice(0, 5)
  showModal("createSessionModal")
}

async function handleCreateSession(e) {
  e.preventDefault()

  const formData = {
    action: "create_session",
    course_id: courseId,
    session_date: document.getElementById("sessionDate").value,
    session_time: document.getElementById("sessionTime").value,
    duration_minutes: Number.parseInt(document.getElementById("duration").value),
    session_type: document.getElementById("sessionType").value,
    location: document.getElementById("location").value,
    notes: document.getElementById("notes").value,
  }

  try {
    const response = await fetch("../../backend/api/attendance.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(formData),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Session created successfully")
      closeModal("createSessionModal")
      loadSessions()
      document.getElementById("createSessionForm").reset()
    } else {
      showError(data.error || "Failed to create session")
    }
  } catch (error) {
    console.error("[v0] Error creating session:", error)
    showError("Failed to create session")
  }
}

async function markAttendance(sessionId) {
  currentSessionId = sessionId

  try {
    const response = await fetch(`../../backend/api/attendance.php?action=session&session_id=${sessionId}`)
    const data = await response.json()

    if (data.session && data.records) {
      displayMarkingInterface(data.session, data.records)
      showModal("markAttendanceModal")
    }
  } catch (error) {
    console.error("[v0] Error loading session:", error)
    showError("Failed to load session")
  }
}

function displayMarkingInterface(session, records) {
  attendanceRecords = records

  document.getElementById("sessionInfo").innerHTML = `
        <h3>${formatDate(session.session_date)} - ${session.session_time}</h3>
        <p>${session.session_type} ${session.location ? `at ${session.location}` : ""}</p>
    `

  const studentsList = document.getElementById("studentsList")
  studentsList.innerHTML = records
    .map((record) => {
      const initials = record.student_name
        .split(" ")
        .map((n) => n[0])
        .join("")

      return `
            <div class="student-row" data-student-id="${record.user_id}">
                <div class="student-info">
                    <div class="student-avatar">${initials}</div>
                    <div>
                        <div class="student-name">${record.student_name}</div>
                        <div class="student-email">${record.student_email}</div>
                    </div>
                </div>
                <div class="attendance-controls">
                    <button class="status-btn present ${record.status === "present" ? "active" : ""}" 
                            onclick="setStatus(${record.user_id}, 'present')">Present</button>
                    <button class="status-btn absent ${record.status === "absent" ? "active" : ""}" 
                            onclick="setStatus(${record.user_id}, 'absent')">Absent</button>
                    <button class="status-btn late ${record.status === "late" ? "active" : ""}" 
                            onclick="setStatus(${record.user_id}, 'late')">Late</button>
                    <button class="status-btn excused ${record.status === "excused" ? "active" : ""}" 
                            onclick="setStatus(${record.user_id}, 'excused')">Excused</button>
                </div>
            </div>
        `
    })
    .join("")
}

function setStatus(studentId, status) {
  const row = document.querySelector(`[data-student-id="${studentId}"]`)
  const buttons = row.querySelectorAll(".status-btn")

  buttons.forEach((btn) => btn.classList.remove("active"))
  row.querySelector(`.status-btn.${status}`).classList.add("active")

  const record = attendanceRecords.find((r) => r.user_id == studentId)
  if (record) {
    record.status = status
  }
}

function markAllPresent() {
  attendanceRecords.forEach((record) => {
    setStatus(record.user_id, "present")
  })
}

function markAllAbsent() {
  attendanceRecords.forEach((record) => {
    setStatus(record.user_id, "absent")
  })
}

async function saveAttendance() {
  const records = attendanceRecords.map((record) => ({
    student_id: record.user_id,
    status: record.status,
  }))

  try {
    const response = await fetch("../../backend/api/attendance.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "bulk_mark",
        session_id: currentSessionId,
        records: records,
      }),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Attendance saved successfully")
      closeModal("markAttendanceModal")
      loadSessions()
    } else {
      showError(data.error || "Failed to save attendance")
    }
  } catch (error) {
    console.error("[v0] Error saving attendance:", error)
    showError("Failed to save attendance")
  }
}

async function deleteSession(sessionId) {
  if (!confirm("Are you sure you want to delete this session? This action cannot be undone.")) {
    return
  }

  try {
    const response = await fetch(`../../backend/api/attendance.php?session_id=${sessionId}`, {
      method: "DELETE",
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Session deleted successfully")
      loadSessions()
    } else {
      showError(data.error || "Failed to delete session")
    }
  } catch (error) {
    console.error("[v0] Error deleting session:", error)
    showError("Failed to delete session")
  }
}

async function loadSettings() {
  try {
    const response = await fetch(`../../backend/api/attendance.php?action=settings&course_id=${courseId}`)
    const data = await response.json()

    if (data.settings) {
      populateSettings(data.settings)
    }
  } catch (error) {
    console.error("[v0] Error loading settings:", error)
  }
}

function populateSettings(settings) {
  document.getElementById("requiredPercentage").value = settings.required_percentage
  document.getElementById("lateThreshold").value = settings.late_threshold_minutes
  document.getElementById("allowSelfCheckin").checked = settings.allow_self_checkin
  document.getElementById("geofenceEnabled").checked = settings.geofence_enabled

  if (settings.geofence_enabled) {
    document.getElementById("geofenceSettings").style.display = "block"
    document.getElementById("geofenceLatitude").value = settings.geofence_latitude || ""
    document.getElementById("geofenceLongitude").value = settings.geofence_longitude || ""
    document.getElementById("geofenceRadius").value = settings.geofence_radius_meters || 100
  }
}

function showSettings() {
  showModal("settingsModal")
}

async function handleSaveSettings(e) {
  e.preventDefault()

  const formData = {
    action: "update_settings",
    course_id: courseId,
    required_percentage: Number.parseFloat(document.getElementById("requiredPercentage").value),
    late_threshold_minutes: Number.parseInt(document.getElementById("lateThreshold").value),
    allow_self_checkin: document.getElementById("allowSelfCheckin").checked,
    geofence_enabled: document.getElementById("geofenceEnabled").checked,
  }

  if (formData.geofence_enabled) {
    formData.geofence_latitude = Number.parseFloat(document.getElementById("geofenceLatitude").value)
    formData.geofence_longitude = Number.parseFloat(document.getElementById("geofenceLongitude").value)
    formData.geofence_radius_meters = Number.parseInt(document.getElementById("geofenceRadius").value)
  }

  try {
    const response = await fetch("../../backend/api/attendance.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(formData),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Settings saved successfully")
      closeModal("settingsModal")
    } else {
      showError(data.error || "Failed to save settings")
    }
  } catch (error) {
    console.error("[v0] Error saving settings:", error)
    showError("Failed to save settings")
  }
}

function exportReport() {
  window.location.href = `../../backend/api/attendance.php?action=report&course_id=${courseId}&format=csv`
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("en-US", {
    weekday: "short",
    year: "numeric",
    month: "short",
    day: "numeric",
  })
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
