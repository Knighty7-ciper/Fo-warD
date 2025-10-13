let courseId // Declare the courseId variable

document.addEventListener("DOMContentLoaded", () => {
  courseId = new URLSearchParams(window.location.search).get("course_id") // Assign the courseId variable
  loadAttendanceSummary()
  loadAttendanceRecords()
  checkSelfCheckinAvailability()
})

async function loadAttendanceSummary() {
  try {
    const response = await fetch(`../../backend/api/attendance.php?action=statistics&course_id=${courseId}`)
    const data = await response.json()

    if (data.statistics) {
      displaySummary(data.statistics)
    }
  } catch (error) {
    console.error("[v0] Error loading summary:", error)
    showError("Failed to load attendance summary")
  }
}

function displaySummary(stats) {
  const container = document.getElementById("attendanceSummary")
  const percentage = Number.parseFloat(stats.attendance_percentage || 0)

  let progressClass = "high"
  if (percentage < 50) progressClass = "low"
  else if (percentage < 75) progressClass = "medium"

  container.innerHTML = `
        <div class="summary-grid">
            <div class="summary-stat">
                <div class="value">${stats.total_sessions || 0}</div>
                <div class="label">Total Sessions</div>
            </div>
            <div class="summary-stat">
                <div class="value">${stats.present || 0}</div>
                <div class="label">Present</div>
            </div>
            <div class="summary-stat">
                <div class="value">${stats.absent || 0}</div>
                <div class="label">Absent</div>
            </div>
            <div class="summary-stat">
                <div class="value">${stats.late || 0}</div>
                <div class="label">Late</div>
            </div>
        </div>
        <div>
            <h3>Overall Attendance: ${percentage.toFixed(1)}%</h3>
            <div class="progress-bar">
                <div class="progress-fill ${progressClass}" style="width: ${percentage}%"></div>
            </div>
        </div>
    `
}

async function loadAttendanceRecords() {
  try {
    const response = await fetch(`../../backend/api/attendance.php?action=records&course_id=${courseId}`)
    const data = await response.json()

    if (data.records) {
      displayRecords(data.records)
    }
  } catch (error) {
    console.error("[v0] Error loading records:", error)
    showError("Failed to load attendance records")
  }
}

function displayRecords(records) {
  const container = document.getElementById("recordsList")

  if (records.length === 0) {
    container.innerHTML = '<p class="empty-state">No attendance records yet.</p>'
    return
  }

  container.innerHTML = records
    .map(
      (record) => `
        <div class="record-item ${record.status}">
            <div class="record-info">
                <h4>${formatDate(record.session_date)} - ${record.session_time}</h4>
                <div class="record-meta">
                    ${record.session_type} ${record.location ? `at ${record.location}` : ""}
                </div>
            </div>
            <div class="record-status ${record.status}">
                ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
            </div>
        </div>
    `,
    )
    .join("")
}

async function checkSelfCheckinAvailability() {
  try {
    const response = await fetch(`../../backend/api/attendance.php?action=settings&course_id=${courseId}`)
    const data = await response.json()

    if (data.settings && data.settings.allow_self_checkin) {
      document.getElementById("selfCheckinSection").style.display = "block"
    }
  } catch (error) {
    console.error("[v0] Error checking self check-in:", error)
  }
}

async function selfCheckin() {
  if (!navigator.geolocation) {
    showError("Geolocation is not supported by your browser")
    return
  }

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      try {
        const response = await fetch("../../backend/api/attendance.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "self_checkin",
            session_id: getCurrentSessionId(),
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          }),
        })

        const data = await response.json()

        if (data.success) {
          showSuccess("Checked in successfully!")
          loadAttendanceSummary()
          loadAttendanceRecords()
        } else {
          showError(data.error || "Failed to check in")
        }
      } catch (error) {
        console.error("[v0] Error checking in:", error)
        showError("Failed to check in")
      }
    },
    (error) => {
      showError("Unable to get your location. Please enable location services.")
    },
  )
}

function getCurrentSessionId() {
  return new URLSearchParams(window.location.search).get("session_id")
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

function showSuccess(message) {
  alert(message)
}

function showError(message) {
  alert("Error: " + message)
}
