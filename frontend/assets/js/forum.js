document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("forum-categories")) {
    loadCategories()
  }
})

async function loadCategories() {
  try {
    const response = await fetch("/backend/api/forum.php?categories=1")
    const categories = await response.json()

    const container = document.getElementById("forum-categories")
    container.innerHTML = categories
      .map(
        (cat) => `
            <div class="category-card" onclick="window.location.href='/frontend/forum/category.php?id=${cat.id}'">
                <div class="category-header">
                    <div class="category-icon">${cat.icon}</div>
                    <div class="category-info">
                        <div class="category-name">${escapeHtml(cat.name)}</div>
                        <div class="category-description">${escapeHtml(cat.description)}</div>
                    </div>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <span>📝</span>
                        <span>${cat.topic_count} Topics</span>
                    </div>
                    <div class="stat-item">
                        <span>💬</span>
                        <span>${cat.post_count} Posts</span>
                    </div>
                </div>
            </div>
        `,
      )
      .join("")
  } catch (error) {
    console.error("Error loading categories:", error)
  }
}

async function searchTopics(event) {
  if (event.key === "Enter") {
    const query = event.target.value
    if (query.length < 3) return

    try {
      const response = await fetch(`/backend/api/forum.php?search=${encodeURIComponent(query)}`)
      const topics = await response.json()

      // Display search results
      console.log("Search results:", topics)
    } catch (error) {
      console.error("Error searching topics:", error)
    }
  }
}

function escapeHtml(text) {
  const div = document.createElement("div")
  div.textContent = text
  return div.innerHTML
}
