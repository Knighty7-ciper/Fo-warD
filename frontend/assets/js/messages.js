let currentFolder = "inbox"
let currentMessageId = null

// Initialize
document.addEventListener("DOMContentLoaded", () => {
  loadMessages()
  loadUnreadCount()
  loadUsers()

  // Check URL for message ID
  const urlParams = new URLSearchParams(window.location.search)
  const messageId = urlParams.get("id")
  if (messageId) {
    loadMessage(messageId)
  }

  // Refresh every 30 seconds
  setInterval(loadUnreadCount, 30000)
})

async function loadMessages() {
  const urlParams = new URLSearchParams(window.location.search)
  currentFolder = urlParams.get("folder") || "inbox"

  try {
    const response = await fetch(`/backend/api/messages.php?folder=${currentFolder}`)
    const messages = await response.json()

    const container = document.getElementById("messages-items")

    if (messages.length === 0) {
      container.innerHTML = '<div class="empty-state"><p>No messages in this folder</p></div>'
      return
    }

    container.innerHTML = messages
      .map(
        (msg) => `
            <div class="message-item ${msg.is_read ? "" : "unread"}" onclick="loadMessage(${msg.id})">
                <div class="message-item-header">
                    <span class="message-sender">${escapeHtml(msg.sender_name)}</span>
                    <span class="message-time">${formatDate(msg.created_at)}</span>
                </div>
                <div class="message-subject">${escapeHtml(msg.subject)}</div>
                <div class="message-preview">${escapeHtml(msg.body.substring(0, 100))}...</div>
            </div>
        `,
      )
      .join("")
  } catch (error) {
    console.error("Error loading messages:", error)
  }
}

async function loadMessage(id) {
  currentMessageId = id

  try {
    const response = await fetch(`/backend/api/messages.php?id=${id}`)
    const message = await response.json()

    const container = document.getElementById("message-view")
    container.innerHTML = `
            <div class="message-content">
                <div class="message-content-header">
                    <h2 class="message-content-subject">${escapeHtml(message.subject)}</h2>
                    <div class="message-content-meta">
                        <div class="message-sender-info">
                            <div class="sender-avatar">${message.sender_name.charAt(0).toUpperCase()}</div>
                            <div class="sender-details">
                                <span class="sender-name">${escapeHtml(message.sender_name)}</span>
                                <span class="message-date">${formatDate(message.created_at)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="message-content-body">${escapeHtml(message.body)}</div>
                
                <div class="message-actions">
                    <button class="btn btn-primary" onclick="showReplyForm()">Reply</button>
                    <button class="btn btn-secondary" onclick="archiveMessage(${id})">Archive</button>
                    <button class="btn btn-secondary" onclick="deleteMessage(${id})">Delete</button>
                </div>
                
                <div class="message-replies" id="message-replies">
                    ${
                      message.replies && message.replies.length > 0
                        ? `
                        <h4>Replies (${message.replies.length})</h4>
                        ${message.replies
                          .map(
                            (reply) => `
                            <div class="reply-item">
                                <div class="message-sender-info">
                                    <div class="sender-avatar">${reply.sender_name.charAt(0).toUpperCase()}</div>
                                    <div class="sender-details">
                                        <span class="sender-name">${escapeHtml(reply.sender_name)}</span>
                                        <span class="message-date">${formatDate(reply.created_at)}</span>
                                    </div>
                                </div>
                                <div class="message-content-body">${escapeHtml(reply.body)}</div>
                            </div>
                        `,
                          )
                          .join("")}
                    `
                        : ""
                    }
                    
                    <div class="reply-form" id="reply-form" style="display: none;">
                        <h4>Write a Reply</h4>
                        <form onsubmit="sendReply(event)">
                            <textarea name="reply_body" rows="5" required placeholder="Type your reply..."></textarea>
                            <div style="margin-top: 10px;">
                                <button type="submit" class="btn btn-primary">Send Reply</button>
                                <button type="button" class="btn btn-secondary" onclick="hideReplyForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `

    // Mark message items as active
    document.querySelectorAll(".message-item").forEach((item) => {
      item.classList.remove("active")
    })
    event.target.closest(".message-item")?.classList.add("active")

    // Update unread count
    loadUnreadCount()
  } catch (error) {
    console.error("Error loading message:", error)
  }
}

async function loadUnreadCount() {
  try {
    const response = await fetch("/backend/api/messages.php?unread_count=1")
    const data = await response.json()
    const badge = document.getElementById("inbox-count")
    if (badge) {
      badge.textContent = data.count
      badge.style.display = data.count > 0 ? "block" : "none"
    }
  } catch (error) {
    console.error("Error loading unread count:", error)
  }
}

async function loadUsers() {
  try {
    const response = await fetch("/backend/api/users.php?all=1")
    const users = await response.json()

    const select = document.getElementById("recipients-select")
    select.innerHTML = users.map((user) => `<option value="${user.id}">${user.name} (${user.role})</option>`).join("")
  } catch (error) {
    console.error("Error loading users:", error)
  }
}

function showComposeModal() {
  document.getElementById("compose-modal").classList.add("active")
}

function closeComposeModal() {
  document.getElementById("compose-modal").classList.remove("active")
  document.getElementById("compose-form").reset()
}

async function sendMessage(event) {
  event.preventDefault()

  const form = event.target
  const formData = new FormData(form)

  const recipients = Array.from(form.recipients.selectedOptions).map((opt) => opt.value)

  const data = {
    subject: formData.get("subject"),
    body: formData.get("body"),
    recipients: recipients,
  }

  try {
    const response = await fetch("/backend/api/messages.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    })

    const result = await response.json()

    if (result.success) {
      closeComposeModal()
      alert("Message sent successfully!")
      loadMessages()
    }
  } catch (error) {
    console.error("Error sending message:", error)
    alert("Failed to send message")
  }
}

function showReplyForm() {
  document.getElementById("reply-form").style.display = "block"
}

function hideReplyForm() {
  document.getElementById("reply-form").style.display = "none"
}

async function sendReply(event) {
  event.preventDefault()

  const form = event.target
  const body = form.reply_body.value

  try {
    const response = await fetch("/backend/api/messages.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        reply_to: currentMessageId,
        body: body,
      }),
    })

    const result = await response.json()

    if (result.success) {
      alert("Reply sent successfully!")
      loadMessage(currentMessageId)
    }
  } catch (error) {
    console.error("Error sending reply:", error)
    alert("Failed to send reply")
  }
}

async function archiveMessage(id) {
  try {
    await fetch("/backend/api/messages.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: id, folder: "archive" }),
    })

    alert("Message archived")
    loadMessages()
    document.getElementById("message-view").innerHTML = '<div class="empty-state"><p>Message archived</p></div>'
  } catch (error) {
    console.error("Error archiving message:", error)
  }
}

async function deleteMessage(id) {
  if (!confirm("Are you sure you want to delete this message?")) return

  try {
    await fetch(`/backend/api/messages.php?id=${id}`, { method: "DELETE" })
    alert("Message deleted")
    loadMessages()
    document.getElementById("message-view").innerHTML = '<div class="empty-state"><p>Message deleted</p></div>'
  } catch (error) {
    console.error("Error deleting message:", error)
  }
}

function refreshMessages() {
  loadMessages()
  loadUnreadCount()
}

function formatDate(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const diff = now - date

  if (diff < 86400000) {
    // Less than 24 hours
    return date.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" })
  } else if (diff < 604800000) {
    // Less than 7 days
    return date.toLocaleDateString("en-US", { weekday: "short" })
  } else {
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric" })
  }
}

function escapeHtml(text) {
  const div = document.createElement("div")
  div.textContent = text
  return div.innerHTML
}
