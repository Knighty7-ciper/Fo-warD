// Gradebook management for teachers
let currentCourseId = null
let gradebookData = null

document.addEventListener("DOMContentLoaded", () => {
  setupCourseSelector()
})

function setupCourseSelector() {
  const courseSelect = document.getElementById("courseSelect")
  courseSelect.addEventListener("change", (e) => {
    currentCourseId = e.target.value
    if (currentCourseId) {
      loadGradebook(currentCourseId)
    } else {
      document.getElementById("gradebookContent").style.display = "none"
    }
  })
}

async function loadGradebook(courseId) {
  try {
    const response = await fetch(`/backend/api/gradebook.php?action=view&course_id=${courseId}`)
    const data = await response.json()

    if (!data.success) {
      showError(data.error || "Failed to load gradebook")
      return
    }

    gradebookData = data.data
    renderGradebook()
    document.getElementById("gradebookContent").style.display = "block"
  } catch (error) {
    console.error("[v0] Error loading gradebook:", error)
    showError("An error occurred while loading the gradebook")
  }
}

function renderGradebook() {
  const { students, grade_items, grades, categories } = gradebookData

  // Update stats
  document.getElementById("totalStudents").textContent = students.length
  document.getElementById("totalItems").textContent = grade_items.length

  // Calculate average and passing rate
  let totalPercentage = 0
  let passingCount = 0
  students.forEach((student) => {
    if (student.current_percentage) {
      totalPercentage += Number.parseFloat(student.current_percentage)
      if (student.current_percentage >= 60) passingCount++
    }
  })

  const avgGrade = students.length > 0 ? (totalPercentage / students.length).toFixed(1) : 0
  const passingRate = students.length > 0 ? ((passingCount / students.length) * 100).toFixed(1) : 0

  document.getElementById("averageGrade").textContent = avgGrade + "%"
  document.getElementById("passingRate").textContent = passingRate + "%"

  // Render table header
  const headerRow = document.getElementById("tableHeader")
  headerRow.innerHTML = '<th class="sticky-col">Student</th>'

  grade_items.forEach((item) => {
    headerRow.innerHTML += `
            <th title="${item.title}">
                ${truncate(item.title, 15)}<br>
                <small>(${item.max_points} pts)</small>
            </th>
        `
  })

  headerRow.innerHTML += '<th class="final-grade-col">Final Grade</th><th class="actions-col">Actions</th>'

  // Render table body
  const tbody = document.getElementById("gradebookBody")
  tbody.innerHTML = ""

  if (students.length === 0) {
    tbody.innerHTML = '<tr><td colspan="100" class="loading-cell">No students enrolled</td></tr>'
    return
  }

  students.forEach((student) => {
    const row = document.createElement("tr")

    // Student name column
    row.innerHTML = `
            <td class="sticky-col">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="${student.avatar || "/frontend/assets/images/default-avatar.png"}" 
                         alt="${student.name}" 
                         style="width: 32px; height: 32px; border-radius: 50%;">
                    <div>
                        <div style="font-weight: 600;">${student.name}</div>
                        <div style="font-size: 12px; color: #718096;">${student.email}</div>
                    </div>
                </div>
            </td>
        `

    // Grade columns
    grade_items.forEach((item) => {
      const grade = grades[student.id]?.[item.id]
      let gradeHtml = ""

      if (grade) {
        if (grade.is_excused) {
          gradeHtml = '<span class="grade-value grade-excused">EXC</span>'
        } else if (grade.is_missing) {
          gradeHtml = '<span class="grade-value grade-missing">MISS</span>'
        } else {
          const letterClass = grade.letter_grade
            ? `grade-${grade.letter_grade.toLowerCase().replace("+", "").replace("-", "")}`
            : ""
          gradeHtml = `<span class="grade-value ${letterClass}">${grade.points_earned}/${item.max_points}</span>`
        }
      } else {
        gradeHtml = '<span class="grade-value grade-missing">--</span>'
      }

      row.innerHTML += `<td class="grade-cell" onclick="openGradeModal(${student.id}, ${item.id})">${gradeHtml}</td>`
    })

    // Final grade column
    const finalGrade = student.current_percentage || "--"
    const letterGrade = student.current_letter_grade || ""
    const letterClass = letterGrade ? `grade-${letterGrade.toLowerCase().replace("+", "").replace("-", "")}` : ""

    row.innerHTML += `
            <td class="final-grade-col">
                <span class="grade-value ${letterClass}">${finalGrade}${finalGrade !== "--" ? "%" : ""} ${letterGrade}</span>
            </td>
        `

    // Actions column
    row.innerHTML += `
            <td class="actions-col">
                <button class="btn btn-sm btn-secondary" onclick="viewStudentDetail(${student.id})">Details</button>
            </td>
        `

    tbody.appendChild(row)
  })
}

function openGradeModal(studentId, gradeItemId) {
  const student = gradebookData.students.find((s) => s.id === studentId)
  const item = gradebookData.grade_items.find((i) => i.id === gradeItemId)
  const grade = gradebookData.grades[studentId]?.[gradeItemId]

  document.getElementById("gradeStudentId").value = studentId
  document.getElementById("gradeItemId").value = gradeItemId
  document.getElementById("gradeStudentName").textContent = student.name
  document.getElementById("gradeItemName").textContent = item.title
  document.getElementById("gradeMaxPoints").textContent = item.max_points

  if (grade) {
    document.getElementById("gradePoints").value = grade.points_earned || ""
    document.getElementById("gradeFeedback").value = grade.feedback || ""
    document.getElementById("gradeExcused").checked = grade.is_excused
    document.getElementById("gradeMissing").checked = grade.is_missing
  } else {
    document.getElementById("gradePoints").value = ""
    document.getElementById("gradeFeedback").value = ""
    document.getElementById("gradeExcused").checked = false
    document.getElementById("gradeMissing").checked = false
  }

  document.getElementById("gradeModal").classList.add("active")
}

document.getElementById("gradeForm")?.addEventListener("submit", async (e) => {
  e.preventDefault()

  const formData = new FormData()
  formData.append("action", "update_grade")
  formData.append("student_id", document.getElementById("gradeStudentId").value)
  formData.append("grade_item_id", document.getElementById("gradeItemId").value)
  formData.append("points_earned", document.getElementById("gradePoints").value)
  formData.append("feedback", document.getElementById("gradeFeedback").value)
  if (document.getElementById("gradeExcused").checked) formData.append("is_excused", "1")
  if (document.getElementById("gradeMissing").checked) formData.append("is_missing", "1")

  try {
    const response = await fetch("/backend/api/gradebook.php", {
      method: "POST",
      body: formData,
    })

    const data = await response.json()

    if (data.success) {
      closeModal("gradeModal")
      loadGradebook(currentCourseId)
      showSuccess("Grade updated successfully")
    } else {
      showError(data.error || "Failed to update grade")
    }
  } catch (error) {
    console.error("[v0] Error updating grade:", error)
    showError("An error occurred")
  }
})

function showCategoryModal() {
  loadCategories()
  document.getElementById("categoryModal").classList.add("active")
}

function showGradeItemModal() {
  loadCategoriesForSelect()
  document.getElementById("gradeItemModal").classList.add("active")
}

async function loadCategories() {
  // Load and display categories
  const categoriesHtml = gradebookData.categories
    .map(
      (cat) => `
        <div class="category-item">
            <div>
                <strong>${cat.name}</strong>
                <span style="color: #718096; margin-left: 10px;">${cat.weight}%</span>
            </div>
            <button class="btn btn-sm btn-danger" onclick="deleteCategory(${cat.id})">Delete</button>
        </div>
    `,
    )
    .join("")

  document.getElementById("categoriesList").innerHTML = categoriesHtml || "<p>No categories yet</p>"
}

function loadCategoriesForSelect() {
  const select = document.getElementById("itemCategory")
  select.innerHTML = '<option value="">No Category</option>'

  gradebookData.categories.forEach((cat) => {
    select.innerHTML += `<option value="${cat.id}">${cat.name}</option>`
  })
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("active")
}

function truncate(str, length) {
  return str.length > length ? str.substring(0, length) + "..." : str
}

function showSuccess(message) {
  alert(message)
}

function showError(message) {
  alert(message)
}

async function exportGradebook() {
  window.location.href = `/backend/api/gradebook.php?action=export&course_id=${currentCourseId}`
}

function viewStudentDetail(studentId) {
  window.location.href = `/frontend/teacher/student-detail.php?student_id=${studentId}&course_id=${currentCourseId}`
}
