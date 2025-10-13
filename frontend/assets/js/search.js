let currentFilter = "all"
let currentQuery = ""

document.addEventListener("DOMContentLoaded", () => {
  const urlParams = new URLSearchParams(window.location.search)
  currentQuery = urlParams.get("q") || ""

  if (currentQuery) {
    performSearch()
  } else {
    loadRecentSearches()
    loadPopularSearches()
  }

  // Setup filter buttons
  document.querySelectorAll(".filter-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".filter-btn").forEach((b) => b.classList.remove("active"))
      btn.classList.add("active")
      currentFilter = btn.dataset.type
      if (currentQuery) {
        performSearch()
      }
    })
  })

  // Setup search input
  const searchInput = document.getElementById("search-input")
  searchInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") {
      performSearch()
    }
  })
})

async function performSearch() {
  const searchInput = document.getElementById("search-input")
  currentQuery = searchInput.value.trim()

  if (!currentQuery) {
    return
  }

  // Update URL
  const url = new URL(window.location)
  url.searchParams.set("q", currentQuery)
  window.history.pushState({}, "", url)

  const resultsContainer = document.getElementById("search-results")
  resultsContainer.innerHTML = '<div class="loading">Searching...</div>'

  try {
    const response = await fetch(`/backend/api/search.php?q=${encodeURIComponent(currentQuery)}&type=${currentFilter}`)
    const data = await response.json()

    displayResults(data)
  } catch (error) {
    console.error("Error searching:", error)
    resultsContainer.innerHTML = '<div class="no-results"><p>An error occurred while searching</p></div>'
  }
}

function displayResults(data) {
  const resultsContainer = document.getElementById("search-results")

  if (data.total_results === 0) {
    resultsContainer.innerHTML = `
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h3>No results found</h3>
                <p>Try different keywords or check your spelling</p>
            </div>
        `
    return
  }

  let html = `
        <div class="results-summary">
            Found <strong>${data.total_results}</strong> results for "<strong>${escapeHtml(data.query)}</strong>"
        </div>
    `

  // Display each category
  const categories = {
    courses: { title: "Courses", icon: "📚" },
    lessons: { title: "Lessons", icon: "📖" },
    assignments: { title: "Assignments", icon: "📝" },
    quizzes: { title: "Quizzes", icon: "❓" },
    forum: { title: "Forum Topics", icon: "💬" },
    users: { title: "Users", icon: "👤" },
  }

  for (const [key, category] of Object.entries(categories)) {
    if (data.results[key] && data.results[key].length > 0) {
      html += `
                <div class="results-category">
                    <h2>
                        <span>${category.icon}</span>
                        ${category.title}
                        <span class="category-count">(${data.results[key].length})</span>
                    </h2>
                    ${data.results[key].map((item) => renderResultItem(item, key)).join("")}
                </div>
            `
    }
  }

  resultsContainer.innerHTML = html
}

function renderResultItem(item, type) {
  let link = ""
  let meta = ""

  switch (type) {
    case "courses":
      link = `/frontend/courses/view.php?id=${item.id}`
      meta = `<span>Created: ${formatDate(item.created_at)}</span>`
      break
    case "lessons":
      link = `/frontend/lessons/view.php?id=${item.id}`
      meta = `<span>Course: ${escapeHtml(item.course_title)}</span>`
      break
    case "assignments":
      link = `/frontend/student/assignment-view.php?id=${item.id}`
      meta = `<span>Due: ${formatDate(item.due_date)}</span>`
      break
    case "quizzes":
      link = `/frontend/student/quiz-take.php?id=${item.id}`
      meta = `<span>Course: ${escapeHtml(item.course_title)}</span>`
      break
    case "forum":
      link = `/frontend/forum/topic.php?id=${item.id}`
      meta = `<span>Category: ${escapeHtml(item.category_name)}</span>`
      break
    case "users":
      link = `/frontend/profile.php?id=${item.id}`
      meta = `<span>Role: ${item.role}</span>`
      break
  }

  return `
        <div class="result-item" onclick="window.location.href='${link}'">
            <div class="result-header">
                <span class="result-type ${type}">${type}</span>
            </div>
            <div class="result-title">${escapeHtml(item.title || item.name)}</div>
            <div class="result-description">${escapeHtml((item.description || item.content || "").substring(0, 200))}...</div>
            <div class="result-meta">
                ${meta}
            </div>
            <div class="result-actions" onclick="event.stopPropagation()">
                <button class="btn-bookmark" onclick="toggleBookmark('${type}', ${item.id})">
                    <span>⭐</span> Bookmark
                </button>
            </div>
        </div>
    `
}

async function loadRecentSearches() {
  try {
    const response = await fetch("/backend/api/search.php?recent=1")
    const searches = await response.json()

    const container = document.getElementById("recent-searches")

    if (searches.length === 0) {
      container.innerHTML = '<p style="color: #999;">No recent searches</p>'
      return
    }

    container.innerHTML = searches
      .map(
        (s) => `
            <div class="suggestion-item" onclick="searchQuery('${escapeHtml(s.query)}')">
                <span class="suggestion-query">${escapeHtml(s.query)}</span>
                <span class="suggestion-count">${formatDate(s.created_at)}</span>
            </div>
        `,
      )
      .join("")
  } catch (error) {
    console.error("Error loading recent searches:", error)
  }
}

async function loadPopularSearches() {
  try {
    const response = await fetch("/backend/api/search.php?popular=1")
    const searches = await response.json()

    const container = document.getElementById("popular-searches")

    if (searches.length === 0) {
      container.innerHTML = '<p style="color: #999;">No popular searches yet</p>'
      return
    }

    container.innerHTML = searches
      .map(
        (s) => `
            <div class="suggestion-item" onclick="searchQuery('${escapeHtml(s.query)}')">
                <span class="suggestion-query">${escapeHtml(s.query)}</span>
                <span class="suggestion-count">${s.search_count} searches</span>
            </div>
        `,
      )
      .join("")
  } catch (error) {
    console.error("Error loading popular searches:", error)
  }
}

function searchQuery(query) {
  document.getElementById("search-input").value = query
  performSearch()
}

async function toggleBookmark(type, id) {
  try {
    const response = await fetch("/backend/api/bookmarks.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ type: type, id: id }),
    })

    const result = await response.json()

    if (result.success) {
      alert("Bookmarked successfully!")
    }
  } catch (error) {
    console.error("Error bookmarking:", error)
  }
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  })
}

function escapeHtml(text) {
  if (!text) return ""
  const div = document.createElement("div")
  div.textContent = text
  return div.innerHTML
}
