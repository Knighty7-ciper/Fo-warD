document.addEventListener("DOMContentLoaded", () => {
  loadPreferences()
  setupForm()
})

async function loadPreferences() {
  try {
    const response = await fetch("../../backend/api/email-preferences.php")
    const data = await response.json()

    if (data.preferences) {
      populateForm(data.preferences)
    }
  } catch (error) {
    console.error("[v0] Error loading preferences:", error)
    showError("Failed to load email preferences")
  }
}

function populateForm(prefs) {
  document.getElementById("emailEnabled").checked = prefs.email_enabled
  document.getElementById("digestFrequency").value = prefs.digest_frequency
  document.getElementById("notifyNewMessage").checked = prefs.notify_new_message
  document.getElementById("notifyAssignmentDue").checked = prefs.notify_assignment_due
  document.getElementById("notifyQuizAvailable").checked = prefs.notify_quiz_available
  document.getElementById("notifyGradePosted").checked = prefs.notify_grade_posted
  document.getElementById("notifyCourseUpdate").checked = prefs.notify_course_update
  document.getElementById("notifyForumReply").checked = prefs.notify_forum_reply
  document.getElementById("notifyAnnouncement").checked = prefs.notify_announcement
  document.getElementById("notifyCertificate").checked = prefs.notify_certificate
  document.getElementById("marketingEmails").checked = prefs.marketing_emails

  updateFormState()
}

function setupForm() {
  const form = document.getElementById("preferencesForm")
  const emailEnabled = document.getElementById("emailEnabled")

  emailEnabled.addEventListener("change", updateFormState)

  form.addEventListener("submit", async (e) => {
    e.preventDefault()
    await savePreferences()
  })
}

function updateFormState() {
  const emailEnabled = document.getElementById("emailEnabled").checked
  const inputs = document.querySelectorAll(
    '#preferencesForm input[type="checkbox"]:not(#emailEnabled), #digestFrequency',
  )

  inputs.forEach((input) => {
    input.disabled = !emailEnabled
  })
}

async function savePreferences() {
  const preferences = {
    email_enabled: document.getElementById("emailEnabled").checked,
    digest_frequency: document.getElementById("digestFrequency").value,
    notify_new_message: document.getElementById("notifyNewMessage").checked,
    notify_assignment_due: document.getElementById("notifyAssignmentDue").checked,
    notify_quiz_available: document.getElementById("notifyQuizAvailable").checked,
    notify_grade_posted: document.getElementById("notifyGradePosted").checked,
    notify_course_update: document.getElementById("notifyCourseUpdate").checked,
    notify_forum_reply: document.getElementById("notifyForumReply").checked,
    notify_announcement: document.getElementById("notifyAnnouncement").checked,
    notify_certificate: document.getElementById("notifyCertificate").checked,
    marketing_emails: document.getElementById("marketingEmails").checked,
  }

  try {
    const response = await fetch("../../backend/api/email-preferences.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(preferences),
    })

    const data = await response.json()

    if (data.success) {
      showSuccess("Email preferences saved successfully")
    } else {
      showError(data.error || "Failed to save preferences")
    }
  } catch (error) {
    console.error("[v0] Error saving preferences:", error)
    showError("Failed to save email preferences")
  }
}

function showSuccess(message) {
  alert(message)
}

function showError(message) {
  alert("Error: " + message)
}
