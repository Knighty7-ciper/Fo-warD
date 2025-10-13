// Profile viewing functionality
let profileData = null
const VIEWING_USER_ID = 1 // Declare the VIEWING_USER_ID variable here or import it from another file

document.addEventListener("DOMContentLoaded", () => {
  loadProfile()
  setupTabs()
})

async function loadProfile() {
  try {
    const response = await fetch(`/backend/api/profile.php?user_id=${VIEWING_USER_ID}`)
    const data = await response.json()

    if (!data.success) {
      showError(data.error || "Failed to load profile")
      return
    }

    profileData = data.data
    renderProfile()
  } catch (error) {
    console.error("[v0] Error loading profile:", error)
    showError("An error occurred while loading the profile")
  }
}

function renderProfile() {
  const { user, stats, skills, education, experience, activity, connections, is_own_profile, is_following } =
    profileData

  // Header
  document.getElementById("profileAvatar").src = user.avatar || "/frontend/assets/images/default-avatar.png"
  document.getElementById("profileName").textContent = user.name
  document.getElementById("profileRole").textContent = user.role
  document.getElementById("profileLocation").textContent = user.location || ""

  // Stats
  document.getElementById("coursesCount").textContent = stats.courses_enrolled || 0
  document.getElementById("certificatesCount").textContent = stats.certificates_earned || 0
  document.getElementById("pointsCount").textContent = user.points || 0
  document.getElementById("followersCount").textContent = connections.followers_count || 0
  document.getElementById("followingCount").textContent = connections.following_count || 0

  // Actions
  const actionsHtml = is_own_profile
    ? `<a href="/frontend/profile-edit.php" class="btn btn-primary">Edit Profile</a>`
    : `<button class="btn ${is_following ? "btn-secondary" : "btn-primary"}" onclick="toggleFollow(${user.id})">
               ${is_following ? "Unfollow" : "Follow"}
           </button>
           <a href="/frontend/messages.php?user=${user.id}" class="btn btn-secondary">Message</a>`
  document.getElementById("profileActions").innerHTML = actionsHtml

  // Bio
  document.getElementById("profileBio").textContent = user.bio || "No bio available."

  // Contact Info
  renderContactInfo(user)

  // Skills
  renderSkills(skills)

  // Education
  renderEducation(education)

  // Experience
  renderExperience(experience)

  // Activity
  renderActivity(activity)
}

function renderContactInfo(user) {
  const contactItems = []

  if (user.email) {
    contactItems.push(`<div class="contact-item"><i>📧</i><a href="mailto:${user.email}">${user.email}</a></div>`)
  }
  if (user.phone) {
    contactItems.push(`<div class="contact-item"><i>📱</i><span>${user.phone}</span></div>`)
  }
  if (user.website) {
    contactItems.push(`<div class="contact-item"><i>🌐</i><a href="${user.website}" target="_blank">Website</a></div>`)
  }
  if (user.linkedin) {
    contactItems.push(
      `<div class="contact-item"><i>💼</i><a href="${user.linkedin}" target="_blank">LinkedIn</a></div>`,
    )
  }
  if (user.twitter) {
    contactItems.push(`<div class="contact-item"><i>🐦</i><a href="${user.twitter}" target="_blank">Twitter</a></div>`)
  }
  if (user.github) {
    contactItems.push(`<div class="contact-item"><i>💻</i><a href="${user.github}" target="_blank">GitHub</a></div>`)
  }

  document.getElementById("contactInfo").innerHTML =
    contactItems.length > 0 ? contactItems.join("") : '<p class="empty-message">No contact information available.</p>'
}

function renderSkills(skills) {
  if (!skills || skills.length === 0) {
    document.getElementById("skillsList").innerHTML = '<p class="empty-message">No skills added yet.</p>'
    return
  }

  const skillsHtml = skills
    .map(
      (skill) => `
        <div class="skill-tag">
            <span>${skill.skill_name}</span>
            <span class="proficiency">${skill.proficiency_level}</span>
        </div>
    `,
    )
    .join("")

  document.getElementById("skillsList").innerHTML = skillsHtml
}

function renderEducation(education) {
  if (!education || education.length === 0) {
    document.getElementById("educationList").innerHTML = '<p class="empty-message">No education history added yet.</p>'
    return
  }

  const educationHtml = education
    .map(
      (edu) => `
        <div class="education-item">
            <h3>${edu.institution}</h3>
            <div class="subtitle">${edu.degree} in ${edu.field_of_study}</div>
            <div class="date-range">${formatDateRange(edu.start_date, edu.end_date, edu.is_current)}</div>
            ${edu.description ? `<div class="description">${edu.description}</div>` : ""}
        </div>
    `,
    )
    .join("")

  document.getElementById("educationList").innerHTML = educationHtml
}

function renderExperience(experience) {
  if (!experience || experience.length === 0) {
    document.getElementById("experienceList").innerHTML = '<p class="empty-message">No work experience added yet.</p>'
    return
  }

  const experienceHtml = experience
    .map(
      (exp) => `
        <div class="experience-item">
            <h3>${exp.position}</h3>
            <div class="subtitle">${exp.company}${exp.location ? ` • ${exp.location}` : ""}</div>
            <div class="date-range">${formatDateRange(exp.start_date, exp.end_date, exp.is_current)}</div>
            ${exp.description ? `<div class="description">${exp.description}</div>` : ""}
        </div>
    `,
    )
    .join("")

  document.getElementById("experienceList").innerHTML = experienceHtml
}

function renderActivity(activity) {
  if (!activity || activity.length === 0) {
    document.getElementById("activityFeed").innerHTML = '<p class="empty-message">No recent activity.</p>'
    return
  }

  const activityHtml = activity
    .map(
      (item) => `
        <div class="activity-item">
            <div class="activity-icon">${getActivityIcon(item.activity_type)}</div>
            <div class="activity-content">
                <div class="description">${item.description}</div>
                <div class="time">${timeAgo(item.created_at)}</div>
            </div>
        </div>
    `,
    )
    .join("")

  document.getElementById("activityFeed").innerHTML = activityHtml
}

function setupTabs() {
  const tabBtns = document.querySelectorAll(".tab-btn")
  const tabContents = document.querySelectorAll(".tab-content")

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const tabName = btn.dataset.tab

      tabBtns.forEach((b) => b.classList.remove("active"))
      tabContents.forEach((c) => c.classList.remove("active"))

      btn.classList.add("active")
      document.getElementById(`${tabName}Tab`).classList.add("active")
    })
  })
}

async function toggleFollow(userId) {
  const isFollowing = profileData.is_following
  const action = isFollowing ? "unfollow" : "follow"

  try {
    const formData = new FormData()
    formData.append("action", action)
    formData.append("user_id", userId)

    const response = await fetch("/backend/api/profile.php", {
      method: "POST",
      body: formData,
    })

    const data = await response.json()

    if (data.success) {
      profileData.is_following = !isFollowing
      profileData.connections.followers_count += isFollowing ? -1 : 1
      renderProfile()
    } else {
      showError(data.error || "Failed to update follow status")
    }
  } catch (error) {
    console.error("[v0] Error toggling follow:", error)
    showError("An error occurred")
  }
}

function formatDateRange(startDate, endDate, isCurrent) {
  const start = startDate ? new Date(startDate).toLocaleDateString("en-US", { month: "short", year: "numeric" }) : ""
  const end = isCurrent
    ? "Present"
    : endDate
      ? new Date(endDate).toLocaleDateString("en-US", { month: "short", year: "numeric" })
      : ""

  return `${start} - ${end}`
}

function getActivityIcon(type) {
  const icons = {
    course_enrolled: "📚",
    lesson_completed: "✅",
    quiz_taken: "📝",
    assignment_submitted: "📄",
    certificate_earned: "🎓",
    forum_post: "💬",
    message_sent: "✉️",
  }
  return icons[type] || "📌"
}

function timeAgo(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.floor((now - date) / 1000)

  if (seconds < 60) return "just now"
  if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)} days ago`

  return date.toLocaleDateString()
}

function showError(message) {
  alert(message)
}
