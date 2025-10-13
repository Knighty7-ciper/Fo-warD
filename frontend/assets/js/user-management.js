let allUsers = []
const selectedUsers = new Set()

document.addEventListener("DOMContentLoaded", () => {
  loadUsers()
})

function loadUsers() {
  fetch("/backend/api/user-management.php?action=list")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        allUsers = data.users
        renderUsers(allUsers)
      }
    })
    .catch((err) => console.error("Error loading users:", err))
}

function renderUsers(users) {
  const tbody = document.getElementById("usersTableBody")

  if (users.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center">No users found</td></tr>'
    return
  }

  let html = ""
  users.forEach((user) => {
    const initials = user.name
      .split(" ")
      .map((n) => n[0])
      .join("")
      .toUpperCase()
    const isSelected = selectedUsers.has(user.id)

    html += `<tr class="${isSelected ? "selected" : ""}">
            <td><input type="checkbox" class="user-checkbox" value="${user.id}" ${isSelected ? "checked" : ""} onchange="toggleUserSelection(${user.id})"></td>
            <td>
                <div class="user-info">
                    <div class="user-avatar">${initials}</div>
                    <div class="user-details">
                        <span class="user-name">${user.name}</span>
                        <span class="user-id">#${user.id}</span>
                    </div>
                </div>
            </td>
            <td>${user.email}</td>
            <td><span class="badge badge-${user.role}">${user.role}</span></td>
            <td><span class="badge badge-${user.status || "active"}">${user.status || "active"}</span></td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td>${user.last_login ? new Date(user.last_login).toLocaleDateString() : "Never"}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-icon" onclick="editUser(${user.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon" onclick="viewUserDetails(${user.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon danger" onclick="deleteUser(${user.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`
  })

  tbody.innerHTML = html
}

function filterUsers() {
  const searchTerm = document.getElementById("searchUsers").value.toLowerCase()
  const roleFilter = document.getElementById("roleFilter").value
  const statusFilter = document.getElementById("statusFilter").value

  const filtered = allUsers.filter((user) => {
    const matchesSearch = user.name.toLowerCase().includes(searchTerm) || user.email.toLowerCase().includes(searchTerm)
    const matchesRole = !roleFilter || user.role === roleFilter
    const matchesStatus = !statusFilter || (user.status || "active") === statusFilter

    return matchesSearch && matchesRole && matchesStatus
  })

  renderUsers(filtered)
}

function toggleSelectAll() {
  const selectAll = document.getElementById("selectAll")
  const checkboxes = document.querySelectorAll(".user-checkbox")

  checkboxes.forEach((cb) => {
    cb.checked = selectAll.checked
    const userId = Number.parseInt(cb.value)
    if (selectAll.checked) {
      selectedUsers.add(userId)
    } else {
      selectedUsers.delete(userId)
    }
  })

  updateBulkActions()
}

function toggleUserSelection(userId) {
  if (selectedUsers.has(userId)) {
    selectedUsers.delete(userId)
  } else {
    selectedUsers.add(userId)
  }
  updateBulkActions()
}

function updateBulkActions() {
  const bulkActions = document.getElementById("bulkActions")
  const selectedCount = document.getElementById("selectedCount")

  if (selectedUsers.size > 0) {
    bulkActions.style.display = "flex"
    selectedCount.textContent = `${selectedUsers.size} user${selectedUsers.size > 1 ? "s" : ""} selected`
  } else {
    bulkActions.style.display = "none"
  }
}

function showAddUserModal() {
  document.getElementById("modalTitle").textContent = "Add New User"
  document.getElementById("userForm").reset()
  document.getElementById("userId").value = ""
  document.getElementById("passwordNote").style.display = "none"
  document.getElementById("userPassword").required = true
  document.getElementById("userModal").style.display = "block"
}

function editUser(userId) {
  const user = allUsers.find((u) => u.id === userId)
  if (!user) return

  document.getElementById("modalTitle").textContent = "Edit User"
  document.getElementById("userId").value = user.id
  document.getElementById("userName").value = user.name
  document.getElementById("userEmail").value = user.email
  document.getElementById("userRole").value = user.role
  document.getElementById("userStatus").value = user.status || "active"
  document.getElementById("userPassword").value = ""
  document.getElementById("passwordNote").style.display = "inline"
  document.getElementById("userPassword").required = false
  document.getElementById("userModal").style.display = "block"
}

function closeUserModal() {
  document.getElementById("userModal").style.display = "none"
}

function saveUser(event) {
  event.preventDefault()

  const formData = new FormData(event.target)
  const userId = formData.get("user_id")
  const action = userId ? "update" : "create"

  formData.append("action", action)

  fetch("/backend/api/user-management.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        alert(data.message)
        closeUserModal()
        loadUsers()
      } else {
        alert("Error: " + data.message)
      }
    })
    .catch((err) => {
      console.error("Error saving user:", err)
      alert("An error occurred while saving the user")
    })
}

function deleteUser(userId) {
  if (!confirm("Are you sure you want to delete this user? This action cannot be undone.")) {
    return
  }

  fetch("/backend/api/user-management.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `action=delete&user_id=${userId}`,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        alert(data.message)
        loadUsers()
      } else {
        alert("Error: " + data.message)
      }
    })
    .catch((err) => {
      console.error("Error deleting user:", err)
      alert("An error occurred while deleting the user")
    })
}

function bulkAction(action) {
  if (selectedUsers.size === 0) return

  const actionText = action === "delete" ? "delete" : action === "activate" ? "activate" : "deactivate"
  if (!confirm(`Are you sure you want to ${actionText} ${selectedUsers.size} user(s)?`)) {
    return
  }

  const userIds = Array.from(selectedUsers)

  fetch("/backend/api/user-management.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      action: "bulk_" + action,
      user_ids: userIds,
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        alert(data.message)
        selectedUsers.clear()
        updateBulkActions()
        loadUsers()
      } else {
        alert("Error: " + data.message)
      }
    })
    .catch((err) => {
      console.error("Error performing bulk action:", err)
      alert("An error occurred while performing the bulk action")
    })
}

function viewUserDetails(userId) {
  window.location.href = `/frontend/admin/user-details.php?id=${userId}`
}

function exportUsers() {
  window.location.href = "/backend/api/user-management.php?action=export"
}

window.onclick = (event) => {
  const modal = document.getElementById("userModal")
  if (event.target === modal) {
    closeUserModal()
  }
}
