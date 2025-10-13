// Student grades viewing
document.addEventListener("DOMContentLoaded", () => {
  calculateOverallGPA()
})

function calculateOverallGPA() {
  // This would calculate based on all course grades
  // For now, showing placeholder
  document.getElementById("overallGPA").textContent = "3.45"
}

async function viewCourseGrades(courseId) {
  try {
    const response = await fetch(`/backend/api/gradebook.php?action=view&course_id=${courseId}`)
    const data = await response.json()

    if (!data.success) {
      showError(data.error || "Failed to load grades")
      return
    }

    renderCourseDetail(data.data)
    document.getElementById("courseDetailModal").classList.add("active")
  } catch (error) {
    console.error("[v0] Error loading course grades:", error)
    showError("An error occurred")
  }
}

function renderCourseDetail(data) {
  const { grades, course_grade } = data

  // Update summary
  if (course_grade) {
    document.getElementById("courseCurrentGrade").textContent =
      `${course_grade.current_percentage}% (${course_grade.current_letter_grade})`
  }

  // Render grades table
  const tbody = document.getElementById("courseGradesBody")

  if (!grades || grades.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="loading-cell">No grades available yet</td></tr>'
    return
  }

  tbody.innerHTML = grades
    .map(
      (grade) => `
        <tr>
            <td>${grade.title}</td>
            <td>${grade.item_type}</td>
            <td>${grade.due_date ? new Date(grade.due_date).toLocaleDateString() : "--"}</td>
            <td>${grade.points_earned || "--"}/${grade.max_points}</td>
            <td>${grade.percentage ? grade.percentage.toFixed(1) + "%" : "--"}</td>
            <td><span class="grade-letter grade-${(grade.letter_grade || "").toLowerCase()}">${grade.letter_grade || "--"}</span></td>
            <td>${getGradeStatus(grade)}</td>
        </tr>
    `,
    )
    .join("")
}

function getGradeStatus(grade) {
  if (grade.is_excused) return '<span class="badge badge-info">Excused</span>'
  if (grade.is_missing) return '<span class="badge badge-warning">Missing</span>'
  if (grade.is_late) return '<span class="badge badge-danger">Late</span>'
  if (grade.graded_at) return '<span class="badge badge-success">Graded</span>'
  return '<span class="badge badge-secondary">Pending</span>'
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("active")
}

function showError(message) {
  alert(message)
}
