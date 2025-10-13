// Profile editing functionality
let currentProfile = null

document.addEventListener("DOMContentLoaded", () => {
  loadCurrentProfile()
  setupForms()
  setupAvatarUpload()
})

async function loadCurrentProfile() {
  try {
    const response = await fetch("/backend/api/profile.php")
    const data = await response.json()

    if (!data.success) {
      showError(data.error || "Failed to load profile")
      return
    }

    currentProfile = data.data
    populateForm()
  } catch (error) {
    console.error("[v0] Error loading profile:", error)
    showError("An error occurred while loading the profile")
  }
}

function populateForm() {
  const { user, skills, education, experience } = currentProfile

  // Basic info
  document.getElementById("name").value = user.name || ""
  document.getElementById("email").value = user.email || ""
  document.getElementById("bio").value = user.bio || ""
  document.getElementById("phone").value = user.phone || ""
  document.getElementById("location").value = user.location || ""
  document.getElementById("date_of_birth").value = user.date_of_birth || ""
  document.getElementById("gender").value = user.gender || ""

  // Avatar
  if (user.avatar) {
    document.getElementById("currentAvatar").src = user.avatar
  }

  // Social links
  document.getElementById("website").value = user.website || ""
  document.getElementById("linkedin").value = user.linkedin || ""
  document.getElementById("twitter").value = user.twitter || ""
  document.getElementById("github").value = user.github || ""

  // Privacy
  document.getElementById("profile_visibility").value = user.profile_visibility || "public"
  document.getElementById("email_notifications").checked = user.email_notifications
  document.getElementById("push_notifications").checked = user.push_notifications

  // Skills
  renderEditSkills(skills)

  // Education
  renderEditEducation(education)

  // Experience
  renderEditExperience(experience)
}

function setupForms() {
  // Basic info form
  document.getElementById("basicInfoForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await updateProfile(new FormData(e.target))
  })

  // Social links form
  document.getElementById("socialLinksForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await updateProfile(new FormData(e.target))
  })

  // Privacy form
  document.getElementById("privacyForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await updateProfile(new FormData(e.target))
  })

  // Add skill form
  document.getElementById("addSkillForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await addSkill(new FormData(e.target))
    e.target.reset()
  })

  // Add education form
  document.getElementById("addEducationForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await addEducation(new FormData(e.target))
    closeModal("addEducationModal")
    e.target.reset()
  })

  // Add experience form
  document.getElementById("addExperienceForm").addEventListener("submit", async (e) => {
    e.preventDefault()
    await addExperience(new FormData(e.target))
    closeModal("addExperienceModal")
    e.target.reset()
  })
}

function setupAvatarUpload() {
  const avatarInput = document.getElementById("avatarInput")
  const uploadBtn = document.getElementById("uploadAvatarBtn")
  const avatarForm = document.getElementById("avatarUploadForm")

  avatarInput.addEventListener("change", () => {
    if (avatarInput.files.length > 0) {
      uploadBtn.style.display = "inline-block"
    }
  })

  avatarForm.addEventListener("submit", async (e) => {
    e.preventDefault()
    await uploadAvatar(new FormData(e.target))
  })
}

async function updateProfile(formData) {
  try {
    const jsonData = {}
    formData.forEach((value, key) => {
      if (key === "email_notifications" || key === "push_notifications") {
        jsonData[key] = document.getElementById(key).checked
      } else {
        jsonData[key] = value
      }
    })

    const response = await fetch("/backend/api/profile.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(jsonData),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Profile updated successfully")
    } else {
      showError(data.error || "Failed to update profile")
    }
  } catch (error) {
    console.error("[v0] Error updating profile:", error)
    showError("An error occurred")
  }
}

async function uploadAvatar(formData) {
  formData.append("action", "upload_avatar")

  try {
    const response = await fetch("/backend/api/profile.php", {
      method: "POST",
      body: formData,
    })

    const data = await response.json()

    if (data.success) {
      document.getElementById("currentAvatar").src = data.data.avatar_url
      document.getElementById("uploadAvatarBtn").style.display = "none"
      document.getElementById("avatarInput").value = ""
      showSuccess("Avatar uploaded successfully")
    } else {
      showError(data.error || "Failed to upload avatar")
    }
  } catch (error) {
    console.error("[v0] Error uploading avatar:", error)
    showError("An error occurred")
  }
}

async function addSkill(formData) {
  formData.append("action", "add_skill")

  try {
    const response = await fetch("/backend/api/profile.php", {
      method: "POST",
      body: formData,
    })

    const data = await response.json()

    if (data.success) {
      await loadCurrentProfile()
      showSuccess("Skill added successfully")
    } else {
      showError(data.error || "Failed to add skill")
    }
  } catch (error) {
    console.error("[v0] Error adding skill:", error)
    showError("An error occurred")
  }
}

async function addEducation(formData) {
  formData.append("action", "add_education")

  try {
    const response = await fetch("/backend/api/profile.php", {
      method: "POST",
      body: formData,
    })

    const data = await response.json()

    if (data.success) {
      await loadCurrentProfile()
      showSuccess("Education added successfully")
    } else {
      showError(data.error || "Failed to add education")
    }
  } catch (error) {
    console.error("[v0] Error adding education:", error)
    showError("An error occurred")
  }
}

async function addExperience(formData) {
  formData.append("action", "add_experience")

  try {
    const response = await fetch("/backend/api/profile.php", {
      method: "POST",
      body: formData,
    })

    const data = await response.json()

    if (data.success) {
      await loadCurrentProfile()
      showSuccess("Experience added successfully")
    } else {
      showError(data.error || "Failed to add experience")
    }
  } catch (error) {
    console.error("[v0] Error adding experience:", error)
    showError("An error occurred")
  }
}

async function removeItem(type, id) {
  if (!confirm("Are you sure you want to remove this item?")) return

  try {
    const response = await fetch(`/backend/api/profile.php?type=${type}&id=${id}`, {
      method: "DELETE",
    })

    const data = await response.json()

    if (data.success) {
      await loadCurrentProfile()
      showSuccess("Item removed successfully")
    } else {
      showError(data.error || "Failed to remove item")
    }
  } catch (error) {
    console.error("[v0] Error removing item:", error)
    showError("An error occurred")
  }
}

function renderEditSkills(skills) {
  if (!skills || skills.length === 0) {
    document.getElementById("editSkillsList").innerHTML = '<p class="empty-message">No skills added yet.</p>'
    return
  }

  const skillsHtml = skills
    .map(
      (skill) => `
        <div class="skill-tag">
            <span>${skill.skill_name} (${skill.proficiency_level})</span>
            <button onclick="removeItem('skill', ${skill.id})" type="button">×</button>
        </div>
    `,
    )
    .join("")

  document.getElementById("editSkillsList").innerHTML = skillsHtml
}

function renderEditEducation(education) {
  if (!education || education.length === 0) {
    document.getElementById("editEducationList").innerHTML = '<p class="empty-message">No education added yet.</p>'
    return
  }

  const educationHtml = education
    .map(
      (edu) => `
        <div class="education-item">
            <button onclick="removeItem('education', ${edu.id})" type="button">×</button>
            <h3>${edu.institution}</h3>
            <div class="subtitle">${edu.degree} in ${edu.field_of_study}</div>
            <div class="date-range">${formatDateRange(edu.start_date, edu.end_date, edu.is_current)}</div>
        </div>
    `,
    )
    .join("")

  document.getElementById("editEducationList").innerHTML = educationHtml
}

function renderEditExperience(experience) {
  if (!experience || experience.length === 0) {
    document.getElementById("editExperienceList").innerHTML = '<p class="empty-message">No experience added yet.</p>'
    return
  }

  const experienceHtml = experience
    .map(
      (exp) => `
        <div class="experience-item">
            <button onclick="removeItem('experience', ${exp.id})" type="button">×</button>
            <h3>${exp.position}</h3>
            <div class="subtitle">${exp.company}</div>
            <div class="date-range">${formatDateRange(exp.start_date, exp.end_date, exp.is_current)}</div>
        </div>
    `,
    )
    .join("")

  document.getElementById("editExperienceList").innerHTML = experienceHtml
}

function showAddEducationModal() {
  document.getElementById("addEducationModal").classList.add("active")
}

function showAddExperienceModal() {
  document.getElementById("addExperienceModal").classList.add("active")
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("active")
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

function showSuccess(message) {
  alert(message)
}

function showError(message) {
  alert(message)
}
