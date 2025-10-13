import { Chart } from "@/components/ui/chart"
// Chart.js is loaded globally via CDN in the HTML file
// No imports needed - Chart is available as a global object

let currentTab = "overview"
let currentPeriod = "30days"
const charts = {}

document.addEventListener("DOMContentLoaded", () => {
  loadOverviewStats()
})

function switchTab(tab) {
  currentTab = tab

  // Update tab buttons
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.tab === tab)
  })

  // Update tab content
  document.querySelectorAll(".tab-content").forEach((content) => {
    content.classList.remove("active")
  })
  document.getElementById(`${tab}-tab`).classList.add("active")

  // Load data for the tab
  switch (tab) {
    case "overview":
      loadOverviewStats()
      break
    case "enrollment":
      loadEnrollmentStats()
      break
    case "courses":
      loadCoursePerformance()
      break
    case "students":
      loadStudentProgress()
      break
    case "revenue":
      loadRevenueStats()
      break
    case "activity":
      loadActivityStats()
      break
  }
}

function updatePeriod() {
  currentPeriod = document.getElementById("periodFilter").value
  switchTab(currentTab)
}

function loadOverviewStats() {
  fetch("/backend/api/reports.php?action=overview")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const stats = data.stats
        document.getElementById("totalUsers").textContent = stats.total_users
        document.getElementById("totalCourses").textContent = stats.total_courses
        document.getElementById("totalEnrollments").textContent = stats.total_enrollments
        document.getElementById("activeStudents").textContent = stats.active_students
        document.getElementById("completedCourses").textContent = stats.completed_courses
        document.getElementById("avgCompletionRate").textContent = stats.avg_completion_rate + "%"
        if (document.getElementById("totalRevenue")) {
          document.getElementById("totalRevenue").textContent = "$" + formatNumber(stats.total_revenue)
        }
        document.getElementById("newUsersMonth").textContent = stats.new_users_this_month
      }
    })
    .catch((err) => console.error("Error loading overview:", err))
}

function loadEnrollmentStats() {
  fetch(`/backend/api/reports.php?action=enrollment&period=${currentPeriod}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        renderEnrollmentChart(data.enrollment_trend)
        renderTopCourses(data.top_courses)
        renderEnrollmentStatusChart(data.enrollment_by_status)
      }
    })
    .catch((err) => console.error("Error loading enrollment stats:", err))
}

function renderEnrollmentChart(data) {
  const ctx = document.getElementById("enrollmentChart")
  if (charts.enrollment) charts.enrollment.destroy()

  charts.enrollment = new Chart(ctx, {
    type: "line",
    data: {
      labels: data.map((d) => d.date),
      datasets: [
        {
          label: "Enrollments",
          data: data.map((d) => d.count),
          borderColor: "#3b82f6",
          backgroundColor: "rgba(59, 130, 246, 0.1)",
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  })
}

function renderTopCourses(courses) {
  const container = document.getElementById("topCoursesList")
  let html = ""

  courses.forEach((course) => {
    html += '<div class="course-item">'
    html += `<span class="course-name">${course.title}</span>`
    html += `<span class="course-count">${course.enrollment_count} students</span>`
    html += "</div>"
  })

  container.innerHTML = html || '<p class="text-muted">No data available</p>'
}

function renderEnrollmentStatusChart(data) {
  const ctx = document.getElementById("enrollmentStatusChart")
  if (charts.enrollmentStatus) charts.enrollmentStatus.destroy()

  charts.enrollmentStatus = new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: data.map((d) => d.status),
      datasets: [
        {
          data: data.map((d) => d.count),
          backgroundColor: ["#3b82f6", "#10b981", "#f59e0b", "#ef4444"],
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
    },
  })
}

function loadCoursePerformance() {
  fetch("/backend/api/reports.php?action=course-performance")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        renderCoursePerformanceTable(data.courses)
      }
    })
    .catch((err) => console.error("Error loading course performance:", err))
}

function renderCoursePerformanceTable(courses) {
  const tbody = document.querySelector("#coursePerformanceTable tbody")
  let html = ""

  courses.forEach((course) => {
    const completionRate = course.completion_rate || 0
    html += "<tr>"
    html += `<td>${course.title}</td>`
    html += `<td>${course.instructor_name || "N/A"}</td>`
    html += `<td>${course.total_students || 0}</td>`
    html += `<td>${Math.round(course.avg_progress || 0)}%</td>`
    html += `<td>${course.completed_count || 0}</td>`
    html += `<td>${Math.round(completionRate)}%</td>`
    html += `<td><button class="btn btn-sm" onclick="viewCourseDetails(${course.id})">View Details</button></td>`
    html += "</tr>"
  })

  tbody.innerHTML = html || '<tr><td colspan="7" class="text-center">No data available</td></tr>'
}

function loadStudentProgress() {
  fetch("/backend/api/reports.php?action=student-progress")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        renderStudentProgressTable(data.students)
      }
    })
    .catch((err) => console.error("Error loading student progress:", err))
}

function renderStudentProgressTable(students) {
  const tbody = document.querySelector("#studentProgressTable tbody")
  let html = ""

  students.forEach((student) => {
    html += "<tr>"
    html += `<td>${student.name}</td>`
    html += `<td>${student.email}</td>`
    html += `<td>${student.enrolled_courses || 0}</td>`
    html += `<td>${Math.round(student.avg_progress || 0)}%</td>`
    html += `<td>${student.completed_courses || 0}</td>`
    html += `<td>${student.last_activity ? new Date(student.last_activity).toLocaleDateString() : "Never"}</td>`
    html += `<td><button class="btn btn-sm" onclick="viewStudentDetails(${student.id})">View Details</button></td>`
    html += "</tr>"
  })

  tbody.innerHTML = html || '<tr><td colspan="7" class="text-center">No data available</td></tr>'
}

function loadRevenueStats() {
  fetch(`/backend/api/reports.php?action=revenue&period=${currentPeriod}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        document.getElementById("revenueTotalAmount").textContent = "$" + formatNumber(data.total_stats.total_revenue)
        document.getElementById("revenueAvgTransaction").textContent =
          "$" + formatNumber(data.total_stats.avg_transaction)
        document.getElementById("revenueTotalTransactions").textContent = data.total_stats.total_transactions

        renderRevenueChart(data.revenue_trend)
        renderTopRevenueCourses(data.revenue_by_course)
        renderRevenueMethodChart(data.revenue_by_method)
      }
    })
    .catch((err) => console.error("Error loading revenue stats:", err))
}

function renderRevenueChart(data) {
  const ctx = document.getElementById("revenueChart")
  if (charts.revenue) charts.revenue.destroy()

  charts.revenue = new Chart(ctx, {
    type: "bar",
    data: {
      labels: data.map((d) => d.date),
      datasets: [
        {
          label: "Revenue",
          data: data.map((d) => d.revenue),
          backgroundColor: "#10b981",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: (value) => "$" + value,
          },
        },
      },
    },
  })
}

function renderTopRevenueCourses(courses) {
  const container = document.getElementById("topRevenueCourses")
  let html = ""

  courses.forEach((course) => {
    html += '<div class="course-item">'
    html += `<span class="course-name">${course.title}</span>`
    html += `<span class="course-count">$${formatNumber(course.total_revenue)}</span>`
    html += "</div>"
  })

  container.innerHTML = html || '<p class="text-muted">No data available</p>'
}

function renderRevenueMethodChart(data) {
  const ctx = document.getElementById("revenueMethodChart")
  if (charts.revenueMethod) charts.revenueMethod.destroy()

  charts.revenueMethod = new Chart(ctx, {
    type: "pie",
    data: {
      labels: data.map((d) => d.payment_method),
      datasets: [
        {
          data: data.map((d) => d.total),
          backgroundColor: ["#3b82f6", "#10b981", "#f59e0b"],
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
    },
  })
}

function loadActivityStats() {
  fetch(`/backend/api/reports.php?action=activity&period=${currentPeriod}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        renderActivityChart(data.activity_trend)
        renderActivityTypeChart(data.activity_by_type)
        renderActiveUsers(data.active_users)
      }
    })
    .catch((err) => console.error("Error loading activity stats:", err))
}

function renderActivityChart(data) {
  const ctx = document.getElementById("activityChart")
  if (charts.activity) charts.activity.destroy()

  charts.activity = new Chart(ctx, {
    type: "line",
    data: {
      labels: data.map((d) => d.date),
      datasets: [
        {
          label: "Activities",
          data: data.map((d) => d.count),
          borderColor: "#8b5cf6",
          backgroundColor: "rgba(139, 92, 246, 0.1)",
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  })
}

function renderActivityTypeChart(data) {
  const ctx = document.getElementById("activityTypeChart")
  if (charts.activityType) charts.activityType.destroy()

  charts.activityType = new Chart(ctx, {
    type: "bar",
    data: {
      labels: data.map((d) => d.activity_type),
      datasets: [
        {
          label: "Count",
          data: data.map((d) => d.count),
          backgroundColor: "#8b5cf6",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      indexAxis: "y",
      plugins: {
        legend: {
          display: false,
        },
      },
    },
  })
}

function renderActiveUsers(users) {
  const container = document.getElementById("activeUsersList")
  let html = ""

  users.forEach((user) => {
    html += '<div class="user-item">'
    html += `<div>`
    html += `<div class="user-name">${user.name}</div>`
    html += `<div class="user-count">${user.role}</div>`
    html += `</div>`
    html += `<span class="badge badge-info">${user.activity_count} activities</span>`
    html += "</div>"
  })

  container.innerHTML = html || '<p class="text-muted">No data available</p>'
}

function formatNumber(num) {
  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(num)
}

function exportReport() {
  const type = currentTab === "overview" ? "enrollments" : currentTab
  window.location.href = `/backend/api/reports.php?action=export&type=${type}`
}

function viewCourseDetails(courseId) {
  window.location.href = `/frontend/courses/view.php?id=${courseId}`
}

function viewStudentDetails(studentId) {
  window.location.href = `/frontend/admin/user-details.php?id=${studentId}`
}
